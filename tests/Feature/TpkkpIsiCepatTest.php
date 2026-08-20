<?php

namespace Tests\Feature;

use App\Models\{Company, TpkkpAssessment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Isi cepat seluruh kolom entitas, dan pagar yang membatasinya.
 *
 * Penilaian CAM atas dua belas mitra kerja sebelumnya diisi satu per
 * satu — dua belas klik untuk satu item, dikali ratusan item. Tombol
 * isi-cepat mengubahnya menjadi satu klik, lalu yang berbeda tinggal
 * disesuaikan.
 *
 * PAGARNYA ADA DI SERVER, bukan di tombolnya. Tombol yang disembunyikan
 * hanya menghilang dari layar; yang menentukan siapa boleh menulis
 * adalah pemeriksaan pada saveAssess. Tanpa uji ini, menyembunyikan
 * tombol terasa seperti sudah mengamankan — dan itu keliru: kiriman
 * dapat disusun tangan.
 */
class TpkkpIsiCepatTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Isi Cepat', 'doc_no_prefix' => 'UIC']);

        TpkkpAssessment::create([
            'company_id' => $this->c->id,
            'tahun'  => now()->year,
            'judul'  => 'Penilaian uji',
            'status' => 'draf',
        ]);
    }

    private function pengguna(bool $admin): User
    {
        return User::factory()->create([
            'is_admin' => $admin, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
    }

    /**
     * Bukan admin tidak dapat menulis nilai, sekalipun kirimannya
     * disusun tangan.
     */
    #[Test]
    public function bukan_admin_ditolak_menyimpan_penilaian(): void
    {
        $this->actingAs($this->pengguna(false));

        $this->post('/tpkkp/penilaian?tahun='.now()->year, [
            'metode' => 'TD',
            'n'      => ['1.1.1' => ['PT Uji Isi Cepat' => 4]],
        ])->assertForbidden();
    }

    /** Admin dapat menulis banyak entitas sekaligus — itulah isi cepat. */
    #[Test]
    public function admin_dapat_mengisi_banyak_entitas_sekaligus(): void
    {
        $this->actingAs($this->pengguna(true));

        $kirim = [];
        foreach (['PT Satu', 'PT Dua', 'PT Tiga'] as $e) $kirim[$e] = 4;

        $this->post('/tpkkp/penilaian?tahun='.now()->year, [
            'metode' => 'TD',
            'n'      => ['1.1.1' => $kirim],
        ])->assertRedirect();

        $a = TpkkpAssessment::withoutGlobalScopes()->first();

        /* Bentuk tersimpannya ['v' => …, 'e' => [entitas => nilai]] —
           nilai per entitas ada di bawah 'e', bukan di akarnya. */
        $tersimpan = $a->scores['TD']['1.1.1']['e'] ?? null;

        $this->assertIsArray($tersimpan, 'Nilai per entitas tidak tersimpan sebagai larik.');

        foreach (['PT Satu', 'PT Dua', 'PT Tiga'] as $e) {
            $this->assertSame(4, (int) ($tersimpan[$e] ?? 0),
                "Kolom \"{$e}\" tidak ikut terisi — isi cepat tidak menulis seluruh entitas.");
        }
    }

    /**
     * Baris isi cepat hanya digambar bagi yang boleh menyunting.
     *
     * Bukan pengaman — pengamannya di server — melainkan kejujuran
     * layar: tombol yang terlihat tetapi selalu ditolak membuat orang
     * menyangka dirinya salah.
     */
    #[Test]
    public function baris_isi_cepat_bergantung_pada_izin_sunting(): void
    {
        $isi = file_get_contents(resource_path('js/Pages/Tpkkp/Penilaian.vue'));

        $this->assertStringContainsString('v-if="bisaSunting && kunciSel.length > 1"', $isi,
            'Baris isi cepat tidak lagi bergantung pada izin sunting — '
            .'tombol yang selalu ditolak membuat orang menyangka dirinya salah.');
    }
}
