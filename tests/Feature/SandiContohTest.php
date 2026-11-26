<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SandiContoh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sandi akun contoh tertulis di kode sumber yang terbuka untuk umum.
 *
 * `demo:pasang` membuat akun ADMINISTRATOR demo@contoh.test bersandi
 * itu. Di server yang dapat dibuka dari internet, akun itu adalah pintu
 * administrator yang kuncinya diumumkan — maka di luar local/testing,
 * sandinya ditolak di pintu masuk untuk surel apa pun.
 */
class SandiContohTest extends TestCase
{
    use RefreshDatabase;

    private function akunContoh(array $atribut = []): User
    {
        return User::factory()->create($atribut + [
            'email'    => 'demo@contoh.test',
            'password' => SandiContoh::BAWAAN,
            'is_admin' => true,
            'active'   => true,
        ]);
    }

    private function diServer(string $lingkungan = 'production'): void
    {
        $this->app['env'] = $lingkungan;

        /* Pemeriksaan CSRF hanya dilewati Laravel bila lingkungannya
           `testing`. Yang diuji di sini pintu masuknya, bukan tokennya. */
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class);
    }

    public function test_admin_contoh_tidak_dapat_masuk_di_produksi(): void
    {
        $this->akunContoh();
        $this->diServer();

        $this->post('/login', ['email' => 'demo@contoh.test', 'password' => SandiContoh::BAWAAN])
            ->assertSessionHasErrors(['email' => SandiContoh::PESAN]);

        $this->assertGuest();
    }

    /**
     * Server yang APP_ENV-nya dikosongkan tetap server yang terbuka.
     * Pemasangan sungguhan pernah berjalan persis begitu.
     */
    public function test_lingkungan_kosong_dan_staging_juga_menolak(): void
    {
        $this->akunContoh();

        foreach (['', 'staging'] as $lingkungan) {
            $this->diServer($lingkungan);

            $this->post('/login', ['email' => 'demo@contoh.test', 'password' => SandiContoh::BAWAAN])
                ->assertSessionHasErrors(['email' => SandiContoh::PESAN]);

            $this->assertGuest();
        }
    }

    /**
     * Ditolak dengan pesan yang sama untuk surel yang tidak terdaftar.
     * Pesan yang berbeda akan memberi tahu penebak surel mana yang ada.
     */
    public function test_penolakan_tidak_membocorkan_surel_yang_terdaftar(): void
    {
        $this->diServer();

        $this->post('/login', ['email' => 'tidak.ada@contoh.test', 'password' => SandiContoh::BAWAAN])
            ->assertSessionHasErrors(['email' => SandiContoh::PESAN]);
    }

    /** Jalur dua faktor memeriksa sandinya sendiri — harus ikut menolak. */
    public function test_akun_berdua_faktor_juga_ditolak(): void
    {
        $this->akunContoh([
            'dua_faktor_rahasia'  => 'JBSWY3DPEHPK3PXP',
            'dua_faktor_aktif_at' => now(),
        ]);
        $this->diServer();

        $this->post('/login', ['email' => 'demo@contoh.test', 'password' => SandiContoh::BAWAAN])
            ->assertSessionHasErrors(['email' => SandiContoh::PESAN]);

        $this->assertGuest();
    }

    /** Sesudah administrator mengatur sandi baru, akun yang sama dapat dipakai lagi. */
    public function test_akun_yang_sama_dapat_masuk_dengan_sandi_baru(): void
    {
        $this->akunContoh(['password' => 'sandi-baru-yang-panjang']);
        $this->diServer();

        $this->post('/login', ['email' => 'demo@contoh.test', 'password' => 'sandi-baru-yang-panjang'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    /** Di mesin pengembang dan saat uji, akun contoh tetap dapat dicoba. */
    public function test_di_lokal_sandi_contoh_tetap_berlaku(): void
    {
        $this->akunContoh();
        $this->diServer('local');

        $this->post('/login', ['email' => 'demo@contoh.test', 'password' => SandiContoh::BAWAAN])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
    }

    /** Satu sumber saja: perintah demo dan pemuat data contoh memakai konstanta ini. */
    public function test_tidak_ada_sandi_contoh_yang_diketik_ulang_di_kode(): void
    {
        $langgar = [];

        $berkas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($berkas as $f) {
            if ($f->getExtension() !== 'php' || str_ends_with($f->getPathname(), 'SandiContoh.php')) continue;

            if (str_contains(file_get_contents($f->getPathname()), "'".SandiContoh::BAWAAN."'")) {
                $langgar[] = str_replace(base_path().'/', '', $f->getPathname());
            }
        }

        $this->assertSame([], $langgar, 'Sandi contoh diketik ulang; pakai SandiContoh::BAWAAN.');
    }
}
