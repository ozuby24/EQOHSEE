<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Batas laju pada pintu masuk.
 *
 * LoginRequest sudah punya penjagaannya sendiri — lima percobaan per
 * pasangan (email, IP) — dan penjagaan itu benar untuk satu jenis
 * serangan saja: menebak sandi SATU akun dari SATU tempat.
 *
 * Yang diuji di sini justru yang lolos darinya, sebab itulah yang dipakai
 * menyerang aplikasi perusahaan dan itulah yang tidak pernah menyalakan
 * peringatan apa pun.
 */
class BatasLajuMasukTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('masuk');
    }

    /**
     * Penyemprotan sandi: satu sandi, banyak akun, satu tempat.
     *
     * Tiap pasangan (email, IP) hanya terpakai SATU dari lima jatahnya,
     * sehingga penjagaan lama tidak pernah menyala sama sekali — padahal
     * pada seratus akun, satu sandi lemah hampir pasti ada.
     */
    public function test_percobaan_ke_banyak_akun_dari_satu_alamat_dihentikan(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $jawaban = $this->post('/login', [
                'email'    => "orang$i@contoh.id",
                'password' => 'Password123',
            ]);

            if ($jawaban->status() === 429) {
                $this->assertLessThan(40, $i,
                    'Batas per-alamat baru menyala setelah 40 akun dicoba.');
                return;
            }
        }

        $this->fail('Empat puluh akun dapat dicoba dari satu alamat tanpa pernah dihentikan.');
    }

    /**
     * Dan sebaliknya: satu akun, banyak alamat.
     *
     * Kunci penjagaan lama memuat IP, jadi penyerang yang berpindah-pindah
     * alamat terhadap satu akun tidak pernah kehabisan jatah. Batas
     * per-akun tanpa IP menutup sisi itu.
     */
    public function test_percobaan_ke_satu_akun_dari_banyak_alamat_dihentikan(): void
    {
        User::factory()->create(['email' => 'korban@contoh.id']);

        for ($i = 0; $i < 30; $i++) {
            $jawaban = $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.$i"])
                ->post('/login', [
                    'email'    => 'korban@contoh.id',
                    'password' => 'tebakan-'.$i,
                ]);

            if ($jawaban->status() === 429) {
                $this->assertLessThan(30, $i);
                return;
            }
        }

        $this->fail('Satu akun dapat ditebak dari tiga puluh alamat tanpa pernah dihentikan.');
    }

    /** Pendaftaran tidak boleh tanpa batas — tiap akun memicu satu surel. */
    public function test_pendaftaran_beruntun_dihentikan(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $jawaban = $this->post('/register', [
                'name'                  => "Orang $i",
                'email'                 => "baru$i@contoh.id",
                'password'              => 'RahasiaSekali123',
                'password_confirmation' => 'RahasiaSekali123',
            ]);

            if ($jawaban->status() === 429) {
                $this->assertLessThan(12, $i);
                return;
            }
        }

        $this->fail('Dua belas akun dapat dibuat berturut-turut tanpa pernah dihentikan.');
    }

    /**
     * Permintaan setel ulang dibatasi per ALAMAT SUREL, bukan hanya per IP.
     *
     * Yang dirugikan bukan server melainkan orang yang kotak masuknya
     * dibanjiri, dan pembanjirnya dapat berpindah IP sesuka hati.
     */
    public function test_permintaan_setel_ulang_ke_satu_surel_dibatasi(): void
    {
        User::factory()->create(['email' => 'dibanjiri@contoh.id']);

        for ($i = 0; $i < 12; $i++) {
            $jawaban = $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.$i"])
                ->post('/forgot-password', ['email' => 'dibanjiri@contoh.id']);

            if ($jawaban->status() === 429) {
                $this->assertLessThan(12, $i);
                return;
            }
        }

        $this->fail('Satu alamat surel dapat dibanjiri dari dua belas alamat IP berbeda.');
    }

    /** Pengguna yang wajar tidak ikut terkena. */
    public function test_satu_percobaan_salah_tidak_mengunci_siapa_pun(): void
    {
        User::factory()->create(['email' => 'pekerja@contoh.id']);

        $this->post('/login', ['email' => 'pekerja@contoh.id', 'password' => 'salah'])
             ->assertStatus(302);

        $this->post('/login', ['email' => 'pekerja@contoh.id', 'password' => 'salah'])
             ->assertStatus(302);
    }
}
