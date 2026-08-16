<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, BiayaAkun, BiayaAnggaran, BiayaRealisasi, Company,
                MineOperationalRecord, MineOperationalTarget, TindakLanjut};
use App\Support\{Alur, Biaya, KopDokumen, PeringatanBiaya};
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pengendalian biaya operasi.
 *
 * Akuntansi manajemen untuk mengendalikan operasi, bukan pembukuan.
 * Angkanya tidak mengikat siapa pun dan tidak menggantikan catatan
 * keuangan; gunanya menjawab pertanyaan pengawas produksi — mengapa
 * biaya naik, dan bagian mana yang masih dapat dikendalikan dari pit.
 *
 * Denominator produksinya diambil dari Mine Operations yang sudah
 * disetujui, tidak dicatat ulang di sini.
 */
class CostController extends Controller
{
    public function index(Request $r)     { return $this->halaman($r, 'dashboard'); }
    public function realisasi(Request $r) { return $this->halaman($r, 'realisasi'); }
    public function anggaran(Request $r)  { return $this->halaman($r, 'anggaran'); }
    public function akun(Request $r)      { return $this->halaman($r, 'akun'); }

    /* ---------- bagan akun ---------- */

    public function simpanAkun(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'kode'       => ['required', 'string', 'max:40'],
            'nama'       => ['required', 'string', 'max:150'],
            'kelompok'   => ['required', 'string', 'max:40'],
            'jenis'      => ['required', Rule::in(BiayaAkun::JENIS)],
            'satuan'     => ['nullable', 'string', 'max:20'],
            'catatan'    => ['nullable', 'string', 'max:2000'],
        ]));

        BiayaAkun::updateOrCreate(
            ['company_id' => $data['company_id'] ?? null, 'kode' => $data['kode']],
            $data + ['aktif' => true],
        );

        ActivityLog::write('Daftarkan akun biaya', $data['kode'].' — '.$data['nama'], 'biaya');

        return back()->with('ok', 'Akun biaya tersimpan.');
    }

    public function hapusAkun(BiayaAkun $akun)
    {
        $akun->delete();

        return back()->with('ok', 'Akun dihapus beserta anggaran dan realisasinya.');
    }

    /* ---------- anggaran ---------- */

    public function simpanAnggaran(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'        => ['nullable', 'exists:companies,id'],
            'biaya_akun_id'     => ['required', 'exists:biaya_akuns,id'],
            'tahun'             => ['required', 'integer', 'min:2000', 'max:2100'],
            'pusat_biaya'       => ['required', Rule::in(BiayaAnggaran::PUSAT)],
            'nilai_rp'          => ['required', 'numeric', 'min:0', 'max:1000000000000000'],
            'kuantitas_rencana' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'catatan'           => ['nullable', 'string', 'max:2000'],
        ]));

        BiayaAnggaran::updateOrCreate([
            'company_id'    => $data['company_id'] ?? null,
            'tahun'         => $data['tahun'],
            'biaya_akun_id' => $data['biaya_akun_id'],
            'pusat_biaya'   => $data['pusat_biaya'],
        ], $data);

        ActivityLog::write('Tetapkan anggaran biaya', 'Tahun '.$data['tahun'], 'biaya');

        return back()->with('ok', 'Anggaran tersimpan.');
    }

    public function hapusAnggaran(BiayaAnggaran $anggaran)
    {
        $anggaran->delete();

        return back()->with('ok', 'Anggaran dihapus.');
    }

    /* ---------- realisasi ---------- */

    public function simpanRealisasi(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'    => ['nullable', 'exists:companies,id'],
            'biaya_akun_id' => ['required', 'exists:biaya_akuns,id'],
            'tahun'         => ['required', 'integer', 'min:2000', 'max:2100'],
            'bulan'         => ['required', 'integer', 'min:1', 'max:12'],
            'pusat_biaya'   => ['required', Rule::in(BiayaAnggaran::PUSAT)],
            'nilai_rp'      => ['required', 'numeric', 'min:0', 'max:1000000000000000'],
            'kuantitas'     => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'catatan'       => ['nullable', 'string', 'max:2000'],
        ]));

        // Kuantitas hanya bermakna pada akun bersatuan; mengisinya pada
        // akun tanpa satuan menghasilkan "harga per apa" yang tidak
        // dapat dijawab siapa pun, lalu ikut terbawa ke pemecahan harga.
        $akun = BiayaAkun::find($data['biaya_akun_id']);
        if ($akun && !$akun->bersatuan() && ($data['kuantitas'] ?? null) !== null) {
            return back()->withErrors([
                'kuantitas' => 'Akun '.$akun->kode.' tidak bersatuan; isikan satuannya lebih dulu di bagan akun.',
            ]);
        }

        $kunci = [
            'company_id'    => $data['company_id'] ?? null,
            'tahun'         => $data['tahun'],
            'bulan'         => $data['bulan'],
            'biaya_akun_id' => $data['biaya_akun_id'],
            'pusat_biaya'   => $data['pusat_biaya'],
        ];

        $ada = BiayaRealisasi::where($kunci)->first();

        // Yang sudah disetujui tidak boleh ditimpa lewat jalur simpan
        // biasa. Tanpa penjagaan ini, satu pengiriman ulang formulir
        // mengubah angka yang sudah masuk laporan tanpa jejak.
        if ($ada && $ada->sudahDisetujui()) {
            return back()->withErrors([
                'alur' => 'Realisasi bulan itu sudah disetujui. Tolak dulu, atau catat pembetulannya pada bulan berjalan.',
            ]);
        }

        $data['user_id'] = auth()->id();

        if ($ada) {
            $ada->update($data);
        } else {
            BiayaRealisasi::create($data + $kunci);
        }

        ActivityLog::write('Catat realisasi biaya', $akun?->kode.' '.$data['bulan'].'/'.$data['tahun'], 'biaya');

        return back()->with('ok', 'Realisasi tersimpan sebagai draf.');
    }

    public function hapusRealisasi(BiayaRealisasi $realisasi)
    {
        if ($realisasi->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Realisasi yang sudah disetujui tidak dapat dihapus.']);
        }

        $realisasi->delete();

        return back()->with('ok', 'Realisasi dihapus.');
    }

    public function ajukan(BiayaRealisasi $realisasi)  { return $this->jalankan($realisasi, fn () => $realisasi->ajukan(), 'Ajukan realisasi biaya', 'Realisasi diajukan untuk ditinjau.'); }
    public function setujui(BiayaRealisasi $realisasi) { return $this->jalankan($realisasi, fn () => $realisasi->setujui(), 'Setujui realisasi biaya', 'Realisasi disetujui dan masuk hitungan.'); }

    public function tolak(Request $request, BiayaRealisasi $realisasi)
    {
        $alasan = $request->validate(['alasan_tolak' => ['required', 'string', 'min:5', 'max:1000']])['alasan_tolak'];

        return $this->jalankan($realisasi, fn () => $realisasi->tolak($alasan),
            'Tolak realisasi biaya', 'Realisasi ditolak dan dikembalikan kepada pengaju.');
    }

    private function jalankan($baris, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, (string) ($baris->id ?? ''), 'biaya');

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

        $data['modul'] = 'biaya';
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
        $tahun = $this->tahun($request);
        $r = $this->rekap($tahun);

        $perusahaan = auth()->user()?->company ?: Company::first();

        return Inertia::render('Print/Biaya', [
            'dok'   => KopDokumen::untuk('laporan-biaya', $perusahaan),
            'tahun' => $tahun,

            'produksi' => $r['produksi'],
            'total'    => $r['total'],
            'baca'     => $r['bacaSerapan'],
            'akun'     => $r['akun'],
            'kelompok' => $r['kelompok'],
            'bulan'    => $r['bulan'],
            'bulanTerisi' => $r['bulanTerisi'],
            'menunggu' => $r['menunggu'],
            'tindak'   => TindakLanjut::modul('biaya')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('biaya.index', ['tahun' => $tahun]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        $tahun = $this->tahun($request);
        $r = $this->rekap($tahun);

        $tindak = TindakLanjut::with('sumber')->modul('biaya')->urutMendesak()->get();

        return Inertia::render('Biaya/Halaman', [
            'mode'  => $mode,
            'tahun' => $tahun,

            'produksi'    => $r['produksi'],
            'total'       => $r['total'],
            'bacaSerapan' => $r['bacaSerapan'],
            'akun'        => $r['akun'],
            'kelompok'    => $r['kelompok'],
            'bulan'       => $r['bulan'],
            'bulanTerisi' => $r['bulanTerisi'],
            'menunggu'    => $r['menunggu'],
            'alerts'      => PeringatanBiaya::susun($r),

            'daftarAkun' => BiayaAkun::where('aktif', true)->orderBy('kode')->get()
                ->map(fn (BiayaAkun $a) => $a->toView())->values(),
            'daftarAnggaran' => BiayaAnggaran::with('akun')->where('tahun', $tahun)
                ->orderBy('biaya_akun_id')->get()
                ->map(fn (BiayaAnggaran $a) => $a->toView())->values(),
            'daftarRealisasi' => BiayaRealisasi::with('akun')->where('tahun', $tahun)
                ->orderByDesc('bulan')->orderBy('biaya_akun_id')->get()
                ->map(fn (BiayaRealisasi $x) => $x->toView())->values(),

            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'jenis'           => BiayaAkun::JENIS,
                'kelompok'        => BiayaAkun::KELOMPOK,
                'pusat'           => BiayaAnggaran::PUSAT,
                'statusTindak'    => TindakLanjut::STATUS,
                'prioritasTindak' => TindakLanjut::PRIORITAS,
                'ambangTarif'     => Biaya::AMBANG_VARIANS_TARIF_PERSEN,
                'ambangNisbah'    => Biaya::AMBANG_SELISIH_NISBAH_PERSEN,
                'tahunPilihan'    => range(now()->year - 4, now()->year + 1),
            ],

            'tautan' => [
                'dashboard' => route('biaya.index', ['tahun' => $tahun]),
                'realisasi' => route('biaya.realisasi', ['tahun' => $tahun]),
                'anggaran'  => route('biaya.anggaran', ['tahun' => $tahun]),
                'akun'      => route('biaya.akun', ['tahun' => $tahun]),
                'cetak'     => route('biaya.cetak', ['tahun' => $tahun]),

                'akunSimpan' => route('biaya.akun.simpan'),
                'akunHapus'  => route('biaya.akun.hapus', ['akun' => '__ID__']),

                'anggaranSimpan' => route('biaya.anggaran.simpan'),
                'anggaranHapus'  => route('biaya.anggaran.hapus', ['anggaran' => '__ID__']),

                'realisasiSimpan' => route('biaya.realisasi.simpan'),
                'realisasiHapus'  => route('biaya.realisasi.hapus', ['realisasi' => '__ID__']),
                'realisasiAjukan' => route('biaya.ajukan', ['realisasi' => '__ID__']),
                'realisasiSetujui'=> route('biaya.setujui', ['realisasi' => '__ID__']),
                'realisasiTolak'  => route('biaya.tolak', ['realisasi' => '__ID__']),

                'tindakSimpan' => route('biaya.tindak.simpan'),
                'tindakUbah'   => route('biaya.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    /**
     * Rekap satu tahun anggaran.
     *
     * Hanya realisasi yang sudah disetujui yang dihitung. Angka yang
     * belum ditinjau membuat setiap indikator membaik sekaligus, dan
     * seluruhnya ke arah yang menyenangkan — karena itu jumlah yang
     * masih menunggu ikut dikembalikan dan ditampilkan.
     *
     * @return array<string,mixed>
     */
    public function rekap(int $tahun): array
    {
        $akun = BiayaAkun::orderBy('kode')->get()->keyBy('id');

        $anggaran = BiayaAnggaran::where('tahun', $tahun)->get();
        $semua    = BiayaRealisasi::where('tahun', $tahun)->get();
        $sah      = $semua->whereIn('status', Alur::terhitung());

        $produksi = $this->produksi($tahun);

        $totalAnggaran = (float) $anggaran->sum('nilai_rp');
        $totalNyata    = (float) $sah->sum('nilai_rp');

        // Bulan yang sudah lengkap = bulan yang punya realisasi disetujui.
        // Memakai bulan kalender berjalan akan menghitung bulan yang
        // datanya baru separuh masuk sebagai bulan penuh, dan proyeksinya
        // meleset ke bawah persis pada saat paling sering dilihat.
        $bulanTerisi = $sah->pluck('bulan')->unique()->count();

        // Faktor peluwesan: sebanding apa volume nyata terhadap rencana.
        // Satu faktor untuk seluruh akun, sehingga pemecahan volume dan
        // pemecahan harga berdiri di atas dasar yang sama.
        $faktorLuwes = ($produksi['tonRencana'] > 0 && $produksi['tonNyata'] > 0)
            ? $produksi['tonNyata'] / $produksi['tonRencana']
            : 1.0;

        $rows = [];
        $tanpaAnggaran = [];

        foreach ($akun as $id => $a) {
            $ang = $anggaran->where('biaya_akun_id', $id);
            $nyt = $sah->where('biaya_akun_id', $id);

            if ($ang->isEmpty() && $nyt->isEmpty()) continue;

            $angRp = (float) $ang->sum('nilai_rp');
            $nytRp = (float) $nyt->sum('nilai_rp');

            if ($angRp <= 0 && $nytRp > 0) $tanpaAnggaran[] = $a->kode;

            $varians = Biaya::varians(
                $angRp, $produksi['tonRencana'], $nytRp, $produksi['tonNyata']
            );

            $hargaPakai = ['dapatDipecah' => false, 'harga' => null, 'pakai' => null,
                           'total' => round($nytRp - $angRp, 2), 'alasan' => 'Akun tanpa satuan.'];

            if ($a->bersatuan()) {
                $kuantitasRencana = (float) $ang->sum('kuantitas_rencana');
                $kuantitasNyata   = (float) $nyt->sum('kuantitas');

                // Kuantitas rencana ikut diluweskan ke volume produksi
                // yang benar-benar terjadi, dengan faktor yang sama
                // seperti anggarannya. Tanpa itu, pemecahan harga
                // terhadap pemakaian berdiri di atas dasar yang berbeda
                // dari pemecahan volume terhadap tarif, dan kedua angka
                // di layar tidak akan pernah bertemu — pembacanya lalu
                // menyimpulkan salah satunya keliru, padahal yang keliru
                // adalah membandingkannya.
                //
                // Dengan diluweskan, harga + pemakaian berjumlah tepat
                // selisih TARIF: pemecahan kedua menjelaskan sisa yang
                // ditinggalkan pemecahan pertama.
                $rencanaLuwes = $kuantitasRencana * $faktorLuwes;

                // Harga satuan dipakai TANPA dibulatkan lebih dulu.
                // Pembulatan dua desimal pada harga per liter tampak
                // tidak berarti, tetapi dikalikan ratusan ribu liter ia
                // menggeser jumlahnya ratusan ribu rupiah — dan kesamaan
                // "harga + pemakaian = selisih tarif" berhenti berlaku
                // persis pada angka yang dipakai orang untuk memeriksa
                // apakah hitungannya benar.
                $hargaPakai = Biaya::variansHargaPakai(
                    $kuantitasRencana > 0 ? $angRp / $kuantitasRencana : null, $rencanaLuwes,
                    $kuantitasNyata   > 0 ? $nytRp / $kuantitasNyata   : null, $kuantitasNyata,
                );
                $hargaPakai['diluweskan'] = $faktorLuwes !== 1.0;
                $hargaPakai['rencanaLuwes'] = round($rencanaLuwes, 3);
            }

            $rows[] = [
                'akunId'    => $id,
                'akun'      => $a->kode,
                'nama'      => $a->nama,
                'kelompok'  => $a->kelompok,
                'jenis'     => $a->jenis,
                'satuan'    => $a->satuan,
                'anggaran'  => round($angRp, 2),
                'realisasi' => round($nytRp, 2),
                'serapan'   => Biaya::serapan($nytRp, $angRp),
                'varians'   => $varians,
                'hargaPakai'=> $hargaPakai,
                'perTon'    => Biaya::perSatuan($nytRp, $produksi['tonNyata']),
                'tarifMenonjol' => Biaya::tarifMenonjol($varians['tarif'], $angRp),
            ];
        }

        usort($rows, fn ($x, $y) => $y['realisasi'] <=> $x['realisasi']);

        $kelompok = collect($rows)->groupBy('kelompok')->map(fn ($g, $k) => [
            'kelompok'  => $k,
            'anggaran'  => round($g->sum('anggaran'), 2),
            'realisasi' => round($g->sum('realisasi'), 2),
            'serapan'   => Biaya::serapan($g->sum('realisasi'), $g->sum('anggaran')),
        ])->sortByDesc('realisasi')->values()->all();

        $serapan  = Biaya::serapan($totalNyata, $totalAnggaran);
        $variansT = Biaya::varians($totalAnggaran, $produksi['tonRencana'], $totalNyata, $produksi['tonNyata']);

        return [
            'tahun'    => $tahun,
            'produksi' => $produksi,
            'total' => [
                'anggaran'  => round($totalAnggaran, 2),
                'realisasi' => round($totalNyata, 2),
                'sisa'      => round($totalAnggaran - $totalNyata, 2),
                'serapan'   => $serapan,
                'varians'   => $variansT,
                'proyeksi'  => Biaya::proyeksiTahunan($totalNyata, $bulanTerisi),
                'perTon'    => Biaya::perSatuan($totalNyata, $produksi['tonNyata']),
                'perBcm'    => Biaya::perSatuan($totalNyata, $produksi['bcmNyata']),
                'perTonAnggaran' => Biaya::perSatuan($totalAnggaran, $produksi['tonRencana']),
            ],
            'bacaSerapan'   => Biaya::bacaSerapan($serapan, $produksi['kemajuan']),
            'akun'          => $rows,
            'kelompok'      => $kelompok,
            'bulan'         => $this->perBulan($sah, $semua),
            'bulanTerisi'   => $bulanTerisi,
            'menunggu'      => $semua->filter(fn (BiayaRealisasi $x) => $x->menungguTinjauan())->count(),
            'tanpaAnggaran' => $tanpaAnggaran,
        ];
    }

    /**
     * Produksi setahun, dari Mine Operations.
     *
     * Nyata hanya dari catatan yang sudah disetujui; rencana dari target
     * bulanan yang sudah ditetapkan. Tidak ada angka produksi yang
     * diketik ulang di modul biaya.
     */
    private function produksi(int $tahun): array
    {
        $nyata = MineOperationalRecord::query()
            ->whereIn('status', Alur::terhitung())
            ->whereYear('tanggal', $tahun)
            ->get(['produksi_ton', 'overburden_bcm']);

        $target = MineOperationalTarget::where('tahun', $tahun)
            ->get(['target_produksi_ton', 'target_overburden_bcm']);

        $tonNyata = (float) $nyata->sum('produksi_ton');
        $bcmNyata = (float) $nyata->sum('overburden_bcm');
        $tonRencana = (float) $target->sum('target_produksi_ton');
        $bcmRencana = (float) $target->sum('target_overburden_bcm');

        $srNyata   = Biaya::nisbahKupas($bcmNyata, $tonNyata);
        $srRencana = Biaya::nisbahKupas($bcmRencana, $tonRencana);

        return [
            'tonNyata'   => $tonNyata,
            'bcmNyata'   => $bcmNyata,
            'tonRencana' => $tonRencana,
            'bcmRencana' => $bcmRencana,
            'srNyata'    => $srNyata,
            'srRencana'  => $srRencana,
            'selisihSr'  => Biaya::selisihNisbah($srRencana, $srNyata),
            'kemajuan'   => $tonRencana > 0 ? round($tonNyata / $tonRencana * 100, 2) : null,
            'adaData'    => $nyata->isNotEmpty(),
            'catatan'    => $nyata->count(),
        ];
    }

    /**
     * @param Collection<int,BiayaRealisasi> $sah
     * @param Collection<int,BiayaRealisasi> $semua
     */
    private function perBulan(Collection $sah, Collection $semua): array
    {
        $keluar = [];

        for ($b = 1; $b <= 12; $b++) {
            $keluar[] = [
                'bulan'     => $b,
                'realisasi' => round((float) $sah->where('bulan', $b)->sum('nilai_rp'), 2),
                'menunggu'  => $semua->where('bulan', $b)
                    ->filter(fn (BiayaRealisasi $x) => $x->menungguTinjauan())->count(),
            ];
        }

        return $keluar;
    }

    private function tahun(Request $request): int
    {
        $t = (int) $request->integer('tahun');

        return $t >= 2000 && $t <= 2100 ? $t : (int) now()->year;
    }

}
