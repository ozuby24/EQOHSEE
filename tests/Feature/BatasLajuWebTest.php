<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Atap lalu lintas web.
 *
 * Pembatas yang sudah ada menjaga pintu-pintu yang menerima rahasia —
 * masuk, daftar, setel ulang sandi. Yang dijaga di sini justru sisanya:
 * halaman biasa yang tidak menerima rahasia apa pun, tetapi masing-masing
 * menyusun kueri, membaca basis data, dan menggambar muatan Inertia.
 *
 * Diminta beribu kali per menit, halaman biasa menghabiskan proses
 * PHP-FPM tanpa satu pun percobaan masuk — dan seluruh pembatas pintu
 * tidak melihat apa-apa, sebab tidak satu pun pintu itu disentuh. Yang
 * terlihat dari luar bukan serangan melainkan situs yang mendadak lambat
 * lalu berhenti menjawab.
 */
class BatasLajuWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* Middleware throttle meng-hash kuncinya (md5 nama-pembatas +
           kunci), sehingga bak hitungannya tidak dapat disentuh dari
           luar dengan nama yang terbaca. Dimatikan lewat penyetel resmi
           kerangkanya sendiri — bukan ditebak bentuk hash-nya, yang akan
           diam-diam meleset begitu rumusnya berubah. */
        ThrottleRequests::shouldHashKeys(false);

        RateLimiter::clear('web:tamu:127.0.0.1');
    }

    protected function tearDown(): void
    {
        ThrottleRequests::shouldHashKeys(true);
        parent::tearDown();
    }

    /**
     * Urutan nyata grup web, dibaca dari kernel.
     *
     * Bukan dari router: ia baru disalin ke sana saat permintaan pertama
     * ditangani, sehingga di dalam uji daftarnya masih kosong dan tiap
     * penjagaan atas urutannya diam-diam lulus tanpa memeriksa apa pun.
     *
     * @return list<string>
     */
    private function grupWeb(): array
    {
        return app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];
    }

    public function test_pembatas_web_terdaftar(): void
    {
        $this->assertNotNull(RateLimiter::limiter('web'),
            'Pembatas "web" hilang — grup web merujuk throttle:web, dan '
            .'middleware yang merujuk pembatas tak terdaftar menolak SETIAP permintaan.');
    }

    /**
     * Grup web benar-benar memakainya.
     *
     * Pembatasnya dapat terdaftar rapi di AppServiceProvider tanpa satu
     * rute pun memanggilnya — dan itu tidak menimbulkan galat, hanya
     * perlindungan yang tidak pernah bekerja.
     */
    public function test_grup_web_memasang_pembatasnya(): void
    {
        $urut = $this->grupWeb();

        $this->assertContains(ThrottleRequests::class.':web', $urut,
            'Grup web tidak lagi memasang throttle:web.');
    }

    /**
     * Atapnya berdiri DI DEPAN pemeriksa token, dan urutan itu berbobot.
     *
     * VerifyCsrfToken menolak POST tanpa token dengan 419 dan menghentikan
     * permintaannya di situ juga — sehingga middleware RUTE, termasuk
     * throttle:masuk pada POST /login, tidak pernah melihat satu pun
     * tebakan tanpa token. Penolakan 419 itu sendiri tidak gratis: tiap
     * satunya sudah menyalakan kerangka, mendekripsi kuki, dan membuka
     * sesi.
     *
     * Artinya, dipasang di BELAKANG pemeriksa token, atap ini tidak lagi
     * menjaga apa pun yang tidak sudah dijaga pembatas pintu — dan justru
     * jenis banjir yang paling murah dikirim penyerang, POST tanpa token,
     * lolos tanpa batas. Diukur langsung: 210 kiriman semacam itu, 180
     * lewat lalu 30 dipotong 429 sebelum sesi dibuka.
     */
    public function test_atap_berjalan_sebelum_pemeriksa_token(): void
    {
        $urut = $this->grupWeb();

        $atap = array_search(ThrottleRequests::class.':web', $urut, true);

        /* Namanya berganti antar versi kerangka — VerifyCsrfToken dulu,
           PreventRequestForgery sekarang. Yang dicari kelasnya, bukan
           namanya, supaya penggantian nama berikutnya tidak membuat uji
           ini lulus atas grup yang sudah tidak memeriksa token sama
           sekali. */
        $token = false;
        foreach ($urut as $i => $m) {
            if (is_a(strtok($m, ':'), PreventRequestForgery::class, true)) {
                $token = $i;
                break;
            }
        }

        $this->assertNotFalse($atap, 'Grup web tidak lagi memasang throttle:web.');
        $this->assertNotFalse($token, 'Grup web tidak lagi memeriksa token CSRF.');

        $this->assertLessThan($token, $atap,
            'throttle:web berjalan SESUDAH pemeriksa token CSRF. POST tanpa token '
            .'ditolak 419 lebih dulu, jadi atapnya tidak pernah menghitungnya — '
            .'sementara tiap 419 itu tetap menyalakan kerangka dan membuka sesi.');
    }

    /**
     * Tamu dibatasi lebih ketat daripada yang sudah masuk.
     *
     * Kuncinya berbeda pula, dan itu yang terpenting: tamu dikunci per
     * ALAMAT, yang pengguna dikunci per AKUN. Satu kantor yang seluruh
     * pegawainya keluar lewat satu alamat NAT karena itu tidak saling
     * menghabiskan jatah — dan satu kunci yang dicuri lalu dipakai
     * beramai-ramai dari banyak alamat tetap terhitung sebagai satu.
     */
    public function test_tamu_dan_pengguna_dibatasi_dengan_kunci_yang_berbeda(): void
    {
        $limiter = RateLimiter::limiter('web');

        $tamu = $limiter(request());

        $c = Company::create(['name' => 'PT Uji', 'code' => 'UJI']);
        $u = User::factory()->create(['company_id' => $c->id]);

        $masuk = $limiter(tap(request(), fn ($r) => $r->setUserResolver(fn () => $u)));

        $this->assertGreaterThan($tamu->maxAttempts, $masuk->maxAttempts,
            'Yang sudah masuk tidak mendapat jatah lebih longgar daripada tamu.');

        $this->assertStringStartsWith('tamu:', $tamu->key);
        $this->assertStringStartsWith('akun:', $masuk->key,
            'Pengguna dikunci per alamat, bukan per akun — satu kantor di balik '
            .'satu NAT jadi saling menghabiskan jatah.');
    }

    /**
     * Yang melewati batas ditolak 429, bukan dilayani.
     *
     * Dijalankan lewat permintaan sungguhan, bukan dengan memanggil
     * pembatasnya langsung: yang diuji di sini bukan rumusnya melainkan
     * bahwa middleware-nya benar-benar terpasang pada jalur yang
     * dilewati permintaan.
     */
    public function test_permintaan_berlebih_ditolak_dengan_429(): void
    {
        $limiter = RateLimiter::limiter('web');
        $batas   = $limiter(request())->maxAttempts;

        // Dihabiskan langsung; mengetuk halaman ratusan kali membuat uji
        // ini lambat tanpa membuktikan apa pun yang lebih.
        for ($i = 0; $i <= $batas; $i++) RateLimiter::hit('web:tamu:127.0.0.1', 60);

        $this->get('/')->assertStatus(429);
    }

    public function test_dalam_batas_tetap_dilayani(): void
    {
        $this->get('/')->assertSuccessful();
    }
}
