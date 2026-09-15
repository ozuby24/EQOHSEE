<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Notification};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifikasi Cloudflare pada dua pintu tamu selain halaman masuk:
 * pendaftaran dan permintaan tautan lupa sandi.
 *
 * Halaman masuk dijaga terpisah di TurnstileMasukTest. Yang dijaga di
 * sini adalah dua pintu yang jauh lebih mudah terlewat justru karena
 * penyalahgunaannya tidak terlihat seperti serangan:
 *
 *  - Pendaftaran tidak menebak apa pun. Ia MEMBUAT — ratusan akun
 *    sekaligus, masing-masing menarik satu surel verifikasi keluar dari
 *    server ini.
 *  - Lupa sandi bahkan tidak membuat akun. Ia mengirim surel ke alamat
 *    yang diketik pengirim permintaannya, dengan nama perusahaan ini
 *    pada bagian pengirimnya. Yang menanggung akibatnya bukan akun
 *    siapa pun, melainkan reputasi pengirim surel domain ini.
 *
 * Seperti pada halaman masuk, penjagaan terpentingnya bukan "Turnstile
 * bekerja" melainkan "memasangnya tidak mengunci siapa pun di luar".
 */
class TurnstileGerbangTamuTest extends TestCase
{
    use RefreshDatabase;

    /** Kunci uji resmi Cloudflare; keduanya tidak pernah dipakai sungguhan. */
    private const SITUS   = '1x00000000000000000000AA';
    private const RAHASIA = '1x0000000000000000000000000000000AA';

    private function nyalakan(): void
    {
        config([
            'turnstile.situs'      => self::SITUS,
            'turnstile.rahasia'    => self::RAHASIA,
            'turnstile.saat_gagal' => 'lolos',
        ]);
    }

    /**
     * Balasan sukses Cloudflare, lengkap dengan penanda pintunya.
     *
     * Ditulis lengkap dan bukan cuma ['success' => true], karena
     * balasan yang sungguhan selalu menyebut action dan hostname —
     * dan tiruan yang lebih ramah daripada aslinya menguji jalur yang
     * tidak pernah dilewati di produksi.
     *
     * @return array<string, mixed>
     */
    private function jawabSukses(string $tindakan): array
    {
        return [
            'success'      => true,
            'action'       => Turnstile::TINDAKAN[$tindakan],
            'hostname'     => 'eqohsee.id',
            'challenge_ts' => now()->toIso8601String(),
        ];
    }

    private function matikan(): void
    {
        config(['turnstile.situs' => null, 'turnstile.rahasia' => null]);
    }

    /** @return array<string, mixed> */
    private function isianDaftar(array $tambahan = []): array
    {
        return $tambahan + [
            'name'                  => 'Pekerja Baru',
            'email'                 => 'pekerja.baru@contoh.test',
            'password'              => 'rahasia-panjang-123',
            'password_confirmation' => 'rahasia-panjang-123',
            'position'              => \App\Support\Hazard::JABATAN[0],
        ];
    }

    /* ═══════════ mati saat kuncinya belum dipasang ═══════════ */

    /**
     * Penjagaan terpenting di berkas ini, dan alasannya sama seperti pada
     * halaman masuk: selama kuncinya belum ada di server, kedua pintu ini
     * HARUS bekerja persis seperti sebelum fitur ini ditambahkan.
     */
    public function test_tanpa_kunci_pendaftaran_berjalan_seperti_biasa(): void
    {
        $this->matikan();
        Http::fake();

        $this->post('/register', $this->isianDaftar())
            ->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_tanpa_kunci_lupa_sandi_berjalan_seperti_biasa(): void
    {
        $this->matikan();
        Notification::fake();
        Http::fake();

        $pengguna = User::factory()->create();

        $this->post('/forgot-password', ['email' => $pengguna->email]);

        Notification::assertSentTo($pengguna, ResetPassword::class);
        Http::assertNothingSent();
    }

    /* ═══════════ menyala ═══════════ */

    public function test_dengan_token_sah_pendaftaran_tetap_berhasil(): void
    {
        $this->nyalakan();
        Http::fake([config('turnstile.url') => Http::response($this->jawabSukses('daftar'))]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token-dari-widget',
        ]))->assertRedirect(route('verification.notice', absolute: false));

        $this->assertAuthenticated();
    }

    /**
     * Tokennya tidak ikut masuk ke User::create — dan yang menahannya
     * adalah daftar putih $fillable, bukan pembuangan di controller.
     *
     * `cf-turnstile-response` lolos dari validate() sebagai kolom yang
     * tervalidasi, lalu ikut ke User::create, dan tabel users tidak punya
     * lajur bernama begitu. Yang menjatuhkannya di tengah jalan adalah
     * $fillable, yang menyebut kolom yang boleh diisi dan bukan yang
     * dilarang.
     *
     * Maka yang dijaga di sini dua hal sekaligus: pendaftarannya memang
     * berhasil, DAN daftar putih itu masih berupa daftar putih. Mengganti
     * $fillable dengan $guarded = [] adalah perubahan yang terlihat
     * seperti kemudahan dan tidak menjatuhkan satu pun uji lain, tetapi
     * mematahkan pendaftaran dengan galat basis data — pada satu-satunya
     * pintu yang dipakai orang yang belum punya siapa pun untuk dimintai
     * tolong.
     */
    public function test_token_tidak_ikut_tersimpan_sebagai_kolom_pengguna(): void
    {
        $this->nyalakan();
        Http::fake([config('turnstile.url') => Http::response($this->jawabSukses('daftar'))]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token-dari-widget',
        ]))->assertSessionHasNoErrors();

        $pengguna = User::where('email', 'pekerja.baru@contoh.test')->firstOrFail();

        $this->assertArrayNotHasKey(Turnstile::KOLOM, $pengguna->getAttributes());

        $terisi = (new User)->getFillable();

        $this->assertNotEmpty($terisi,
            'User memakai $guarded, bukan $fillable. Token Turnstile akan ikut ke '
            .'perintah insert dan menjatuhkan seluruh pendaftaran.');

        $this->assertNotContains(Turnstile::KOLOM, $terisi);
    }

    public function test_pendaftaran_tanpa_token_ditolak_dan_tidak_membuat_akun(): void
    {
        $this->nyalakan();
        Http::fake();

        $this->post('/register', $this->isianDaftar())
            ->assertSessionHasErrors(Turnstile::KOLOM);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'pekerja.baru@contoh.test']);
    }

    /**
     * Inilah gunanya kotak ini pada pintu lupa sandi: surelnya TIDAK
     * terkirim. Penolakan yang tetap mengirim surel hanya memindahkan
     * penyalahgunaannya, tidak menghentikannya.
     */
    public function test_lupa_sandi_tanpa_token_tidak_mengirim_surel(): void
    {
        $this->nyalakan();
        Notification::fake();
        Http::fake();

        $pengguna = User::factory()->create();

        $this->post('/forgot-password', ['email' => $pengguna->email])
            ->assertSessionHasErrors(Turnstile::KOLOM);

        Notification::assertNothingSent();
    }

    public function test_lupa_sandi_dengan_token_sah_tetap_mengirim_surel(): void
    {
        $this->nyalakan();
        Notification::fake();
        Http::fake([config('turnstile.url') => Http::response($this->jawabSukses('lupa-sandi'))]);

        $pengguna = User::factory()->create();

        $this->post('/forgot-password', [
            'email'                 => $pengguna->email,
            'cf-turnstile-response' => 'token-dari-widget',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($pengguna, ResetPassword::class);
    }

    public function test_token_yang_ditolak_cloudflare_tidak_meloloskan(): void
    {
        $this->nyalakan();
        Notification::fake();
        Http::fake([config('turnstile.url') => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-response'],
        ])]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token-palsu',
        ]))->assertSessionHasErrors(Turnstile::KOLOM);

        $this->assertGuest();

        $pengguna = User::factory()->create();

        $this->post('/forgot-password', [
            'email'                 => $pengguna->email,
            'cf-turnstile-response' => 'token-palsu',
        ])->assertSessionHasErrors(Turnstile::KOLOM);

        Notification::assertNothingSent();
    }

    /* ═══════════ yang sampai ke peramban ═══════════ */

    /**
     * @return array<string, array{0: string}>
     */
    public static function halamanTamu(): array
    {
        return [
            'masuk'      => ['/login'],
            'daftar'     => ['/register'],
            'lupa sandi' => ['/forgot-password'],
        ];
    }

    /**
     * Rahasianya tidak pernah ikut ke peramban, pada ketiga halamannya.
     *
     * Diuji per halaman dan bukan sekali saja: yang bocor nanti adalah
     * halaman yang ditambahkan belakangan oleh orang yang menyalin
     * pengiriman propnya dari halaman lain dan keliru menyalin
     * rahasianya, bukan kunci situsnya. Keduanya sama-sama string
     * pendek, dan yang salah tetap menggambar kotak yang tampak normal.
     *
     */
    #[DataProvider('halamanTamu')]
    public function test_rahasia_tidak_pernah_sampai_ke_peramban(string $alamat): void
    {
        $this->nyalakan();

        $isi = $this->get($alamat)->assertOk()->getContent();

        $this->assertStringNotContainsString(self::RAHASIA, $isi,
            "Rahasia Turnstile tergambar di {$alamat}.");

        $this->assertStringContainsString(self::SITUS, $isi,
            "Kunci situs tidak dikirim ke {$alamat}, sehingga kotak verifikasinya "
            .'tidak akan digambar — dan setiap kiriman dari halaman itu akan ditolak.');
    }

    /**
     * Dicari kunci situsnya sendiri, bukan pola prop-nya.
     *
     * Prop Inertia diserialkan sebagai JSON dengan tanda kutip biasa,
     * sehingga pola ber-&quot; yang tampak masuk akal tidak pernah ada di
     * halamannya — dan uji yang mencarinya lulus tanpa memeriksa apa
     * pun, pada halaman yang membocorkan kuncinya sekalipun. Kunci
     * situsnya sendiri hanya muncul kalau ia memang dikirim.
     *
     * @see test_rahasia_tidak_pernah_sampai_ke_peramban — pasangannya,
     *      yang membuktikan bahwa kunci itu MEMANG muncul saat menyala,
     *      sehingga ketiadaannya di sini berarti sesuatu.
     */
    #[DataProvider('halamanTamu')]
    public function test_kunci_tidak_dikirim_ketika_fiturnya_mati(string $alamat): void
    {
        $this->matikan();

        $isi = $this->get($alamat)->assertOk()->getContent();

        $this->assertStringNotContainsString(self::SITUS, $isi,
            "Kunci situs Turnstile dikirim ke {$alamat} padahal fiturnya mati.");

        $this->assertStringNotContainsString('"turnstile":"', $isi,
            "Prop turnstile berisi nilai di {$alamat} padahal fiturnya mati.");
    }

    /* ═══════════ token sah, pintu yang salah ═══════════ */

    /**
     * Token dari pintu lain ditolak, meski Cloudflare menjawab success.
     *
     * Inilah yang ditutup penanda tindakan. Halaman masuk terbuka untuk
     * siapa saja, jadi token sah dapat dipanen dari sana dengan peramban
     * sungguhan — satu per satu, gratis — lalu dipakai pada pintu daftar
     * yang sedang dibanjiri skrip. Tanpa pemeriksaan ini, verifikasinya
     * meloloskannya: tokennya memang sah, hanya sah untuk pintu lain.
     */
    public function test_token_dari_pintu_lain_ditolak(): void
    {
        $this->nyalakan();

        /* Cloudflare menjawab success, tetapi menyebut pintu tempat
           token itu sebenarnya terbit. */
        Http::fake([config('turnstile.url') => Http::response([
            'success'  => true,
            'action'   => Turnstile::TINDAKAN['masuk'],
            'hostname' => 'eqohsee.id',
        ])]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token-dipanen-dari-halaman-masuk',
        ]))->assertSessionHasErrors(Turnstile::KOLOM);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'pekerja.baru@contoh.test']);
    }

    public function test_setiap_pintu_menerima_tokennya_sendiri(): void
    {
        $this->nyalakan();
        Notification::fake();

        foreach ([
            'daftar'     => fn () => $this->post('/register', $this->isianDaftar([
                Turnstile::KOLOM => 'token',
            ])),
            'lupa-sandi' => fn () => $this->post('/forgot-password', [
                'email'          => User::factory()->create()->email,
                Turnstile::KOLOM => 'token',
            ]),
        ] as $tindakan => $kirim) {
            Http::fake([config('turnstile.url') => Http::response([
                'success'  => true,
                'action'   => Turnstile::TINDAKAN[$tindakan],
                'hostname' => 'eqohsee.id',
            ])]);

            $kirim()->assertSessionHasNoErrors();
        }
    }

    /* ═══════════ inang yang menerbitkan token ═══════════ */

    /**
     * Daftar inang KOSONG berarti tidak diperiksa.
     *
     * Bawaannya, dan yang paling penting dijaga: daftar yang tidak diisi
     * tidak boleh berubah menjadi daftar kosong yang menolak semuanya.
     */
    public function test_inang_kosong_tidak_memeriksa_apa_pun(): void
    {
        $this->nyalakan();
        config(['turnstile.inang' => null]);

        Http::fake([config('turnstile.url') => Http::response([
            'success'  => true,
            'action'   => Turnstile::TINDAKAN['daftar'],
            'hostname' => 'inang-yang-tidak-pernah-didaftarkan.test',
        ])]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token',
        ]))->assertSessionHasNoErrors();
    }

    public function test_inang_di_luar_daftar_ditolak(): void
    {
        $this->nyalakan();
        config(['turnstile.inang' => 'eqohsee.id, www.eqohsee.id']);

        Http::fake([config('turnstile.url') => Http::response([
            'success'  => true,
            'action'   => Turnstile::TINDAKAN['daftar'],
            'hostname' => 'eqohsee.id.penyerang.test',
        ])]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token',
        ]))->assertSessionHasErrors(Turnstile::KOLOM);
    }

    /**
     * Spasi di sekitar koma tidak boleh mengunci siapa pun.
     *
     * "eqohsee.id, www.eqohsee.id" adalah cara orang menulis daftar.
     * Dibaca mentah, entri keduanya menjadi " www.eqohsee.id" berikut
     * spasinya, dan tidak akan pernah cocok dengan apa pun.
     */
    public function test_spasi_di_daftar_inang_tidak_dihitung(): void
    {
        $this->nyalakan();
        config(['turnstile.inang' => ' eqohsee.id ,  www.eqohsee.id ']);

        $this->assertSame(['eqohsee.id', 'www.eqohsee.id'], Turnstile::inang());

        Http::fake([config('turnstile.url') => Http::response([
            'success'  => true,
            'action'   => Turnstile::TINDAKAN['daftar'],
            'hostname' => 'www.eqohsee.id',
        ])]);

        $this->post('/register', $this->isianDaftar([
            'cf-turnstile-response' => 'token',
        ]))->assertSessionHasNoErrors();
    }

    /* ═══════════ penjagaan statis ═══════════ */

    /**
     * Ketiga pintu tamu memakai aturan yang SAMA, dari satu tempat.
     *
     * Bukan karena menyalinnya salah hari ini, melainkan karena salinan
     * berubah sendiri seiring waktu: satu formulir diberi `nullable`
     * "sementara" saat mengejar tenggat, lalu tetap begitu. Perbedaan itu
     * tidak menimbulkan galat apa pun — hanya satu pintu yang penjaganya
     * sudah lama pulang, dan tidak ada yang tahu pintu yang mana.
     */
    public function test_setiap_pintu_tamu_memakai_aturan_yang_sama(): void
    {
        $pintu = [
            'app/Http/Requests/Auth/LoginRequest.php',
            'app/Http/Controllers/Auth/RegisteredUserController.php',
            'app/Http/Controllers/Auth/PasswordResetLinkController.php',
        ];

        foreach ($pintu as $berkas) {
            $isi = file_get_contents(base_path($berkas));

            $this->assertStringContainsString('Turnstile::KOLOM => Turnstile::aturan(Turnstile::TINDAKAN[', $isi,
                "{$berkas} tidak memasang verifikasi Turnstile lewat Turnstile::aturan(). "
                .'Pintu tamu tanpa kotak verifikasi adalah pintu yang penjaganya tidak ada.');
        }
    }

    /**
     * Halaman Vue-nya benar-benar menggambar kotaknya.
     *
     * Aturan di server tanpa widget di halaman menghasilkan bentuk
     * kegagalan yang paling buruk dari semuanya: formulirnya terlihat
     * normal, terisi benar, dan ditolak setiap kali dengan pesan yang
     * menyebut kolom yang tidak ada di layar.
     */
    public function test_setiap_halaman_tamu_menggambar_kotaknya(): void
    {
        $halaman = ['Login.vue', 'Register.vue', 'LupaSandi.vue'];

        foreach ($halaman as $berkas) {
            $isi = file_get_contents(resource_path("js/Pages/Auth/{$berkas}"));

            $this->assertStringContainsString('VerifikasiTurnstile', $isi,
                "{$berkas} tidak memuat komponen VerifikasiTurnstile, padahal "
                .'servernya menuntut token dari halaman ini.');

            $this->assertStringContainsString("form['cf-turnstile-response']", $isi,
                "{$berkas} tidak mengirimkan kolom cf-turnstile-response.");

            $this->assertStringContainsString(':tindakan=', $isi,
                "{$berkas} tidak meneruskan penanda tindakan ke widget-nya, sehingga "
                .'tokennya terbit tanpa penanda dan server akan menolak setiap kiriman '
                .'dari halaman ini.');
        }
    }

    /**
     * Penanda tindakan memenuhi batas yang ditetapkan Cloudflare.
     *
     * Paling panjang 32 huruf, hanya a-z A-Z 0-9 _ dan -. Nilai di luar
     * itu tidak ditolak saat widget digambar; ia hanya tidak ikut pada
     * tokennya, lalu setiap kiriman ditolak karena tindakannya tidak
     * cocok — dengan pesan yang tidak menyebut satu pun dari semua ini.
     */
    public function test_penanda_tindakan_sesuai_batas_cloudflare(): void
    {
        foreach (Turnstile::TINDAKAN as $kunci => $nilai) {
            $this->assertSame($kunci, $nilai,
                'Kunci dan nilai TINDAKAN berbeda; salah satunya pasti terlupa saat diubah.');

            $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]{1,32}$/', $nilai,
                "Penanda tindakan '{$nilai}' di luar batas yang diterima Cloudflare.");
        }
    }
}
