<?php

namespace App\Http\Controllers;

use App\Rules\DalamPerusahaan;

use App\Models\{ActivityLog, Company, LedakHasil, LedakRencana, LedakTitik, LedakUkur, TindakLanjut};
use App\Support\{Alur, KopDokumen, Peledakan, PeringatanPeledakan};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pengeboran dan Peledakan.
 *
 * Satu-satunya modul yang persetujuannya mendahului pekerjaannya.
 * Rancangan yang disetujui berarti boleh diledakkan, sehingga tinjauan
 * di sini bukan pemeriksaan angka melainkan izin kerja — dan hasilnya
 * hanya boleh dicatat pada rancangan yang memang sudah disetujui.
 */
class BlastingController extends Controller
{
    public function index(Request $r)   { return $this->halaman($r, 'dashboard'); }
    public function rencana(Request $r) { return $this->halaman($r, 'rencana'); }
    public function titik(Request $r)   { return $this->halaman($r, 'titik'); }
    public function getaran(Request $r) { return $this->halaman($r, 'getaran'); }

    /* ---------- titik terlindung ---------- */

    public function simpanTitik(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'      => ['nullable', 'exists:companies,id'],
            'kode'            => ['required', 'string', 'max:40'],
            'nama'            => ['required', 'string', 'max:150'],
            'jenis'           => ['required', Rule::in(LedakTitik::JENIS)],
            'lokasi'          => ['nullable', 'string', 'max:150'],
            'ppv_ambang_mm_s' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'acuan_ambang'    => ['nullable', 'string', 'max:200'],
            'catatan'         => ['nullable', 'string', 'max:2000'],
        ]));

        LedakTitik::updateOrCreate(
            ['company_id' => $data['company_id'] ?? null, 'kode' => $data['kode']],
            $data + ['aktif' => true],
        );

        ActivityLog::write('Daftarkan titik terlindung', $data['kode'].' — '.$data['nama'], 'peledakan');

        return back()->with('ok', 'Titik terlindung tersimpan.');
    }

    public function hapusTitik(LedakTitik $titik)
    {
        $titik->delete();

        return back()->with('ok', 'Titik dihapus.');
    }

    /* ---------- rencana ---------- */

    public function simpanRencana(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'         => ['nullable', 'exists:companies,id'],
            'kode'               => ['required', 'string', 'max:40'],
            'lokasi'             => ['nullable', 'string', 'max:150'],
            'tanggal_rencana'    => ['required', 'date'],
            'jenis_batuan'       => ['nullable', 'string', 'max:100'],
            'faktor_batuan'      => ['required', 'numeric', 'min:1', 'max:25'],
            'diameter_lubang_mm' => ['required', 'numeric', 'min:20', 'max:500'],
            'burden_m'           => ['required', 'numeric', 'min:0.5', 'max:30'],
            'spasi_m'            => ['required', 'numeric', 'min:0.5', 'max:40'],
            'kedalaman_m'        => ['required', 'numeric', 'min:0.5', 'max:60'],
            'subdrill_m'         => ['nullable', 'numeric', 'min:0', 'max:10'],
            'stemming_m'         => ['nullable', 'numeric', 'min:0', 'max:30'],
            'tinggi_jenjang_m'   => ['required', 'numeric', 'min:0.5', 'max:50'],
            'jumlah_lubang'      => ['required', 'integer', 'min:1', 'max:5000'],
            'pola'               => ['required', Rule::in(LedakRencana::POLA)],
            'bahan_peledak'      => ['nullable', 'string', 'max:100'],
            'kekuatan_relatif'   => ['required', 'numeric', 'min:10', 'max:300'],
            'isi_per_lubang_kg'  => ['required', 'numeric', 'min:0.1', 'max:10000'],

            // Isi per tundaan tidak boleh melebihi isi satu lubang: satu
            // tundaan paling sedikit memuat satu lubang, dan angka yang
            // lebih kecil dari itu berarti satu lubang dipecah menjadi
            // beberapa tundaan — yang mungkin, tetapi lebih besar tidak.
            'isi_per_tunda_kg'   => ['required', 'numeric', 'min:0.1', 'max:100000'],
            'catatan'            => ['nullable', 'string', 'max:3000'],
        ]));

        $data['user_id'] = auth()->id();

        // Kedalaman harus mencakup jenjang beserta subdrill-nya; kalau
        // tidak, lubangnya berhenti di atas lantai jenjang dan tonjolan
        // tertinggal di seluruh area.
        $perlu = $data['tinggi_jenjang_m'] + ($data['subdrill_m'] ?? 0);
        if ($data['kedalaman_m'] + 0.001 < $perlu) {
            return back()->withErrors([
                'kedalaman_m' => 'Kedalaman kurang dari tinggi jenjang ditambah subdrill ('
                                 .round($perlu, 2).' m).',
            ]);
        }

        // Stemming adalah bagian dari kedalaman lubang, bukan tambahan.
        if (($data['stemming_m'] ?? 0) >= $data['kedalaman_m']) {
            return back()->withErrors([
                'stemming_m' => 'Stemming tidak boleh sepanjang atau melebihi kedalaman lubang.',
            ]);
        }

        $r = LedakRencana::create($data);
        ActivityLog::write('Susun rencana peledakan', $r->kode, 'peledakan');

        return back()->with('ok', 'Rencana tersimpan sebagai draf.');
    }

    public function hapusRencana(LedakRencana $rencana)
    {
        if ($rencana->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Rencana yang sudah disetujui tidak dapat dihapus.']);
        }

        $rencana->delete();

        return back()->with('ok', 'Rencana dihapus.');
    }

    public function ajukanRencana(LedakRencana $rencana)  { return $this->jalankan($rencana, fn () => $rencana->ajukan(), 'Ajukan rencana peledakan', 'Rencana diajukan untuk ditinjau.'); }

    public function setujuiRencana(LedakRencana $rencana)
    {
        return $this->jalankan($rencana, fn () => $rencana->setujui(),
            'Setujui rencana peledakan', 'Rencana disetujui — peledakan boleh dilaksanakan.');
    }

    public function tolakRencana(Request $request, LedakRencana $rencana)
    {
        $alasan = $request->validate(['alasan_tolak' => ['required', 'string', 'min:5', 'max:1000']])['alasan_tolak'];

        return $this->jalankan($rencana, fn () => $rencana->tolak($alasan),
            'Tolak rencana peledakan', 'Rencana ditolak dan dikembalikan kepada pengaju.');
    }

    /* ---------- hasil ---------- */

    public function simpanHasil(Request $request, LedakRencana $rencana)
    {
        // Hasil hanya boleh dicatat pada rancangan yang izinnya sudah
        // keluar. Mencatat hasil pada rancangan yang belum disetujui
        // berarti peledakan itu dilaksanakan tanpa izin — dan aplikasi
        // tidak boleh membuat jejak yang menyamarkannya.
        if (!$rencana->sudahDisetujui()) {
            return back()->withErrors([
                'alur' => 'Hasil hanya dapat dicatat pada rencana yang sudah disetujui.',
            ]);
        }

        $data = $this->pemilik($request->validate([
            'company_id'      => ['nullable', 'exists:companies,id'],
            'waktu_ledak'     => ['required', 'date'],
            'volume_bcm'      => ['required', 'numeric', 'min:0', 'max:100000000'],
            'ada_misfire'     => ['nullable', 'boolean'],
            'misfire_lubang'  => ['nullable', 'integer', 'min:0', 'max:5000'],
            'ada_flyrock'     => ['nullable', 'boolean'],
            'flyrock_jarak_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'backbreak_m'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bongkah_persen'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'kejadian'        => ['nullable', 'string', 'max:3000'],
            'catatan'         => ['nullable', 'string', 'max:3000'],
        ]));

        // Jumlah lubang misfire yang diisi menandakan ada misfire, apa
        // pun kotak centangnya — angka yang diisi tetapi tidak ditandai
        // tidak akan pernah memicu peringatan.
        if (($data['misfire_lubang'] ?? 0) > 0)  $data['ada_misfire'] = true;
        if (($data['flyrock_jarak_m'] ?? 0) > 0) $data['ada_flyrock'] = true;

        $data['user_id'] = auth()->id();
        $data['ledak_rencana_id'] = $rencana->id;

        $h = LedakHasil::updateOrCreate(['ledak_rencana_id' => $rencana->id], $data);

        ActivityLog::write('Catat hasil peledakan', $rencana->kode, 'peledakan');

        return back()->with('ok', $h->ada_misfire
            ? 'Hasil tersimpan. Misfire yang ditandai sudah langsung masuk ke peringatan.'
            : 'Hasil tersimpan sebagai draf.');
    }

    public function ajukanHasil(LedakHasil $hasil)  { return $this->jalankan($hasil, fn () => $hasil->ajukan(), 'Ajukan hasil peledakan', 'Hasil diajukan untuk ditinjau.'); }
    public function setujuiHasil(LedakHasil $hasil) { return $this->jalankan($hasil, fn () => $hasil->setujui(), 'Setujui hasil peledakan', 'Hasil disetujui.'); }

    public function tolakHasil(Request $request, LedakHasil $hasil)
    {
        $alasan = $request->validate(['alasan_tolak' => ['required', 'string', 'min:5', 'max:1000']])['alasan_tolak'];

        return $this->jalankan($hasil, fn () => $hasil->tolak($alasan),
            'Tolak hasil peledakan', 'Hasil ditolak dan dikembalikan kepada pengaju.');
    }

    /* ---------- pengukuran getaran ---------- */

    public function simpanUkur(Request $request, LedakRencana $rencana)
    {
        $data = $this->pemilik($request->validate([
            'company_id'     => ['nullable', 'exists:companies,id'],
            'ledak_titik_id' => ['required', new DalamPerusahaan('ledak_titiks')],
            'jarak_m'        => ['required', 'numeric', 'min:1', 'max:50000'],
            'ppv_mm_s'       => ['required', 'numeric', 'min:0.0001', 'max:10000'],
            'frekuensi_hz'   => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'airblast_db'    => ['nullable', 'numeric', 'min:0', 'max:200'],
            'alat_ukur'      => ['nullable', 'string', 'max:100'],
        ]));

        $data['ledak_rencana_id'] = $rencana->id;

        LedakUkur::updateOrCreate(
            ['ledak_rencana_id' => $rencana->id, 'ledak_titik_id' => $data['ledak_titik_id']],
            $data,
        );

        return back()->with('ok', 'Pengukuran getaran tersimpan.');
    }

    public function hapusUkur(LedakUkur $ukur)
    {
        $ukur->delete();

        return back()->with('ok', 'Pengukuran dihapus.');
    }

    private function jalankan($baris, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, (string) ($baris->id ?? ''), 'peledakan');

        return back()->with('ok', $pesan);
    }

    /* ---------- tindak lanjut ---------- */

    public function simpanTindakLanjut(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'       => ['nullable', 'exists:companies,id'],
            'kode_pemicu'      => ['nullable', 'string', 'max:60'],
            'judul'            => ['required', 'string', 'max:200'],
            'prioritas'        => ['required', Rule::in(TindakLanjut::PRIORITAS)],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'target_selesai'   => ['nullable', 'date'],
            'uraian'           => ['nullable', 'string', 'max:3000'],
        ]));

        $data['modul'] = 'peledakan';
        $data['user_id'] = auth()->id();
        TindakLanjut::create($data);

        return back()->with('ok', 'Tindak lanjut ditambahkan.');
    }

    public function ubahTindakLanjut(Request $request, TindakLanjut $tindak)
    {
        $status = $request->validate(['status' => ['required', Rule::in(TindakLanjut::STATUS)]])['status'];

        $tindak->update([
            'status' => $status,
            'selesai_pada' => $status === 'selesai' ? now()->toDateString() : null,
        ]);

        return back()->with('ok', 'Status tindak lanjut diperbarui.');
    }

    /* ---------- laporan ---------- */

    public function cetak(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $d = $this->kumpulkan($dari, $sampai);

        $perusahaan = $this->perusahaanKop();

        return Inertia::render('Print/Peledakan', [
            'dok'    => KopDokumen::untuk('laporan-peledakan', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'dasar' => [
                'rencana'       => $d['rencana']->count(),
                'disetujui'     => $d['sah']->count(),
                'belumDitinjau' => $d['rencana']->count() - $d['sah']->count(),
                'titik'         => $d['titik']->count(),
            ],

            'ringkas' => $d['ringkas'],
            'tetapan' => $d['tetapan'],
            'rencana' => $d['sah']->map(fn (LedakRencana $r) => $r->toView($d['tetapan']))->values(),
            'ukur'    => $d['ukur']->map(fn (LedakUkur $u) => $u->toView())->values(),
            'titik'   => $d['titik']->map(fn (LedakTitik $t) => $t->toView())->values(),
            'tindak'  => TindakLanjut::modul('peledakan')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('peledakan.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        [$dari, $sampai] = $this->rentang($request);
        $d = $this->kumpulkan($dari, $sampai);

        $tindak = TindakLanjut::with('sumber')->modul('peledakan')->urutMendesak()->get();

        return Inertia::render('Peledakan/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas' => $d['ringkas'],
            'tetapan' => $d['tetapan'],
            'alerts'  => PeringatanPeledakan::susun($d['rencana'], $d['titik'], $d['ukur'], $d['tetapan']),

            'rencana' => $d['rencana']->map(fn (LedakRencana $r) => $r->toView($d['tetapan']) + [
                'getaran' => $r->perkiraanGetaran($d['titik'], $d['tetapan']),
                'ukur'    => $r->ukur->map(fn (LedakUkur $u) => $u->toView())->values(),
            ])->values(),

            'titik'  => $d['titik']->map(fn (LedakTitik $t) => $t->toView())->values(),
            'ukur'   => $d['ukur']->map(fn (LedakUkur $u) => $u->toView())->values(),
            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'pola'            => LedakRencana::POLA,
                'jenisTitik'      => LedakTitik::JENIS,
                'statusTindak'    => TindakLanjut::STATUS,
                'prioritasTindak' => TindakLanjut::PRIORITAS,
                'ambangBawaan'    => [
                    'peka'       => Peledakan::PPV_BANGUNAN_PEKA,
                    'permukiman' => Peledakan::PPV_PERMUKIMAN,
                    'industri'   => Peledakan::PPV_INDUSTRI,
                ],
                'minKalibrasi' => Peledakan::MIN_TITIK_KALIBRASI,
            ],

            'tautan' => [
                'dashboard' => route('peledakan.index'),
                'rencana'   => route('peledakan.rencana'),
                'titik'     => route('peledakan.titik'),
                'getaran'   => route('peledakan.getaran'),
                'cetak'     => route('peledakan.cetak'),
                'titikSimpan'   => route('peledakan.titik.simpan'),
                'titikHapus'    => route('peledakan.titik.hapus', ['titik' => '__ID__']),
                'rencanaSimpan' => route('peledakan.rencana.simpan'),
                'rencanaHapus'  => route('peledakan.rencana.hapus', ['rencana' => '__ID__']),
                'rencanaAjukan' => route('peledakan.rencana.ajukan', ['rencana' => '__ID__']),
                'rencanaSetujui'=> route('peledakan.rencana.setujui', ['rencana' => '__ID__']),
                'rencanaTolak'  => route('peledakan.rencana.tolak', ['rencana' => '__ID__']),
                'hasilSimpan'   => route('peledakan.hasil.simpan', ['rencana' => '__ID__']),
                'hasilAjukan'   => route('peledakan.hasil.ajukan', ['hasil' => '__ID__']),
                'hasilSetujui'  => route('peledakan.hasil.setujui', ['hasil' => '__ID__']),
                'hasilTolak'    => route('peledakan.hasil.tolak', ['hasil' => '__ID__']),
                'ukurSimpan'    => route('peledakan.ukur.simpan', ['rencana' => '__ID__']),
                'ukurHapus'     => route('peledakan.ukur.hapus', ['ukur' => '__ID__']),
                'tindakSimpan'  => route('peledakan.tindak.simpan'),
                'tindakUbah'    => route('peledakan.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    private function kumpulkan(Carbon $dari, Carbon $sampai): array
    {
        $rencana = LedakRencana::with(['hasil', 'ukur.titik'])
            ->whereBetween('tanggal_rencana', [$dari, $sampai])
            ->orderByDesc('tanggal_rencana')->get();

        $sah   = $rencana->whereIn('status', Alur::terhitung());
        $titik = LedakTitik::where('aktif', true)->orderBy('kode')->get();

        // Kalibrasi memakai SELURUH pengukuran yang ada, bukan hanya
        // yang jatuh pada rentang tanggal yang sedang dilihat: tetapan
        // situs adalah sifat batuannya, bukan sifat periodenya, dan
        // membatasinya pada satu bulan membuang data yang justru
        // membuatnya layak dipercaya.
        $semuaUkur = LedakUkur::with('titik')->get();

        $tetapan = Peledakan::kalibrasi(
            $semuaUkur->map(fn (LedakUkur $u) => [
                'jarak_m' => $u->jarak_m, 'isi_kg' => $u->rencana?->isi_per_tunda_kg ?? 0, 'ppv' => $u->ppv_mm_s,
            ])->filter(fn ($x) => $x['isi_kg'] > 0)->values()->all()
        );

        return [
            'rencana' => $rencana,
            'sah'     => $sah,
            'titik'   => $titik,
            'ukur'    => $semuaUkur,
            'tetapan' => $tetapan,
            'ringkas' => $this->ringkas($rencana, $sah, $semuaUkur),
        ];
    }

    /**
     * @param Collection<int,LedakRencana> $rencana
     * @param Collection<int,LedakRencana> $sah
     * @param Collection<int,LedakUkur>    $ukur
     */
    private function ringkas(Collection $rencana, Collection $sah, Collection $ukur): array
    {
        $terlaksana = $rencana->filter(fn (LedakRencana $r) => $r->hasil !== null);
        $volume = (float) $terlaksana->sum(fn (LedakRencana $r) => (float) $r->hasil->volume_bcm);
        $bahan  = (float) $terlaksana->sum(fn (LedakRencana $r) => $r->totalBahanPeledak());

        return [
            'rencana'     => $rencana->count(),
            'disetujui'   => $sah->count(),
            'terlaksana'  => $terlaksana->count(),
            'volume'      => round($volume, 2),
            'bahan'       => round($bahan, 2),
            'pf'          => $volume > 0 ? Peledakan::powderFactor($bahan, $volume) : null,
            'misfire'     => $terlaksana->filter(fn (LedakRencana $r) => $r->hasil->ada_misfire)->count(),
            'flyrock'     => $terlaksana->filter(fn (LedakRencana $r) => $r->hasil->ada_flyrock)->count(),
            'getaranLewat'=> $ukur->filter(fn (LedakUkur $u) => $u->melampaui())->count(),
            'penyimpangan'=> (int) $rencana->sum(fn (LedakRencana $r) => count($r->penyimpanganGeometri())),
        ];
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $request): array
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();

        return $dari->greaterThan($sampai) ? [$sampai, $dari] : [$dari, $sampai];
    }

}
