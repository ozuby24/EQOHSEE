<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Miners\{Alur, Skema};
use App\Models\User;
use App\Support\Miners\{AksesMiners, Penyetuju};
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Siapa yang menyetujui apa, per IUP — layar Alur Persetujuan Safe Track
 * (Sistem\AlurController).
 *
 * Sebelum layar ini ada di Safe Track, alur hanya dapat diisi lewat basis
 * data, dan setiap pemasangan baru berangkat dengan skema kosong — setiap
 * pengajuan berakhir dengan galat tanpa keterangan. Di sini pun sama:
 * tanpa skema, tidak ada berkas yang dapat dikirim, dan pesan kirimnya
 * menunjuk ke layar ini.
 *
 * Boleh dibuka admin dan HSE perusahaan IUP ("admin" Safe Track) — yang
 * sehari-hari perlu mengganti KTT atau dokter tanpa menunggu siapa pun.
 */
class AlurPersetujuanController extends Controller
{
    public function index(Request $r)
    {
        $u = $r->user();
        AksesMiners::tegakkan(AksesMiners::kelola($u), 'Hanya admin atau HSE IUP yang mengatur alur persetujuan.');

        $iup = Penyetuju::iupTerkelola($u);

        $kartu = $iup->map(fn (Company $c) => $this->kartu($c->id, $c));

        /* Alur UMUM — cadangan bagi IUP tanpa skema sendiri. Hanya
           administrator: ia berlaku lintas perusahaan. */
        if ($u->isAdmin()) {
            $kartu->push($this->kartu(null, null));
        }

        return Inertia::render('Miners/AlurPersetujuan', [
            'judul' => 'Alur Persetujuan',
            'kop'   => false,

            'kartu' => $kartu->values(),
            'rantai' => collect(Alur::LANGKAH)->map(fn ($r, $k) => [
                'kunci' => $k,
                'label' => self::LABEL_RANTAI[$k] ?? $k,
                'peran' => array_map(fn ($p) => Alur::PERAN[$p], $r),
            ])->values(),
        ]);
    }

    public function simpan(Request $r, string $iup)
    {
        $u = $r->user();
        $iupId = (int) $iup ?: null;

        /* Yang boleh disimpan hanya IUP yang memang boleh ia atur; alur
           umum hanya oleh administrator. */
        $boleh = $iupId === null
            ? $u->isAdmin()
            : Penyetuju::iupTerkelola($u)->contains('id', $iupId);

        AksesMiners::tegakkan($boleh, 'Anda tidak berwenang mengatur alur IUP ini.');

        $calon = Penyetuju::calon($iupId)->pluck('id')->map(fn ($v) => (int) $v)->all();

        $aturan = [];
        foreach (Skema::KOLOM as $kolom) {
            $aturan[$kolom] = ['nullable', 'integer', 'in:'.implode(',', $calon ?: [0])];
        }
        foreach (Skema::PENERIMA as $daftar) {
            foreach ($daftar as $kolom) $aturan[$kolom] = ['nullable', 'string', 'max:1000'];
        }

        $sah = $r->validate($aturan, [
            '*.in' => 'Pengguna yang dipilih bukan orang IUP ini atau mitranya.',
        ]);

        /* Peran yang dikosongkan disimpan KOSONG, bukan dibiarkan berisi
           orang lama — alur boleh diisi bertahap, tetapi tidak boleh
           diam-diam menyimpan pilihan yang sudah dihapus. */
        $data = [];
        foreach (array_keys($aturan) as $k) $data[$k] = $sah[$k] ?? null;

        /* Alamat diperiksa satu per satu supaya salah ketik ketahuan di
           sini, bukan nanti ketika surelnya diam-diam tidak terkirim. */
        foreach (Skema::PENERIMA as $peran => $daftar) {
            foreach ($daftar as $kolom) {
                $teks = trim((string) ($data[$kolom] ?? ''));
                if ($teks === '') continue;

                if (count(Penyetuju::pecahEmail($teks)) !== Penyetuju::jumlahDitulis($teks)) {
                    return back()->withErrors([
                        $kolom => 'Ada alamat surel yang tidak sah pada kolom '.Penyetuju::LABEL[$peran].'.',
                    ])->withInput();
                }

                $data[$kolom] = implode(', ', Penyetuju::pecahEmail($teks));
            }
        }

        Skema::query()->updateOrCreate(['company_id' => $iupId], $data + ['user_id' => $u->id]);

        $pindah = Penyetuju::terapkanKeAntrean($iupId);

        $kurang = Penyetuju::yangBelumDiisi($iupId, array_keys(Skema::KOLOM));
        $nama   = $iupId ? (Company::query()->find($iupId)?->code ?: Company::query()->find($iupId)?->name) : 'umum';

        $pesan = $kurang
            ? 'Alur '.$nama.' tersimpan, belum lengkap: '.implode(', ', $kurang).'. Pengajuan belum dapat dikirim.'
            : 'Alur persetujuan '.$nama.' sudah lengkap.';

        if ($pindah > 0) {
            $pesan .= ' '.$pindah.' langkah yang sedang menunggu dipindahkan ke penyetuju baru.';
        }

        return back()->with('sukses', $pesan);
    }

    private const LABEL_RANTAI = [
        'mcu'                 => 'MCU',
        'induksi'             => 'Induksi',
        'permit'              => 'Mine Permit',
        'simper'              => 'SIMPER',
        'penambahan'          => 'Penambahan Unit',
        'upgrade'             => 'Upgrade SIMPER',
        'perpanjangan'        => 'Perpanjangan SIMPER',
        'perpanjangan_permit' => 'Perpanjangan Mine Permit',
    ];

    /** @return array<string,mixed> */
    private function kartu(?int $iupId, ?Company $c): array
    {
        $s = $iupId ? Skema::query()->where('company_id', $iupId)->first()
                    : Skema::query()->whereNull('company_id')->first();

        $peran = fn (User $x) => array_values(array_filter([
            $x->is_admin ? 'Admin' : null,
            $x->ohse_role === 'paramedis' ? 'Paramedis' : null,
            $x->ohse_role === 'ohse' ? 'OHSE' : null,
            $x->lms_role === 'ktt' ? 'KTT' : null,
        ]));

        return [
            'iup'      => $iupId ?? 0,
            'kode'     => $c ? ($c->code ?: $c->name) : 'Alur Umum',
            'nama'     => $c?->name ?? 'Cadangan bagi IUP yang belum punya alur sendiri',
            'logo'     => \App\Support\Berkas::terbuka($c?->logo_grup ?: $c?->logo),
            'kurang'   => Penyetuju::yangBelumDiisi($iupId, array_keys(Skema::KOLOM)),
            'sendiri'  => $s !== null,
            'isi'      => array_merge(
                array_fill_keys(array_values(Skema::KOLOM), null),
                array_fill_keys(['dokter_cc', 'ohse_to', 'ohse_cc', 'ktt_cc'], ''),
                $s ? $s->only([...array_values(Skema::KOLOM), 'dokter_cc', 'ohse_to', 'ohse_cc', 'ktt_cc']) : [],
            ),
            'pengguna' => Penyetuju::calon($iupId)->map(fn (User $x) => [
                'id'    => $x->id,
                'nama'  => $x->name,
                'email' => $x->email,
                'peran' => $peran($x),
            ])->values(),
            'peran' => collect(Skema::KOLOM)->map(fn ($kolom, $p) => [
                'kunci'    => $p,
                'kolom'    => $kolom,
                'label'    => Penyetuju::LABEL[$p],
                'penerima' => Skema::PENERIMA[$p],
            ])->values(),
        ];
    }
}
