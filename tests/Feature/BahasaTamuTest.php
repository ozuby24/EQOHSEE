<?php

namespace Tests\Feature;

use App\Http\Middleware\BahasaTamu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pilihan bahasa halaman tamu (Indonesia / Inggris).
 *
 * Pil bahasa di halaman masuk menulis kuki `eq_bahasa` dari peramban.
 * Di jalur tamu — masuk, daftar, lupa & atur ulang sandi, dua faktor,
 * verifikasi surel, masuk dengan Google/Microsoft — pesan server ikut
 * bahasa itu, supaya halaman berbahasa Inggris tidak menjawab dengan
 * galat berbahasa Indonesia. Aplikasi di balik login tetap Indonesia.
 */
class BahasaTamuTest extends TestCase
{
    use RefreshDatabase;

    public function test_kuki_inggris_membuat_halaman_tamu_berbahasa_inggris(): void
    {
        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'en')->get('/login')
            ->assertOk()->assertSee('<html lang="en"', false);

        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'en')->get('/forgot-password')
            ->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_tanpa_kuki_atau_nilai_asing_tetap_indonesia(): void
    {
        $this->get('/login')->assertOk()->assertSee('<html lang="id"', false);

        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'fr')->get('/login')
            ->assertOk()->assertSee('<html lang="id"', false);
    }

    public function test_pesan_validasi_masuk_mengikuti_bahasa(): void
    {
        $salah = ['email' => 'tidak.ada@contoh.test', 'password' => 'sandi yang salah sekali'];

        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'en')->post('/login', $salah)
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'id')->post('/login', $salah)
            ->assertSessionHasErrors(['email' => trans('auth.failed', [], 'id')]);
    }

    /** Nama kolom dalam pesan validasi, bukan nama kolom mentah. */
    public function test_nama_kolom_pesan_validasi_inggris(): void
    {
        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'en')->post('/register', [])
            ->assertSessionHasErrors([
                'name'     => 'The full name field is required.',
                'position' => 'The job title field is required.',
            ]);
    }

    public function test_aplikasi_di_balik_login_tetap_berbahasa_indonesia(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'en')->get('/dashboard')
            ->assertOk()->assertSee('<html lang="id"', false);
    }

    /**
     * Kukinya ditulis JavaScript di peramban, jadi tidak boleh ikut
     * dienkripsi server: kuki terenkripsi yang dikirim mentah dibuang
     * EncryptCookies, dan pilihan bahasanya hilang tanpa jejak.
     */
    public function test_kuki_bahasa_dikecualikan_dari_enkripsi(): void
    {
        $this->assertStringContainsString('BahasaTamu::KUKI', file_get_contents(base_path('bootstrap/app.php')));
        $this->assertStringContainsString("KUKI_BAHASA = 'eq_bahasa'", file_get_contents(resource_path('js/tamu/bahasa.ts')));
        $this->assertSame('eq_bahasa', BahasaTamu::KUKI);
    }
}
