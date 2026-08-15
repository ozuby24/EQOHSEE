<?php

namespace App\Support;

use App\Models\{AngkutAlat, AngkutMuatan, AngkutRegu, BiayaAkun, BiayaAnggaran, BiayaRealisasi,
                Company, GeoBacaan, GeoInstrumen, GeoLereng, GudangBarang, GudangLokasi,
                GudangMutasi, IzinAmbang, IzinGas, IzinKerja, IzinPeriksa, IzinSyarat,
                LedakHasil, LedakRencana, LedakTitik, LedakUkur, LingkunganArea,
                LingkunganPantau, LingkunganParameter, MineOperationalRecord,
                MineOperationalTarget, ReklamasiKemajuan, TindakLanjut, User,
                WaterLog, WaterSump, WaterSumpPump};
use Illuminate\Support\Carbon;

/**
 * Pemuat data contoh untuk satu perusahaan.
 *
 * Gunanya satu: membuat setiap modul terisi sekaligus, supaya angka
 * turunannya dapat diperiksa apakah masuk akal. Halaman kosong tidak
 * pernah salah — dan justru karena itu ia tidak pernah membuktikan apa
 * pun tentang benar-tidaknya hitungan di belakangnya.
 *
 * Yang paling penting di kelas ini bukan pengisiannya melainkan
 * PENGHAPUSANNYA. Memuat ulang berarti membuang seluruh data perusahaan
 * itu lebih dulu, dan itu tidak dapat dibatalkan. Tiga lapis yang
 * memisahkannya dari kehilangan data sungguhan:
 *
 * 1. Hanya perusahaan bertanda `demo` yang dapat dimuati. Penandaan itu
 *    tindakan tersendiri yang harus disengaja lebih dulu; tombol yang
 *    ditekan pada perusahaan yang salah tidak akan menemukan sasaran.
 *
 * 2. Penghapusan selalu menyebut company_id secara tegas, satu tabel
 *    satu perintah. Tidak ada truncate, tidak ada delete tanpa where —
 *    keduanya bekerja sempurna sampai satu kali dijalankan di tempat
 *    yang salah.
 *
 * 3. Scope perusahaan sengaja DILEPAS saat menghapus. Terdengar
 *    berlawanan, tetapi justru itu yang membuatnya tepat: dengan scope
 *    terpasang, administrator melihat seluruh perusahaan dan
 *    penghapusannya akan ikut melebar; tanpa scope, satu-satunya
 *    penyaring yang tersisa adalah company_id yang ditulis tegas di
 *    baris itu juga.
 *
 * Angkanya sengaja tidak bulat dan tidak sempurna: ada bulan yang
 * melampaui anggaran, lereng yang lajunya naik, pompa yang rusak, izin
 * yang lewat waktu belum ditutup. Data contoh yang seluruhnya rapi tidak
 * pernah menunjukkan apakah peringatannya bekerja — dan peringatan yang
 * tidak pernah menyala tidak dapat dibedakan dari peringatan yang rusak.
 */
final class DataContoh
{
    /**
     * Tabel yang dibersihkan, anak lebih dulu daripada induknya.
     *
     * Urutannya bukan selera: kunci asingnya nyata, dan menghapus induk
     * lebih dulu membuat penghapusannya gagal di tengah jalan — separuh
     * terbuang, separuh tertinggal.
     */
    private const URUTAN_HAPUS = [
        AngkutMuatan::class, AngkutRegu::class, AngkutAlat::class,
        BiayaRealisasi::class, BiayaAnggaran::class, BiayaAkun::class,
        IzinGas::class, IzinPeriksa::class, IzinKerja::class,
        IzinAmbang::class, IzinSyarat::class,
        LedakUkur::class, LedakHasil::class, LedakRencana::class, LedakTitik::class,
        GeoBacaan::class, GeoInstrumen::class, GeoLereng::class,
        ReklamasiKemajuan::class, LingkunganPantau::class,
        LingkunganArea::class, LingkunganParameter::class,
        WaterLog::class, WaterSumpPump::class, WaterSump::class,
        GudangMutasi::class, GudangBarang::class, GudangLokasi::class,
        MineOperationalRecord::class, MineOperationalTarget::class,
        TindakLanjut::class,
    ];

    /** @var list<string> hal yang perlu diketahui pemanggilnya */
    private array $catatan = [];

    /** @var array<string,int> */
    private array $dibuat = [];

    private function __construct(
        private readonly Company $c,
        private readonly ?User $pengaju,
        private readonly ?User $peninjau,
        private readonly Carbon $kini,
    ) {}

    /**
     * Muat ulang data contoh satu perusahaan.
     *
     * @param  User|null  $peninjau  yang menekan tombolnya; dialah yang
     *                               menyetujui baris-barisnya, sebab data
     *                               contoh yang berhenti sebagai draf
     *                               tidak masuk satu pun hitungan KPI.
     * @return array{dihapus:int,dibuat:array<string,int>,catatan:list<string>}
     *
     * @throws \RuntimeException bila perusahaan itu bukan perusahaan contoh
     */
    public static function muat(Company $c, ?User $peninjau = null): array
    {
        if (!$c->demo) {
            throw new \RuntimeException(
                'Perusahaan "'.$c->name.'" bukan perusahaan contoh. '
                .'Tandai dulu sebagai perusahaan contoh bila datanya memang boleh dibuang.'
            );
        }

        /* Pengaju harus orang perusahaan itu sendiri, dan bukan
           peninjaunya: alurnya menolak orang yang menyetujui
           pekerjaannya sendiri, dan penolakan itu memang benar. */
        $pengaju = User::withoutGlobalScopes()
            ->where('company_id', $c->id)
            ->when($peninjau, fn ($q) => $q->where('id', '!=', $peninjau->getKey()))
            ->orderBy('id')->first();

        $diri = new self($c, $pengaju, $peninjau, Carbon::now());

        $dihapus = $diri->bersihkan();
        $diri->periksaKesiapan();
        $diri->isi();

        return [
            'dihapus' => $dihapus,
            'dibuat'  => $diri->dibuat,
            'catatan' => $diri->catatan,
        ];
    }

    /** Hitung baris yang akan terhapus, tanpa menghapus apa pun. */
    public static function hitungIsi(Company $c): int
    {
        return array_sum(self::rincianIsi($c));
    }

    /**
     * Rincian per tabel dari apa yang akan terhapus.
     *
     * Angka inilah yang diperlihatkan sebelum tombol yang tidak dapat
     * dibatalkan ditekan, dan rinciannya lebih berguna daripada
     * jumlahnya: satu tabel yang isinya jauh di luar dugaan adalah
     * tanda bahwa sasarannya salah.
     *
     * @return array<string,int> nama tabel => jumlah baris, yang kosong dibuang
     */
    public static function rincianIsi(Company $c): array
    {
        $rincian = [];

        foreach (self::URUTAN_HAPUS as $kelas) {
            $n = self::kueri($kelas, $c)->count();

            if ($n > 0) $rincian[(new $kelas)->getTable()] = $n;
        }

        return $rincian;
    }

    /* ═══════════ penghapusan ═══════════ */

    private function bersihkan(): int
    {
        $n = 0;
        foreach (self::URUTAN_HAPUS as $kelas) {
            $n += self::kueri($kelas, $this->c)->delete();
        }

        return $n;
    }

    /**
     * Kueri satu tabel yang dibatasi tegas pada satu perusahaan.
     *
     * Anak yang tidak punya company_id sendiri disaring lewat induknya —
     * kolom yang tidak ada tidak dapat dijadikan penyaring, dan
     * menghapus tanpa penyaring adalah cara termudah kehilangan data
     * perusahaan lain.
     *
     * Yang tidak dikenali sengaja tidak menghapus apa pun. Itu membuat
     * tabel baru yang lupa didaftarkan meninggalkan baris yatim — yang
     * terlihat, dapat diperbaiki, dan jauh lebih ringan akibatnya
     * daripada tebakan yang salah tentang siapa pemiliknya.
     */
    private static function kueri(string $kelas, Company $c)
    {
        $q = $kelas::withoutGlobalScopes();
        $model = new $kelas;

        if ($model->getConnection()->getSchemaBuilder()
                  ->hasColumn($model->getTable(), 'company_id')) {
            return $q->where('company_id', $c->id);
        }

        return match ($kelas) {
            WaterSumpPump::class => $q->whereIn('water_sump_id',
                WaterSump::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),
            GeoInstrumen::class => $q->whereIn('geo_lereng_id',
                GeoLereng::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),
            default => $q->whereRaw('1 = 0'),
        };
    }

    /* ═══════════ pengisian ═══════════ */

    /**
     * Peringatkan lebih dulu bila barisnya tidak akan dapat disetujui.
     *
     * Tanpa ini kegagalannya berbentuk halaman yang terisi penuh dengan
     * seluruh indikatornya nol — bentuk kegagalan yang paling mudah
     * disalahartikan sebagai hitungan yang rusak.
     */
    private function periksaKesiapan(): void
    {
        if (!$this->pengaju) {
            $this->catatan[] = 'Perusahaan ini belum punya pengguna, sehingga datanya '
                .'tersimpan sebagai draf dan belum masuk hitungan KPI. Tambahkan satu '
                .'pengguna pada perusahaan ini, lalu muat ulang.';

            return;
        }

        if (!Alur::peninjau($this->peninjau)) {
            $this->catatan[] = 'Pemuatan dijalankan tanpa peninjau berwenang, sehingga '
                .'datanya tersimpan sebagai draf dan belum masuk hitungan KPI.';
        }
    }

    private function isi(): void
    {
        $this->dibuat = array_filter([
            'Operasi'    => $this->operasi(),
            'Gudang'     => $this->gudang(),
            'Air'        => $this->air(),
            'Geoteknik'  => $this->geoteknik(),
            'Lingkungan' => $this->lingkungan(),
            'Peledakan'  => $this->peledakan(),
            'Angkutan'   => $this->angkutan(),
            'Biaya'      => $this->biaya(),
            'Izin kerja' => $this->izin(),
        ]);
    }

    /* ─────────── operasi ─────────── */

    private function operasi(): int
    {
        $n = 0;

        foreach (range(1, 12) as $b) {
            $this->baru(MineOperationalTarget::class, [
                'tahun' => $this->kini->year, 'bulan' => $b,
                'target_produksi_ton' => 120_000, 'target_overburden_bcm' => 960_000,
                'target_strip_ratio' => 8, 'target_jarak_km' => 3.4,
            ]);
            $n++;
        }

        /* Bulan-bulan yang sudah lewat: satu catatan sebulan, cukup
           untuk menggambar kecenderungan tahunannya. Produksinya
           berbeda tiap bulan — capaian yang kebetulan pas 100% pada
           semua bulan tidak membuktikan bahwa pembaginya benar. */
        $lalu = [104_800, 111_200, 98_600, 117_400, 108_100, 113_900,
                 121_300, 96_400, 109_700, 114_600, 102_900];

        foreach (range(1, $this->kini->month - 1) as $b) {
            $r = $this->baru(MineOperationalRecord::class, [
                'user_id' => $this->pengaju?->getKey(),
                'tanggal' => $this->kini->copy()->setMonth($b)->setDay(15)->toDateString(),
                'shift' => 'siang', 'pit' => 'Pit Utara', 'area' => 'Blok '.$b,
                'material' => 'batubara', 'produksi_ton' => $lalu[($b - 1) % count($lalu)],
                'overburden_bcm' => round($lalu[($b - 1) % count($lalu)] * 8.6),
                'jarak_angkut_km' => 3.4, 'jumlah_truk' => 18, 'jumlah_excavator' => 4,
                'jam_operasi' => 9, 'jam_delay' => 1,
            ]);
            $this->setujui($r);
            $n++;
        }

        /* Bulan berjalan diisi per hari, dua shift, sampai kemarin.
           Halaman operasi memakai bulan berjalan sebagai periode
           bawaannya: data yang hanya ada di bulan-bulan lalu membuat
           seluruh indikatornya nol pada tampilan pertama — persis
           bentuk kegagalan yang hendak dihindari data contoh.

           Sampai KEMARIN, bukan sampai hari ini: shift hari berjalan
           memang belum sepatutnya dilaporkan, dan kelengkapan yang
           menuntutnya adalah kelengkapan yang salah hitung. */
        $hariIni = (int) $this->kini->day;

        foreach (range(1, max(1, $hariIni - 1)) as $h) {
            foreach (['siang', 'malam'] as $j => $shift) {
                // Satu hari libur tiap pekan, dan shift malam lebih
                // rendah — keduanya wajar, dan keduanya membuat
                // rata-ratanya bukan angka datar.
                if ($h % 7 === 0) continue;

                $dasar = $shift === 'siang' ? 2_150 : 1_760;
                $ton = $dasar + (($h * 37 + $j * 53) % 420) - 210;

                $r = $this->baru(MineOperationalRecord::class, [
                    'user_id' => $this->pengaju?->getKey(),
                    'tanggal' => $this->kini->copy()->setDay($h)->toDateString(),
                    'shift' => $shift, 'pit' => 'Pit Utara',
                    'area' => 'Blok '.$this->kini->month, 'material' => 'batubara',
                    'produksi_ton' => $ton, 'overburden_bcm' => round($ton * 8.2),
                    'jarak_angkut_km' => 3.4, 'jumlah_truk' => 18, 'jumlah_excavator' => 4,
                    'jam_operasi' => $shift === 'siang' ? 10 : 9,
                    'jam_delay' => $shift === 'siang' ? 1 : 1.6,
                ]);
                $this->setujui($r);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── gudang ─────────── */

    private function gudang(): int
    {
        $umum = $this->baru(GudangLokasi::class, [
            'kode' => 'GD-01', 'nama' => 'Gudang Utama', 'jenis' => 'umum',
            'lokasi' => 'Workshop Pit Utara', 'penanggung_jawab' => 'Petugas Gudang',
        ]);
        $b3 = $this->baru(GudangLokasi::class, [
            'kode' => 'GD-B3', 'nama' => 'Gudang B3', 'jenis' => 'b3',
            'lokasi' => 'Belakang workshop', 'penanggung_jawab' => 'Petugas B3',
            'berventilasi' => true, 'tahan_api' => true, 'ada_tanggul' => true,
            'ada_apar' => true, 'ada_eyewash' => true,
        ]);
        $n = 2;

        /* Barang terakhir sengaja tersisa di bawah stok minimumnya —
           tanpa satu pun barang menipis, peringatan stok tidak pernah
           menyala dan tidak dapat dibedakan dari peringatan yang mati. */
        $barang = [
            ['B3-001', 'Solar industri',       'b3',       'liter',  'mudah_menyala', $b3,   600, 420],
            ['B3-002', 'Oli bekas',            'b3',       'liter',  'berbahaya_air', $b3,   300, 180],
            ['APD-001', 'Helm keselamatan',    'apd',      'unit',   null,            $umum, 240, 90],
            ['APD-002', 'Sepatu safety',       'apd',      'pasang', null,            $umum, 200, 140],
            ['MTR-001', 'Ban OTR 27.00R49',    'material', 'unit',   null,            $umum, 24,  22],
        ];

        foreach ($barang as [$kode, $nama, $kat, $satuan, $kelas, $lokasi, $masuk, $keluar]) {
            $x = $this->baru(GudangBarang::class, [
                'lokasi_id' => $lokasi->id, 'kode' => $kode, 'nama' => $nama,
                'kategori' => $kat, 'satuan' => $satuan, 'kelas_b3' => $kelas,
                'wajib_msds' => $kat === 'b3', 'msds' => $kat === 'b3',
                'stok_min' => $kat === 'material' ? 4 : 50, 'aktif' => true,
            ]);
            $n++;

            $this->baru(GudangMutasi::class, [
                'barang_id' => $x->id, 'jenis' => 'masuk', 'jumlah' => $masuk,
                'nomor' => 'MSK/'.$kode, 'pihak' => 'Pemasok contoh',
                'tanggal' => $this->kini->copy()->subDays(28)->toDateString(),
                'user_id' => $this->pengaju?->getKey(),
            ]);
            $this->baru(GudangMutasi::class, [
                'barang_id' => $x->id, 'jenis' => 'keluar', 'jumlah' => $keluar,
                'nomor' => 'KLR/'.$kode, 'pihak' => 'Regu Perawatan',
                'tanggal' => $this->kini->copy()->subDays(5)->toDateString(),
                'user_id' => $this->pengaju?->getKey(),
            ]);
            $n += 2;
        }

        return $n;
    }

    /* ─────────── penirisan ─────────── */

    private function air(): int
    {
        $kolam = $this->baru(WaterSump::class, [
            'user_id' => $this->pengaju?->getKey(),
            'kode' => 'SMP-01', 'nama' => 'Kolam Pit Utara', 'jenis' => 'sump',
            'lokasi' => 'Dasar Pit Utara', 'kapasitas_m3' => 45_000,
            'luas_tangkapan_ha' => 62, 'koefisien_limpasan' => 0.8,
            'elevasi_luapan_m' => 12.5, 'status' => 'aktif',
            'pembersihan_terakhir' => $this->kini->copy()->subDays(96)->toDateString(),
            'interval_bersih_hari' => 90,
        ]);

        // Satu pompa rusak: kapasitas pemompaan turun separuh, dan
        // itulah keadaan yang membuat neraca airnya menarik dibaca.
        $this->baru(WaterSumpPump::class, ['water_sump_id' => $kolam->id, 'nama' => 'Pompa A',
                                           'kapasitas_m3_jam' => 450, 'status' => 'jalan']);
        $this->baru(WaterSumpPump::class, ['water_sump_id' => $kolam->id, 'nama' => 'Pompa B',
                                           'kapasitas_m3_jam' => 450, 'status' => 'rusak']);
        $n = 3;

        /* Empat belas hari sampai KEMARIN, dengan satu hari hujan lebat
           di tengahnya. Hari itu debit masuknya melampaui yang mampu
           dipompa dan volumenya naik — persis keadaan yang perlu
           terbaca. */
        $hujan = [4, 0, 12, 0, 0, 38, 96, 22, 6, 0, 0, 14, 3, 0];

        $volume = 18_000;
        foreach ($hujan as $i => $mm) {
            $masuk  = round($mm * 62 * 0.8 * 10);          // mm × ha × C × 10 = m³
            $keluar = min($masuk + 1_500, 450 * 16);
            $volume = max(0, min(45_000, $volume + $masuk - $keluar));

            $sampel = $i % 4 === 0;

            $x = $this->baru(WaterLog::class, [
                'user_id' => $this->pengaju?->getKey(), 'water_sump_id' => $kolam->id,
                'tanggal' => $this->kini->copy()->subDays(count($hujan) - $i)->toDateString(),
                'curah_hujan_mm' => $mm, 'level_m' => round($volume / 4_500, 2),
                'volume_m3' => $volume, 'debit_masuk_m3' => $masuk, 'debit_keluar_m3' => $keluar,
                'jam_pompa' => round($keluar / 450, 1), 'energi_kwh' => round($keluar / 450 * 132),
                // Hari terberat sengaja melampaui baku mutu TSS.
                'ph' => $sampel ? 7.1 : null,
                'tss_mgl' => $sampel ? ($mm > 50 ? 268 : 84) : null,
                'fe_mgl' => $sampel ? 2.4 : null,
                'mn_mgl' => $sampel ? 1.1 : null,
            ]);
            $this->setujui($x);
            $n++;
        }

        return $n;
    }

    /* ─────────── geoteknik ─────────── */

    private function geoteknik(): int
    {
        $lereng = $this->baru(GeoLereng::class, [
            'user_id' => $this->pengaju?->getKey(),
            'kode' => 'HW-01', 'nama' => 'Highwall Sisi Timur', 'jenis' => 'highwall',
            'lokasi' => 'Pit Utara', 'litologi' => 'Batulempung berselang batupasir',
            'tinggi_rencana_m' => 48, 'sudut_rencana_deg' => 42,
            'tinggi_jenjang_rencana_m' => 8, 'lebar_berm_rencana_m' => 5,
            'tinggi_aktual_m' => 51, 'sudut_aktual_deg' => 45,
            'tinggi_jenjang_aktual_m' => 8.6, 'lebar_berm_aktual_m' => 4.2,
            'fk_rencana' => 1.3, 'ppa_rencana_persen' => 15,
            'kajian_oleh' => 'Kajian geoteknik contoh',
            'kajian_tanggal' => $this->kini->copy()->subDays(210)->toDateString(),
            'interval_kajian_hari' => 180,
            'ambang_waspada_mm_hari' => 5, 'ambang_siaga_mm_hari' => 15,
            'ambang_awas_mm_hari' => 30, 'status' => 'aktif',
        ]);

        $alat = $this->baru(GeoInstrumen::class, [
            'geo_lereng_id' => $lereng->id, 'kode' => 'PRISM-01',
            'jenis' => 'prisma', 'status' => 'siap', 'elevasi_m' => 78,
            'kalibrasi_terakhir' => $this->kini->copy()->subDays(40)->toDateString(),
        ]);
        $n = 2;

        /* Perpindahan yang LAJUNYA meningkat. Bacaan yang bertambah rata
           hanya membuktikan penjumlahan; yang membuktikan pembacaan
           kebalikan lajunya adalah percepatan. */
        foreach ([0, 3, 7, 13, 22, 36, 58] as $i => $mm) {
            $r = $this->baru(GeoBacaan::class, [
                'user_id' => $this->pengaju?->getKey(),
                'geo_lereng_id' => $lereng->id, 'geo_instrumen_id' => $alat->id,
                'tanggal' => $this->kini->copy()->subDays(7 - $i)->toDateString(),
                'perpindahan_mm' => $mm, 'retakan_mm' => round($mm * 0.4, 1),
                'muka_air_m' => 6.2 - $i * 0.1,
                'curah_hujan_mm' => [4, 0, 12, 0, 38, 96, 22][$i],
                'ada_gejala' => $mm >= 22,
                'gejala' => $mm >= 22 ? 'Retakan tarik memanjang di crest' : null,
            ]);
            $this->setujui($r);
            $n++;
        }

        return $n;
    }

    /* ─────────── lingkungan ─────────── */

    private function lingkungan(): int
    {
        $n = 0;
        $area = [];

        foreach ([['PTK-01', 'timbunan', 12.5, 'penataan',   3],
                  ['PTK-02', 'timbunan', 8.0,  'revegetasi', 2],
                  ['PTK-03', 'bukaan',   5.5,  'selesai',    4]] as [$kode, $jenis, $ha, $tahap, $umur]) {
            $a = $this->baru(LingkunganArea::class, [
                'user_id' => $this->pengaju?->getKey(),
                'kode' => $kode, 'nama' => 'Petak '.$kode, 'jenis' => $jenis,
                'luas_ha' => $ha, 'tahap' => $tahap,
                'tanggal_buka' => $this->kini->copy()->subYears($umur)->toDateString(),
                'tanggal_selesai_tambang' => $this->kini->copy()->subYears($umur - 1)->toDateString(),
                'rencana_selesai_reklamasi' => $this->kini->copy()->addYear()->toDateString(),
                'pohon_rencana' => (int) round($ha * 625),
            ]);
            $area[] = [$a, $tahap, $ha];
            $n++;
        }

        foreach ($area as [$a, $tahap, $ha]) {
            if ($tahap === 'penataan') continue;   // belum ada tanaman

            $r = $this->baru(ReklamasiKemajuan::class, [
                'user_id' => $this->pengaju?->getKey(), 'lingkungan_area_id' => $a->id,
                'tanggal' => $this->kini->copy()->subDays(45)->toDateString(),
                'tahap' => $tahap, 'luas_ha' => $ha,
                'pohon_ditanam' => (int) round($ha * 625 * 0.82),
                'tingkat_tumbuh_persen' => $tahap === 'selesai' ? 88 : 76,
            ]);
            $this->setujui($r);
            $n++;
        }

        $param = [];
        foreach ([['PH', 'Derajat keasaman', 'air', '-',    6.0,  9.0],
                  ['TSS', 'Padatan tersuspensi', 'air', 'mg/L', null, 200.0],
                  ['FE',  'Besi terlarut',   'air', 'mg/L', null, 7.0],
                  ['MN',  'Mangan terlarut', 'air', 'mg/L', null, 4.0],
                  ['DEBU', 'Debu total',     'udara', 'µg/Nm³', null, 230.0]] as [$kode, $nama, $media, $sat, $min, $maks]) {
            $param[$kode] = $this->baru(LingkunganParameter::class, [
                'kode' => $kode, 'nama' => $nama, 'media' => $media, 'satuan' => $sat,
                'batas_min' => $min, 'batas_maks' => $maks,
                'acuan' => 'Baku mutu contoh — sesuaikan dengan izin lingkungan',
                'aktif' => true,
            ]);
            $n++;
        }

        /* Satu hasil uji yang MELAMPAUI baku mutu. Tanpa itu, halaman
           pemantauan tampak sehat sempurna dan tidak menunjukkan apa
           yang terjadi ketika sesuatu terlampaui. */
        foreach ([['PH', 7.2, false], ['TSS', 268.0, true], ['FE', 2.4, false],
                  ['MN', 1.1, false], ['DEBU', 96.0, false]] as [$kode, $nilai, $lewat]) {
            $x = $this->baru(LingkunganPantau::class, [
                'user_id' => $this->pengaju?->getKey(),
                'lingkungan_parameter_id' => $param[$kode]->id,
                'titik' => 'Outlet Settling Pond SP-01',
                'tanggal' => $this->kini->copy()->subDays(8)->toDateString(),
                'nilai' => $nilai, 'laboratorium' => 'Lab terakreditasi contoh',
                'catatan' => $lewat ? 'Diambil sehari setelah hujan lebat.' : null,
            ]);
            $this->setujui($x);
            $n++;
        }

        return $n;
    }

    /* ─────────── peledakan ─────────── */

    private function peledakan(): int
    {
        $titik = $this->baru(LedakTitik::class, [
            'kode' => 'RMH-01', 'nama' => 'Permukiman Sungai Bening',
            'jenis' => 'permukiman', 'lokasi' => '640 m dari batas pit',
            'ppv_ambang_mm_s' => 5, 'acuan_ambang' => 'Izin lingkungan contoh',
            'aktif' => true,
        ]);
        $n = 1;

        foreach ([[6, 40, 60.0], [13, 44, 66.0]] as [$lalu, $lubang, $isi]) {
            $r = $this->baru(LedakRencana::class, [
                'user_id' => $this->pengaju?->getKey(),
                'kode' => 'BL-CTH-'.$lalu, 'lokasi' => 'Pit Utara',
                'tanggal_rencana' => $this->kini->copy()->subDays($lalu)->toDateString(),
                'jenis_batuan' => 'Batupasir sedang', 'faktor_batuan' => 7,
                'diameter_lubang_mm' => 150, 'burden_m' => 4, 'spasi_m' => 5,
                'kedalaman_m' => 11.2, 'subdrill_m' => 1.2, 'stemming_m' => 3.2,
                'tinggi_jenjang_m' => 10, 'jumlah_lubang' => $lubang,
                'pola' => 'selang-seling', 'bahan_peledak' => 'ANFO',
                'kekuatan_relatif' => 100, 'isi_per_lubang_kg' => $isi,
                'isi_per_tunda_kg' => $isi,
            ]);
            $this->setujui($r);
            $n++;

            $h = $this->baru(LedakHasil::class, [
                'user_id' => $this->pengaju?->getKey(), 'ledak_rencana_id' => $r->id,
                'waktu_ledak' => $this->kini->copy()->subDays($lalu)->setTime(12, 5),
                'volume_bcm' => $lubang * 4 * 5 * 10,
                'ada_misfire' => false, 'ada_flyrock' => false,
                'backbreak_m' => 1.4, 'bongkah_persen' => 6,
            ]);
            $this->setujui($h);

            /* Peledakan kedua melampaui ambang PPV di permukiman. Itu
               satu-satunya cara membuktikan bahwa pembandingnya bekerja
               — data yang selalu di bawah ambang tidak membuktikan
               apa pun tentang ambangnya. */
            $this->baru(LedakUkur::class, [
                'ledak_rencana_id' => $r->id, 'ledak_titik_id' => $titik->id,
                'jarak_m' => 640, 'ppv_mm_s' => $lalu === 6 ? 3.1 : 6.4,
                'frekuensi_hz' => 18, 'airblast_db' => $lalu === 6 ? 118 : 127,
                'alat_ukur' => 'Seismograf contoh',
            ]);
            $n += 2;
        }

        return $n;
    }

    /* ─────────── angkutan ─────────── */

    private function angkutan(): int
    {
        $ex = $this->baru(AngkutAlat::class, [
            'kode' => 'EX-01', 'nama' => 'Excavator PC1250', 'kelas' => 'alat-muat',
            'tipe' => 'PC1250-8', 'kapasitas_bucket_m3' => 6.7, 'faktor_isi' => 0.85,
            'aktif' => true,
        ]);
        $truk = $this->baru(AngkutAlat::class, [
            'kode' => 'DT-01', 'nama' => 'Dump Truck HD785', 'kelas' => 'truk',
            'tipe' => 'HD785-7', 'kapasitas_ton' => 91, 'aktif' => true,
        ]);
        $n = 2;

        /* Satu regu kekurangan truk dan satu kelebihan, supaya
           pembacaan match factor punya kedua sisinya. Regu kedua
           antreannya panjang — dan justru itu yang harus TIDAK ikut
           memperbaiki match factor-nya. */
        foreach ([['RG-CTH-01', 4, 2], ['RG-CTH-02', 9, 9]] as [$kode, $jumlah, $antre]) {
            $r = $this->baru(AngkutRegu::class, [
                'user_id' => $this->pengaju?->getKey(), 'alat_muat_id' => $ex->id,
                'kode' => $kode, 'tanggal' => $this->kini->copy()->subDays(3)->toDateString(),
                'shift' => '1', 'pit' => 'Pit Utara', 'tujuan' => 'Disposal Barat',
                'material' => 'overburden', 'jumlah_alat_muat' => 1, 'jumlah_truk' => $jumlah,
                'jarak_km' => 3.4, 'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
                'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
                'waktu_antre_menit' => $antre, 'ritase' => 86 * $jumlah / 4,
                'tonase' => round(86 * $jumlah / 4 * 89), 'jam_kerja' => 9, 'jam_delay' => 1,
                'batas_kecepatan_kmh' => 40,
            ]);
            $this->setujui($r);
            $n++;

            /* Satu muatan di atas 120% kapasitas. Kaidah 10/10/20 tidak
               dapat diperiksa pada data yang seluruhnya patuh. */
            foreach ([88, 90, 87, 115] as $i => $ton) {
                $this->baru(AngkutMuatan::class, [
                    'angkut_regu_id' => $r->id, 'angkut_alat_id' => $truk->id,
                    'rit_ke' => $i + 1, 'muatan_ton' => $ton,
                    'waktu_timbang' => $this->kini->copy()->subDays(3)->setTime(8 + $i, 20),
                    'sumber' => 'Jembatan timbang',
                ]);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── biaya ─────────── */

    private function biaya(): int
    {
        $n = 0;

        /* Serapan per bulan sengaja berbeda antar akun: solar melampaui
           pagunya, ban jauh di bawah. Varians yang seragam tidak
           menunjukkan apakah pemecahannya benar-benar memecah. */
        $akun = [
            ['BB-01', 'Solar alat berat', 'bahan-bakar', 'liter', 14_500_000_000, 1_000_000, 0.098, 0.092],
            ['BN-01', 'Ban OTR',          'ban',         'ban',    3_200_000_000,       160, 0.061, 0.058],
            ['SC-01', 'Suku cadang alat', 'suku-cadang', null,     5_400_000_000,      null, 0.088, null],
            ['UP-01', 'Upah operator',    'upah',        null,     4_800_000_000,      null, 0.083, null],
        ];

        foreach ($akun as [$kode, $nama, $kel, $sat, $pagu, $kuan, $porsiRp, $porsiKuan]) {
            $a = $this->baru(BiayaAkun::class, [
                'kode' => $kode, 'nama' => $nama, 'kelompok' => $kel,
                'jenis' => $kel === 'upah' ? 'tetap' : 'variabel',
                'satuan' => $sat, 'aktif' => true,
            ]);
            $this->baru(BiayaAnggaran::class, [
                'biaya_akun_id' => $a->id, 'tahun' => $this->kini->year,
                'pusat_biaya' => 'penambangan', 'nilai_rp' => $pagu,
                'kuantitas_rencana' => $kuan,
            ]);
            $n += 2;

            foreach (range(1, 6) as $b) {
                // Sedikit naik-turun tiap bulan supaya grafiknya bergerak.
                $goyang = 1 + (($b % 3) - 1) * 0.06;

                $r = $this->baru(BiayaRealisasi::class, [
                    'user_id' => $this->pengaju?->getKey(), 'biaya_akun_id' => $a->id,
                    'tahun' => $this->kini->year, 'bulan' => $b, 'pusat_biaya' => 'penambangan',
                    'nilai_rp' => round($pagu * $porsiRp * $goyang, 2),
                    'kuantitas' => $porsiKuan ? round($kuan * $porsiKuan * $goyang, 3) : null,
                ]);
                $this->setujui($r);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── izin kerja ─────────── */

    private function izin(): int
    {
        $n = 0;

        foreach ([['o2', 19.5, 23.5, '%'], ['lel', null, 10.0, '%LEL'],
                  ['co', null, 25.0, 'ppm'], ['h2s', null, 10.0, 'ppm']] as [$p, $min, $maks, $sat]) {
            $this->baru(IzinAmbang::class, [
                'parameter' => $p, 'batas_min' => $min, 'batas_maks' => $maks,
                'satuan' => $sat, 'acuan' => 'Prosedur ruang terbatas contoh',
            ]);
            $n++;
        }

        /* Ketujuh jenis izin diberi daftar periksa, bukan dua.
           Jenis yang daftar periksanya kosong dapat diterbitkan tanpa
           satu pun syarat wajib yang menghalanginya — dan itu bukan
           ketidaksempurnaan yang menarik untuk ditunjukkan, hanya
           kekosongan. Isinya contoh; tiap situs menyusun sendiri dari
           prosedurnya. */
        $syarat = [
            'panas' => ['APAR tersedia dan berfungsi',
                        'Fire watcher ditunjuk dan berada di tempat',
                        'Bahan mudah menyala disingkirkan radius 11 m',
                        'Uji gas dilakukan sebelum pekerjaan dimulai'],
            'ruang-terbatas' => ['Isolasi energi terpasang dan terkunci',
                                 'Ventilasi paksa berjalan',
                                 'Petugas jaga lubang masuk ditunjuk',
                                 'Rencana penyelamatan disiapkan dan diuji'],
            'ketinggian' => ['Harness dan lanyard diperiksa layak',
                             'Titik angkur diperiksa dan dicatat',
                             'Area bawah dibarikade'],
            'penggalian' => ['Utilitas bawah tanah dipetakan',
                             'Dinding galian ditopang atau dilandaikan',
                             'Jalan keluar tersedia tiap 7,5 m'],
            'listrik' => ['LOTO terpasang dan diverifikasi nol tegangan',
                          'APD listrik sesuai kelas tegangan',
                          'Pekerja bersertifikat kelistrikan'],
            'pengangkatan' => ['Sertifikat alat angkat masih berlaku',
                               'Rigger dan operator bersertifikat',
                               'Radius ayun dibarikade dan bebas orang',
                               'Beban dan radius diperiksa terhadap tabel muat'],
            'radiografi' => ['Batas radiasi diukur dan dibarikade',
                             'Petugas Proteksi Radiasi hadir',
                             'Sumber dihitung sebelum dan sesudah'],
        ];

        $urut = 0;
        foreach ($syarat as $jenis => $daftar) {
            foreach ($daftar as $teks) {
                $this->baru(IzinSyarat::class, [
                    'jenis' => $jenis, 'urutan' => ++$urut, 'teks' => $teks,
                    'wajib' => true, 'aktif' => true,
                ]);
                $n++;
            }
        }

        /* Dua izin, dan keduanya punya alasan berbeda untuk ada:

           - Izin panas yang MASIH berlaku, lengkap dengan uji gasnya —
             memperlihatkan bentuk izin yang benar.
           - Izin ketinggian yang sudah LEWAT waktunya dan belum
             ditutup. Itu peringatan terpenting modul ini, dan ia hanya
             dapat diperiksa bila datanya benar-benar ada. */
        $n += $this->satuIzin(
            'IK-CTH-01', 'panas', 'Pengelasan chute CV-02', 'Conveyor CV-02',
            $this->kini->copy()->subHours(1), $this->kini->copy()->addHours(6), true, true,
        );

        $n += $this->satuIzin(
            'IK-CTH-02', 'ketinggian', 'Penggantian idler atas', 'Conveyor CV-03',
            $this->kini->copy()->subDay()->setTime(8, 0),
            $this->kini->copy()->subDay()->setTime(17, 0), false, true,
        );

        return $n;
    }

    private function satuIzin(string $nomor, string $jenis, string $uraian, string $lokasi,
                              Carbon $mulai, Carbon $selesai, bool $ujiGas, bool $terbit): int
    {
        $izin = $this->baru(IzinKerja::class, [
            'user_id' => $this->pengaju?->getKey(), 'nomor' => $nomor, 'jenis' => $jenis,
            'lokasi' => $lokasi, 'uraian' => $uraian, 'pelaksana' => 'Regu Mekanik',
            'jumlah_pekerja' => 5, 'pengawas_lapangan' => 'Pengawas Contoh',
            'mulai' => $mulai, 'selesai' => $selesai,
        ]);
        $n = 1;

        foreach (IzinSyarat::withoutGlobalScopes()->where('company_id', $this->c->id)
                     ->where('jenis', $jenis)->orderBy('urutan')->get() as $s) {
            $this->baru(IzinPeriksa::class, [
                'izin_kerja_id' => $izin->id, 'izin_syarat_id' => $s->id,
                'teks' => $s->teks, 'wajib' => true, 'terpenuhi' => true,
            ]);
            $n++;
        }

        if ($ujiGas) {
            $this->baru(IzinGas::class, [
                'izin_kerja_id' => $izin->id,
                'waktu_uji' => $mulai->copy()->subMinutes(20),
                'o2' => 20.8, 'lel' => 0, 'co' => 2, 'h2s' => 0,
                'alat' => 'Multigas detector contoh', 'petugas' => 'Petugas Gas Contoh',
            ]);
            $n++;
        }

        if ($terbit) $this->setujui($izin);

        return $n;
    }

    /* ═══════════ perkakas ═══════════ */

    /**
     * Buat satu baris milik perusahaan contoh ini.
     *
     * company_id ditulis tegas, bukan diserahkan kepada
     * BerpemilikPerusahaan: pemuat ini dijalankan oleh administrator,
     * yang perusahaannya belum tentu — dan biasanya bukan — perusahaan
     * yang sedang dimuati.
     */
    private function baru(string $kelas, array $isi)
    {
        $model = new $kelas;

        if ($model->getConnection()->getSchemaBuilder()
                  ->hasColumn($model->getTable(), 'company_id')) {
            $isi = ['company_id' => $this->c->id] + $isi;
        }

        return $kelas::withoutGlobalScopes()->create($isi);
    }

    /**
     * Bawa satu baris melewati alur tinjauannya.
     *
     * Data contoh yang seluruhnya berhenti sebagai draf tidak masuk satu
     * pun hitungan KPI — halamannya terisi, tetapi seluruh indikatornya
     * nol, dan itu terbaca sebagai hitungan yang rusak.
     *
     * Kegagalannya dicatat, bukan ditelan. Pemuat yang diam-diam
     * meninggalkan seluruh barisnya sebagai draf adalah persis bentuk
     * kesalahan yang paling sulit ditemukan dari halaman jadinya.
     */
    private function setujui($baris): void
    {
        if (!$this->pengaju || !Alur::peninjau($this->peninjau)) return;

        try {
            $baris->ajukan($this->pengaju);
            $baris->setujui($this->peninjau);
        } catch (\Throwable $e) {
            $kunci = class_basename($baris).': '.$e->getMessage();

            if (!in_array($kunci, $this->catatan, true)) $this->catatan[] = $kunci;
        }
    }
}
