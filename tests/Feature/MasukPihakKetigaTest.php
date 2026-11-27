<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\DuaFaktorTantanganController as Tantangan;
use App\Http\Middleware\{BahasaTamu, PastikanAktif};
use App\Models\{Company, User};
use App\Support\{DuaFaktor, MasukPihakKetiga, Totp};
use App\Support\Sso\PenyediaMicrosoft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as PenggunaSosial;
use Mockery;
use Tests\TestCase;

/**
 * Masuk dengan Google / Microsoft.
 *
 * Penyedia hanya membuktikan SIAPA orangnya; akunnya tetap akun EQOHSEE
 * yang sudah ada. Yang dijaga: tidak ada tombol tanpa alur yang
 * terpasang, tidak ada akun yang dibuat diam-diam, surel yang belum
 * terbukti tidak dipercaya (termasuk celah "nOAuth" Microsoft), dan akun
 * nonaktif serta akun berdua faktor diperlakukan persis seperti pada
 * masuk bersandi.
 */
class MasukPihakKetigaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Lepas dari isi .env pengembang: setiap uji memasang sendiri.
        config([
            'services.google.client_id' => '', 'services.google.client_secret' => '',
            'services.microsoft.client_id' => '', 'services.microsoft.client_secret' => '',
        ]);
    }

    private function pasang(string $penyedia): void
    {
        config(["services.{$penyedia}.client_id" => 'id-uji', "services.{$penyedia}.client_secret" => 'rahasia-uji']);
    }

    private function akun(array $isi = []): User
    {
        $c = Company::create(['name' => 'PT Uji Masuk Pihak Ketiga', 'code' => 'UMP']);

        return User::factory()->create($isi + [
            'email'             => 'penjaga@tambang.co.id',
            'company_id'        => $c->id,
            'active'            => true,
            'email_verified_at' => now(),
        ]);
    }

    private function sosial(string $surel, bool $terverifikasi = true): PenggunaSosial
    {
        return (new PenggunaSosial)
            ->setRaw(['email' => $surel, 'email_verified' => $terverifikasi])
            ->map(['id' => 'sosial-1', 'name' => 'Penjaga Tambang', 'email' => $surel]);
    }

    /** Kembali dari penyedia dengan hasil tertentu (pengguna, atau galat). */
    private function kembali(PenggunaSosial|\Throwable $hasil, string $penyedia = 'google')
    {
        $alur = Mockery::mock(Provider::class);
        $hasil instanceof \Throwable
            ? $alur->shouldReceive('user')->andThrow($hasil)
            : $alur->shouldReceive('user')->andReturn($hasil);

        Socialite::shouldReceive('driver')->with($penyedia)->andReturn($alur);

        return $this->get("/masuk/{$penyedia}/kembali?code=kode-uji&state=keadaan-uji");
    }

    /* ═══════════ tombol dan rute ═══════════ */

    public function test_tanpa_kredensial_tidak_ada_tombol_dan_rutenya_tertutup(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p
            ->where('sso.google', false)
            ->where('sso.microsoft', false));

        $this->get('/masuk/google')->assertNotFound();
        $this->get('/masuk/microsoft')->assertNotFound();
        $this->get('/masuk/google/kembali?code=x')->assertNotFound();
        $this->get('/masuk/facebook')->assertNotFound();
    }

    public function test_tombol_hanya_untuk_penyedia_yang_dipasang(): void
    {
        $this->pasang('google');

        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p
            ->where('sso.google', true)
            ->where('sso.microsoft', false));

        // Setiap tombol digambar hanya bila penyedianya dipasang.
        $halaman = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));
        $this->assertMatchesRegularExpression('#<a v-if="sso\.google" href="/masuk/google"#', $halaman);
        $this->assertMatchesRegularExpression('#<a v-if="sso\.microsoft" href="/masuk/microsoft"#', $halaman);
        $this->assertSame(['google' => true, 'microsoft' => false], MasukPihakKetiga::tersedia());
    }

    public function test_dialihkan_ke_google_dengan_pilihan_akun(): void
    {
        $this->pasang('google');

        $tujuan = $this->get('/masuk/google')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $tujuan);
        $this->assertStringContainsString('prompt=select_account', $tujuan,
            'Di komputer bersama, akun Google yang terakhir dipakai belum tentu milik orang yang sekarang.');
        $this->assertStringContainsString(urlencode(url('/masuk/google/kembali')), $tujuan);
    }

    public function test_dialihkan_ke_penyewa_microsoft_yang_disetel(): void
    {
        $this->pasang('microsoft');
        config(['services.microsoft.tenant' => 'tambang.co.id']);

        $tujuan = $this->get('/masuk/microsoft')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://login.microsoftonline.com/tambang.co.id/oauth2/v2.0/authorize', $tujuan);
        $this->assertStringContainsString('prompt=select_account', $tujuan);
    }

    /* ═══════════ kembali dari penyedia ═══════════ */

    public function test_akun_terdaftar_masuk(): void
    {
        $this->pasang('google');
        $u = $this->akun();

        // Surel dari penyedia dibandingkan tanpa membedakan huruf besar.
        $this->kembali($this->sosial('Penjaga@Tambang.co.id'))->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($u);
    }

    public function test_surel_tanpa_akun_ditolak_dan_tidak_membuat_akun(): void
    {
        $this->pasang('google');
        $sebelum = User::count();

        $this->kembali($this->sosial('orang.baru@contoh.test'))
            ->assertRedirect('/login')
            ->assertSessionHas('galat', fn ($p) => str_contains($p, 'Belum ada akun EQOHSEE untuk orang.baru@contoh.test'));

        $this->assertGuest();
        $this->assertSame($sebelum, User::count(), 'Masuk dengan Google membuat akun baru diam-diam.');
    }

    public function test_surel_yang_belum_diverifikasi_penyedia_ditolak(): void
    {
        $this->pasang('google');
        $this->akun();

        $this->kembali($this->sosial('penjaga@tambang.co.id', terverifikasi: false))
            ->assertRedirect('/login')
            ->assertSessionHas('galat', fn ($p) => str_contains($p, 'belum terverifikasi'));

        $this->assertGuest();
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->pasang('google');
        $this->akun(['active' => false]);

        $this->kembali($this->sosial('penjaga@tambang.co.id'))
            ->assertRedirect('/login')
            ->assertSessionHas('galat', PastikanAktif::PESAN);

        $this->assertGuest();
    }

    public function test_akun_berdua_faktor_tetap_diminta_kodenya(): void
    {
        $this->pasang('google');
        $u = $this->akun();
        DuaFaktor::sahkan($u, Totp::kode(DuaFaktor::mulai($u)));
        $this->assertTrue(DuaFaktor::menyala($u->fresh()));

        $this->kembali($this->sosial('penjaga@tambang.co.id'))
            ->assertRedirect(route('dua-faktor.tantangan', absolute: false))
            ->assertSessionHas(Tantangan::KUNCI, fn ($m) => $m['id'] === $u->id && $m['ingat'] === false);

        $this->assertGuest();
    }

    public function test_dibatalkan_di_halaman_penyedia(): void
    {
        $this->pasang('google');
        Socialite::shouldReceive('driver')->never();

        $this->get('/masuk/google/kembali?error=access_denied&state=x')
            ->assertRedirect('/login')
            ->assertSessionHas('galat', 'Masuk dengan Google dibatalkan.');
    }

    public function test_galat_penyedia_menjadi_pesan_bukan_halaman_galat(): void
    {
        $this->pasang('google');

        $this->kembali(new InvalidStateException)
            ->assertRedirect('/login')
            ->assertSessionHas('galat', fn ($p) => str_contains($p, 'Masuk dengan Google gagal'));

        $this->assertGuest();
    }

    public function test_pesannya_mengikuti_bahasa_halaman_tamu(): void
    {
        $this->pasang('google');

        $this->withUnencryptedCookie(BahasaTamu::KUKI, 'en')
            ->get('/masuk/google/kembali?error=access_denied')
            ->assertSessionHas('galat', 'Sign-in with Google was cancelled.');
    }

    public function test_akun_microsoft_terdaftar_masuk(): void
    {
        $this->pasang('microsoft');
        $u = $this->akun();

        $this->kembali($this->sosial('penjaga@tambang.co.id'), 'microsoft')->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($u);
    }

    /* ═══════════ penyedia Microsoft ═══════════ */

    private function microsoft(): PenyediaMicrosoft
    {
        return new PenyediaMicrosoft(Request::create('/'), 'id-uji', 'rahasia-uji', 'https://eqohsee.test/masuk/microsoft/kembali');
    }

    private function petakan(array $graf): ?string
    {
        $m = new \ReflectionMethod(PenyediaMicrosoft::class, 'mapUserToObject');

        return $m->invoke($this->microsoft(), $graf)->getEmail();
    }

    /**
     * Celah "nOAuth": atribut `mail` di Entra dapat diisi bebas oleh admin
     * penyewa mana pun. Surelnya hanya dipercaya bila domainnya termasuk
     * domain yang sudah dibuktikan kepemilikannya oleh penyewa itu.
     */
    public function test_microsoft_hanya_mempercayai_surel_berdomain_milik_penyewa(): void
    {
        $this->assertSame('penjaga@tambang.co.id', $this->petakan([
            'id' => '1', 'mail' => 'Penjaga@Tambang.co.id', 'domain_terverifikasi' => ['tambang.co.id'],
        ]));

        $this->assertNull($this->petakan([
            'id' => '2', 'mail' => 'direktur@tambang.co.id', 'domain_terverifikasi' => ['penyerang.onmicrosoft.com'],
        ]), 'Surel berdomain milik orang lain dipercaya — celah nOAuth.');

        $this->assertNull($this->petakan(['id' => '3', 'mail' => 'pribadi@outlook.com']),
            'Akun tanpa domain terverifikasi (akun pribadi) dipercaya.');

        // Tanpa `mail`, nama utama (UPN) dipakai — dengan aturan yang sama.
        $this->assertSame('operator@tambang.co.id', $this->petakan([
            'id' => '4', 'userPrincipalName' => 'Operator@tambang.co.id', 'domain_terverifikasi' => ['tambang.co.id'],
        ]));
    }

    public function test_penyewa_microsoft_yang_tidak_sah_jatuh_ke_organizations(): void
    {
        $alamat = new \ReflectionMethod(PenyediaMicrosoft::class, 'getAuthUrl');

        config(['services.microsoft.tenant' => 'evil.example/../common?x=']);
        $this->assertStringStartsWith('https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize',
            $alamat->invoke($this->microsoft(), 'keadaan'));

        config(['services.microsoft.tenant' => '0f9e8d7c-1b2a-4c3d-9e8f-7a6b5c4d3e2f']);
        $this->assertStringStartsWith('https://login.microsoftonline.com/0f9e8d7c-1b2a-4c3d-9e8f-7a6b5c4d3e2f/oauth2/v2.0/authorize',
            $alamat->invoke($this->microsoft(), 'keadaan'));
    }
}
