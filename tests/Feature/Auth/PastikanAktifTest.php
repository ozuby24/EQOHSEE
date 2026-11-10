<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\PastikanAktif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Akun yang dinonaktifkan kehilangan akses pada permintaan berikutnya
 * (porting Aurorasys) — bukan hanya ketika masuk.
 */
class PastikanAktifTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_baru_aktif_secara_bawaan_dan_tidak_terganggu(): void
    {
        $u = User::factory()->create();
        $this->assertTrue((bool) $u->fresh()->active);

        $r = $this->actingAs($u)->get("/dashboard"); $this->assertSame(200, $r->status(), (string) $r->headers->get("Location"));
        $this->assertAuthenticatedAs($u);
    }

    public function test_kunjungan_inertia_akun_nonaktif_dialihkan_ke_masuk_dengan_pesan(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['active' => false])->save();

        $this->actingAs($u)
            ->get('/dashboard', ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('galat', PastikanAktif::PESAN);
        $this->assertGuest();

        // Pesannya tampil di halaman masuk (bilah kilat GuestLayout).
        $this->get(route('login'))->assertInertia(
            fn ($p) => $p->where('kilat.galat', PastikanAktif::PESAN),
        );
    }

    public function test_permintaan_json_mode_lapangan_mendapat_401(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['active' => false])->save();

        $this->actingAs($u)->getJson('/dashboard')
            ->assertStatus(401)
            ->assertJson(['message' => PastikanAktif::PESAN]);
        $this->assertGuest();
    }

    public function test_cookie_ingat_saya_tidak_lagi_memasukkan_akun_nonaktif(): void
    {
        $u = User::factory()->create(['remember_token' => 'tokeningat1234567890']);
        $nama = Auth::guard('web')->getRecallerName();
        $nilai = $u->id.'|tokeningat1234567890|'.$u->getAuthPassword();

        // Cookie recaller yang sah memasukkan akun aktif tanpa sesi.
        $this->withCookie($nama, $nilai)->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($u);

        Auth::guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $u->forceFill(['active' => false, 'remember_token' => 'tokeningat1234567890'])->save();

        $this->withCookie($nama, $nilai)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_masuk_dengan_sandi_benar_dan_akun_nonaktif_ditolak(): void
    {
        $mati = User::factory()->create(['active' => false]);
        $this->post('/login', ['email' => $mati->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => PastikanAktif::PESAN]);
        $this->assertGuest();

        // Sandi salah tetap dijawab "kredensial salah": status akun tidak bocor.
        $this->post('/login', ['email' => $mati->email, 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }
}
