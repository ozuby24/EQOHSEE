<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Jalan keluar ketika surel tidak sampai.
 *
 * Pengantar surel 'log' tidak mengirim apa pun; kodenya hanya ditulis ke
 * berkas log. Halaman verifikasi tetap berkata "kami mengirim kode" dan
 * pendaftarnya menunggu surel yang tidak akan pernah datang — persis
 * yang terjadi di server sungguhan.
 */
class VerifikasiSuratTest extends TestCase
{
    use RefreshDatabase;

    private function belum(): User
    {
        $u = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($u);

        return $u;
    }

    public function test_halaman_mengaku_bila_surel_tidak_benar_benar_dikirim(): void
    {
        config(['mail.default' => 'log']);
        $this->belum();

        $p = $this->get(route('verification.notice'))->assertOk()->viewData('page')['props'];

        $this->assertFalse($p['suratAktif']);
    }

    public function test_halaman_menjanjikan_surel_hanya_bila_pengantarnya_sungguhan(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $this->belum();

        $p = $this->get(route('verification.notice'))->assertOk()->viewData('page')['props'];

        $this->assertTrue($p['suratAktif']);
    }

    public function test_perintah_menandai_surel_terverifikasi(): void
    {
        $u = User::factory()->create(['email_verified_at' => null]);

        $this->artisan('eqohsee:verifikasi', ['email' => $u->email])
             ->assertSuccessful();

        $this->assertTrue($u->fresh()->hasVerifiedEmail());
    }

    public function test_perintah_dengan_kode_menerbitkan_kode_baru_tanpa_memverifikasi(): void
    {
        // Kode tersimpan tercacah dan tidak bisa dibaca ulang, bahkan oleh
        // administrator. Yang bisa dilakukan hanya menerbitkan yang baru.
        $u = User::factory()->create(['email_verified_at' => null]);

        $this->artisan('eqohsee:verifikasi', ['email' => $u->email, '--kode' => true])
             ->assertSuccessful();

        $u->refresh();

        $this->assertFalse($u->hasVerifiedEmail());
        $this->assertNotNull($u->kode_verifikasi);
        $this->assertFalse($u->kodeVerifikasiKedaluwarsa());
    }

    public function test_perintah_menolak_surel_yang_tidak_terdaftar(): void
    {
        $this->artisan('eqohsee:verifikasi', ['email' => 'tidak@ada.test'])
             ->assertFailed();
    }

    public function test_smtp_yang_gagal_tidak_menjatuhkan_halaman_verifikasi(): void
    {
        // Halaman inilah satu-satunya jalan keluar orang yang belum
        // terverifikasi. Ia harus tetap terbuka justru ketika pengiriman
        // surelnya bermasalah — bukan berubah jadi 500.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1',
                'mail.mailers.smtp.port' => 1]);

        $this->belum();

        $p = $this->get(route('verification.notice'))->assertOk()->viewData('page')['props'];

        $this->assertFalse($p['suratAktif'],
            'Halaman tidak boleh menjanjikan surel yang gagal dikirim.');
    }
}
