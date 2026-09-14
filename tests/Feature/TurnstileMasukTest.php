<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifikasi Cloudflare pada halaman masuk.
 *
 * Yang dijaga di sini bukan "Turnstile bekerja" — itu urusan Cloudflare —
 * melainkan bahwa memasangnya tidak pernah mengunci orang di luar
 * situsnya sendiri. Fitur yang berdiri tepat di jalan masuk punya satu
 * bentuk kegagalan yang jauh lebih mahal daripada semua bentuk lainnya:
 * tidak ada yang bisa masuk, termasuk yang seharusnya memperbaikinya.
 */
class TurnstileMasukTest extends TestCase
{
    use RefreshDatabase;

    /** Kunci uji resmi Cloudflare; keduanya tidak pernah dipakai sungguhan. */
    private const SITUS   = '1x00000000000000000000AA';
    private const RAHASIA = '1x0000000000000000000000000000000AA';

    private function pengguna(): User
    {
        $c = Company::create(['name' => 'PT Uji Turnstile', 'code' => 'UTS']);

        return User::factory()->create([
            'email'             => 'penjaga@contoh.test',
            'password'          => 'rahasia-panjang-123',
            'company_id'        => $c->id,
            'email_verified_at' => now(),
        ]);
    }

    private function nyalakan(string $saatGagal = 'lolos'): void
    {
        config([
            'turnstile.situs'      => self::SITUS,
            'turnstile.rahasia'    => self::RAHASIA,
            'turnstile.saat_gagal' => $saatGagal,
        ]);
    }

    /* ═══════════ mati saat kuncinya belum dipasang ═══════════ */

    /**
     * Inilah penjagaan terpenting di berkas ini.
     *
     * Selama kuncinya belum ada di server, halaman masuk HARUS bekerja
     * persis seperti sebelum fitur ini ditambahkan. Kalau tidak, deploy
     * pertama yang membawa fitur ini akan mengunci seluruh penggunanya
     * di luar — dan gejalanya bukan galat melainkan "sandi saya tidak
     * diterima lagi", yang akan dicari di tempat yang sama sekali salah.
     */
    public function test_tanpa_kunci_masuk_berjalan_seperti_biasa(): void
    {
        config(['turnstile.situs' => null, 'turnstile.rahasia' => null]);

        $this->assertFalse(Turnstile::aktif());

        Http::fake();

        $this->post('/login', [
            'email'    => $this->pengguna()->email,
            'password' => 'rahasia-panjang-123',
        ])->assertRedirect();

        $this->assertAuthenticated();

        Http::assertNothingSent();
    }

    /**
     * Satu kunci saja tidak menyalakan apa pun.
     *
     * Rahasia tanpa kunci situs adalah keadaan paling berbahaya: server
     * menuntut token dari kotak verifikasi yang tidak pernah digambar,
     * sehingga tidak ada satu pun percobaan masuk yang dapat berhasil.
     */
    public function test_satu_kunci_saja_tidak_menyalakannya(): void
    {
        config(['turnstile.situs' => self::SITUS, 'turnstile.rahasia' => null]);
        $this->assertFalse(Turnstile::aktif(), 'Kunci situs saja tidak boleh menyalakannya.');

        config(['turnstile.situs' => null, 'turnstile.rahasia' => self::RAHASIA]);
        $this->assertFalse(Turnstile::aktif(),
            'Rahasia tanpa kunci situs menuntut token dari kotak yang tidak pernah digambar — '
            .'seluruh percobaan masuk akan ditolak.');
    }

    /* ═══════════ menyala ═══════════ */

    public function test_dengan_token_sah_tetap_dapat_masuk(): void
    {
        $this->nyalakan();
        Http::fake([config('turnstile.url') => Http::response(['success' => true])]);

        $this->post('/login', [
            'email'                 => $this->pengguna()->email,
            'password'              => 'rahasia-panjang-123',
            'cf-turnstile-response' => 'token-dari-widget',
        ])->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->nyalakan();
        Http::fake();

        $this->post('/login', [
            'email'    => $this->pengguna()->email,
            'password' => 'rahasia-panjang-123',
        ])->assertSessionHasErrors(Turnstile::KOLOM);

        $this->assertGuest();
    }

    public function test_token_yang_ditolak_cloudflare_tidak_meloloskan(): void
    {
        $this->nyalakan();
        Http::fake([config('turnstile.url') => Http::response([
            'success' => false, 'error-codes' => ['invalid-input-response'],
        ])]);

        $this->post('/login', [
            'email'                 => $this->pengguna()->email,
            'password'              => 'rahasia-panjang-123',
            'cf-turnstile-response' => 'token-palsu',
        ])->assertSessionHasErrors(Turnstile::KOLOM);

        $this->assertGuest();
    }

    /**
     * Token kosong tidak diantar ke Cloudflare.
     *
     * Ia pasti ditolak, dan mengantarnya berarti kiriman kosong — yang
     * justru paling murah dibuat penyerang — membangkitkan satu
     * permintaan keluar dari server kita untuk tiap satunya.
     */
    public function test_token_kosong_tidak_memanggil_cloudflare(): void
    {
        $this->nyalakan();
        Http::fake();

        $this->assertFalse(Turnstile::sah('', '127.0.0.1'));
        $this->assertFalse(Turnstile::sah(null, '127.0.0.1'));

        Http::assertNothingSent();
    }

    /* ═══════════ ketika Cloudflare tidak dapat dihubungi ═══════════ */

    public function test_cloudflare_mati_dan_disetel_lolos_tetap_meneruskan(): void
    {
        $this->nyalakan('lolos');
        Http::fake(fn () => throw new \RuntimeException('jaringan putus'));

        $this->assertTrue(Turnstile::sah('token', '127.0.0.1'),
            'Gangguan di pihak Cloudflare tidak boleh mengunci seluruh orang di luar '
            .'ketika setelannya "lolos".');
    }

    public function test_cloudflare_mati_dan_disetel_tolak_menolak(): void
    {
        $this->nyalakan('tolak');
        Http::fake(fn () => throw new \RuntimeException('jaringan putus'));

        $this->assertFalse(Turnstile::sah('token', '127.0.0.1'));
    }

    public function test_balasan_galat_diperlakukan_sebagai_tidak_terhubung(): void
    {
        $this->nyalakan('tolak');
        Http::fake([config('turnstile.url') => Http::response('', 503)]);

        $this->assertFalse(Turnstile::sah('token', '127.0.0.1'));
    }

    /* ═══════════ yang sampai ke peramban ═══════════ */

    /**
     * Rahasianya TIDAK PERNAH ikut ke peramban.
     *
     * Kunci situs memang dirancang publik; rahasianya adalah satu-satunya
     * hal yang membuat jawaban widget dapat dipercaya. Bocor sekali, ia
     * membuat seluruh verifikasi ini tidak berarti apa-apa — dan
     * bocornya tidak menimbulkan gejala apa pun.
     */
    public function test_rahasia_tidak_pernah_sampai_ke_peramban(): void
    {
        $this->nyalakan();

        $isi = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::RAHASIA, $isi,
            'Rahasia Turnstile tergambar di halaman masuk.');

        $this->assertStringContainsString(self::SITUS, $isi,
            'Kunci situs tidak dikirim, sehingga kotak verifikasinya tidak akan digambar.');
    }

    public function test_kunci_tidak_dikirim_ketika_fiturnya_mati(): void
    {
        config(['turnstile.situs' => null, 'turnstile.rahasia' => null]);

        $isi = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('turnstile&quot;:&quot;', $isi);
    }

    /* ═══════════ CSP ═══════════ */

    /**
     * CSP dilonggarkan HANYA ketika fiturnya menyala.
     *
     * Dan ketiganya diperlukan bersama. Yang paling mudah terlewat
     * adalah frame-src: widget-nya sebuah iframe, dan tanpa direktif itu
     * ia jatuh ke default-src 'self' yang memblokirnya — menghasilkan
     * ruang kosong di halaman masuk, token yang tidak pernah terbit, dan
     * setiap percobaan masuk ditolak dengan alasan yang tidak menyebut
     * CSP sama sekali.
     */
    public function test_csp_membuka_cloudflare_hanya_saat_menyala(): void
    {
        $asal = (string) config('turnstile.asal');

        config(['turnstile.situs' => null, 'turnstile.rahasia' => null]);
        $mati = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString($asal, $mati,
            'CSP dilonggarkan untuk Cloudflare padahal fiturnya mati.');

        $this->nyalakan();
        $nyala = $this->get('/login')->headers->get('Content-Security-Policy');

        foreach (['script-src', 'frame-src', 'connect-src'] as $arahan) {
            $baris = collect(explode('; ', $nyala))->first(fn ($d) => str_starts_with($d, $arahan.' '));

            $this->assertNotNull($baris, "CSP kehilangan {$arahan}.");
            $this->assertStringContainsString($asal, $baris,
                "{$arahan} tidak mengizinkan {$asal}; widget-nya akan diblokir tanpa pesan apa pun.");
        }
    }
}
