<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Miners\{Alur, Departemen, HasilMcu, Induksi, InduksiOrang, Jabatan,
    Kompetensi, Mcu, McuOrang, Pekerja, Permit, Simper, SimperAjuan, Subkontraktor};
use App\Support\Berkas;
use App\Support\Miners\{AksesMiners, Jalur, Keadaan, Laporan, MonitoringMiners, RincianDokumen, TugasMiners, Verifikasi, Zona};
use App\Support\Perusahaan;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Miners — MCU, Mine Permit, dan SIMPER.
 *
 * Menjawab satu pertanyaan yang ditanyakan tiap pagi di gerbang: boleh
 * atau tidak orang ini bekerja hari ini. Jawabannya menuntut tiga
 * dokumen berlaku BERSAMAAN dan BERURUTAN — MCU menentukan Mine Permit,
 * Mine Permit menentukan SIMPER — sehingga yang paling sering salah
 * bukan salah satu dokumennya melainkan sambungannya: kartu yang
 * tanggalnya masih berlaku padahal MCU yang mendasarinya sudah lewat.
 *
 * KEADAAN DIBACA DARI habisEfektif(), BUKAN DARI KOLOM TANGGALNYA.
 * Lihat App\Support\Miners\Keadaan — pembedaan itu yang membuat layar
 * ini berguna, dan yang paling mudah hilang saat kodenya disederhanakan.
 */
class MinersController extends Controller
{
    /* ═══════════════════ ringkasan ═══════════════════ */

    public function dasbor(Request $r)
    {
        $orang = $this->orangDenganBerkas()->get();

        $baris = $orang->map(fn (Pekerja $p) => $this->keadaanOrang($p));

        /* Dihitung atas SELURUH orang, bukan atas yang tersaring.
           Ringkasan yang mengikuti penyaring menyebut "100% habis"
           begitu seseorang memilih "habis" — angka yang benar bagi
           daftar tersaring dan menyesatkan sebagai gambaran keadaan. */
        $ringkas = [];
        foreach (['mcu', 'induksi', 'permit', 'simper'] as $jenis) {
            $ringkas[$jenis] = $baris->countBy(fn (array $b) => $b[$jenis]['keadaan'])->all()
                + array_fill_keys(array_keys(Keadaan::LABEL), 0);
        }

        return Inertia::render('Miners/Dasbor', [
            'judul'    => 'Miners — Ringkasan',
            'subjudul' => 'MCU, Mine Permit, dan SIMPER dalam satu rantai.',

            'ringkas' => $ringkas,

            'jumlah' => [
                'pekerja'      => $orang->count(),
                'pekerjaAktif' => $orang->where('status', 'aktif')->count(),
                'permitTerbit' => Permit::query()->where('status', 'terbit')->count(),
                'simperTerbit' => Simper::query()->where('status', 'terbit')->count(),
            ],

            /* Antrean per peran, dan hanya yang MENUNGGU. Bagi yang
               bukan peninjau seluruhnya nol — dan itu jawaban yang
               benar, bukan kerusakan. */
            'antrean' => $this->antrean(),

            /* Delapan orang yang paling mendesak, diurut dari yang
               terburuk. Daftar penuh ada di halaman pemantauan; yang di
               sini cukup menunjukkan bahwa ada yang harus dikerjakan. */
            'mendesak' => $baris
                ->filter(fn (array $b) => in_array($b['terburuk'], [Keadaan::HABIS, Keadaan::BELUM], true))
                ->sortBy(fn (array $b) => array_search($b['terburuk'], Keadaan::URUTAN, true))
                ->take(8)->values(),

            'kompetensiHabis' => Kompetensi::query()->akanHabis()->with('pekerja')
                ->orderBy('berlaku_sampai')->take(8)->get()
                ->map(fn (Kompetensi $k) => [
                    'id'      => $k->id,
                    'pekerja' => $k->pekerja?->nama,
                    'nama'    => $k->nama,
                    'sampai'  => $k->berlaku_sampai?->toDateString(),
                    'sisa'    => $k->sisaHari(),
                ]),

            'tinjauan' => $this->tinjauanHarian($r),

            /* Sebaran zona masa berlaku MCU dan Mine Permit untuk donat
               dasbor — dari sisa hari yang sudah dihitung keadaanOrang(),
               zona yang sama dengan kartu zona Manpower MCU. */
            'zonaMcu'    => Zona::ringkas($baris->map(fn (array $b) => Zona::dariSisa($b['mcu']['sisa']))),
            'zonaPermit' => Zona::ringkas($baris->map(fn (array $b) => Zona::dariSisa($b['permit']['sisa']))),

            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
        ]);
    }

    /**
     * Tinjauan Harian MCU — dasbor Safe Track (b947f60): pengajuan MCU pada
     * satu tanggal PENGAJUAN (bukan tanggal pemeriksaan) — diajukan,
     * menunggu dokter, disetujui, ditolak — beserta daftar namanya.
     *
     * Tanggalnya hari tambang; rentangnya dihitung dalam UTC, zona
     * penyimpanan `created_at`.
     *
     * @return array<string,mixed>
     */
    private function tinjauanHarian(Request $r): array
    {
        try {
            $tanggal = \Illuminate\Support\Carbon::parse((string) $r->query('tgl_tinjau', Waktu::kini()->toDateString()), Waktu::zona());
        } catch (\Throwable) {
            $tanggal = Waktu::kini();
        }

        $dari   = $tanggal->copy()->startOfDay()->utc();
        $sampai = $tanggal->copy()->endOfDay()->utc();

        $surat = fn () => Mcu::query()->whereBetween('created_at', [$dari, $sampai]);

        $orang = McuOrang::query()->with(['mcu.company:id,name'])
            ->whereIn('mcu_id', $surat()->select('id'))
            ->orderByDesc('created_at')->limit(50)->get()
            ->map(fn (McuOrang $o) => [
                'id'         => $o->id,
                'mcu_id'     => $o->mcu_id,
                'nama'       => $o->nama,
                'perusahaan' => $o->mcu?->company?->name,
                'jam'        => Waktu::lokal($o->created_at)?->format('H:i'),
                'status'     => $o->mcu?->status,
            ]);

        return [
            'tanggal'   => $tanggal->toDateString(),
            'diajukan'  => $surat()->count(),
            'menunggu'  => $surat()->whereIn('status', ['diajukan', 'diperiksa'])->count(),
            'disetujui' => $surat()->whereIn('status', ['selesai', 'kedaluwarsa'])->count(),
            'ditolak'   => $surat()->where('status', 'ditolak')->count(),
            'orang'     => $orang,
        ];
    }

    /* ═══════════════════ pekerja ═══════════════════ */

    /**
     * Manpower — layar "Manpower MCU" Safe Track (manpower_mcu_admin).
     *
     * Kartu zona masa berlaku MCU yang sekaligus saringan, baris saring
     * (cari, perusahaan, status karyawan, zona), rekap per perusahaan
     * dengan bilah sebaran, lalu daftar manpower berhalaman.
     *
     * Masa berlaku yang dibaca adalah MCU TERAKHIR yang aktif tiap orang.
     * Zona dihitung di PHP, bukan di SQL: rumus selisih tanggal berbeda
     * pada SQLite, MySQL, dan PostgreSQL, dan jumlah manpower satu
     * pemasangan berukuran ribuan, bukan jutaan.
     */
    /**
     * Daftar Manpower tersaring — layar dan unduhan Excel-nya.
     *
     * @return array{0: array<string,string>, 1: Collection, 2: Collection, 3: array, 4: array, 5: array}
     */
    private function manpower(Request $r): array
    {
        $saring = [
            'cari'       => trim((string) $r->query('cari', '')),
            'perusahaan' => (string) $r->query('perusahaan', ''),
            'aktif'      => (string) $r->query('aktif', ''),
            'zona'       => in_array($r->query('zona'), Zona::KUNCI, true) ? (string) $r->query('zona') : '',
        ];

        $mcuTerakhir = McuOrang::withoutGlobalScopes()->select('id')
            ->whereColumn('pekerja_id', 'mnr_pekerja.id')->where('aktif', true)
            ->orderByDesc('tanggal_periksa')->orderByDesc('id')->limit(1);

        $semua = Pekerja::query()
            ->select('mnr_pekerja.*')->addSelect(['mcu_terakhir_id' => $mcuTerakhir])
            ->with(['company:id,name', 'departemen:id,nama', 'jabatan:id,nama'])
            ->orderBy('nama')->get();

        $mcu = McuOrang::withoutGlobalScopes()->with(['mcu', 'hasil'])
            ->whereIn('id', $semua->pluck('mcu_terakhir_id')->filter())->get()->keyBy('id');

        $baris = $semua->map(function (Pekerja $p) use ($mcu) {
            $m    = $mcu[$p->mcu_terakhir_id] ?? null;
            $sisa = Zona::sisa($m?->berlaku_sampai);

            return [
                'id'         => $p->id,
                'nama'       => $p->nama,
                'nik'        => $p->nik,
                'noid'       => $p->no_induk ?: $p->nik,
                'company_id' => $p->company_id,
                'perusahaan' => $p->company?->name,
                'departemen' => $p->departemen?->nama,
                'jabatan'    => $p->jabatan?->nama,
                'status'     => $p->status,
                'aktif'      => $p->aktif(),
                'nonaktifSejak' => $p->tanggal_nonaktif?->toDateString(),
                'mcu_id'     => $m?->mcu_id,
                'noMcu'      => $m?->mcu?->no_registrasi,
                'hasil'      => $m?->hasil?->nama,
                'berlaku'    => $m?->berlaku_sampai?->toDateString(),
                'sisa'       => $sisa,
                'zona'       => Zona::dariSisa($sisa),
                'pengajuan'  => $m?->mcu?->status,
                'cari'       => mb_strtolower($p->nama.' '.$p->nik.' '.$p->no_induk.' '.$p->no_registrasi.' '.$m?->mcu?->no_registrasi),

                /* Isian formulir ubah — dikirim bersama barisnya supaya
                   pensil di kolom "#" tidak perlu permintaan kedua. */
                'isian' => [
                    'company_id'       => $p->company_id ?? '',
                    'nama'             => $p->nama,
                    'nik'              => $p->nik ?? '',
                    'no_induk'         => $p->no_induk ?? '',
                    'tanggal_lahir'    => $p->tanggal_lahir?->toDateString() ?? '',
                    'gol_darah'        => $p->gol_darah ?? '',
                    'telepon'          => $p->telepon ?? '',
                    'telepon_darurat'  => $p->telepon_darurat ?? '',
                    'departemen_id'    => $p->departemen_id ?? '',
                    'jabatan_id'       => $p->jabatan_id ?? '',
                    'subkontraktor_id' => $p->subkontraktor_id ?? '',
                    'status_kerja'     => $p->status_kerja ?? '',
                    'catatan'          => $p->catatan ?? '',
                ],
            ];
        });

        /* Ringkasan manpower dihitung atas SELURUH orang dalam
           jangkauan; kartu zona dan rekap mengikuti saringan selain
           zona — memilih satu zona tidak boleh membuat kartu zona lain
           ikut menjadi nol. */
        $manpower = [
            'total'    => $baris->count(),
            'aktif'    => $baris->where('aktif', true)->count(),
            'nonaktif' => $baris->where('aktif', false)->count(),
        ];

        $tersaring = $baris
            ->when($saring['cari'] !== '', fn ($c) => $c->filter(fn ($b) => str_contains($b['cari'], mb_strtolower($saring['cari']))))
            ->when($saring['perusahaan'] !== '', fn ($c) => $c->where('company_id', (int) $saring['perusahaan']))
            ->when($saring['aktif'] !== '', fn ($c) => $c->where('aktif', $saring['aktif'] === '1'));

        $ringkas = Zona::ringkas($tersaring->pluck('zona'));
        $rekap   = Zona::rekapPerusahaan($tersaring->all(), true);

        if ($saring['zona'] !== '') {
            $tersaring = $tersaring->where('zona', $saring['zona']);
        }

        return [$saring, $baris, $tersaring, $ringkas, $rekap, $manpower];
    }

    /** Unduhan Excel Manpower MCU — kolom layar, saringan layar. */
    public function ekspor(Request $r)
    {
        [, , $isi] = $this->manpower($r);
        $zona = Zona::daftar();

        return Laporan::unduh('manpower-mcu-'.Waktu::kini()->format('Ymd-Hi'), [
            'No Registrasi', 'Nama Employee', 'NIK', 'Noid', 'Perusahaan', 'Departemen', 'Jabatan', 'Hasil MCU',
            'Masa Berlaku MCU', 'Sisa Hari', 'Zona', 'Karyawan', 'Status Pengajuan',
        ], $isi->values()->map(fn ($b) => [
            $b['noMcu'], $b['nama'], $b['nik'], $b['noid'], $b['perusahaan'], $b['departemen'], $b['jabatan'], $b['hasil'],
            $b['berlaku'], $b['sisa'], $zona[$b['zona']]['label'], $b['aktif'] ? 'Aktif' : 'Tidak aktif',
            Mcu::STATUS[$b['pengajuan'] ?? ''] ?? $b['pengajuan'],
        ]));
    }

    public function index(Request $r)
    {
        $u = $r->user();

        [$saring, $baris, $tersaring, $ringkas, $rekap, $manpower] = $this->manpower($r);

        $halaman = $this->halaman($r, $tersaring->values(), 15);

        return Inertia::render('Miners/Daftar', [
            'judul' => 'Manpower MCU',
            'kop'   => false,

            'saring'   => $saring,
            'manpower' => $manpower,
            'ringkas'  => $ringkas,
            'rekap'    => $rekap,
            'baris'    => $halaman,

            'kelola'     => AksesMiners::kelola($u),
            'perusahaan' => $this->perusahaanPilihan($u),
            'pilihan'    => [
                'departemen'    => Departemen::pilihan(),
                'jabatan'       => Jabatan::pilihan(),
                'subkontraktor' => Subkontraktor::query()->where('aktif', true)->orderBy('nama')->pluck('nama', 'id'),
                'golDarah'      => Pekerja::GOL_DARAH,
                'statusKerja'   => Pekerja::STATUS_KERJA,
            ],
            'MCU_STATUS' => Mcu::STATUS,
        ]);
    }

    /**
     * Satu halaman dari koleksi yang sudah tersaring, berbentuk paginator
     * Laravel supaya layar memakai tautan yang sama dengan daftar lain.
     */
    private function halaman(Request $r, Collection $isi, int $per)
    {
        $kini = max(1, (int) $r->query('page', 1));

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $isi->forPage($kini, $per)->values()->map(fn ($b) => \Illuminate\Support\Arr::except($b, ['cari'])),
            $isi->count(), $per, $kini,
            ['path' => $r->url(), 'query' => $r->except('page')],
        );
    }

    /**
     * Perusahaan yang dapat dipilih pada saringan dan formulir manpower.
     *
     * @return list<array{nilai:int,label:string}>
     */
    private function perusahaanPilihan($u): array
    {
        $q = Company::query()->orderBy('name');

        if (! $u->isAdmin()) {
            $q->where(fn ($w) => $w->whereKey($u->company_id)->orWhere('parent_id', $u->company_id));
        } elseif ($lihat = Perusahaan::terpilih($u)) {
            $q->where(fn ($w) => $w->whereKey($lihat)->orWhere('parent_id', $lihat));
        }

        return $q->get(['id', 'name'])->map(fn ($c) => ['nilai' => $c->id, 'label' => $c->name])->all();
    }

    /**
     * Detail karyawan — `detail_manpower` Safe Track dalam bahasa rupa
     * baru: kepala profil, lalu kartu per tahap rantainya (MCU → Induksi
     * → Mine Permit → SIMPER), perpanjangan, berkas, dan tautan cetak
     * kartu / verifikasi. Pengguna biasa hanya membuka data miliknya.
     */
    public function show(Request $r, Pekerja $pekerja)
    {
        $u = $r->user();
        AksesMiners::tegakkan(AksesMiners::lihatPekerja($pekerja, $u), 'Anda hanya dapat membuka data karyawan yang Anda ajukan.');

        $pekerja->load([
            'departemen', 'jabatan', 'subkontraktor', 'blok', 'subBlok', 'company',
            'mcu.hasil', 'mcu.mcu', 'mcu.rujukan',
            'induksi.induksi',
            'permit.mcuOrang', 'permit.berkas', 'permit.alur', 'permit.perpanjanganBerjalan', 'permit.perpanjangan',
            'simper.unit.kendaraan', 'simper.unit.jenisUnit', 'simper.ajuan',
        ]);

        $kompetensi = Kompetensi::query()->where('pekerja_id', $pekerja->id)
            ->with('jenis')->orderByDesc('berlaku_sampai')->get();

        $riwayat = \App\Models\Miners\RiwayatPerpanjangan::query()
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('jenis', 'permit')->whereIn('dokumen_id', $pekerja->permit->pluck('id')))
                ->orWhere(fn ($w) => $w->where('jenis', 'simper')->whereIn('dokumen_id', $pekerja->simper->pluck('id'))))
            ->orderByDesc('created_at')->get();

        $permitAktif = $pekerja->permit->first(fn (Permit $p) => in_array($p->status, ['terbit', 'kedaluwarsa'], true) && $p->aktif);
        $kelola = AksesMiners::kelola($u);

        $berkas = [];
        foreach ($pekerja->mcu as $m) {
            foreach (['mnh' => 'Hasil MCU', 'mnk' => 'Rekomendasi MCU', 'mnz' => 'Hasil NAPZA'] as $j => $l) {
                $berkas[] = ['label' => $l.' '.($m->mcu?->no_registrasi ?? ''), 'kelompok' => 'MCU'] + Berkas::lampiran($u, $m, $j);
            }
        }
        foreach ($pekerja->induksi as $i) {
            $berkas[] = ['label' => 'Sertifikat induksi '.($i->induksi?->no_registrasi ?? ''), 'kelompok' => 'Induksi'] + Berkas::lampiran($u, $i, 'mns');
        }
        foreach ($pekerja->permit as $p) {
            foreach (['mnt' => 'KTP', 'mnq' => 'Permohonan Mine Permit'] as $j => $l) {
                $berkas[] = ['label' => $l.' '.$p->no_registrasi, 'kelompok' => 'Mine Permit'] + Berkas::lampiran($u, $p, $j);
            }
        }
        foreach ($pekerja->simper as $sp) {
            $berkas[] = ['label' => 'Berkas SIM '.$sp->no_simper, 'kelompok' => 'SIMPER'] + Berkas::lampiran($u, $sp, 'mnp');
        }

        return Inertia::render('Miners/Rincian', [
            'judul' => $pekerja->nama,
            'kop'   => false,

            'pekerja' => [
                'id'            => $pekerja->id,
                'nama'          => $pekerja->nama,
                'nik'           => $pekerja->nik,
                'no_induk'      => $pekerja->no_induk,
                'no_registrasi' => $pekerja->no_registrasi,
                'tanggal_lahir' => $pekerja->tanggal_lahir?->toDateString(),
                'usia'          => $pekerja->usia(),
                'gol_darah'     => $pekerja->gol_darah,
                'telepon'       => $pekerja->telepon,
                'telepon_darurat' => $pekerja->telepon_darurat,
                'departemen'    => $pekerja->departemen?->nama,
                'jabatan'       => $pekerja->jabatan?->nama,
                'subkontraktor' => $pekerja->subkontraktor?->nama,
                'lokasi'        => trim(($pekerja->blok?->nama ?? '').' '.($pekerja->subBlok?->nama ?? '')),
                'aktif'         => $pekerja->aktif(),
                'status'        => Pekerja::STATUS[$pekerja->status] ?? $pekerja->status,
                'status_kerja'  => Pekerja::STATUS_KERJA[$pekerja->status_kerja ?? ''] ?? $pekerja->status_kerja,
                'perusahaan'    => $pekerja->company?->name,
                'catatan'       => $pekerja->catatan,
                'nonaktifSejak' => $pekerja->tanggal_nonaktif?->toDateString(),
                'alasanNonaktif'=> $pekerja->alasan_nonaktif,
                'dataLama'      => $pekerja->asal === \App\Support\Miners\DataLama::ASAL,
                'foto'          => $permitAktif ? (Berkas::lampiran($u, $permitAktif, 'mnf')['url'] ?? null) : null,
            ],

            'keadaan' => $this->keadaanOrang($pekerja),

            /* Hasil MCU dan rujukannya hanya bagi pembaca isi medis. */
            'mcu' => $pekerja->mcu->map(fn (McuOrang $m) => [
                'id'       => $m->id,
                'mcu_id'   => $m->mcu_id,
                'surat'    => $m->mcu?->no_registrasi,
                'tanggal'  => $m->tanggal_periksa?->toDateString(),
                'sampai'   => $m->berlaku_sampai?->toDateString(),
                'sisa'     => Zona::sisa($m->berlaku_sampai),
                'aktif'    => (bool) $m->aktif,
                'status'   => $m->mcu?->status,
            ] + (AksesMiners::medis($u, $m->mcu)
                ? ['hasil' => $m->hasil?->nama, 'layak' => $m->layak(), 'rujukan' => $m->rujukan->count()]
                : ['hasil' => null, 'layak' => null, 'rujukan' => 0]))->values(),

            'induksi' => $pekerja->induksi->map(fn (InduksiOrang $i) => [
                'id'        => $i->id,
                'induksi_id'=> $i->induksi_id,
                'surat'     => $i->induksi?->no_registrasi,
                'tanggal'   => $i->tanggal_induksi?->toDateString(),
                'sampai'    => $i->berlaku_sampai?->toDateString(),
                'sisa'      => Zona::sisa($i->berlaku_sampai),
                'nilai'     => $i->nilai,
                'hasil'     => InduksiOrang::STATUS[$i->status] ?? $i->status,
                'lulus'     => $i->status === 'lulus',
                'verifikasi'=> $i->status === 'lulus' ? Verifikasi::alamat(Verifikasi::kode('ID', $i->id)) : null,
            ])->values(),

            'permit' => $pekerja->permit->map(fn (Permit $p) => [
                'id'        => $p->id,
                'nomor'     => $p->no_registrasi,
                'tanggal'   => $p->tanggal?->toDateString(),
                'jenis'     => $p->jenis_kartu,
                'cakupan'   => Permit::CAKUPAN[$p->cakupan_area]['label'] ?? $p->cakupan_area,
                'status'    => $p->status,
                'aktif'     => (bool) $p->aktif,
                'efektif'   => $p->habisEfektif()?->toDateString(),
                'sisa'      => Zona::sisa($p->habisEfektif()),
                'sumber'    => Permit::SUMBER[$p->sumber_berlaku ?? 'mcu'] ?? null,
                'dataLama'  => $p->asal === \App\Support\Miners\DataLama::ASAL,
                'cetak'     => $kelola && in_array($p->status, ['terbit', 'kedaluwarsa'], true) ? route('miners.kartu.cetak', $p->id) : null,
                'verifikasi'=> in_array($p->status, ['terbit', 'kedaluwarsa'], true) ? Verifikasi::alamat(Verifikasi::kode('MP', $p->id)) : null,
                'perpanjangan' => $p->perpanjangan->sortByDesc('tanggal')->map(fn ($x) => [
                    'id' => $x->id, 'nomor' => $x->no_registrasi, 'tanggal' => $x->tanggal?->toDateString(),
                    'berlakuBaru' => $x->berlaku_baru?->toDateString(), 'status' => $x->status,
                ])->values(),
            ])->values(),

            'simper' => $pekerja->simper->map(fn (Simper $s) => [
                'id'        => $s->id,
                'nomor'     => $s->no_simper,
                'tanggal'   => $s->tanggal?->toDateString(),
                'jenisSim'  => $s->jenis_simpol,
                'noSim'     => AksesMiners::noSim($s->no_simpol, $u),
                'status'    => $s->status,
                'aktif'     => (bool) $s->aktif,
                'efektif'   => $s->habisEfektif()?->toDateString(),
                'sisa'      => Zona::sisa($s->habisEfektif()),
                'sumber'    => Simper::SUMBER_SIM[$s->simpol_sumber ?? ''] ?? null,
                'verifikasi'=> $s->status === 'terbit' ? Verifikasi::alamat(Verifikasi::kode('SP', $s->id)) : null,
                /* Unit yang tercetak di kartu — termasuk unit penambahan
                   dan upgrade yang sudah disetujui (asal-nya tercatat). */
                'unit' => $s->unit->map(fn ($x) => [
                    'id' => $x->id, 'unit' => $x->kendaraan?->nama, 'type' => $x->jenisUnit?->nama,
                    'authority' => $x->authority, 'asal' => \App\Models\Miners\SimperUnit::ASAL[$x->asal] ?? $x->asal,
                ])->values(),
                'ajuan' => $s->ajuan->sortByDesc('tanggal')->map(fn (SimperAjuan $a) => [
                    'id' => $a->id, 'jenis' => SimperAjuan::JENIS[$a->jenis] ?? $a->jenis, 'nomor' => $a->no_registrasi,
                    'tanggal' => $a->tanggal?->toDateString(), 'status' => $a->status,
                ])->values(),
            ])->values(),

            'riwayat' => $riwayat->map(fn ($x) => [
                'id' => $x->id, 'jenis' => $x->jenis === 'permit' ? 'Mine Permit' : 'SIMPER',
                'pada' => $x->created_at ? Waktu::lokal($x->created_at)->toDateString() : null,
                'lama' => $x->berlaku_lama?->toDateString(), 'baru' => $x->berlaku_baru?->toDateString(),
            ])->values(),

            'berkas' => array_values(array_filter($berkas, fn ($b) => $b['ada'])),

            'kompetensi' => $kompetensi->map(fn (Kompetensi $k) => [
                'id'      => $k->id,
                'nama'    => $k->nama,
                'lembaga' => $k->lembaga,
                'nomor'   => $k->nomor,
                'terbit'  => $k->tanggal_terbit?->toDateString(),
                'sampai'  => $k->berlaku_sampai?->toDateString(),
                'sisa'    => $k->sisaHari(),
            ])->values(),

            'kelola' => $kelola,
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validasiPekerja($r);

        $pekerja = Pekerja::create($data + ['user_id' => $r->user()?->id, 'status' => 'aktif']);

        return back()->with('sukses', 'Manpower '.$pekerja->nama.' ditambahkan.');
    }

    /**
     * Ubah data manpower — admin atau HSE IUP (lingkup perusahaannya
     * dijaga global scope), atau yang mendaftarkan pekerja itu. Isiannya
     * NIK, tanggal lahir, golongan darah, telepon, dan perusahaan: bukan
     * sesuatu yang boleh diubah setiap pengguna di perusahaan yang sama.
     */
    public function update(Request $r, Pekerja $pekerja)
    {
        $u = $r->user();
        AksesMiners::tegakkan(AksesMiners::kelola($u) || (int) $pekerja->user_id === (int) $u->getKey(),
            'Data manpower hanya diubah admin, HSE IUP, atau yang mendaftarkannya.');

        $pekerja->update($this->validasiPekerja($r, $pekerja));

        return back()->with('sukses', 'Data manpower '.$pekerja->nama.' diperbarui.');
    }

    /**
     * Aktif / tidak aktif — `masa-berlaku/manpower/{id}/status` Safe Track.
     *
     * Hanya "admin" (admin atau HSE IUP), sama dengan Safe Track sejak
     * b39614d: status karyawan menentukan siapa yang dihitung di seluruh
     * layar masa berlaku.
     */
    public function status(Request $r, Pekerja $pekerja)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Status karyawan hanya diubah admin atau HSE IUP.');

        $data = $r->validate([
            'aktif'   => ['required', 'boolean'],
            'tanggal' => ['nullable', 'date'],
            'alasan'  => ['nullable', 'string', 'max:200'],
        ]);

        if ($r->boolean('aktif')) {
            $pekerja->forceFill(['status' => 'aktif', 'tanggal_nonaktif' => null, 'alasan_nonaktif' => null])->save();

            return back()->with('sukses', $pekerja->nama.' kembali aktif.');
        }

        $pekerja->nonaktifkan($data['alasan'] ?? null,
            ! empty($data['tanggal']) ? Waktu::tanggal($data['tanggal']) : null);

        return back()->with('sukses', $pekerja->nama.' dinonaktifkan sejak '
            .$pekerja->tanggal_nonaktif?->format('d-m-Y').'.');
    }

    /**
     * Hapus — atau nonaktifkan bila orangnya masih punya riwayat.
     *
     * Absensi, cuti, lembur, slip gaji, dan kontrak menunjuk pekerja
     * dengan cascadeOnDelete: menghapusnya diam-diam menghapus seluruh
     * riwayat HR orang itu. Safe Track tidak pernah menghapus manpower;
     * ia menonaktifkannya. Yang benar-benar dihapus hanya orang yang
     * belum punya satu baris pun di mana pun — salah ketik, misalnya.
     */
    public function destroy(Request $r, Pekerja $pekerja)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Manpower hanya dihapus admin atau HSE IUP.');

        if ($riwayat = $pekerja->riwayatTerkait()) {
            $pekerja->nonaktifkan('Dihapus dari daftar; riwayat dipertahankan.');

            return back()->with('sukses', $pekerja->nama.' masih punya riwayat ('.count($riwayat)
                .' tabel) — dinonaktifkan, tidak dihapus.');
        }

        $pekerja->delete();

        return redirect()->route('miners.index')->with('sukses', 'Manpower '.$pekerja->nama.' dihapus.');
    }

    /* ═══════════════════ pemantauan masa berlaku ═══════════════════ */

    public function kedaluwarsa(Request $r)
    {
        [$saring, $baris] = $this->monitoring($r);

        $ringkas = MonitoringMiners::ringkas($baris);
        $rekap   = MonitoringMiners::rekap($baris);

        /* Tanpa saringan: "Perlu Tindak Lanjut" (lewat / ≤ 30 hari, 15
           paling mendesak) seperti Safe Track. Dengan saringan jenis atau
           zona (klik kartu zona): seluruh dokumen yang cocok, berhalaman. */
        $tersaring = $this->saringMonitoring($baris, $saring);
        $disaring  = $saring['jenis'] !== '' || $saring['zona'] !== '';
        $isi       = $disaring ? $tersaring : MonitoringMiners::mendesak($tersaring);
        $kini      = max(1, (int) $r->query('page', 1));
        $per       = $disaring ? 25 : 15;

        return Inertia::render('Miners/Kedaluwarsa', [
            'judul'    => 'Monitoring Masa Berlaku',
            'subjudul' => 'MCU, Induksi, Mine Permit, dan SIMPER — siapa yang hari ini tidak boleh masuk.',
            'ringkas'  => $ringkas,
            'rekap'    => $rekap,
            'manpower' => [
                'total'    => $baris->where('jenis', 'mcu')->count(),
                'aktif'    => $baris->where('jenis', 'mcu')->where('aktif', true)->count(),
                'nonaktif' => $baris->where('jenis', 'mcu')->where('aktif', false)->count(),
            ],
            'daftar' => new \Illuminate\Pagination\LengthAwarePaginator(
                $isi->forPage($kini, $per)->values(), $disaring ? $isi->count() : min($isi->count(), $per), $per, $kini,
                ['path' => $r->url(), 'query' => $r->except('page')],
            ),
            'mendesakTotal' => MonitoringMiners::mendesak($tersaring)->count(),
            'disaring' => $disaring,
            'saring'   => $saring,
            'JENIS'    => MonitoringMiners::JENIS,
            'KETERANGAN' => MonitoringMiners::KETERANGAN,
            'perusahaan' => AksesMiners::perusahaan($r->user())->map->only(['id', 'name'])->values(),
        ]);
    }

    /** Unduhan Excel monitoring — kolom = kolom tabel di layar, saringan yang sama. */
    public function kedaluwarsaEkspor(Request $r)
    {
        [$saring, $baris] = $this->monitoring($r);
        $isi = $this->saringMonitoring($baris, $saring);
        if ($saring['jenis'] === '' && $saring['zona'] === '') $isi = MonitoringMiners::mendesak($isi);
        $zona = Zona::daftar();

        return Laporan::unduh('monitoring-masa-berlaku-'.Waktu::kini()->format('Ymd-Hi'),
            ['Modul', 'Nama', 'No ID', 'Perusahaan', 'No Dokumen', 'Berakhir', 'Sisa Hari', 'Zona', 'Status'],
            $isi->map(fn ($b) => [
                MonitoringMiners::JENIS[$b['jenis']], $b['nama'], $b['noid'], $b['perusahaan'], $b['nomor'],
                $b['berakhir'], $b['sisa'], $zona[$b['zona']]['label'], $b['aktif'] ? 'Aktif' : 'Tidak aktif',
            ]));
    }

    /** @return array{0: array<string,string>, 1: Collection<int,array<string,mixed>>} */
    private function monitoring(Request $r): array
    {
        $saring = [
            'cari'       => trim((string) $r->query('cari', '')),
            'perusahaan' => (string) $r->query('perusahaan', ''),
            'jenis'      => array_key_exists($j = (string) $r->query('jenis'), MonitoringMiners::JENIS) ? $j : '',
            'zona'       => in_array($z = (string) $r->query('zona'), Zona::KUNCI, true) ? $z : '',
        ];

        $baris = MonitoringMiners::baris()
            ->when($saring['perusahaan'] !== '', fn ($c) => $c->where('company_id', (int) $saring['perusahaan']))
            ->when($saring['cari'] !== '', function ($c) use ($saring) {
                $k = mb_strtolower($saring['cari']);

                return $c->filter(fn ($b) => str_contains(mb_strtolower($b['nama'].' '.$b['noid'].' '.$b['nomor']), $k));
            })->values();

        return [$saring, $baris];
    }

    /** @param Collection<int,array<string,mixed>> $baris */
    private function saringMonitoring(Collection $baris, array $saring): Collection
    {
        return $baris
            ->when($saring['jenis'] !== '', fn ($c) => $c->where('jenis', $saring['jenis']))
            ->when($saring['zona'] !== '', fn ($c) => $c->where('zona', $saring['zona'])->where('aktif', true))
            /* Paling mendesak lebih dulu; yang tanggalnya kosong di akhir. */
            ->sortBy(fn ($b) => sprintf('%012d|%s', ($b['sisa'] ?? 99_999_999) + 1_000_000, mb_strtolower((string) $b['nama'])))
            ->values();
    }

    /* ═══════════════════ riwayat ═══════════════════ */

    /**
     * Riwayat satu jenis dokumen.
     *
     * Lima nama rute tersendiri, bukan satu rute berparameter —
     * RuteInertiaTest memanggil SETIAP nama rute terdaftar tanpa
     * parameter, dan satu rute berparameter memaksa uji itu menyimpan
     * daftar parameter contoh yang akan tertinggal saat jenisnya
     * berubah.
     */
    public function riwayat(Request $r)
    {
        $tahap = $r->route('tahap');
        $cari  = trim((string) $r->query('cari'));

        /* Riwayat MCU, Induksi, Mine Permit, dan SIMPER dilayani
           pengendali masing-masing (rute riwayat.* menunjuk ke sana);
           yang tersisa di sini hanya riwayat Authority/kompetensi. */
        $baris = Kompetensi::query()->with('pekerja')
            ->when($cari, fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$cari}%")
                ->orWhereHas('pekerja', fn ($pk) => $pk->where('nama', 'like', "%{$cari}%"))))
            ->orderByDesc('berlaku_sampai')->limit(300)->get()
            ->map(fn (Kompetensi $k) => [
                'id'      => $k->id,
                'pekerja_id' => $k->pekerja_id,
                'nama'    => $k->pekerja?->nama,
                'nomor'   => $k->nomor,
                'tanggal' => $k->tanggal_terbit?->toDateString(),
                'sampai'  => $k->berlaku_sampai?->toDateString(),
                'ket'     => $k->nama,
                'keadaan' => Keadaan::kompetensi($k),
            ]);

        return Inertia::render('Miners/Riwayat', [
            'judul'    => 'Miners — Riwayat Kompetensi',
            'subjudul' => 'Apa yang pernah terjadi pada dokumen ini.',

            'tahap'   => $tahap,
            'baris'   => $baris,
            'saring'  => ['cari' => $cari],
            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
        ]);
    }

    /* ═══════════════════ antrean ═══════════════════ */

    /** Judul dan sumber tiap daftar antrean. */
    private const ANTREAN = [
        'outstanding-mcu'     => ['Data Outstanding MCU',         'Outstanding MCU Table',         'mcu',     null],
        'outstanding-induksi' => ['Data Outstanding Induction',   'Outstanding Induction Table',   'induksi', null],
        'outstanding-permit'  => ['Data Outstanding Mine Permit', 'Outstanding Mine Permit Table', 'permit',  null],
        'outstanding-simper'  => ['Data Outstanding SIMPER',      'Outstanding SIMPER Table',      'simper',  null],
        'perpanjangan-permit' => ['Perpanjangan Mine Permit',     'Perpanjangan Mine Permit Table','perpanjangan_permit', null],
    ];

    /**
     * Antrean persetujuan dan daftar menyilang — Outstanding Safe Track.
     *
     * Bawaannya "Perlu Approval Saya": dokumen yang langkahnya SEDANG
     * menunggu orang ini — angka yang sama dengan lonceng tugasnya.
     * `?semua=1` memperlihatkan seluruh yang masih berjalan dalam
     * jangkauan perusahaannya (commit 72250f7, 9305535 Safe Track).
     */
    public function daftar(Request $r)
    {
        $jenis = $r->route('jenis');

        if (isset(self::ANTREAN[$jenis])) {
            return $this->antreanPersetujuan($r, $jenis);
        }

        /* Daftar rujukan berisi poliklinik, dokter spesialis, dan catatan
           medisnya: hanya admin, tim OHSE, dan paramedis. */
        if ($jenis === 'rujukan') {
            AksesMiners::tegakkan(AksesMiners::medis($r->user()), 'Data rujukan MCU hanya dibuka admin, tim OHSE, atau paramedis.');
        }

        [$baris, $judul] = match ($jenis) {
            /* `master_admin_rujukan` Safe Track: orang yang hasilnya
               "Rujukan" — ditambah yang sudah punya surat rujukan, supaya
               rujukan lama tetap terbaca. */
            'rujukan' => [
                McuOrang::query()
                    ->where(fn ($q) => $q->whereHas('rujukan')
                        ->when(HasilMcu::rujukan(), fn ($w, $id) => $w->orWhere('hasil_id', $id)))
                    ->with(['pekerja.company', 'rujukan', 'hasil', 'mcu'])
                    ->orderByDesc('tanggal_periksa')->get()
                    ->map(fn (McuOrang $m) => [
                        'id'         => $m->id,
                        'url'        => $m->mcu_id ? '/miners/mcu/'.$m->mcu_id : null,
                        'nomor'      => $m->mcu?->no_registrasi,
                        'tanggal'    => $m->rujukan->first()?->tanggal_surat?->toDateString(),
                        'judul'      => $m->nama.' — '.($m->rujukan->first()?->poliklinik ?: ($m->hasil?->nama ?? 'rujukan')),
                        'perusahaan' => $m->pekerja?->company?->name,
                        'status'     => $m->mcu?->status,
                        'oleh'       => $m->rujukan->first()?->dokter,
                        'posisi'     => $m->rujukan->first()?->keterangan,
                    ]),
                'Data Rujukan MCU',
            ],

            /* Cetak Kartu kini Miners\KartuController (rute daftar.cetak-kartu). */
            default => abort(404),
        };

        return Inertia::render('Miners/Antrean', [
            'judul' => $judul,
            'kop'   => false,
            'jenis' => $jenis,
            'tabel' => $judul.' Table',
            'baris' => $baris->values(),
            'pilih' => null,
        ]);
    }

    private function antreanPersetujuan(Request $r, string $jenis)
    {
        [$judul, $tabel, $dokumen, $ajuan] = self::ANTREAN[$jenis];

        $kelas = TugasMiners::KELAS[$dokumen];
        $u     = $r->user();

        $berjalan = fn () => $kelas::query()
            ->whereIn('status', TugasMiners::BERJALAN)
            ->when($ajuan, fn ($q) => $q->where('jenis', $ajuan));

        $milikSaya = Alur::query()->giliran()
            ->where('dokumen', $dokumen)
            ->where('ditugaskan_user_id', $u->getKey())
            ->where('peran', '!=', 'pengaju')
            ->select('dokumen_id');

        /* Yang ditugaskan kepada orang ini dihitung TANPA batas
           perusahaan, sama seperti lonceng: HSE IUP harus melihat berkas
           mitranya yang menunggu dirinya. */
        $saya = $kelas::withoutGlobalScopes()
            ->whereIn('status', TugasMiners::BERJALAN)
            ->when($ajuan, fn ($q) => $q->where('jenis', $ajuan))
            ->whereIn('id', $milikSaya);

        $semua = $r->boolean('semua');

        $dok = ($semua ? $berjalan() : $saya)
            ->with(array_merge(['user', 'alur.ditugaskan', 'alur.user'], match ($dokumen) {
                'permit', 'simper' => ['pekerja.company'],
                'ajuan'            => ['simper.pekerja.company'],
                'perpanjangan_permit' => ['permit.pekerja.company'],
                default            => ['company'],
            }))
            ->orderByDesc('tanggal')->orderByDesc('id')->get();

        return Inertia::render('Miners/Antrean', [
            'judul' => $judul,
            'kop'   => false,
            'jenis' => $jenis,
            'tabel' => $tabel,
            'baris' => $dok->map(fn ($d) => [
                'id'         => $d->id,
                'url'        => RincianDokumen::tautan($d),
                'nomor'      => $d->no_registrasi ?? $d->no_simper ?? null,
                'tanggal'    => $d->tanggal?->toDateString(),
                'judul'      => match ($dokumen) {
                    'mcu'                 => $d->perihal ?: $d->kepada,
                    'induksi'             => $d->perihal,
                    'permit', 'simper'    => $d->pekerja?->nama,
                    'ajuan'               => $d->simper?->pekerja?->nama,
                    'perpanjangan_permit' => $d->permit?->pekerja?->nama,
                    default               => null,
                },
                'perusahaan' => match ($dokumen) {
                    'permit', 'simper'    => $d->pekerja?->company?->name,
                    'ajuan'               => $d->simper?->pekerja?->company?->name,
                    'perpanjangan_permit' => $d->permit?->pekerja?->company?->name,
                    default               => $d->company?->name,
                },
                'status'     => $d->status,
                'oleh'       => $d->user?->name,
                'posisi'     => Jalur::posisi($d)['pesan'],
            ])->values(),
            'pilih' => [
                'aktif' => $semua ? 'semua' : 'saya',
                'saya'  => (clone $saya)->count(),
                'semua' => $berjalan()->count(),
            ],
        ]);
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /** Kueri pekerja beserta keempat berkasnya, sekali muat. */
    private function orangDenganBerkas()
    {
        return Pekerja::query()
            ->with([
                'departemen', 'jabatan', 'company',
                'mcu.hasil',
                'induksi',
                'permit.mcuOrang', 'permit.tipe',
                'simper.permit.mcuOrang',
            ])
            ->orderBy('nama');
    }

    /**
     * Keadaan keempat berkas satu orang, beserta yang terburuk.
     *
     * Yang dibaca selalu yang TERBARU — `mcu`, `permit`, dan `simper`
     * pada model sudah terurut menurun — sebab yang menentukan boleh
     * tidaknya masuk hari ini adalah dokumen terakhirnya, bukan
     * dokumen mana pun yang kebetulan pernah berlaku.
     *
     * @return array<string,mixed>
     */
    private function keadaanOrang(Pekerja $p): array
    {
        $mcu     = $p->mcu->first();
        $induksi = $p->induksi->first();
        $permit  = $p->permit->first();
        $simper  = $p->simper->first();

        /* SISA HARI HANYA BAGI YANG PUNYA MASA BERLAKU.
         *
         * Kartu yang belum terbit tetap punya tanggal — draf pun
         * berkolom berlaku_sampai — sehingga mengirimkan sisanya apa
         * adanya menghasilkan lencana berbunyi "Belum ada · 4 hari
         * lagi". Itu bukan sekadar janggal: ia terbaca seolah ada
         * sesuatu yang akan habis empat hari lagi, pada baris yang
         * justru berarti belum ada apa pun untuk dihabiskan. */
        $k = [
            'mcu'     => $this->kolom(Keadaan::mcu($mcu),         $mcu?->berlaku_sampai,      $mcu?->sisaHari()),
            'induksi' => $this->kolom(Keadaan::induksi($induksi), $induksi?->berlaku_sampai,  null),
            'permit'  => $this->kolom(Keadaan::permit($permit),   $permit?->habisEfektif(),   $permit?->sisaHari()),
            'simper'  => $this->kolom(Keadaan::simper($simper),   $simper?->habisEfektif(),   $simper?->sisaHari()),
        ];

        return [
            'id'         => $p->id,
            'nama'       => $p->nama,
            'nik'        => $p->nik,
            'jabatan'    => $p->jabatan?->nama,
            'departemen' => $p->departemen?->nama,
            'perusahaan' => $p->company?->name,
            'status'     => $p->status,

            /* Sebab kartunya gugur ditulis tersendiri. "Kartu habis 12
               Maret" tidak memberi tahu apa yang harus diperbarui;
               "MCU habis 12 Maret" memberi tahu. */
            'sebab'      => $simper?->penyebabHabis(),

            'terburuk'   => Keadaan::terburuk(array_column($k, 'keadaan')),
        ] + $k;
    }

    /**
     * Satu kolom keadaan pada baris pekerja.
     *
     * @return array{keadaan:string,sampai:?string,sisa:?int}
     */
    private function kolom(string $keadaan, ?\Illuminate\Support\Carbon $sampai, ?int $sisa): array
    {
        $ada = $keadaan !== Keadaan::BELUM;

        return [
            'keadaan' => $keadaan,
            'sampai'  => $ada ? $sampai?->toDateString() : null,
            'sisa'    => $ada ? $sisa : null,
        ];
    }

    /**
     * Berapa dokumen tiap jenis yang menunggu tindakan.
     *
     * @return array<string,int>
     */
    private function antrean(): array
    {
        $n = [];

        foreach (['mcu' => Mcu::class, 'induksi' => Induksi::class,
                  'permit' => Permit::class, 'simper' => Simper::class] as $jenis => $kelas) {
            /* Hanya yang SUDAH DIKIRIM dan masih berjalan. Draf punya
               langkah "menunggu" juga — langkah pengajunya — tetapi belum
               menunggu siapa pun selain pembuatnya sendiri. */
            $n[$jenis] = $kelas::query()
                ->whereIn('status', TugasMiners::BERJALAN)
                ->whereIn('id', Alur::query()->where('dokumen', $jenis)
                    ->where('keadaan', 'menunggu')->select('dokumen_id'))
                ->count();
        }

        return $n;
    }

    /**
     * Isian manpower.
     *
     * NIK UNIK PER PERUSAHAAN. Safe Track menolak NIK ganda pada impor
     * data lama; manpower yang tercatat dua kali berarti dua MCU, dua
     * permit, dan dua baris di setiap rekap untuk satu orang.
     *
     * @return array<string,mixed>
     */
    private function validasiPekerja(Request $r, ?Pekerja $lama = null): array
    {
        $u = $r->user();
        $bolehPerusahaan = array_column($this->perusahaanPilihan($u), 'nilai');

        $perusahaan = $r->filled('company_id') ? (int) $r->input('company_id')
            : ($lama?->company_id ?? $u->company_id);

        $data = $r->validate([
            'company_id'       => ['nullable', 'integer', 'in:'.implode(',', $bolehPerusahaan ?: [0])],
            'nama'             => ['required', 'string', 'max:150'],
            'nik'              => ['nullable', 'string', 'max:40',
                \Illuminate\Validation\Rule::unique('mnr_pekerja', 'nik')
                    ->where(fn ($q) => $perusahaan ? $q->where('company_id', $perusahaan) : $q->whereNull('company_id'))
                    ->ignore($lama?->id)],
            'no_induk'         => ['nullable', 'string', 'max:40'],
            'no_registrasi'    => ['nullable', 'string', 'max:40'],
            'tanggal_lahir'    => ['nullable', 'date'],
            'gol_darah'        => ['nullable', 'string', 'in:'.implode(',', Pekerja::GOL_DARAH)],
            'telepon'          => ['nullable', 'string', 'max:30'],
            'telepon_darurat'  => ['nullable', 'string', 'max:30'],
            'departemen_id'    => ['nullable', 'exists:mnr_departemen,id'],
            'jabatan_id'       => ['nullable', 'exists:mnr_jabatan,id'],
            'subkontraktor_id' => ['nullable', 'exists:mnr_subkontraktor,id'],
            'blok_id'          => ['nullable', 'exists:mnr_blok,id'],
            'sub_blok_id'      => ['nullable', 'exists:mnr_sub_blok,id'],
            'status_kerja'     => ['nullable', 'in:'.implode(',', array_keys(Pekerja::STATUS_KERJA))],
            'catatan'          => ['nullable', 'string'],
        ], [
            'nik.unique'    => 'NIK ini sudah terdaftar pada perusahaan yang sama.',
            'company_id.in' => 'Perusahaan di luar jangkauan Anda.',
        ]);

        if (! array_key_exists('company_id', $data) || $data['company_id'] === null) {
            unset($data['company_id']);
        }

        return $data;
    }
}
