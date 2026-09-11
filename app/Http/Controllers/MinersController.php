<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Miners\{Alur, Blok, Departemen, HasilMcu, Induksi, InduksiOrang, Jabatan,
    JenisUnit, KategoriPermit, Kendaraan, Kompetensi, Mcu, McuOrang, Pekerja, Permit,
    Pjo, Simper, SimperAjuan, Subkontraktor, TipePermit};
use App\Models\KompetensiJenis;
use App\Support\Miners\{Acuan, Keadaan};
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

    public function index(Request $r)
    {
        $orang = $this->orangDenganBerkas()
            ->cari(trim((string) $r->query('cari')))
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($r->query('departemen'), fn ($q, $d) => $q->where('departemen_id', $d))
            ->get();

        $baris = $orang->map(fn (Pekerja $p) => $this->keadaanOrang($p));

        /* Disaring SESUDAH dimuat, bukan lewat kueri. Keadaan terburuk
           seseorang dihitung dari empat tabel anaknya sekaligus, dan
           menuliskannya sebagai SQL menghasilkan kueri yang tidak dapat
           dibaca siapa pun enam bulan lagi. Jumlah pekerja per
           perusahaan berukuran ratusan, bukan jutaan. */
        if ($keadaan = $r->query('keadaan')) {
            $baris = $baris->filter(fn (array $b) => $b['terburuk'] === $keadaan)->values();
        }

        return Inertia::render('Miners/Daftar', [
            'judul'    => 'Miners — Pekerja',
            'subjudul' => 'Daftar orang beserta keadaan keempat berkasnya.',

            'baris'  => $baris,
            'saring' => [
                'cari'       => $r->query('cari', ''),
                'status'     => $r->query('status', ''),
                'keadaan'    => $r->query('keadaan', ''),
                'departemen' => $r->query('departemen', ''),
            ],

            'STATUS'     => Pekerja::STATUS,
            'KEADAAN'    => Keadaan::LABEL,
            'NADA'       => Keadaan::NADA,
            'departemen' => Departemen::pilihan(),
        ]);
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
        $pekerja = Pekerja::create($this->validasiPekerja($r) + ['user_id' => $r->user()?->id]);

        return redirect()->route('miners.show', $pekerja)
            ->with('sukses', 'Pekerja ditambahkan.');
    }

    public function update(Request $r, Pekerja $pekerja)
    {
        $pekerja->update($this->validasiPekerja($r));

        return back()->with('sukses', 'Data pekerja diperbarui.');
    }

    public function destroy(Pekerja $pekerja)
    {
        $pekerja->delete();

        return redirect()->route('miners.index')->with('sukses', 'Pekerja dihapus.');
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

    /**
     * Daftar menyilang orang — antrean, pengajuan lanjutan, rujukan,
     * dan kartu siap cetak.
     */
    public function daftar(Request $r)
    {
        $jenis = $r->route('jenis');

        [$baris, $judul] = match ($jenis) {
            'outstanding-mcu'     => [$this->antreanDokumen('mcu'), 'Antrean MCU'],
            'outstanding-induksi' => [$this->antreanDokumen('induksi'), 'Antrean Induksi'],
            'outstanding-permit'  => [$this->antreanDokumen('permit'), 'Antrean Mine Permit'],
            'outstanding-simper'  => [$this->antreanDokumen('simper'), 'Antrean SIMPER'],

            'penambahan-unit'  => [$this->ajuanJenis('penambahan'), 'Penambahan Unit'],
            'upgrade-simper'   => [$this->ajuanJenis('upgrade'), 'Upgrade SIMPER'],
            'perpanjangan'     => [$this->ajuanJenis('perpanjangan'), 'Perpanjangan SIMPER'],

            'rujukan' => [
                McuOrang::query()->whereHas('rujukan')->with(['pekerja', 'rujukan', 'hasil'])
                    ->orderByDesc('tanggal_periksa')->get()
                    ->map(fn (McuOrang $m) => [
                        'id'      => $m->id,
                        'pekerja_id' => $m->pekerja_id,
                        'nama'    => $m->nama,
                        'nomor'   => $m->rujukan->first()?->dokter,
                        'tanggal' => $m->rujukan->first()?->tanggal_surat?->toDateString(),
                        'ket'     => $m->rujukan->first()?->keterangan,
                        'keadaan' => Keadaan::mcu($m),
                    ]),
                'Rujukan Dokter',
            ],

            default => [
                Permit::query()->where('status', 'terbit')->with(['pekerja', 'tipe', 'mcuOrang'])
                    ->orderByDesc('tanggal')->get()
                    ->map(fn (Permit $p) => [
                        'id'      => $p->id,
                        'pekerja_id' => $p->pekerja_id,
                        'nama'    => $p->pekerja?->nama,
                        'nomor'   => $p->no_registrasi,
                        'tanggal' => $p->tanggal?->toDateString(),
                        'ket'     => $p->tipe?->nama.' · '.$p->cakupan_area,
                        'keadaan' => Keadaan::permit($p),
                    ]),
                'Kartu Siap Cetak',
            ],
        };

        return Inertia::render('Miners/Antrean', [
            'judul'    => 'Miners — '.$judul,
            'subjudul' => 'Yang menunggu keputusan atau tindakan.',

            'jenis'   => $jenis,
            'baris'   => $baris,
            'KEADAAN' => Keadaan::LABEL,
            'NADA'    => Keadaan::NADA,
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
            $n[$jenis] = $kelas::query()
                ->whereIn('id', Alur::query()->where('dokumen', $jenis)
                    ->where('keadaan', 'menunggu')->select('dokumen_id'))
                ->count();
        }

        return $n;
    }

    private function antreanDokumen(string $jenis): Collection
    {
        $menunggu = Alur::query()->where('dokumen', $jenis)
            ->where('keadaan', 'menunggu')->pluck('dokumen_id');

        return match ($jenis) {
            'mcu' => Mcu::query()->whereKey($menunggu)->with('orang')->orderByDesc('tanggal')->get()
                ->map(fn (Mcu $m) => [
                    'id' => $m->id, 'pekerja_id' => null,
                    'nama' => $m->kepada, 'nomor' => $m->no_registrasi,
                    'tanggal' => $m->tanggal?->toDateString(),
                    'ket' => $m->orang->count().' orang · '.(Mcu::STATUS[$m->status] ?? $m->status),
                    'keadaan' => Keadaan::BELUM,
                ]),

            'induksi' => Induksi::query()->whereKey($menunggu)->with('orang')->orderByDesc('tanggal')->get()
                ->map(fn (Induksi $i) => [
                    'id' => $i->id, 'pekerja_id' => null,
                    'nama' => $i->perihal, 'nomor' => $i->no_registrasi,
                    'tanggal' => $i->tanggal?->toDateString(),
                    'ket' => $i->orang->count().' orang · '.(Induksi::STATUS[$i->status] ?? $i->status),
                    'keadaan' => Keadaan::BELUM,
                ]),

            'permit' => Permit::query()->whereKey($menunggu)->with(['pekerja', 'tipe'])->orderByDesc('tanggal')->get()
                ->map(fn (Permit $p) => [
                    'id' => $p->id, 'pekerja_id' => $p->pekerja_id,
                    'nama' => $p->pekerja?->nama, 'nomor' => $p->no_registrasi,
                    'tanggal' => $p->tanggal?->toDateString(),
                    'ket' => $p->tipe?->nama.' · '.(Permit::STATUS[$p->status] ?? $p->status),
                    'keadaan' => Keadaan::BELUM,
                ]),

            default => Simper::query()->whereKey($menunggu)->with(['pekerja', 'unit'])->orderByDesc('tanggal')->get()
                ->map(fn (Simper $s) => [
                    'id' => $s->id, 'pekerja_id' => $s->pekerja_id,
                    'nama' => $s->pekerja?->nama, 'nomor' => $s->no_simper,
                    'tanggal' => $s->tanggal?->toDateString(),
                    'ket' => 'Kelas '.$s->kelas.' · '.(Simper::STATUS[$s->status] ?? $s->status),
                    'keadaan' => Keadaan::BELUM,
                ]),
        };
    }

    private function ajuanJenis(string $jenis): Collection
    {
        return SimperAjuan::query()->where('jenis', $jenis)
            ->with(['simper.pekerja', 'unit'])
            ->orderByDesc('tanggal')->get()
            ->map(fn (SimperAjuan $a) => [
                'id'         => $a->id,
                'pekerja_id' => $a->simper?->pekerja_id,
                'nama'       => $a->simper?->pekerja?->nama,
                'nomor'      => $a->no_registrasi,
                'tanggal'    => $a->tanggal?->toDateString(),
                'ket'        => $a->unit->count().' unit · '.(SimperAjuan::STATUS[$a->status] ?? $a->status),
                'keadaan'    => $a->status === 'selesai' ? Keadaan::BERLAKU : Keadaan::BELUM,
            ]);
    }

    /** @return array<string,mixed> */
    private function validasiPekerja(Request $r): array
    {
        return $r->validate([
            'nama'             => ['required', 'string', 'max:150'],
            'nik'              => ['nullable', 'string', 'max:40'],
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
            'status'           => ['required', 'in:'.implode(',', array_keys(Pekerja::STATUS))],
            'catatan'          => ['nullable', 'string'],
        ]);
    }
}
