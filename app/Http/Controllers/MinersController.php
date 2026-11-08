<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Miners\{Alur, Blok, Departemen, HasilMcu, Induksi, InduksiOrang, Jabatan,
    JenisUnit, KategoriPermit, Kendaraan, Kompetensi, Mcu, McuOrang, Pekerja, Permit,
    Pjo, Simper, SimperAjuan, Subkontraktor, TipePermit};
use App\Models\KompetensiJenis;
use App\Support\Miners\{Acuan, AksesMiners, Jalur, Keadaan, RincianDokumen, TugasMiners, Zona};
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

    public function dasbor()
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

            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
        ]);
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
    public function index(Request $r)
    {
        $u = $r->user();

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

    public function show(Pekerja $pekerja)
    {
        $pekerja->load([
            'departemen', 'jabatan', 'subkontraktor', 'blok', 'subBlok', 'company',
            'mcu.hasil', 'mcu.mcu', 'mcu.rujukan',
            'induksi.induksi',
            'permit.tipe', 'permit.kategori', 'permit.mcuOrang', 'permit.berkas', 'permit.alur',
            'simper.unit.kendaraan', 'simper.unit.jenisUnit', 'simper.permit.mcuOrang',
            'simper.ajuan.unit', 'simper.alur',
        ]);

        $kompetensi = Kompetensi::query()->where('pekerja_id', $pekerja->id)
            ->with('jenis')->orderByDesc('berlaku_sampai')->get();

        return Inertia::render('Miners/Rincian', [
            'judul'    => $pekerja->nama,
            'subjudul' => 'Berkas kelayakan kerja — MCU, induksi, Mine Permit, SIMPER, kompetensi.',

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
                'status'        => $pekerja->status,
                'status_kerja'  => $pekerja->status_kerja,
                'perusahaan'    => $pekerja->company?->name,
                'catatan'       => $pekerja->catatan,
            ],

            'keadaan' => $this->keadaanOrang($pekerja),

            'mcu' => $pekerja->mcu->map(fn (McuOrang $m) => [
                'id'       => $m->id,
                'surat'    => $m->mcu?->no_registrasi,
                'tanggal'  => $m->tanggal_periksa?->toDateString(),
                'sampai'   => $m->berlaku_sampai?->toDateString(),
                'hasil'    => $m->hasil?->nama,
                'layak'    => $m->layak(),
                'napza'    => $m->hasil_napza,
                'sisa'     => $m->sisaHari(),
                'keadaan'  => Keadaan::mcu($m),
                'rujukan'  => $m->rujukan->map(fn ($r) => [
                    'id'        => $r->id,
                    'tanggal'   => $r->tanggal_surat?->toDateString(),
                    'dokter'    => $r->dokter,
                    'poliklinik'=> $r->poliklinik,
                    'keterangan'=> $r->keterangan,
                ]),
            ]),

            'induksi' => $pekerja->induksi->map(fn (InduksiOrang $i) => [
                'id'        => $i->id,
                'surat'     => $i->induksi?->no_registrasi,
                'tanggal'   => $i->tanggal_induksi?->toDateString(),
                'sampai'    => $i->berlaku_sampai?->toDateString(),
                'nilai'     => $i->nilai,
                'percobaan' => $i->percobaan,
                'status'    => $i->status,
                'lulus'     => $i->lulus(),
                'keadaan'   => Keadaan::induksi($i),
            ]),

            'permit' => $pekerja->permit->map(fn (Permit $p) => $this->barisPermit($p)),
            'simper' => $pekerja->simper->map(fn (Simper $s) => $this->barisSimper($s)),

            'kompetensi' => $kompetensi->map(fn (Kompetensi $k) => [
                'id'      => $k->id,
                'nama'    => $k->nama,
                'lembaga' => $k->lembaga,
                'nomor'   => $k->nomor,
                'terbit'  => $k->tanggal_terbit?->toDateString(),
                'sampai'  => $k->berlaku_sampai?->toDateString(),
                'sisa'    => $k->sisaHari(),
                'keadaan' => Keadaan::kompetensi($k),
            ]),

            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
            'NILAI_LULUS' => InduksiOrang::NILAI_LULUS,
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validasiPekerja($r);

        $pekerja = Pekerja::create($data + ['user_id' => $r->user()?->id, 'status' => 'aktif']);

        return back()->with('sukses', 'Manpower '.$pekerja->nama.' ditambahkan.');
    }

    public function update(Request $r, Pekerja $pekerja)
    {
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
        $jenis = in_array($j = $r->query('jenis'), ['mcu', 'induksi', 'permit', 'simper'], true) ? $j : null;

        $orang = $this->orangDenganBerkas()
            ->cari(trim((string) $r->query('cari')))
            ->when($r->query('perusahaan'), fn ($q, $c) => $q->where('company_id', $c))
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->get();

        $baris = $orang->map(fn (Pekerja $p) => $this->keadaanOrang($p));

        /* Ringkasan dihitung SEBELUM penyaring keadaan dipasang —
           alasannya sama dengan pada dasbor di atas. */
        $ringkas = [];
        foreach (['mcu', 'induksi', 'permit', 'simper'] as $k) {
            $ringkas[$k] = array_fill_keys(array_keys(Keadaan::LABEL), 0)
                + $baris->countBy(fn (array $b) => $b[$k]['keadaan'])->all();
        }

        $tersaring = $baris;

        if ($jenis) {
            $tersaring = $tersaring->filter(
                fn (array $b) => $b[$jenis]['keadaan'] !== Keadaan::BERLAKU,
            )->values();
        }

        if ($keadaan = $r->query('keadaan')) {
            $tersaring = $tersaring->filter(fn (array $b) => $jenis
                ? $b[$jenis]['keadaan'] === $keadaan
                : $b['terburuk'] === $keadaan)->values();
        }

        return Inertia::render('Miners/Kedaluwarsa', [
            'judul'    => 'Miners — Masa Berlaku Berkas',
            'subjudul' => 'Siapa yang hari ini tidak boleh masuk, dan apa yang harus diurus.',

            'baris'   => $tersaring,
            'ringkas' => $ringkas,

            'saring' => [
                'cari'       => $r->query('cari', ''),
                'jenis'      => $jenis ?? '',
                'keadaan'    => $r->query('keadaan', ''),
                'perusahaan' => $r->query('perusahaan', ''),
                'status'     => $r->query('status', ''),
            ],

            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
            'STATUS'  => Pekerja::STATUS,
            'JENIS'   => [
                'mcu'     => 'MCU',
                'induksi' => 'Induksi',
                'permit'  => 'Mine Permit',
                'simper'  => 'SIMPER',
            ],
            'perusahaan' => Company::query()
                ->when(! auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
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

        $baris = match ($tahap) {
            'mcu' => McuOrang::query()->with(['mcu', 'pekerja', 'hasil'])
                ->when($cari, fn ($q) => $q->where('nama', 'like', "%{$cari}%"))
                ->orderByDesc('tanggal_periksa')->limit(300)->get()
                ->map(fn (McuOrang $m) => [
                    'id'      => $m->id,
                    'pekerja_id' => $m->pekerja_id,
                    'nama'    => $m->nama,
                    'nomor'   => $m->mcu?->no_registrasi,
                    'tanggal' => $m->tanggal_periksa?->toDateString(),
                    'sampai'  => $m->berlaku_sampai?->toDateString(),
                    'ket'     => $m->hasil?->nama,
                    'keadaan' => Keadaan::mcu($m),
                ]),

            'induksi' => InduksiOrang::query()->with(['induksi', 'pekerja'])
                ->orderByDesc('tanggal_induksi')->limit(300)->get()
                ->map(fn (InduksiOrang $i) => [
                    'id'      => $i->id,
                    'pekerja_id' => $i->pekerja_id,
                    'nama'    => $i->pekerja?->nama,
                    'nomor'   => $i->induksi?->no_registrasi,
                    'tanggal' => $i->tanggal_induksi?->toDateString(),
                    'sampai'  => $i->berlaku_sampai?->toDateString(),
                    'ket'     => $i->nilai === null ? null : 'Nilai '.$i->nilai,
                    'keadaan' => Keadaan::induksi($i),
                ]),

            'mine-permit' => Permit::query()->with(['pekerja', 'tipe', 'mcuOrang'])
                ->orderByDesc('tanggal')->limit(300)->get()
                ->map(fn (Permit $p) => [
                    'id'      => $p->id,
                    'pekerja_id' => $p->pekerja_id,
                    'nama'    => $p->pekerja?->nama,
                    'nomor'   => $p->no_registrasi,
                    'tanggal' => $p->tanggal?->toDateString(),
                    'sampai'  => $p->habisEfektif()?->toDateString(),
                    'ket'     => $p->tipe?->nama,
                    'keadaan' => Keadaan::permit($p),
                ]),

            'mine-license' => Simper::query()->with(['pekerja', 'unit', 'permit.mcuOrang'])
                ->orderByDesc('tanggal')->limit(300)->get()
                ->map(fn (Simper $s) => [
                    'id'      => $s->id,
                    'pekerja_id' => $s->pekerja_id,
                    'nama'    => $s->pekerja?->nama,
                    'nomor'   => $s->no_simper,
                    'tanggal' => $s->tanggal?->toDateString(),
                    'sampai'  => $s->habisEfektif()?->toDateString(),
                    'ket'     => 'Kelas '.$s->kelas.' · '.$s->unit->count().' unit',
                    'keadaan' => Keadaan::simper($s),
                ]),

            default => Kompetensi::query()->with('pekerja')
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
                ]),
        };

        return Inertia::render('Miners/Riwayat', [
            'judul'    => 'Miners — Riwayat '.($this->JUDUL_TAHAP[$tahap] ?? 'Kompetensi'),
            'subjudul' => 'Apa yang pernah terjadi pada dokumen ini.',

            'tahap'   => $tahap,
            'baris'   => $baris,
            'saring'  => ['cari' => $cari],
            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
        ]);
    }

    private array $JUDUL_TAHAP = [
        'mcu'          => 'MCU',
        'induksi'      => 'Induksi',
        'mine-permit'  => 'Mine Permit',
        'mine-license' => 'SIMPER',
        'authority'    => 'Kompetensi',
    ];

    /* ═══════════════════ antrean ═══════════════════ */

    /** Judul dan sumber tiap daftar antrean. */
    private const ANTREAN = [
        'outstanding-mcu'     => ['Data Outstanding MCU',         'Outstanding MCU Table',         'mcu',     null],
        'outstanding-induksi' => ['Data Outstanding Induction',   'Outstanding Induction Table',   'induksi', null],
        'outstanding-permit'  => ['Data Outstanding Mine Permit', 'Outstanding Mine Permit Table', 'permit',  null],
        'outstanding-simper'  => ['Data Outstanding SIMPER',      'Outstanding SIMPER Table',      'simper',  null],
        'penambahan-unit'     => ['Penambahan Unit',              'Penambahan Unit Table',         'ajuan',   'penambahan'],
        'upgrade-simper'      => ['Upgrade SIMPER',               'Upgrade SIMPER Table',          'ajuan',   'upgrade'],
        'perpanjangan'        => ['Perpanjangan SIMPER',          'Perpanjangan SIMPER Table',     'ajuan',   'perpanjangan'],
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

        [$baris, $judul] = match ($jenis) {
            'rujukan' => [
                McuOrang::query()->whereHas('rujukan')->with(['pekerja.company', 'rujukan', 'hasil', 'mcu'])
                    ->orderByDesc('tanggal_periksa')->get()
                    ->map(fn (McuOrang $m) => [
                        'id'         => $m->id,
                        'url'        => $m->mcu_id ? '/miners/mcu/'.$m->mcu_id : null,
                        'nomor'      => $m->mcu?->no_registrasi,
                        'tanggal'    => $m->rujukan->first()?->tanggal_surat?->toDateString(),
                        'judul'      => $m->nama.' — '.($m->rujukan->first()?->poliklinik ?: 'rujukan'),
                        'perusahaan' => $m->pekerja?->company?->name,
                        'status'     => $m->mcu?->status,
                        'oleh'       => $m->rujukan->first()?->dokter,
                        'posisi'     => $m->rujukan->first()?->keterangan,
                    ]),
                'Data Rujukan MCU',
            ],

            default => [
                Permit::query()->where('status', 'terbit')->with(['pekerja.company', 'user'])
                    ->orderByDesc('tanggal')->get()
                    ->map(fn (Permit $p) => [
                        'id'         => $p->id,
                        'url'        => '/miners/permit/'.$p->id,
                        'nomor'      => $p->no_registrasi,
                        'tanggal'    => $p->tanggal?->toDateString(),
                        'judul'      => $p->pekerja?->nama,
                        'perusahaan' => $p->pekerja?->company?->name,
                        'status'     => $p->status,
                        'oleh'       => $p->user?->name,
                        'posisi'     => trim(($p->jenis_kartu ?: 'Mine Permit').' · '.$p->cakupan_area, ' ·'),
                    ]),
                'Kartu Siap Cetak',
            ],
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
            'sebab'      => $permit?->gugurKarenaMcu() ? 'mcu' : ($simper?->penyebabHabis()),

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

    /** @return array<string,mixed> */
    private function barisPermit(Permit $p): array
    {
        return [
            'id'        => $p->id,
            'nomor'     => $p->no_registrasi,
            'tanggal'   => $p->tanggal?->toDateString(),
            'tipe'      => $p->tipe?->nama,
            'kategori'  => $p->kategori?->nama,
            'cakupan'   => $p->cakupan_area,
            'warna'     => $p->kode_warna,
            'status'    => $p->status,
            'sampai'    => $p->berlaku_sampai?->toDateString(),
            'efektif'   => $p->habisEfektif()?->toDateString(),
            'sumber'    => $p->sumber_berlaku,
            'sisa'      => $p->sisaHari(),
            'gugurMcu'  => $p->gugurKarenaMcu(),
            'keadaan'   => Keadaan::permit($p),
            'berkas'    => $p->relationLoaded('berkas')
                ? $p->berkas->map(fn ($b) => ['id' => $b->id, 'jenis' => $b->jenis, 'catatan' => $b->catatan])
                : [],
            'alur'      => $this->barisAlur($p),
        ];
    }

    /** @return array<string,mixed> */
    private function barisSimper(Simper $s): array
    {
        return [
            'id'       => $s->id,
            'nomor'    => $s->no_simper,
            'tanggal'  => $s->tanggal?->toDateString(),
            'kelas'    => $s->kelas,
            'simpol'   => $s->jenis_simpol,
            'no_simpol'=> $s->no_simpol,
            'simpolSampai' => $s->simpol_berlaku_sampai?->toDateString(),
            'status'   => $s->status,
            'sampai'   => $s->berlaku_sampai?->toDateString(),
            'efektif'  => $s->habisEfektif()?->toDateString(),
            'sebab'    => $s->penyebabHabis(),
            'sisa'     => $s->sisaHari(),
            'keadaan'  => Keadaan::simper($s),
            'unit'     => $s->relationLoaded('unit')
                ? $s->unit->map(fn ($u) => [
                    'id'        => $u->id,
                    'golongan'  => $u->kendaraan?->nama,
                    'unit'      => $u->jenisUnit?->nama,
                    'kewenangan'=> $u->kewenangan,
                    'asal'      => $u->asal,
                    'lulus'     => $u->lulus(),
                    'nilai'     => [
                        'p2h'     => $u->nilai_p2h,
                        'praktek' => $u->nilai_praktek,
                        'teori'   => $u->nilai_teori,
                        'rambu'   => $u->nilai_rambu,
                    ],
                ])
                : [],
            'ajuan'    => $s->relationLoaded('ajuan')
                ? $s->ajuan->map(fn (SimperAjuan $a) => [
                    'id'      => $a->id,
                    'jenis'   => $a->jenis,
                    'nomor'   => $a->no_registrasi,
                    'tanggal' => $a->tanggal?->toDateString(),
                    'status'  => $a->status,
                ])
                : [],
            'alur'     => $this->barisAlur($s),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function barisAlur(object $dokumen): array
    {
        if (! $dokumen->relationLoaded('alur')) return [];

        return $dokumen->alur->map(fn (Alur $a) => [
            'urutan'  => $a->urutan,
            'peran'   => $a->peran,
            'label'   => Alur::PERAN[$a->peran] ?? $a->peran,
            'keadaan' => $a->keadaan,
            'pada'    => $a->bertindak_pada ? Waktu::lokal($a->bertindak_pada)->toDateTimeString() : null,
            'catatan' => $a->catatan,
        ])->all();
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
