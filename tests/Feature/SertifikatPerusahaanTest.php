<?php

namespace Tests\Feature;

use App\Models\{Certificate, Company, Course, Enrollment, PostTrainingEvaluation, Signatory, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sertifikat harus melekat pada perusahaan penerimanya.
 *
 * Tiga hal yang dijaga di sini, dan ketiganya pernah salah sekaligus di
 * dalam satu method penerbitan:
 *
 * 1. Penanda tangan diambil dari perusahaan penerima. Sebelumnya
 *    `Signatory::where('is_active', true)->first()` — penanda tangan
 *    PERTAMA di seluruh sistem, siapa pun perusahaannya. Akibatnya
 *    sertifikat PT A dapat terbit membawa nama DAN gambar tanda tangan
 *    pejabat PT B. Tidak ada galat; berkasnya terlihat sah sempurna.
 *
 * 2. Nomor urut dihitung per perusahaan. Sebelumnya penghitungnya
 *    menghitung SELURUH sertifikat pada tahun berjalan, sehingga nomor
 *    PT A melompat setiap kali PT B menerbitkan satu.
 *
 * 3. Nomor diturunkan dari yang tertinggi, bukan dari jumlah baris.
 *    Dengan `count()+1`, satu sertifikat yang dihapus membuat nomor
 *    berikutnya MENGULANG nomor yang sudah pernah terbit — dua lembar
 *    bernomor sama, keduanya lolos verifikasi barcode.
 */
class SertifikatPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-15 09:00:00');

        $this->a = Company::create(['name' => 'PT Alpha', 'doc_no_prefix' => 'ALP']);
        $this->b = Company::create(['name' => 'PT Beta',  'doc_no_prefix' => 'BET']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kursus(): Course
    {
        return Course::create([
            'title' => 'Dasar Keselamatan Tambang',
            'slug' => 'dasar-keselamatan-'.uniqid(),
            'require_evaluation' => false,
        ]);
    }

    private function peserta(Company $c, Course $k): User
    {
        $u = User::factory()->create(['company_id' => $c->id]);

        Enrollment::create([
            'user_id' => $u->id, 'course_id' => $k->id,
            'status' => 'finished', 'progress' => 90,
        ]);

        return $u;
    }

    private function terbitkan(User $u, Course $k): Certificate
    {
        $this->actingAs($u)->post(route('certificates.store', $k))->assertRedirect();

        return Certificate::withoutGlobalScopes()
            ->where('user_id', $u->id)->where('course_id', $k->id)->firstOrFail();
    }

    /* ---------- penanda tangan ---------- */

    public function test_sertifikat_memakai_penanda_tangan_perusahaannya_sendiri(): void
    {
        // Penanda tangan PT Beta sengaja dibuat LEBIH DULU, sehingga
        // dialah yang terambil oleh `->first()` versi lama.
        Signatory::create(['company_id' => $this->b->id, 'name' => 'Pejabat Beta',
                           'title' => 'KTT PT Beta', 'is_active' => true]);
        $ttdA = Signatory::create(['company_id' => $this->a->id, 'name' => 'Pejabat Alpha',
                                   'title' => 'KTT PT Alpha', 'is_active' => true]);

        $k = $this->kursus();
        $cert = $this->terbitkan($this->peserta($this->a, $k), $k);

        $this->assertSame($ttdA->id, $cert->signatory_id);
        $this->assertSame('Pejabat Alpha', $cert->signed_by_name);
    }

    public function test_penanda_tangan_milik_bersama_tetap_dapat_dipakai(): void
    {
        // company_id NULL berarti belum dimiliki perusahaan mana pun —
        // itu milik bersama, bukan milik pihak lain yang disembunyikan.
        $umum = Signatory::create(['company_id' => null, 'name' => 'Pejabat Pusat', 'is_active' => true]);

        $k = $this->kursus();
        $cert = $this->terbitkan($this->peserta($this->a, $k), $k);

        $this->assertSame($umum->id, $cert->signatory_id);
    }

    public function test_penanda_tangan_perusahaan_diutamakan_atas_yang_umum(): void
    {
        Signatory::create(['company_id' => null, 'name' => 'Pejabat Pusat', 'is_active' => true]);
        $ttdA = Signatory::create(['company_id' => $this->a->id, 'name' => 'Pejabat Alpha', 'is_active' => true]);

        $k = $this->kursus();
        $cert = $this->terbitkan($this->peserta($this->a, $k), $k);

        $this->assertSame($ttdA->id, $cert->signatory_id);
    }

    public function test_sertifikat_tidak_memakai_penanda_tangan_perusahaan_lain(): void
    {
        Signatory::create(['company_id' => $this->b->id, 'name' => 'Pejabat Beta', 'is_active' => true]);

        $k = $this->kursus();
        $cert = $this->terbitkan($this->peserta($this->a, $k), $k);

        // Lebih baik tanpa penanda tangan daripada memakai tanda tangan
        // pejabat perusahaan lain.
        $this->assertNull($cert->signatory_id);
        $this->assertNull($cert->signed_by_name);
    }

    public function test_penanda_tangan_perusahaan_lain_tidak_terlihat_di_daftar(): void
    {
        Signatory::create(['company_id' => $this->b->id, 'name' => 'Pejabat Beta', 'is_active' => true]);
        Signatory::create(['company_id' => $this->a->id, 'name' => 'Pejabat Alpha', 'is_active' => true]);

        $adminA = User::factory()->create(['company_id' => $this->a->id]);

        $terlihat = $this->actingAs($adminA)->getJson('/');   // konteks auth saja
        $nama = Signatory::all()->pluck('name')->all();

        $this->assertNotContains('Pejabat Beta', $nama);
        $this->assertContains('Pejabat Alpha', $nama);
    }

    /* ---------- penomoran ---------- */

    public function test_nomor_urut_dihitung_per_perusahaan(): void
    {
        $k1 = $this->kursus();
        $k2 = $this->kursus();

        // PT Beta menerbitkan dua lebih dulu.
        $this->terbitkan($this->peserta($this->b, $k1), $k1);
        $this->terbitkan($this->peserta($this->b, $k2), $k2);

        // Sertifikat PERTAMA PT Alpha harus tetap bernomor 0001.
        $cert = $this->terbitkan($this->peserta($this->a, $k1), $k1);

        $this->assertSame('ALP/SRT/2026/0001', $cert->certificate_number);
    }

    public function test_nomor_urut_naik_di_dalam_satu_perusahaan(): void
    {
        $k1 = $this->kursus();
        $k2 = $this->kursus();

        $satu = $this->terbitkan($this->peserta($this->a, $k1), $k1);
        $dua  = $this->terbitkan($this->peserta($this->a, $k2), $k2);

        $this->assertSame('ALP/SRT/2026/0001', $satu->certificate_number);
        $this->assertSame('ALP/SRT/2026/0002', $dua->certificate_number);
    }

    public function test_nomor_melanjutkan_yang_tertinggi_bukan_menghitung_baris(): void
    {
        // Inilah keadaan data yang sudah terlanjur ada: nomor-nomor lama
        // lahir dari penghitung global, sehingga satu perusahaan bisa
        // memegang nomor 0007 padahal barisnya baru dua. Dengan
        // count()+1 nomor berikutnya menjadi 0003 — mundur, lalu
        // MENGULANG 0007 beberapa penerbitan kemudian, dan dua lembar
        // bernomor sama sama-sama lolos verifikasi barcode.
        $k1 = $this->kursus();
        $k2 = $this->kursus();
        $k3 = $this->kursus();

        $this->terbitkan($this->peserta($this->a, $k1), $k1);
        $lama = $this->terbitkan($this->peserta($this->a, $k2), $k2);

        // Nomor peninggalan penghitung global.
        $lama->forceFill(['certificate_number' => 'ALP/SRT/2026/0007'])->save();

        $baru = $this->terbitkan($this->peserta($this->a, $k3), $k3);

        $this->assertSame('ALP/SRT/2026/0008', $baru->certificate_number);
    }

    public function test_nomor_tahun_lalu_tidak_menahan_urutan_tahun_ini(): void
    {
        $k1 = $this->kursus();
        $k2 = $this->kursus();

        $lalu = $this->terbitkan($this->peserta($this->a, $k1), $k1);
        $lalu->forceFill([
            'certificate_number' => 'ALP/SRT/2025/0042',
            'issued_at' => '2025-11-02 09:00:00',
        ])->save();

        $ini = $this->terbitkan($this->peserta($this->a, $k2), $k2);

        $this->assertSame('ALP/SRT/2026/0001', $ini->certificate_number);
    }

    public function test_nomor_memakai_prefiks_perusahaan_penerima(): void
    {
        $k = $this->kursus();
        $cert = $this->terbitkan($this->peserta($this->b, $k), $k);

        $this->assertStringStartsWith('BET/', $cert->certificate_number);
    }

    /* ---------- kepemilikan sertifikat ---------- */

    public function test_sertifikat_melekat_pada_perusahaan_penerimanya(): void
    {
        $k = $this->kursus();
        $cert = $this->terbitkan($this->peserta($this->b, $k), $k);

        $this->assertSame($this->b->id, $cert->company_id);
    }

    public function test_pengguna_tanpa_perusahaan_tidak_dititipkan_ke_perusahaan_mana_pun(): void
    {
        // Sebelumnya diambilkan Company::orderBy('id')->first() —
        // sertifikatnya diam-diam tercatat milik perusahaan ber-id
        // terkecil, lengkap dengan kop dan logonya.
        $k = $this->kursus();
        $u = User::factory()->create(['company_id' => null]);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $k->id,
                            'status' => 'finished', 'progress' => 90]);

        $cert = $this->terbitkan($u, $k);

        $this->assertNull($cert->company_id);
        $this->assertStringStartsWith('EQ/', $cert->certificate_number);
    }
}
