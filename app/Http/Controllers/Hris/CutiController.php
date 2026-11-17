<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{Cuti, JenisCuti, Roster, SaldoCuti};
use App\Models\Miners\Pekerja;
use App\Support\Berkas;
use App\Support\Hr\{JalurCuti, KebijakanCuti};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Cuti & izin.
 *
 * LAYAR PERSETUJUAN MENAMPILKAN DAMPAK ROSTERNYA, bukan hanya sisa
 * saldonya. Yang memutuskan bukan sedang memeriksa hak seseorang — ia
 * sedang memutuskan apakah regu itu masih cukup orang pada hari-hari
 * yang diminta. Tanpa angka itu, persetujuan diberikan berdasarkan
 * saldo dan kekurangan orangnya baru ketahuan pada pergantian shift.
 */
class CutiController extends Controller
{
    public function index(Request $r)
    {
        $tahun = (int) ($r->query('tahun') ?: Waktu::kini()->format('Y'));

        $daftar = Cuti::query()
            ->with(['pekerja.jabatan', 'jenis', 'penindak'])
            ->whereYear('mulai', $tahun)
            ->orderByDesc('mulai')
            ->limit(200)
            ->get();

        $menunggu = $daftar->where('status', 'menunggu');

        return Inertia::render('Hris/Cuti/Daftar', [
            'judul'    => 'HRIS — Cuti & Izin',
            'subjudul' => 'Pengajuan, persetujuan, dan dampaknya pada kalender regu.',

            'tahun'  => $tahun,

            /* Antrean persetujuan digambar TERPISAH dari riwayat, dan
               bukan sebagai penyaring atas daftar yang sama. Yang
               membuka layar ini hampir selalu datang untuk menindak
               sesuatu — dan antrean yang harus dicari dulu di antara
               dua ratus baris riwayat adalah antrean yang terlewat. */
            'antrean' => $menunggu->map(fn (Cuti $c) => $this->baris($c, true))->values(),
            'riwayat' => $daftar->where('status', '<>', 'menunggu')
                ->map(fn (Cuti $c) => $this->baris($c))->values(),

            'pekerja' => Pekerja::query()->aktif()->orderBy('nama')
                ->get(['id', 'nama', 'nik', 'tanggal_masuk'])
                ->map(fn (Pekerja $p) => [
                    'id'    => $p->id,
                    'nama'  => $p->nama,
                    'nik'   => $p->nik,
                    'masuk' => $p->tanggal_masuk?->toDateString(),
                ]),

            'jenis' => JenisCuti::query()->terpakai()->get()
                ->map(fn (JenisCuti $j) => [
                    'id'          => $j->id,
                    'kode'        => $j->kode,
                    'nama'        => $j->nama,
                    'dasar'       => $j->dasar,
                    'hari'        => $j->hari,
                    'potong'      => $j->potong_saldo,
                    'berbayar'    => $j->berbayar,
                    'perluBukti'  => $j->perlu_bukti,
                ]),

            'STATUS'    => Cuti::STATUS,
            'MAKS_BUKTI'=> Berkas::MAKS_BUKTI_KB,
        ]);
    }

    public function saldo(Request $r)
    {
        $tahun = (int) ($r->query('tahun') ?: Waktu::kini()->format('Y'));

        /* Hanya jenis yang BERAKRU yang punya saldo. Ditampilkan
           seluruhnya, sebelas kolom nol berderet di samping satu kolom
           yang berisi — dan yang membacanya menyangka sebelas jenis
           izin itu kehabisan jatah. */
        $jenis = JenisCuti::query()->terpakai()->where('akrual', true)->get();

        $pekerja = Pekerja::query()->aktif()->with('jabatan')->orderBy('nama')->get();

        $baris = $pekerja->map(function (Pekerja $p) use ($jenis, $tahun) {
            $kolom = $jenis->map(fn (JenisCuti $j) => [
                'jenis' => $j->nama,
                'kode'  => $j->kode,
            ] + KebijakanCuti::ringkas($p, $j, $tahun));

            return [
                'id'      => $p->id,
                'nama'    => $p->nama,
                'nik'     => $p->nik,
                'jabatan' => $p->jabatan?->nama,
                'masuk'   => $p->tanggal_masuk?->toDateString(),

                /* Masa kerja ditulis apa adanya, sebab itulah yang
                   menjelaskan hak nol pada orang yang baru masuk —
                   pasal 79 menyebut haknya timbul sesudah dua belas
                   bulan, dan tanpa angka ini "hak 0" terbaca seperti
                   kesalahan data. */
                'bulan'   => $this->masaKerja($p),

                'saldo'   => $kolom->values(),
            ];
        });

        return Inertia::render('Hris/Cuti/Saldo', [
            'judul'    => 'HRIS — Saldo Cuti',
            'subjudul' => 'Hak, bawaan tahun lalu, terpakai, dan yang masih antre.',

            'tahun' => $tahun,
            'baris' => $baris,

            'jenis' => $jenis->map(fn (JenisCuti $j) => [
                'kode'  => $j->kode,
                'nama'  => $j->nama,
                'dasar' => $j->dasar,
                'hari'  => $j->hari,
                'carry' => $j->carry_over_maks,
            ])->values(),

            'BULAN_HAK' => KebijakanCuti::BULAN_HAK_TAHUNAN,
        ]);
    }

    /* ═══════════════════ pengajuan ═══════════════════ */

    public function store(Request $r)
    {
        $data = $r->validate([
            'pekerja_id'    => ['required', 'exists:mnr_pekerja,id'],
            'jenis_cuti_id' => ['required', 'exists:hr_jenis_cuti,id'],
            'mulai'         => ['required', 'date'],
            'selesai'       => ['required', 'date', 'after_or_equal:mulai'],
            'alasan'        => ['nullable', 'string', 'max:500'],
            'bukti'         => Berkas::ATURAN_BUKTI,
        ]);

        $p     = Pekerja::findOrFail($data['pekerja_id']);
        $jenis = JenisCuti::findOrFail($data['jenis_cuti_id']);

        $mulai   = Waktu::tanggal($data['mulai']);
        $selesai = Waktu::tanggal($data['selesai']);

        if ($jenis->perlu_bukti && ! $r->hasFile('bukti')) {
            return back()->withErrors([
                'bukti' => $jenis->nama.' menuntut bukti — '.($jenis->dasar ?: 'sesuai kebijakan').'.',
            ]);
        }

        $alasan = KebijakanCuti::periksa($p, $jenis, $mulai, $selesai);

        if ($alasan !== null) return back()->withErrors(['mulai' => $alasan]);

        $n = KebijakanCuti::hariTerpotong($p, $mulai, $selesai);

        Cuti::create([
            'company_id'    => $p->company_id,
            'pekerja_id'    => $p->id,
            'jenis_cuti_id' => $jenis->id,
            'mulai'         => $mulai,
            'selesai'       => $selesai,
            'hari'          => $n['hari'],
            'kalender'      => $n['kalender'],
            'alasan'        => $data['alasan'] ?? null,
            'berkas_bukti'  => $r->hasFile('bukti') ? Berkas::simpan($r->file('bukti'), 'hris/cuti') : null,
            'status'        => 'menunggu',
            'diajukan_oleh' => $r->user()?->id,
            'diajukan_pada' => Waktu::kiniSimpan(),
        ]);

        return back()->with('sukses', sprintf(
            'Pengajuan %s %s – %s dikirim: %d hari kerja terpotong dari %d hari kalender.',
            $jenis->nama, $mulai->toDateString(), $selesai->toDateString(), $n['hari'], $n['kalender'],
        ));
    }

    /* ═══════════════════ tindakan ═══════════════════ */

    public function setujui(Request $r, Cuti $cuti)
    {
        return $this->tindak(
            JalurCuti::setujui($cuti, $r->user(), $r->input('catatan')),
            'Cuti disetujui dan dituliskan ke kalender regu.',
        );
    }

    public function tolak(Request $r, Cuti $cuti)
    {
        $r->validate(['catatan' => ['required', 'string', 'min:5', 'max:300']]);

        return $this->tindak(
            JalurCuti::tolak($cuti, $r->user(), $r->input('catatan')),
            'Pengajuan ditolak.',
        );
    }

    public function teruskan(Request $r, Cuti $cuti)
    {
        return $this->tindak(
            JalurCuti::teruskan($cuti, $r->user(), $r->input('catatan')),
            'Pengajuan diteruskan ke jenjang di atasnya.',
        );
    }

    public function batalkan(Request $r, Cuti $cuti)
    {
        return $this->tindak(
            JalurCuti::batalkan($cuti, $r->user(), $r->input('catatan')),
            'Cuti dibatalkan; baris rosternya dikembalikan.',
        );
    }

    private function tindak(?string $alasan, string $sukses)
    {
        return $alasan === null
            ? back()->with('sukses', $sukses)
            : back()->withErrors(['tindak' => $alasan]);
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /** @return array<string,mixed> */
    private function baris(Cuti $c, bool $denganDampak = false): array
    {
        $isi = [
            'id'        => $c->id,
            'pekerja'   => $c->pekerja?->nama,
            'nik'       => $c->pekerja?->nik,
            'jabatan'   => $c->pekerja?->jabatan?->nama,
            'jenis'     => $c->jenis?->nama,
            'kode'      => $c->jenis?->kode,
            'potong'    => (bool) $c->jenis?->potong_saldo,
            'mulai'     => $c->mulai?->toDateString(),
            'selesai'   => $c->selesai?->toDateString(),
            'hari'      => $c->hari,
            'kalender'  => $c->kalender,
            'alasan'    => $c->alasan,
            'bukti'     => $c->berkas_bukti !== null,
            'status'    => $c->status,
            'jenjang'   => (bool) $c->perlu_jenjang,
            'penindak'  => $c->penindak?->name,
            'catatan'   => $c->catatan_tindak,
        ];

        if (! $denganDampak) return $isi;

        return $isi + [
            'sisa'   => $this->sisa($c),
            'dampak' => $this->dampak($c),
        ];
    }

    /** @return array<string,int>|null */
    private function sisa(Cuti $c): ?array
    {
        if (! $c->pekerja || ! $c->jenis || ! $c->jenis->potong_saldo) return null;

        $r = KebijakanCuti::ringkas($c->pekerja, $c->jenis, (int) $c->mulai->format('Y'));

        return ['sebelum' => $r['sisa'], 'sesudah' => $r['sisa'] - $c->hari];
    }

    /**
     * Dampak pengajuan ini pada jumlah orang di regunya.
     *
     * INILAH ANGKA YANG SEBENARNYA DIPUTUSKAN. Yang menyetujui bukan
     * sedang memeriksa hak seseorang — ia sedang memutuskan apakah
     * regunya masih cukup orang pada hari-hari yang diminta. Tanpa
     * angka ini, persetujuan diberikan berdasarkan saldo dan
     * kekurangan orangnya baru ketahuan pada pergantian shift.
     *
     * @return array{regu:?string,paling_tipis:?int,sebelum:?int}|null
     */
    private function dampak(Cuti $c): ?array
    {
        $baris = Roster::withoutGlobalScopes()
            ->where('pekerja_id', $c->pekerja_id)
            ->antara($c->mulai->toDateString(), $c->selesai->toDateString())
            ->bekerja()
            ->get();

        if ($baris->isEmpty()) return null;

        $reguId = $baris->first()->regu_id;

        if (! $reguId) return null;

        $tanggal = $baris->pluck('tanggal')->map->toDateString()->unique();

        /* Dihitung per TANGGAL lalu diambil yang paling tipis. Dihitung
           sebagai rerata, satu hari yang kekurangan dua orang tertutup
           enam hari yang berlebih — dan hari itulah satu-satunya yang
           menentukan. */
        $perHari = Roster::withoutGlobalScopes()
            ->where('regu_id', $reguId)
            ->antara($c->mulai->toDateString(), $c->selesai->toDateString())
            ->bekerja()
            ->get()
            ->groupBy(fn (Roster $r) => $r->tanggal->toDateString())
            ->map->count();

        $tipis = $perHari->only($tanggal->all())->min();

        return [
            'regu'         => $baris->first()->regu?->nama,
            'sebelum'      => $tipis,
            'paling_tipis' => $tipis === null ? null : $tipis - 1,
        ];
    }

    private function masaKerja(Pekerja $p): ?int
    {
        if (! $p->tanggal_masuk) return null;

        $masuk = Carbon::parse($p->tanggal_masuk->format('Y-m-d'), Waktu::zona());

        return (int) floor($masuk->diffInMonths(Waktu::kini()));
    }
}
