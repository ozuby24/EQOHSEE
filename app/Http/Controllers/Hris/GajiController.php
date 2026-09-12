<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{PeriodeGaji, SlipGaji, Upah};
use App\Models\Miners\Pekerja;
use App\Support\Hr\{Bpjs, Pajak, Penggajian};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Penggajian — periode, slip, dan acuan peraturannya.
 *
 * ACUAN YANG BELUM DIVERIFIKASI DITAMPILKAN DI DEPAN, bukan
 * disembunyikan di halaman pengaturan. Tabel tarif pajak yang belum
 * dicocokkan dengan naskah peraturannya menghasilkan angka yang tampak
 * sama meyakinkannya dengan tabel yang sudah — dan satu-satunya yang
 * membedakan keduanya adalah seseorang yang benar-benar memeriksanya.
 */
class GajiController extends Controller
{
    public function index(Request $r)
    {
        $periode = PeriodeGaji::query()->terbaru()->withCount('slip')->limit(36)->get();

        $terpilih = $r->query('periode')
            ? $periode->firstWhere('id', (int) $r->query('periode'))
            : $periode->first();

        return Inertia::render('Hris/Gaji/Periode', [
            'judul'    => 'HRIS — Penggajian',
            'subjudul' => 'Ditarik dari roster, absensi, dan lembur yang sudah tercatat.',

            'periode' => $periode->map(fn (PeriodeGaji $p) => [
                'id'        => $p->id,
                'tahun'     => $p->tahun,
                'bulan'     => $p->bulan,
                'label'     => $p->label(),
                'status'    => $p->status,
                'slip'      => $p->slip_count,
                'rekonsiliasi' => $p->rekonsiliasi(),
                'dihitung'  => $p->dihitung_pada ? Waktu::lokal($p->dihitung_pada)?->format('d/m/Y H:i') : null,
                'dikunci'   => $p->dikunci_pada ? Waktu::lokal($p->dikunci_pada)?->format('d/m/Y H:i') : null,
            ]),

            'terpilih' => $terpilih?->id,
            'slip'     => $terpilih ? $this->slip($terpilih) : [],
            'ringkas'  => $terpilih ? $this->ringkas($terpilih) : null,

            'belumVerifikasi' => Penggajian::acuanBelumTerverifikasi(),

            'tahunIni' => (int) Waktu::kini()->format('Y'),
            'BULAN'    => PeriodeGaji::BULAN,
            'STATUS'   => PeriodeGaji::STATUS,
        ]);
    }

    public function buat(Request $r)
    {
        $data = $r->validate([
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $company = $r->user()?->company_id;

        $ada = PeriodeGaji::withoutGlobalScopes()
            ->where('company_id', $company)
            ->where('tahun', $data['tahun'])->where('bulan', $data['bulan'])
            ->first();

        if ($ada) return back()->withErrors(['bulan' => 'Periode itu sudah ada.']);

        $p = PeriodeGaji::create($data + ['company_id' => $company]);

        return back()->with('sukses', 'Periode '.$p->label().' dibuat.');
    }

    public function hitung(Request $r, PeriodeGaji $periode)
    {
        $hasil = Penggajian::hitung($periode, $r->user());

        if (is_string($hasil)) return back()->withErrors(['hitung' => $hasil]);

        return back()->with('sukses', sprintf(
            '%d slip dibuat, %d diperbarui. Bruto %s, PPh 21 %s, dibawa pulang %s.',
            $hasil['dibuat'], $hasil['diperbarui'],
            $this->rupiah($hasil['bruto']), $this->rupiah($hasil['pph21']), $this->rupiah($hasil['neto']),
        ));
    }

    public function kunci(Request $r, PeriodeGaji $periode)
    {
        $alasan = Penggajian::kunci($periode, $r->user());

        return $alasan === null
            ? back()->with('sukses', 'Periode '.$periode->label().' dikunci.')
            : back()->withErrors(['kunci' => $alasan]);
    }

    /* ═══════════════════ acuan peraturan ═══════════════════ */

    public function acuan()
    {
        $acuan = DB::table('pay_acuan')
            ->leftJoin('users', 'users.id', '=', 'pay_acuan.diverifikasi_oleh')
            ->orderBy('pay_acuan.id')
            ->get(['pay_acuan.*', 'users.name as pemeriksa']);

        return Inertia::render('Hris/Gaji/Acuan', [
            'judul'    => 'HRIS — Acuan Pajak & BPJS',
            'subjudul' => 'Angka peraturan yang dipakai menghitung gaji, beserta siapa yang memeriksanya.',

            'acuan' => $acuan->map(fn ($a) => [
                'id'       => $a->id,
                'kunci'    => $a->kunci,
                'nama'     => $a->nama,
                'sumber'   => $a->sumber,
                'catatan'  => $a->catatan,
                'terverifikasi' => (bool) $a->terverifikasi,
                'pemeriksa'     => $a->pemeriksa,
                'pada'          => $a->diverifikasi_pada
                    ? Waktu::lokal($a->diverifikasi_pada)?->format('d/m/Y H:i')
                    : null,
            ]),

            /* Tabel tarifnya ditampilkan seluruhnya. Diringkas menjadi
               "125 baris terpasang", yang memeriksa tidak punya apa pun
               untuk dicocokkan dengan naskah peraturannya — dan
               penandanya menjadi centang tanpa isi. */
            'ter' => DB::table('pay_ter')->orderBy('kategori')->orderBy('batas_bawah')->get()
                ->groupBy('kategori')
                ->map(fn ($baris) => $baris->map(fn ($b) => [
                    'bawah' => (float) $b->batas_bawah,
                    'atas'  => $b->batas_atas === null ? null : (float) $b->batas_atas,
                    'tarif' => (float) $b->tarif,
                ])->values()),

            'ptkp' => collect(Pajak::STATUS)->map(fn ($label, $kode) => [
                'kode'     => $kode,
                'label'    => $label,
                'kategori' => Pajak::kategori($kode),
                'ptkp'     => Pajak::ptkp($kode),
            ])->values(),

            'pasal17' => collect(Pajak::PASAL_17)->map(fn ($l) => ['atas' => $l[0], 'tarif' => $l[1]]),

            'bpjs' => [
                ['nama' => 'BPJS Kesehatan', 'perusahaan' => Bpjs::KESEHATAN_PERUSAHAAN,
                 'karyawan' => Bpjs::KESEHATAN_KARYAWAN, 'plafon' => Bpjs::KESEHATAN_PLAFON],
                ['nama' => 'JHT', 'perusahaan' => Bpjs::JHT_PERUSAHAAN,
                 'karyawan' => Bpjs::JHT_KARYAWAN, 'plafon' => null],
                ['nama' => 'JP', 'perusahaan' => Bpjs::JP_PERUSAHAAN,
                 'karyawan' => Bpjs::JP_KARYAWAN, 'plafon' => Bpjs::JP_PLAFON],
                ['nama' => 'JKK (risiko sangat tinggi)', 'perusahaan' => Bpjs::JKK_TARIF['sangat_tinggi'],
                 'karyawan' => 0, 'plafon' => null],
                ['nama' => 'JKM', 'perusahaan' => Bpjs::JKM, 'karyawan' => 0, 'plafon' => null],
            ],
        ]);
    }

    /**
     * Tandai sebuah acuan sudah diperiksa terhadap naskahnya.
     *
     * HANYA ADMIN, dan siapa yang menandainya ikut tercatat. Tanda
     * tanpa nama adalah tanda yang tidak dapat ditanyakan kepada
     * siapa pun ketika angkanya ternyata keliru.
     */
    public function verifikasi(Request $r, int $acuan)
    {
        $data = $r->validate(['terverifikasi' => ['required', 'boolean']]);

        $baris = DB::table('pay_acuan')->where('id', $acuan)->first();

        if (! $baris) abort(404);

        DB::table('pay_acuan')->where('id', $acuan)->update([
            'terverifikasi'     => $data['terverifikasi'],
            'diverifikasi_oleh' => $data['terverifikasi'] ? $r->user()?->id : null,
            'diverifikasi_pada' => $data['terverifikasi'] ? Waktu::kiniSimpan() : null,
            'updated_at'        => Waktu::kiniSimpan(),
        ]);

        return back()->with('sukses', $data['terverifikasi']
            ? $baris->nama.' ditandai sudah diperiksa.'
            : $baris->nama.' dikembalikan menjadi belum diperiksa.');
    }

    /* ═══════════════════ data pajak pekerja ═══════════════════ */

    public function pekerja(Request $r)
    {
        $data = $r->validate([
            'pekerja_id'  => ['required', 'exists:mnr_pekerja,id'],
            'status_ptkp' => ['required', 'in:'.implode(',', array_keys(Pajak::STATUS))],
            'npwp'        => ['nullable', 'string', 'max:25'],
        ]);

        $p = Pekerja::findOrFail($data['pekerja_id']);

        $p->update(['status_ptkp' => $data['status_ptkp'], 'npwp' => $data['npwp'] ?? null]);

        return back()->with('sukses', 'Status PTKP '.$p->nama.' disimpan.');
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /** @return list<array<string,mixed>> */
    private function slip(PeriodeGaji $periode): array
    {
        return SlipGaji::query()
            ->where('periode_id', $periode->id)
            ->with('pekerja.jabatan')
            ->get()
            ->sortBy(fn (SlipGaji $s) => (string) $s->pekerja?->nama)
            ->map(fn (SlipGaji $s) => [
                'id'        => $s->id,
                'pekerja'   => $s->pekerja?->nama,
                'nik'       => $s->pekerja?->nik,
                'jabatan'   => $s->pekerja?->jabatan?->nama,

                'pokok'     => $s->pokok,
                'tetap'     => $s->tunjangan_tetap,
                'tidakTetap'=> $s->tunjangan_tidak_tetap,
                'site'      => $s->tunjangan_site,
                'hariSite'  => $s->hari_site,
                'lembur'    => $s->lembur,
                'lemburJam' => $s->lembur_jam,

                'bpjsPerusahaan' => $s->bpjs_perusahaan,
                'bruto'     => $s->bruto,

                'bpjs'      => $s->bpjs_karyawan,
                'pph21'     => $s->pph21,
                'neto'      => $s->neto,

                'ptkp'      => $s->status_ptkp,
                'kategori'  => $s->ter_kategori,
                'tarif'     => $s->ter_tarif,
                'rincian'   => $s->rincian,
            ])
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function ringkas(PeriodeGaji $periode): array
    {
        $slip = SlipGaji::query()->where('periode_id', $periode->id)->get();

        return [
            'orang'      => $slip->count(),
            'bruto'      => round((float) $slip->sum('bruto'), 2),
            'site'       => round((float) $slip->sum('tunjangan_site'), 2),
            'lembur'     => round((float) $slip->sum('lembur'), 2),
            'bpjsPerusahaan' => round((float) $slip->sum('bpjs_perusahaan'), 2),
            'bpjs'       => round((float) $slip->sum('bpjs_karyawan'), 2),
            'pph21'      => round((float) $slip->sum('pph21'), 2),
            'neto'       => round((float) $slip->sum('neto'), 2),

            /* Yang tanpa status PTKP disebut terpisah: pajaknya dihitung
               dengan kategori A sebagai jaga-jaga, dan itu hampir pasti
               bukan status yang sebenarnya. */
            'tanpaPtkp'  => $slip->whereNull('status_ptkp')->count(),
        ];
    }

    private function rupiah(float $n): string
    {
        return 'Rp '.number_format(round($n), 0, ',', '.');
    }
}
