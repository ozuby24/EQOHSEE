<?php

namespace Tests\Feature;

use App\Models\{Company, GeoInstrumen, GeoLereng, GudangBarang, IzinKerja,
                MineOperationalRecord, User, WaterSump, WaterSumpPump};
use App\Support\{Alur, DataContoh};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pemuat data contoh.
 *
 * Yang diuji di sini bukan "apakah datanya muncul" melainkan tiga hal
 * yang bila salah tidak menimbulkan galat apa pun:
 *
 * 1. Ia hanya menyentuh perusahaan yang ditandai contoh. Penghapusan
 *    yang melebar satu perusahaan saja sudah berarti kehilangan data
 *    sungguhan, dan tidak ada yang membatalkannya.
 *
 * 2. Ia dapat dijalankan berulang tanpa menumpuk. Pemuat yang menumpuk
 *    membuat angkanya berlipat setiap kali tombolnya ditekan — dan
 *    angka yang berlipat masih terlihat wajar sampai seseorang
 *    membandingkannya dengan sesuatu.
 *
 * 3. Barisnya benar-benar disetujui. Data contoh yang berhenti sebagai
 *    draf mengisi halaman tetapi meninggalkan seluruh KPI nol — bentuk
 *    kegagalan yang paling mudah disalahartikan sebagai hitungan yang
 *    rusak, yaitu persis hal yang hendak diperiksa dengan data contoh.
 */
class DataContohTest extends TestCase
{
    use RefreshDatabase;

    private Company $contoh;
    private Company $sungguhan;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contoh    = Company::create(['name' => 'PT Contoh', 'demo' => true]);
        $this->sungguhan = Company::create(['name' => 'PT Sungguhan']);

        // Pengaju: orang perusahaan contoh itu sendiri.
        User::factory()->create(['company_id' => $this->contoh->id]);

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function muat(): array
    {
        return DataContoh::muat($this->contoh->fresh(), $this->admin);
    }

    /* ---------- penjaga ---------- */

    public function test_perusahaan_yang_tidak_ditandai_contoh_ditolak(): void
    {
        $this->expectException(\RuntimeException::class);

        DataContoh::muat($this->sungguhan, $this->admin);
    }

    public function test_penolakan_terjadi_sebelum_apa_pun_terhapus(): void
    {
        MineOperationalRecord::withoutGlobalScopes()->create([
            'company_id' => $this->sungguhan->id, 'tanggal' => '2026-01-05',
            'shift' => 'siang', 'produksi_ton' => 1000,
        ]);

        try {
            DataContoh::muat($this->sungguhan, $this->admin);
        } catch (\RuntimeException) {
            // memang ditolak
        }

        $this->assertSame(1, MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', $this->sungguhan->id)->count());
    }

    /* ---------- batas perusahaan ---------- */

    public function test_data_perusahaan_lain_tidak_ikut_terhapus(): void
    {
        $lain = MineOperationalRecord::withoutGlobalScopes()->create([
            'company_id' => $this->sungguhan->id, 'tanggal' => '2026-01-05',
            'shift' => 'siang', 'produksi_ton' => 1000,
        ]);
        $barang = GudangBarang::withoutGlobalScopes()->create([
            'company_id' => $this->sungguhan->id, 'kode' => 'X-1',
            'nama' => 'Barang sungguhan', 'kategori' => 'material',
        ]);

        $this->muat();
        $this->muat();   // dua kali: penghapusannya benar-benar berjalan

        $this->assertNotNull(MineOperationalRecord::withoutGlobalScopes()->find($lain->id));
        $this->assertNotNull(GudangBarang::withoutGlobalScopes()->find($barang->id));
    }

    /**
     * Anak yang tidak punya company_id sendiri disaring lewat induknya.
     *
     * Bila penyaringnya luput, pompa dan instrumen perusahaan lain ikut
     * terhapus — dua alat yang statusnya dipakai orang untuk memutuskan
     * apakah suatu tempat aman dimasuki.
     */
    public function test_anak_tanpa_company_id_milik_perusahaan_lain_tidak_terhapus(): void
    {
        $kolam = WaterSump::withoutGlobalScopes()->create([
            'company_id' => $this->sungguhan->id, 'kode' => 'SMP-X', 'nama' => 'Kolam lain',
        ]);
        $pompa = WaterSumpPump::withoutGlobalScopes()->create([
            'water_sump_id' => $kolam->id, 'nama' => 'Pompa lain', 'status' => 'jalan',
        ]);

        $lereng = GeoLereng::withoutGlobalScopes()->create([
            'company_id' => $this->sungguhan->id, 'kode' => 'HW-X', 'nama' => 'Lereng lain',
        ]);
        $alat = GeoInstrumen::withoutGlobalScopes()->create([
            'geo_lereng_id' => $lereng->id, 'kode' => 'PRISM-X', 'jenis' => 'prisma',
        ]);

        $this->muat();
        $this->muat();

        $this->assertNotNull(WaterSumpPump::withoutGlobalScopes()->find($pompa->id));
        $this->assertNotNull(GeoInstrumen::withoutGlobalScopes()->find($alat->id));
    }

    /**
     * Anak tanpa company_id harus ikut TERHITUNG, bukan sekadar ikut
     * terhapus.
     *
     * Terhapusnya sudah dijamin oleh cascade kunci asingnya, jadi
     * penyaring lewat-induk tidak akan pernah ketahuan hilang dari situ.
     * Yang ketahuan adalah angkanya: hitungIsi() itulah yang
     * diperlihatkan kepada orang sebelum ia menekan tombol yang tidak
     * dapat dibatalkan. Angka yang kekurangan menjanjikan penghapusan
     * yang lebih kecil daripada yang benar-benar terjadi.
     */
    public function test_hitung_isi_menyertakan_anak_tanpa_company_id(): void
    {
        $this->muat();

        $alat  = GeoInstrumen::withoutGlobalScopes()->count();
        $pompa = WaterSumpPump::withoutGlobalScopes()->count();

        $this->assertGreaterThan(0, $alat);
        $this->assertGreaterThan(0, $pompa);

        $rincian = DataContoh::rincianIsi($this->contoh->fresh());

        $this->assertSame($alat, $rincian['geo_instrumens'] ?? 0);
        $this->assertSame($pompa, $rincian['water_sump_pumps'] ?? 0);

        $this->assertSame([], DataContoh::rincianIsi($this->sungguhan),
            'Data perusahaan lain tidak boleh ikut terhitung.');
    }

    /* ---------- muat ulang ---------- */

    public function test_muat_ulang_tidak_menumpuk(): void
    {
        $pertama = $this->muat();
        $kedua   = $this->muat();

        $this->assertSame(0, $pertama['dihapus'], 'Pemuatan pertama tidak menghapus apa pun.');
        $this->assertSame(array_sum($pertama['dibuat']), $kedua['dihapus'],
            'Pemuatan kedua harus membuang persis sebanyak yang dibuat pemuatan pertama.');
        $this->assertSame($pertama['dibuat'], $kedua['dibuat']);
    }

    public function test_hitung_isi_cocok_dengan_yang_terhapus_berikutnya(): void
    {
        $this->muat();

        $ramalan = DataContoh::hitungIsi($this->contoh->fresh());
        $nyata   = $this->muat()['dihapus'];

        $this->assertSame($ramalan, $nyata);
    }

    public function test_hitung_isi_perusahaan_kosong_nol(): void
    {
        $this->assertSame(0, DataContoh::hitungIsi($this->contoh));
    }

    /* ---------- isinya ---------- */

    public function test_setiap_modul_terisi(): void
    {
        $hasil = $this->muat();

        foreach (['Operasi', 'Gudang', 'Air', 'Geoteknik', 'Lingkungan',
                  'Peledakan', 'Angkutan', 'Biaya', 'Izin kerja'] as $modul) {
            $this->assertArrayHasKey($modul, $hasil['dibuat']);
            $this->assertGreaterThan(0, $hasil['dibuat'][$modul], "Modul {$modul} kosong.");
        }
    }

    public function test_seluruh_baris_terbuat_milik_perusahaan_contoh(): void
    {
        $this->muat();

        $this->assertSame(0, MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', '!=', $this->contoh->id)->count());
        $this->assertSame(0, GudangBarang::withoutGlobalScopes()
            ->where('company_id', '!=', $this->contoh->id)->count());
    }

    /**
     * Yang paling mudah luput: barisnya ada, tetapi seluruhnya draf.
     * Halamannya penuh, seluruh KPI-nya nol.
     */
    public function test_baris_beralur_berakhir_disetujui(): void
    {
        $hasil = $this->muat();

        $this->assertSame([], $hasil['catatan'],
            'Pemuat melaporkan baris yang gagal disetujui.');

        $draf = MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', $this->contoh->id)
            ->where('status', '!=', Alur::DISETUJUI)->count();

        $this->assertSame(0, $draf, 'Masih ada catatan operasi yang belum disetujui.');
    }

    public function test_pengaju_bukan_peninjau(): void
    {
        $this->muat();

        $baris = MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', $this->contoh->id)->firstOrFail();

        $this->assertNotNull($baris->diajukan_oleh);
        $this->assertSame($this->admin->id, $baris->ditinjau_oleh);
        $this->assertNotSame($baris->diajukan_oleh, $baris->ditinjau_oleh);
    }

    /**
     * Perusahaan contoh tanpa pengguna tetap termuat, tetapi datanya
     * berhenti sebagai draf. Itu keadaan yang harus DIKATAKAN, bukan
     * dibiarkan terbaca sebagai hitungan yang rusak.
     */
    public function test_tanpa_pengaju_datanya_draf_dan_dikatakan(): void
    {
        $kosong = Company::create(['name' => 'PT Contoh Kosong', 'demo' => true]);

        $hasil = DataContoh::muat($kosong, $this->admin);

        $this->assertNotEmpty($hasil['catatan']);
        $this->assertStringContainsString('draf', $hasil['catatan'][0]);
        $this->assertGreaterThan(0, array_sum($hasil['dibuat']));
    }

    public function test_tanpa_peninjau_berwenang_datanya_draf_dan_dikatakan(): void
    {
        $biasa = User::factory()->create(['company_id' => $this->contoh->id]);

        $hasil = DataContoh::muat($this->contoh, $biasa);

        $this->assertNotEmpty($hasil['catatan']);
        $this->assertStringContainsString('draf', $hasil['catatan'][0]);
    }

    /* ---------- tanggal ---------- */

    /**
     * Pemuat ini dijalankan kapan saja, dan aritmetika tanggalnya punya
     * dua jebakan yang keduanya diam:
     *
     * - range(1, 0) di PHP menghasilkan [1, 0] yang menurun, bukan
     *   senarai kosong. Pada bulan Januari itu membuat satu catatan
     *   lahir dengan bulan 0, yang berguling ke Desember tahun lalu.
     *
     * - setMonth() sebelum setDay(): dijalankan pada tanggal 31,
     *   setMonth(2) berguling ke Maret lebih dulu, sehingga catatan
     *   Februari diam-diam tercatat sebagai Maret — dua catatan di satu
     *   bulan, dan nol di bulan lain.
     *
     * Keduanya lolos seluruh uji selama ujinya kebetulan berjalan pada
     * tanggal yang aman. Karena itu jamnya dibekukan di sini.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tanggalRawan')]
    public function test_catatan_operasi_jatuh_pada_bulan_yang_benar(string $saat): void
    {
        Carbon::setTestNow($saat);

        try {
            $this->muat();

            $baris = MineOperationalRecord::withoutGlobalScopes()
                ->where('company_id', $this->contoh->id)->get();

            $this->assertNotEmpty($baris, "Tidak ada catatan operasi pada {$saat}.");

            $tahunIni = Carbon::parse($saat)->year;

            foreach ($baris as $b) {
                $t = Carbon::parse($b->tanggal);

                $this->assertSame($tahunIni, $t->year,
                    "Catatan {$b->tanggal} keluar dari tahun berjalan (dijalankan {$saat}).");
                $this->assertLessThanOrEqual(Carbon::parse($saat)->month, $t->month,
                    "Catatan {$b->tanggal} jatuh di bulan yang belum terjadi (dijalankan {$saat}).");
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Tiap bulan yang sudah lewat harus punya persis satu catatan
     * bulanan. Yang berguling membuat satu bulan berisi dua dan bulan
     * tetangganya kosong — dan jumlah totalnya tetap sama, sehingga
     * hitungan baris tidak pernah menunjukkannya.
     */
    public function test_setiap_bulan_lewat_terwakili_tepat_sekali(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');

        try {
            $this->muat();

            // Bulan berjalan diisi harian, jadi hanya bulan 1..7 yang
            // memakai catatan bulanan tunggal.
            $perBulan = MineOperationalRecord::withoutGlobalScopes()
                ->where('company_id', $this->contoh->id)
                ->get()
                ->filter(fn ($b) => Carbon::parse($b->tanggal)->month < 8)
                ->groupBy(fn ($b) => Carbon::parse($b->tanggal)->month)
                ->map->count();

            foreach (range(1, 7) as $b) {
                $this->assertSame(1, $perBulan[$b] ?? 0,
                    "Bulan {$b} terwakili ".($perBulan[$b] ?? 0).' kali, seharusnya sekali.');
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Modul Operasi tidak boleh kosong, tanggal berapa pun pemuatnya
     * dijalankan.
     *
     * Yang paling rawan 1 Januari: tidak ada bulan lewat untuk diisi
     * dan tidak ada kemarin di bulan berjalan. Percobaan pertama
     * menuntut "sampai kemarin" secara ketat dan menghasilkan nol
     * catatan di sana — halaman terbuka penuh dengan seluruh
     * indikatornya nol, persis bentuk kegagalan yang hendak dihindari
     * data contoh.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tanggalRawan')]
    public function test_modul_operasi_tidak_pernah_kosong(string $saat): void
    {
        Carbon::setTestNow($saat);

        try {
            $this->muat();

            $n = MineOperationalRecord::withoutGlobalScopes()
                ->where('company_id', $this->contoh->id)
                ->where('status', Alur::DISETUJUI)->count();

            $this->assertGreaterThan(0, $n,
                "Modul Operasi kosong bila pemuat dijalankan {$saat}.");
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Satu tanggal dan satu shift hanya boleh punya satu catatan.
     *
     * Ini bukan sekadar kerapian: tonase tiap catatan dijumlahkan apa
     * adanya, sehingga dua catatan untuk shift yang sama berarti
     * produksi terhitung dua kali. Angkanya tetap terlihat wajar —
     * hanya terlalu besar — dan tidak ada yang menandainya.
     *
     * Gelang bulanan dan gelang harian mengisi rentang yang berbeda,
     * dan uji inilah yang menjaga keduanya tidak pernah bertindih.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('tanggalRawan')]
    public function test_tidak_ada_shift_ganda(string $saat): void
    {
        Carbon::setTestNow($saat);

        try {
            $this->muat();

            $ganda = MineOperationalRecord::withoutGlobalScopes()
                ->where('company_id', $this->contoh->id)
                ->get()
                ->groupBy(fn ($b) => Carbon::parse($b->tanggal)->toDateString().' '.$b->shift)
                ->filter(fn ($g) => $g->count() > 1)
                ->keys()->all();

            $this->assertSame([], $ganda,
                "Shift terhitung lebih dari sekali (dijalankan {$saat}): ".implode(', ', $ganda));
        } finally {
            Carbon::setTestNow();
        }
    }

    /** @return array<string,array{string}> */
    public static function tanggalRawan(): array
    {
        return [
            'Januari (range menurun)'        => ['2026-01-15 09:00:00'],
            'tanggal 1 Januari'              => ['2026-01-01 09:00:00'],
            'tanggal 31 (bulan berguling)'   => ['2026-08-31 09:00:00'],
            'tanggal 29 Februari kabisat'    => ['2028-02-29 09:00:00'],
            'tanggal 30 di akhir tahun'      => ['2026-12-30 09:00:00'],
            'tanggal 1 bulan tengah'         => ['2026-06-01 09:00:00'],
        ];
    }

    /* ---------- data yang memang tidak sempurna ---------- */

    /**
     * Data contoh yang seluruhnya patuh tidak pernah menunjukkan apakah
     * peringatannya bekerja. Beberapa pelanggaran memang disengaja, dan
     * hilangnya pelanggaran itu adalah kemunduran, bukan perbaikan.
     */
    public function test_ada_izin_lewat_waktu_yang_belum_ditutup(): void
    {
        $this->muat();

        $lewat = IzinKerja::withoutGlobalScopes()
            ->where('company_id', $this->contoh->id)
            ->where('status', Alur::DISETUJUI)
            ->whereNull('ditutup_pada')
            ->where('selesai', '<', now())
            ->count();

        $this->assertGreaterThan(0, $lewat,
            'Tanpa izin yang lewat waktu, peringatan terpenting modul izin tidak dapat diperiksa.');
    }

    public function test_ada_pompa_rusak_dan_barang_menipis(): void
    {
        $this->muat();

        $this->assertGreaterThan(0, WaterSumpPump::withoutGlobalScopes()
            ->where('status', 'rusak')->count());

        $menipis = GudangBarang::withoutGlobalScopes()
            ->where('company_id', $this->contoh->id)->get()
            ->filter(fn ($b) => $b->stok < $b->stok_min)->count();

        $this->assertGreaterThan(0, $menipis, 'Tidak ada barang di bawah stok minimum.');
    }
}
