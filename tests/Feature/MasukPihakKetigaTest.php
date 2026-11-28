<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\DuaFaktorTantanganController as Tantangan;
use App\Http\Middleware\{BahasaTamu, PastikanAktif};
use App\Models\{Company, User};
use App\Support\{DuaFaktor, Hazard, MasukPihakKetiga, Totp};
use App\Support\Sso\PenyediaMicrosoft;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as PermintaanGuzzle;
use GuzzleHttp\Psr7\Response as JawabanGuzzle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as PenggunaSosial;
use Mockery;
use Tests\TestCase;

/**
 * Masuk dan daftar dengan Google / Microsoft.
 *
 * Penyedia hanya membuktikan SIAPA orangnya. Surel yang sudah punya akun
 * masuk ke akun itu; surel yang belum punya diantar ke formulir Daftar
 * dengan surel terkunci, dan akunnya baru ada sesudah formulir itu
 * dikirim. Yang dijaga: tidak ada tombol tanpa alur yang terpasang,
 * tidak ada akun yang dibuat diam-diam, surel yang belum terbukti tidak
 * dipercaya (termasuk celah "nOAuth" Microsoft), identitas yang
 * terbukti tidak dapat ditukar dengan surel lain atau dipakai sesudah
 * lewat waktunya, dan akun nonaktif serta akun berdua faktor
 * diperlakukan persis seperti pada masuk bersandi.
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

    /**
     * Kembali dari penyedia dengan hasil tertentu (pengguna, atau galat).
     *
     * State "keadaan-uji" dititipkan seperti saat berangkat — di sesi, atau
     * hanya di kuki khusus ($lewatKuki: sesi yang kembali tanpa state, yang
     * terjadi di produksi). Socialite dipanggil stateless dengan alamat
     * kembali yang sama persis dengan saat berangkat.
     */
    private function kembali(PenggunaSosial|\Throwable $hasil, string $penyedia = 'google', ?string $alamat = null, bool $lewatKuki = false)
    {
        $alur = Mockery::mock(Provider::class);
        $alur->shouldReceive('stateless')->andReturnSelf();
        $alur->shouldReceive('redirectUrl')->with(MasukPihakKetiga::alamatKembali($penyedia))->andReturnSelf();
        $hasil instanceof \Throwable
            ? $alur->shouldReceive('user')->andThrow($hasil)
            : $alur->shouldReceive('user')->andReturn($hasil);

        Socialite::shouldReceive('driver')->with($penyedia)->andReturn($alur);

        $lewatKuki
            ? $this->withCookie(MasukPihakKetiga::KUKI_STATE, 'keadaan-uji')
            : $this->withSession([MasukPihakKetiga::KUNCI_STATE => 'keadaan-uji']);

        return $this->get(($alamat ?? "/masuk/{$penyedia}/kembali").'?code=kode-uji&state=keadaan-uji');
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

    /* ═══════════ state OAuth milik sendiri ═══════════ */

    public function test_berangkat_menitipkan_state_di_sesi_dan_kuki(): void
    {
        $this->pasang('google');

        $jawab = $this->get('/masuk/google')->assertRedirect();
        parse_str((string) parse_url($jawab->headers->get('Location'), PHP_URL_QUERY), $kueri);

        $this->assertSame(40, strlen($kueri['state'] ?? ''), 'Alamat ke Google tidak membawa state acak.');
        $jawab->assertSessionHas(MasukPihakKetiga::KUNCI_STATE, $kueri['state'])
            ->assertCookie(MasukPihakKetiga::KUKI_STATE, $kueri['state']);
    }

    /**
     * Kasus produksi: sesi kembali dari Google TANPA state (has_state=false,
     * has_cookie=true) dan setiap percobaan berakhir InvalidStateException.
     * Kuki state khusus cukup untuk membuktikan bahwa peramban yang sama
     * yang memulai alurnya.
     */
    public function test_state_dari_kuki_cukup_walau_sesinya_kosong(): void
    {
        $this->pasang('google');
        $u = $this->akun();

        $this->kembali($this->sosial('penjaga@tambang.co.id'), lewatKuki: true)
            ->assertRedirect('/dashboard')
            ->assertCookieExpired(MasukPihakKetiga::KUKI_STATE);

        $this->assertAuthenticatedAs($u);
    }

    public function test_state_yang_tidak_cocok_ditolak_tanpa_menghubungi_penyedia(): void
    {
        $this->pasang('google');
        $this->akun();
        Socialite::shouldReceive('driver')->never();

        $this->withSession([MasukPihakKetiga::KUNCI_STATE => 'state-lain'])
            ->get('/masuk/google/kembali?code=kode-uji&state=keadaan-uji')
            ->assertRedirect('/login')
            ->assertSessionHas('galat', fn ($p) => str_contains($p, 'habis atau terputus'));

        // Tanpa state di alamat, atau tanpa titipan sama sekali: sama.
        $this->withSession([MasukPihakKetiga::KUNCI_STATE => 'keadaan-uji'])
            ->get('/masuk/google/kembali?code=kode-uji')->assertRedirect('/login');
        $this->get('/masuk/google/kembali?code=kode-uji&state=keadaan-uji')->assertRedirect('/login');

        $this->assertGuest();
    }

    /** Alamat lazim panduan Socialite — yang terdaftar di konsol Google pemasangan ini. */
    public function test_alamat_auth_google_juga_dilayani(): void
    {
        $this->pasang('google');
        $u = $this->akun();

        $this->assertStringStartsWith('https://accounts.google.com/',
            $this->get('/auth/google')->assertRedirect()->headers->get('Location'));

        $this->kembali($this->sosial('penjaga@tambang.co.id'), alamat: '/auth/google/callback')
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($u);
    }

    /**
     * Alamat kembali selalu lengkap dari APP_URL — bukan dari host
     * permintaan, yang di balik proksi bisa terbaca http:// atau datang
     * lewat www. (Error 400: redirect_uri_mismatch).
     */
    public function test_alamat_kembali_lengkap_dari_app_url(): void
    {
        $this->pasang('google');
        config(['app.url' => 'https://eqohsee.example']);

        $this->assertStringContainsString('redirect_uri='.urlencode('https://eqohsee.example/masuk/google/kembali'),
            $this->get('/masuk/google')->headers->get('Location'));

        // GOOGLE_REDIRECT_URI yang relatif ikut dilengkapi; yang lengkap dipakai apa adanya.
        config(['services.google.redirect' => '/auth/google/callback']);
        $this->assertSame('https://eqohsee.example/auth/google/callback', MasukPihakKetiga::alamatKembali('google'));
        config(['services.google.redirect' => 'https://eqohsee.example/masuk/google/kembali']);
        $this->assertSame('https://eqohsee.example/masuk/google/kembali', MasukPihakKetiga::alamatKembali('google'));
    }

    public function test_berangkat_dari_www_dipindah_ke_host_kanonik(): void
    {
        $this->pasang('google');
        config(['app.url' => 'https://eqohsee.example']);

        $this->get('https://www.eqohsee.example/masuk/google')
            ->assertRedirect('https://eqohsee.example/masuk/google');

        // Host lain sama sekali (APP_URL salah isi) tidak dipindahkan.
        $this->assertStringStartsWith('https://accounts.google.com/',
            $this->get('https://situs-lain.example/masuk/google')->headers->get('Location'));
    }

    public function test_galat_pemasangan_disebut_dengan_kodenya(): void
    {
        $this->pasang('google');

        $galat = new ClientException(
            'Client error: `POST https://www.googleapis.com/oauth2/v4/token` resulted in a `401 Unauthorized` response',
            new PermintaanGuzzle('POST', 'https://www.googleapis.com/oauth2/v4/token'),
            new JawabanGuzzle(401, [], '{"error":"invalid_client","error_description":"Unauthorized"}'),
        );

        $this->kembali($galat)
            ->assertRedirect('/login')
            ->assertSessionHas('galat', fn ($p) => str_contains($p, 'belum disetel dengan benar') && str_contains($p, 'invalid_client'));

        $this->assertGuest();
    }

    public function test_perintah_periksa_menyebut_alamat_yang_harus_didaftarkan(): void
    {
        $this->pasang('google');
        config(['app.url' => 'https://eqohsee.example', 'session.same_site' => 'lax', 'session.domain' => null]);

        $this->artisan('eqohsee:periksa-sso')
            ->expectsOutputToContain('https://eqohsee.example/masuk/google/kembali')
            ->expectsOutputToContain('https://eqohsee.example/auth/google/callback')
            ->assertExitCode(0);

        config(['session.same_site' => 'strict']);
        $this->artisan('eqohsee:periksa-sso')->expectsOutputToContain('SameSite strict')->assertExitCode(1);
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

    public function test_surel_tanpa_akun_diantar_ke_formulir_daftar_tanpa_membuat_akun(): void
    {
        $this->pasang('google');
        $sebelum = User::count();

        $this->kembali($this->sosial('Orang.Baru@contoh.test'))->assertRedirect('/register');

        $this->assertGuest();
        $this->assertSame($sebelum, User::count(), 'Masuk dengan Google membuat akun baru diam-diam.');

        // Formulir Daftar menerima identitasnya: surel yang terbukti,
        // dan tanda bahwa orangnya datang dari tombol Masuk.
        $this->get('/register')->assertOk()->assertInertia(fn ($p) => $p
            ->component('Auth/Register')
            ->where('identitas.email', 'orang.baru@contoh.test')
            ->where('identitas.penyedia', 'google')
            ->where('identitas.namaPenyedia', 'Google')
            ->where('identitas.nama', 'Penjaga Tambang')
            ->where('identitas.dariMasuk', true));
    }

    /* ═══════════ daftar dengan Google / Microsoft ═══════════ */

    public function test_tombol_daftar_hanya_untuk_penyedia_yang_dipasang(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn ($p) => $p
            ->where('sso.google', false)
            ->where('sso.microsoft', false)
            ->where('identitas', null));
        $this->get('/daftar/google')->assertNotFound();
        $this->get('/daftar/microsoft')->assertNotFound();
        $this->get('/daftar/facebook')->assertNotFound();

        $this->pasang('microsoft');
        $this->get('/register')->assertInertia(fn ($p) => $p
            ->where('sso.google', false)
            ->where('sso.microsoft', true));

        $halaman = file_get_contents(resource_path('js/Pages/Auth/Register.vue'));
        $this->assertMatchesRegularExpression('#<a v-if="sso\.google" href="/daftar/google"#', $halaman);
        $this->assertMatchesRegularExpression('#<a v-if="sso\.microsoft" href="/daftar/microsoft"#', $halaman);
    }

    public function test_daftar_dialihkan_ke_penyedia_lewat_alamat_kembali_yang_sama(): void
    {
        $this->pasang('google');

        $tujuan = $this->get('/daftar/google')
            ->assertRedirect()
            ->assertSessionHas(MasukPihakKetiga::KUNCI_NIAT, 'daftar')
            ->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/', $tujuan);
        $this->assertStringContainsString('prompt=select_account', $tujuan);
        $this->assertStringContainsString(urlencode(url('/masuk/google/kembali')), $tujuan,
            'Alur daftar memakai alamat kembali lain — yang didaftarkan di konsol penyedia hanya /masuk/google/kembali.');

        // Tombol Masuk sesudahnya membuang niat daftar yang tertinggal.
        $this->get('/masuk/google')->assertRedirect()->assertSessionMissing(MasukPihakKetiga::KUNCI_NIAT);
    }

    public function test_menyelesaikan_daftar_membuat_akun_terverifikasi_tanpa_sandi(): void
    {
        $this->pasang('google');
        $demo = Company::create(['name' => 'PT Contoh Demo', 'demo' => true]);

        $this->withSession([MasukPihakKetiga::KUNCI_NIAT => 'daftar']);
        $this->kembali($this->sosial('pendaftar@contoh.test'))->assertRedirect('/register');
        $this->assertGuest();

        $this->post('/register', [
            'name'      => 'Pendaftar Lewat Google',
            'email'     => 'penyusup@lain.test',   // diabaikan: surelnya milik penyedia
            'position'  => Hazard::JABATAN[0],
            'lewat_sso' => true,
        ])->assertSessionHasNoErrors()
          ->assertRedirect('/dashboard')
          ->assertSessionMissing(MasukPihakKetiga::KUNCI_DAFTAR);

        $u = User::where('email', 'pendaftar@contoh.test')->first();
        $this->assertNotNull($u, 'Akun tidak dibuat dengan surel dari penyedia.');
        $this->assertFalse(User::where('email', 'penyusup@lain.test')->exists(),
            'Surel dari formulir menggantikan surel yang dibuktikan penyedia.');
        $this->assertAuthenticatedAs($u);
        $this->assertNotNull($u->email_verified_at, 'Surel yang sudah dibuktikan penyedia masih diminta kode verifikasi.');
        $this->assertSame($demo->id, (int) $u->company_id);
        $this->assertSame('Pendaftar Lewat Google', $u->name);
        $this->assertFalse(Hash::check('', $u->password), 'Akun dari penyedia bersandi kosong.');
    }

    public function test_daftar_tetap_hanya_ke_perusahaan_demo(): void
    {
        $this->pasang('google');
        $demo = Company::create(['name' => 'PT Contoh Demo', 'demo' => true]);
        $nyata = Company::create(['name' => 'PT Pelanggan Sungguhan', 'demo' => false]);

        $this->kembali($this->sosial('pendaftar@contoh.test'));

        // Perusahaan yang dikirim permintaan buatan diabaikan.
        $this->post('/register', [
            'name' => 'Pendaftar', 'position' => Hazard::JABATAN[0], 'company_id' => $nyata->id, 'lewat_sso' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($demo->id, (int) User::where('email', 'pendaftar@contoh.test')->value('company_id'));
    }

    public function test_identitas_yang_lewat_waktu_tidak_dapat_dipakai_mendaftar(): void
    {
        $this->pasang('google');
        Company::create(['name' => 'PT Contoh Demo', 'demo' => true]);
        $this->kembali($this->sosial('terlambat@contoh.test'))->assertRedirect('/register');

        $this->travel(MasukPihakKetiga::MENIT_DAFTAR + 1)->minutes();

        $this->get('/register')->assertInertia(fn ($p) => $p->where('identitas', null));
        $this->post('/register', [
            'name' => 'Terlambat', 'position' => Hazard::JABATAN[0], 'lewat_sso' => true,
        ])->assertSessionHasErrors(['email' => 'Waktu untuk menyelesaikan pendaftaran dengan Google atau Microsoft sudah habis. Ulangi lewat tombolnya.']);

        $this->assertFalse(User::where('email', 'terlambat@contoh.test')->exists());
        $this->assertGuest();
    }

    public function test_daftar_dengan_akun_yang_sudah_ada_langsung_masuk(): void
    {
        $this->pasang('google');
        $u = $this->akun();

        $this->withSession([MasukPihakKetiga::KUNCI_NIAT => 'daftar']);
        $this->kembali($this->sosial('penjaga@tambang.co.id'))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('sukses', 'Akun EQOHSEE untuk penjaga@tambang.co.id sudah ada, jadi Anda langsung masuk.');

        $this->assertAuthenticatedAs($u);
        $this->assertSame(1, User::where('email', 'penjaga@tambang.co.id')->count());
    }

    public function test_batal_di_penyedia_saat_daftar_kembali_ke_halaman_daftar(): void
    {
        $this->pasang('microsoft');
        Socialite::shouldReceive('driver')->never();

        $this->withSession([MasukPihakKetiga::KUNCI_NIAT => 'daftar'])
            ->get('/masuk/microsoft/kembali?error=access_denied&state=x')
            ->assertRedirect('/register')
            ->assertSessionHas('galat', 'Daftar dengan Microsoft dibatalkan.')
            ->assertSessionMissing(MasukPihakKetiga::KUNCI_NIAT);
    }

    public function test_pakai_akun_lain_membuang_identitas(): void
    {
        $this->pasang('google');
        $this->kembali($this->sosial('salah.akun@contoh.test'))->assertRedirect('/register');

        $this->post('/daftar/sso/batal')
            ->assertRedirect('/register')
            ->assertSessionMissing(MasukPihakKetiga::KUNCI_DAFTAR);
        $this->get('/register')->assertInertia(fn ($p) => $p->where('identitas', null));

        // Dari catatan "belum ada akun": kembali ke halaman masuk.
        $this->kembali($this->sosial('salah.akun@contoh.test'));
        $this->post('/daftar/sso/batal', ['ke' => 'masuk'])->assertRedirect('/login');
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
