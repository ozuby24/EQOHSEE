<?php

namespace Tests\Feature;

use App\Models\{Company, Course, InspectionTemplate, Quiz, User, WaterSump};
use App\Support\DataContoh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membuang data contoh, sebagai tindakan tersendiri.
 *
 * Terpisah dari "muat ulang" dan sengaja begitu. `muat()` menyegarkan:
 * buang lalu isi, dan modulnya kembali berisi. Yang ini MENGOSONGKAN —
 * dipakai ketika pemeriksaan sudah selesai dan pemasangannya hendak
 * dipakai sungguhan.
 *
 * Tanpa pemisahan itu, satu-satunya jalan mengosongkan adalah menghapus
 * dua ratus empat puluh baris di tujuh belas modul satu per satu lewat
 * antarmukanya masing-masing — jalan yang tidak akan ditempuh siapa
 * pun. Data contohnya karena itu akan tertinggal, lalu ikut terhitung
 * sebagai data sungguhan pada laporan pertama yang dicetak.
 *
 * Yang dijaga di sini bukan kemudahannya melainkan SASARANNYA. Ini
 * satu-satunya tombol di aplikasi yang membuang data seluruh
 * perusahaan sekaligus.
 */
class BuangDataContohTest extends TestCase
{
    use RefreshDatabase;

    private function perusahaanBerisi(bool $demo = true): array
    {
        $c = Company::create(['name' => 'PT Contoh', 'demo' => $demo]);

        $admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]);
        User::factory()->create(['company_id' => $c->id, 'email_verified_at' => now()]);

        if ($demo) DataContoh::muat($c, $admin);

        return [$c, $admin];
    }

    /* ═══════════ sasaran ═══════════ */

    /**
     * Perusahaan yang BUKAN perusahaan contoh tidak dapat dikosongkan.
     *
     * Lapis pertama dari tiga, dan yang paling menentukan: penandaan
     * `demo` adalah tindakan tersendiri yang harus disengaja lebih
     * dulu, sehingga tombol yang tertekan pada baris yang salah tidak
     * menemukan sasaran.
     */
    public function test_perusahaan_sungguhan_menolak_dikosongkan(): void
    {
        [$c] = $this->perusahaanBerisi(demo: false);

        // Data sungguhan, dibuat langsung — bukan lewat pemuat contoh.
        WaterSump::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'SUNGGUHAN', 'nama' => 'Kolam sungguhan',
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            DataContoh::buang($c);
        } finally {
            // Data perusahaan sungguhan tidak boleh ikut terbuang.
            $this->assertDatabaseHas('water_sumps', ['kode' => 'SUNGGUHAN']);
        }
    }

    public function test_data_perusahaan_lain_tidak_ikut_terbuang(): void
    {
        [$contoh] = $this->perusahaanBerisi();

        $lain = Company::create(['name' => 'PT Lain']);
        WaterSump::withoutGlobalScopes()->create([
            'company_id' => $lain->id, 'kode' => 'MILIK-LAIN', 'nama' => 'Kolam PT Lain',
        ]);

        DataContoh::buang($contoh);

        // Pengosongan satu perusahaan tidak boleh menyentuh perusahaan lain.
        $this->assertDatabaseHas('water_sumps', ['kode' => 'MILIK-LAIN']);
    }

    /* ═══════════ pengosongan ═══════════ */

    public function test_mengosongkan_seluruh_modul(): void
    {
        [$c] = $this->perusahaanBerisi();

        $sebelum = DataContoh::hitungIsi($c);
        $this->assertGreaterThan(100, $sebelum, 'Data contohnya tidak termuat; uji ini hampa.');

        $hasil = DataContoh::buang($c);

        $this->assertSame($sebelum, $hasil['dihapus']);
        $this->assertSame(0, DataContoh::hitungIsi($c), 'Masih ada baris yang tertinggal.');
    }

    /**
     * Rinciannya dihitung SEBELUM penghapusan.
     *
     * Sesudahnya semuanya nol, dan yang menekan tombolnya tidak akan
     * pernah tahu apa yang barusan hilang.
     */
    public function test_rincian_menyebut_apa_yang_dibuang(): void
    {
        [$c] = $this->perusahaanBerisi();

        $hasil = DataContoh::buang($c);

        $this->assertNotEmpty($hasil['rincian']);
        $this->assertArrayHasKey('water_sumps', $hasil['rincian']);
        $this->assertGreaterThan(0, $hasil['rincian']['water_sumps']);
    }

    /** Mengosongkan yang sudah kosong bukan galat. */
    public function test_mengosongkan_dua_kali_aman(): void
    {
        [$c] = $this->perusahaanBerisi();

        DataContoh::buang($c);
        $kedua = DataContoh::buang($c);

        $this->assertSame(0, $kedua['dihapus']);
    }

    /* ═══════════ pustaka bersama ═══════════ */

    /**
     * Kursus SUNGGUHAN selamat; hanya yang bertanda contoh terbuang.
     *
     * Kursus, kuis, dan template inspeksi tidak punya company_id —
     * ketiganya pustaka bersama — sehingga penghapus tidak dapat
     * menyaringnya lewat perusahaan. Penyaringnya kolom `demo_company_id`, dan
     * kolom itu bawaannya null. Bila penyaringnya suatu saat
     * dilonggarkan menjadi "buang yang tidak punya pemilik", seluruh
     * kurikulum pemasangan ini akan lenyap dalam satu klik.
     */
    public function test_kursus_sungguhan_tidak_ikut_terbuang(): void
    {
        [$c] = $this->perusahaanBerisi();

        $asli = Course::withoutGlobalScopes()->create([
            'title' => 'Kurikulum Sungguhan', 'cert_template' => 'default',
            'auto_certificate' => false, 'require_code' => false, 'require_evaluation' => false,
        ]);
        $kuisAsli = Quiz::withoutGlobalScopes()->create([
            'course_id' => $asli->id, 'title' => 'Kuis sungguhan', 'pass_score' => 70,
        ]);
        $tplAsli = InspectionTemplate::withoutGlobalScopes()->create([
            'nama' => 'Template sungguhan', 'is_active' => true,
        ]);

        // Data contohnya memang ada sebelum dibuang.
        $this->assertGreaterThan(0, Course::withoutGlobalScopes()->whereNotNull('demo_company_id')->count());

        DataContoh::buang($c);

        $this->assertNotNull(Course::withoutGlobalScopes()->find($asli->id),
            'Kurikulum sungguhan terbuang bersama data contoh.');
        $this->assertNotNull(Quiz::withoutGlobalScopes()->find($kuisAsli->id));
        $this->assertNotNull(InspectionTemplate::withoutGlobalScopes()->find($tplAsli->id));

        $this->assertSame(0, Course::withoutGlobalScopes()->whereNotNull('demo_company_id')->count(),
            'Kursus contoh tertinggal.');
    }

    /**
     * Dua perusahaan contoh tidak saling membuang pustakanya.
     *
     * Inilah sebabnya penandanya bukan boolean. Versi pertama memakai
     * `demo = true` saja, dan uji lain — DataContohTest — langsung
     * merah: hitungan perusahaan B ikut memuat pustaka contoh milik A,
     * dan menekan "buang" pada B akan membuang kursus contoh A.
     */
    public function test_dua_perusahaan_contoh_tidak_saling_membuang(): void
    {
        [$a] = $this->perusahaanBerisi();

        $b = Company::create(['name' => 'PT Contoh Kedua', 'demo' => true]);
        $adminB = User::factory()->create(['is_admin' => true, 'company_id' => $b->id]);
        User::factory()->create(['company_id' => $b->id]);
        DataContoh::muat($b, $adminB);

        $kursusA = Course::withoutGlobalScopes()->where('demo_company_id', $a->id)->count();
        $kursusB = Course::withoutGlobalScopes()->where('demo_company_id', $b->id)->count();

        $this->assertGreaterThan(0, $kursusA);
        $this->assertGreaterThan(0, $kursusB);

        DataContoh::buang($b);

        $this->assertSame($kursusA,
            Course::withoutGlobalScopes()->where('demo_company_id', $a->id)->count(),
            'Membuang data contoh satu perusahaan ikut membuang pustaka contoh perusahaan lain.');

        $this->assertSame(0,
            Course::withoutGlobalScopes()->where('demo_company_id', $b->id)->count());
    }

    /** Modul LMS benar-benar terisi, bukan sekadar terdaftar. */
    public function test_lms_terisi_utuh_sampai_sertifikat(): void
    {
        [$c] = $this->perusahaanBerisi();

        $kursus = Course::withoutGlobalScopes()->whereNotNull('demo_company_id')->first();

        $this->assertNotNull($kursus, 'Modul LMS tetap kosong sesudah data contoh dimuat.');

        $this->assertGreaterThan(0, $kursus->modules()->count(), 'Kursus contoh tanpa modul.');
        $this->assertDatabaseCount('quiz_questions', 3);
        $this->assertGreaterThan(0, \App\Models\Enrollment::withoutGlobalScopes()->count());

        /* Sertifikat hanya bagi yang lulus. Bila semuanya bersertifikat,
           aturan "sertifikat menyusul kelulusan" tidak pernah terbukti. */
        $sert = \App\Models\Certificate::withoutGlobalScopes()->count();
        $daftar = \App\Models\Enrollment::withoutGlobalScopes()->count();

        $this->assertGreaterThan(0, $sert);
        $this->assertLessThan($daftar, $sert,
            'Semua peserta bersertifikat, termasuk yang belum lulus.');
    }

    /* ═══════════ lewat antarmuka ═══════════ */

    public function test_admin_dapat_mengosongkan_lewat_pusat_kendali(): void
    {
        [$c, $admin] = $this->perusahaanBerisi();

        $this->actingAs($admin)
            ->delete(route('admin.system.demo.hapus', $c))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DataContoh::hitungIsi($c));
    }

    public function test_bukan_admin_tidak_dapat_mengosongkan(): void
    {
        [$c] = $this->perusahaanBerisi();

        $biasa = User::factory()->create(['company_id' => $c->id, 'email_verified_at' => now()]);

        $this->actingAs($biasa)
            ->delete(route('admin.system.demo.hapus', $c))
            ->assertForbidden();

        $this->assertGreaterThan(0, DataContoh::hitungIsi($c),
            'Pengguna biasa mengosongkan seluruh data perusahaan.');
    }

    public function test_perusahaan_sungguhan_ditolak_lewat_antarmuka(): void
    {
        [$c, ] = $this->perusahaanBerisi(demo: false);

        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)
            ->delete(route('admin.system.demo.hapus', $c))
            ->assertSessionHasErrors('demo');
    }

    /**
     * Data contoh BERTAHAN sampai tombolnya ditekan.
     *
     * Inti dari pemisahan ini: memeriksa alur dari input sampai laporan
     * menuntut datanya tetap ada di antara langkah-langkahnya. Membuka
     * halaman, mencetak laporan, berpindah modul — tak satu pun boleh
     * mengurangi isinya.
     */
    public function test_data_contoh_bertahan_sampai_dibuang(): void
    {
        [$c, $admin] = $this->perusahaanBerisi();

        $awal = DataContoh::hitungIsi($c);

        foreach (['dashboard', 'air.index', 'air.cetak', 'geoteknik.index', 'dokumen.index'] as $rute) {
            $this->actingAs($admin)->get(route($rute));
        }

        $this->assertSame($awal, DataContoh::hitungIsi($c),
            'Isi data contoh berubah hanya karena halamannya dibuka.');

        $this->actingAs($admin)->delete(route('admin.system.demo.hapus', $c));

        $this->assertSame(0, DataContoh::hitungIsi($c));
    }
}
