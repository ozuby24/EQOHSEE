<?php

namespace Tests\Feature;

use App\Models\{Certificate, Company, Course, User};
use App\Support\{Alur, Diagnosa};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pemeriksaan mandiri sistem.
 *
 * Yang diuji di sini bukan bahwa pemeriksaannya berjalan, melainkan
 * bahwa ia MENEMUKAN. Pemeriksaan yang selalu menjawab "aman" lulus
 * setiap uji yang hanya memanggilnya, dan justru itulah bentuk
 * kegagalan yang paling berbahaya di kelas ini: ia memindahkan
 * kewaspadaan orang ke tempat yang keliru, sehingga lebih buruk
 * daripada tidak ada pemeriksaan sama sekali.
 *
 * Karena itu tiap pemeriksaan penting diuji dua arah — keadaan yang
 * sehat harus terbaca aman, dan keadaan yang rusak harus terbaca
 * rusak.
 */
class DiagnosaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* Pemetaan skema di-memo untuk seumur proses. Antar uji itu
           membuat hasilnya bergantung pada urutan berjalan — cacat yang
           muncul dan hilang sendiri, dan yang paling mahal dikejar. */
        Diagnosa::lupakanSkema();
    }

    /** @return array<string,array<string,mixed>> hasil, dikunci per kode */
    private function periksa(): array
    {
        $h = [];
        foreach (Diagnosa::jalankan() as $x) $h[$x['kode']] = $x;

        return $h;
    }

    private function keadaan(string $kode): string
    {
        $h = $this->periksa();
        $this->assertArrayHasKey($kode, $h, "Pemeriksaan '{$kode}' tidak ada.");

        return $h[$kode]['keadaan'];
    }

    /* ---------- bentuk keluaran ---------- */

    public function test_setiap_pemeriksaan_menyebut_bidang_yang_sama(): void
    {
        foreach (Diagnosa::jalankan() as $x) {
            foreach (['kode', 'kelompok', 'judul', 'keadaan', 'nilai', 'uraian', 'tindakan'] as $b) {
                $this->assertArrayHasKey($b, $x, "Pemeriksaan '{$x['kode']}' tidak menyebut {$b}.");
            }

            $this->assertContains($x['keadaan'],
                [Diagnosa::AMAN, Diagnosa::PERHATIAN, Diagnosa::GAWAT, Diagnosa::TAK_TAHU]);
        }
    }

    /**
     * Baris merah tanpa langkah hanya melahirkan kebiasaan mengabaikan
     * warna merah.
     */
    public function test_yang_tidak_aman_selalu_menyebut_langkahnya(): void
    {
        foreach (Diagnosa::jalankan() as $x) {
            if ($x['keadaan'] === Diagnosa::AMAN) continue;

            $this->assertNotEmpty($x['tindakan'],
                "Pemeriksaan '{$x['kode']}' berkeadaan {$x['keadaan']} tanpa menyebut langkahnya.");
        }
    }

    public function test_yang_aman_tidak_menuntut_langkah(): void
    {
        foreach (Diagnosa::jalankan() as $x) {
            if ($x['keadaan'] !== Diagnosa::AMAN) continue;

            $this->assertNull($x['tindakan'],
                "Pemeriksaan '{$x['kode']}' berkeadaan aman tetapi masih menuntut langkah.");
        }
    }

    public function test_yang_gawat_diurutkan_paling_atas(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        $hasil = Diagnosa::jalankan();

        $this->assertSame(Diagnosa::GAWAT, $hasil[0]['keadaan']);
    }

    public function test_ringkasan_menjumlah_seluruh_pemeriksaan(): void
    {
        $hasil = Diagnosa::jalankan();

        $this->assertSame(count($hasil), array_sum(Diagnosa::ringkas($hasil)));
    }

    /* ---------- keamanan ---------- */

    public function test_debug_menyala_di_produksi_terbaca_gawat(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        $this->assertSame(Diagnosa::GAWAT, $this->keadaan('debug'));
    }

    public function test_debug_mati_terbaca_aman(): void
    {
        config(['app.debug' => false]);

        $this->assertSame(Diagnosa::AMAN, $this->keadaan('debug'));
    }

    public function test_kunci_aplikasi_kosong_terbaca_gawat(): void
    {
        config(['app.key' => null]);

        $this->assertSame(Diagnosa::GAWAT, $this->keadaan('kunci-aplikasi'));
    }

    public function test_kuki_tanpa_secure_pada_situs_https_terbaca_perhatian(): void
    {
        config(['app.url' => 'https://eqohsee.test', 'session.secure' => false]);

        $this->assertSame(Diagnosa::PERHATIAN, $this->keadaan('kuki-aman'));
    }

    /**
     * Pada situs http, kuki bertanda secure justru TIDAK PERNAH
     * terkirim — dan akibatnya tidak seorang pun dapat masuk.
     * Pemeriksaan ini tidak boleh menuntutnya di sana.
     */
    public function test_kuki_tanpa_secure_pada_situs_http_tidak_dipersoalkan(): void
    {
        config(['app.url' => 'http://localhost', 'session.secure' => false]);

        $this->assertSame(Diagnosa::AMAN, $this->keadaan('kuki-aman'));
    }

    public function test_berkas_rahasia_di_direktori_publik_terbaca_gawat(): void
    {
        $umpan = public_path('.env');
        $adaSebelumnya = file_exists($umpan);

        if (!$adaSebelumnya) file_put_contents($umpan, "APP_KEY=palsu\n");

        try {
            $this->assertSame(Diagnosa::GAWAT, $this->keadaan('bocor-publik'));
        } finally {
            if (!$adaSebelumnya) @unlink($umpan);
        }
    }

    public function test_tanpa_berkas_rahasia_di_publik_terbaca_aman(): void
    {
        $this->assertSame(Diagnosa::AMAN, $this->keadaan('bocor-publik'));
    }

    public function test_tanpa_administrator_terbaca_gawat(): void
    {
        $this->assertSame(Diagnosa::GAWAT, $this->keadaan('administrator'));
    }

    public function test_administrator_berlebihan_terbaca_perhatian(): void
    {
        User::factory()->count(6)->create(['is_admin' => true]);

        $this->assertSame(Diagnosa::PERHATIAN, $this->keadaan('administrator'));
    }

    public function test_perusahaan_contoh_dilaporkan(): void
    {
        User::factory()->create(['is_admin' => true]);
        Company::create(['name' => 'PT Contoh', 'demo' => true]);

        $h = $this->periksa()['perusahaan-contoh'];

        $this->assertSame(Diagnosa::PERHATIAN, $h['keadaan']);
        $this->assertStringContainsString('PT Contoh', $h['nilai']);
    }

    /* ---------- basis data ---------- */

    public function test_migrasi_tertunda_terbaca_gawat(): void
    {
        // Buang satu catatan migrasi: berkasnya masih ada, catatannya
        // tidak — persis keadaan server yang belum dimigrasi.
        DB::table('migrations')->orderByDesc('id')->limit(1)->delete();

        $h = $this->periksa()['migrasi'];

        $this->assertSame(Diagnosa::GAWAT, $h['keadaan']);
        $this->assertStringContainsString('1 tertunda', $h['nilai']);
    }

    public function test_migrasi_mutakhir_terbaca_aman(): void
    {
        $this->assertSame(Diagnosa::AMAN, $this->keadaan('migrasi'));
    }

    /* ---------- keutuhan data ---------- */

    public function test_nomor_sertifikat_kembar_terbaca_gawat(): void
    {
        $k = Course::create(['title' => 'K', 'slug' => 'k-'.uniqid()]);
        $c = Company::create(['name' => 'PT A']);

        foreach ([1, 2] as $i) {
            Certificate::withoutGlobalScopes()->create([
                'user_id' => User::factory()->create(['company_id' => $c->id])->id,
                'course_id' => $k->id, 'company_id' => $c->id,
                'certificate_number' => 'A/SRT/2026/0001',
                'issued_at' => now(),
            ]);
        }

        $h = $this->periksa()['nomor-kembar'];

        $this->assertSame(Diagnosa::GAWAT, $h['keadaan']);
        $this->assertStringContainsString('A/SRT/2026/0001', $h['nilai']);
    }

    public function test_nomor_sertifikat_unik_terbaca_aman(): void
    {
        $this->assertSame(Diagnosa::AMAN, $this->keadaan('nomor-kembar'));
    }

    public function test_baris_tanpa_perusahaan_dilaporkan(): void
    {
        DB::table('mine_operational_records')->insert([
            'company_id' => null, 'tanggal' => '2026-01-05', 'shift' => 'siang',
            'produksi_ton' => 1000, 'status' => Alur::DRAF,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $h = $this->periksa()['tanpa-pemilik'];

        $this->assertNotSame(Diagnosa::AMAN, $h['keadaan']);
        $this->assertStringContainsString('mine_operational_records', $h['nilai']);
    }

    public function test_tanpa_baris_yatim_terbaca_aman(): void
    {
        $this->assertSame(Diagnosa::AMAN, $this->keadaan('tanpa-pemilik'));
    }

    public function test_pengguna_tanpa_perusahaan_dilaporkan(): void
    {
        User::factory()->create(['company_id' => null, 'is_admin' => false, 'active' => true]);

        $this->assertSame(Diagnosa::PERHATIAN, $this->keadaan('pengguna-yatim'));
    }

    /** Administrator memang tidak terikat perusahaan; itu bukan temuan. */
    public function test_administrator_tanpa_perusahaan_bukan_temuan(): void
    {
        User::factory()->create(['company_id' => null, 'is_admin' => true]);

        $this->assertSame(Diagnosa::AMAN, $this->keadaan('pengguna-yatim'));
    }

    public function test_pengajuan_yang_menggantung_lama_dilaporkan(): void
    {
        $c = Company::create(['name' => 'PT A']);

        DB::table('mine_operational_records')->insert([
            'company_id' => $c->id, 'tanggal' => '2026-01-05', 'shift' => 'siang',
            'produksi_ton' => 1000, 'status' => Alur::DIAJUKAN,
            'diajukan_pada' => now()->subDays(30),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $h = $this->periksa()['tertahan'];

        $this->assertNotSame(Diagnosa::AMAN, $h['keadaan']);
        $this->assertStringContainsString('mine_operational_records', $h['nilai']);
    }

    public function test_pengajuan_yang_baru_belum_dipersoalkan(): void
    {
        $c = Company::create(['name' => 'PT A']);

        DB::table('mine_operational_records')->insert([
            'company_id' => $c->id, 'tanggal' => '2026-01-05', 'shift' => 'siang',
            'produksi_ton' => 1000, 'status' => Alur::DIAJUKAN,
            'diajukan_pada' => now()->subHours(3),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(Diagnosa::AMAN, $this->keadaan('tertahan'));
    }

    /* ---------- ketahanan ---------- */

    /**
     * Satu pemeriksaan yang meledak tidak boleh mematikan halaman yang
     * justru dibuka untuk mencari tahu apa yang rusak.
     */
    public function test_tabel_yang_hilang_tidak_mematikan_seluruh_diagnosa(): void
    {
        DB::statement('DROP TABLE failed_jobs');

        $h = $this->periksa();

        $this->assertArrayHasKey('antrean-gagal', $h);
        $this->assertSame(Diagnosa::TAK_TAHU, $h['antrean-gagal']['keadaan']);
        $this->assertGreaterThan(15, count($h), 'Pemeriksaan lain ikut berhenti.');
    }
}
