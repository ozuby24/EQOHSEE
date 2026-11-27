<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Identitas EQOHSEE (pita Deep Teal–Olive Green–Warm Grey) dan halaman
 * masuk yang memakainya.
 *
 * Yang dijaga di sini cacat yang tidak melempar galat: logo lama yang
 * kembali di satu sudut aplikasi, berkas SVG yang membawa skrip, angka
 * di halaman pertama yang tidak lagi benar, tombol yang tidak bekerja,
 * dan kotak verifikasi bertema gelap di kartu putih.
 */
class HalamanMasukMerekTest extends TestCase
{
    use RefreshDatabase;

    /** Lambang dan wordmark resmi dipakai di setiap kerangka halaman. */
    public function test_identitas_baru_dipakai_di_kerangka_utama(): void
    {
        $periksa = [
            'js/Components/Wordmark.vue' => ['/brand/eqohsee-navbar-dark.svg', '/brand/eqohsee-navbar-light.svg', '/brand/eqohsee-icon-dark.svg'],
            'js/Layouts/AppLayout.vue' => ['/brand/eqohsee-icon-dark.svg', '/brand/eqohsee-wordmark-dark.svg'],
            'js/Components/TurSelamatDatang.vue' => ['/brand/eqohsee-icon-dark.svg', '/brand/eqohsee-wordmark-dark.svg'],
            'js/Layouts/GuestLayout.vue' => ['/brand/eqohsee-icon-dark.svg', '/brand/eqohsee-wordmark-dark.svg', '/brand/masuk-tambang.webp'],
            'js/Pages/Landing.vue' => ['/brand/eqohsee-navbar-dark.svg'],
            'views/hukum/tata.blade.php' => ['/brand/eqohsee-navbar-light.svg', '/brand/eqohsee-navbar-dark.svg'],
        ];

        foreach ($periksa as $berkas => $rujukan) {
            $isi = file_get_contents(resource_path($berkas));
            foreach ($rujukan as $r) {
                $this->assertStringContainsString($r, $isi, "{$berkas} tidak memakai {$r}.");
            }
        }

        // Wordmark ditulis dari vektor resminya, bukan teks berhuruf Q jingga.
        foreach (['js/Layouts/AppLayout.vue', 'js/Components/TurSelamatDatang.vue', 'js/Pages/Landing.vue', 'js/Pages/Lapangan/Sertifikat.vue'] as $berkas) {
            $this->assertDoesNotMatchRegularExpression('#E<(em|i|b)>Q</(em|i|b)>OHSEE#', file_get_contents(resource_path($berkas)),
                "{$berkas} kembali menulis wordmark sebagai teks.");
        }

        // Favicon dan ikon raster: warna resmi Deep Teal dan Olive Green.
        $favicon = file_get_contents(public_path('brand/favicon.svg'));
        $this->assertStringContainsString('#0F4C45', $favicon, 'Favicon tidak berwarna Deep Teal.');
        $this->assertStringContainsString('#7A9E3A', $favicon, 'Favicon tidak berwarna Olive Green.');

        foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'brand/eqohsee-icon-512.png', 'brand/og-eqohsee.jpg'] as $berkas) {
            $this->assertFileExists(public_path($berkas));
        }
    }

    /**
     * Berkas SVG merek dimuat lewat <img> dan disajikan dari asal yang sama;
     * SVG yang membawa skrip, penangan peristiwa, atau rujukan ke luar
     * adalah celah, bukan logo.
     */
    public function test_berkas_svg_merek_bersih_dari_skrip_dan_rujukan_luar(): void
    {
        $berkas = glob(public_path('brand/*.svg')) ?: [];
        $this->assertNotEmpty($berkas);

        foreach ($berkas as $f) {
            $isi = file_get_contents($f);
            $nama = basename($f);

            $this->assertStringNotContainsStringIgnoringCase('<script', $isi, "{$nama} memuat skrip.");
            $this->assertStringNotContainsStringIgnoringCase('href', $isi, "{$nama} memuat rujukan (href).");
            $this->assertStringNotContainsStringIgnoringCase('<foreignObject', $isi, "{$nama} memuat foreignObject.");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $isi, "{$nama} memuat penangan peristiwa.");

            // Satu-satunya alamat yang boleh ada: ruang nama SVG itu sendiri.
            $tanpaRuangNama = str_replace('http://www.w3.org/2000/svg', '', $isi);
            $this->assertDoesNotMatchRegularExpression('#https?://#i', $tanpaRuangNama, "{$nama} merujuk alamat luar.");
        }
    }

    /**
     * Angka di kaca kiri-bawah halaman tamu dihitung server, bukan ditulis
     * di tampilan. Halaman pertama yang dilihat calon pengguna tidak boleh
     * menyebut jumlah modul yang sudah tidak benar.
     */
    public function test_angka_widget_halaman_masuk_dari_registri_modul_dan_pilar(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p
            ->where('ringkasPlatform.modul', count(\App\Support\Modules::all()))
            ->where('ringkasPlatform.aspek', count(\App\Support\Pillars::all()))
            ->has('ringkasPlatform.tren', 7)
            ->has('ringkasPlatform.modulPerPilar'));

        $tataLetak = file_get_contents(resource_path('js/Layouts/GuestLayout.vue'));
        $this->assertStringContainsString('{{ tampil(s.nilai) }}', $tataLetak);
        foreach (['r.pengguna', 'r.pengunjung', 'r.modul', 'r.aspek'] as $sumber) {
            $this->assertStringContainsString($sumber, $tataLetak, "Angka {$sumber} tidak lagi dari properti server.");
        }
        $this->assertDoesNotMatchRegularExpression('#<b class="ms-stat-angka">\s*\d#', $tataLetak,
            'Angka widget ditulis tetap di tampilan.');

        // Angka nol tidak ditampilkan: "0 pengunjung" terbaca sebagai situs mati.
        $this->assertMatchesRegularExpression('/\.filter\(\(b\) => b\.nilai > 0\)/', $tataLetak);
    }

    /** Pengguna yang sudah masuk tidak menerima angka panel halaman tamu. */
    public function test_angka_widget_tidak_dikirim_ke_pengguna_yang_sudah_masuk(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $this->get('/dashboard')->assertOk()->assertInertia(fn ($p) => $p->where('ringkasPlatform', null));
    }

    /**
     * Tombol "Masuk dengan Google/Microsoft" digambar hanya bila alurnya
     * benar-benar terpasang (kredensial di .env). Tombol yang tidak bekerja
     * di halaman pertama yang dilihat orang adalah cacat. Rincian alurnya:
     * MasukPihakKetigaTest.
     */
    public function test_tombol_pihak_ketiga_hanya_bila_alurnya_terpasang(): void
    {
        config(['services.google.client_id' => '', 'services.google.client_secret' => '',
                'services.microsoft.client_id' => '', 'services.microsoft.client_secret' => '']);

        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p
            ->where('sso.google', false)->where('sso.microsoft', false));

        $halaman = file_get_contents(resource_path('js/Pages/Auth/Login.vue'));
        $this->assertStringContainsString('<template v-if="adaSso">', $halaman);
        $this->assertStringContainsString('v-if="sso.google" href="/masuk/google"', $halaman);
        $this->assertStringContainsString('v-if="sso.microsoft" href="/masuk/microsoft"', $halaman);

        // Kerangka tamu tidak punya tombol pihak ketiga sendiri.
        $this->assertDoesNotMatchRegularExpression('#/masuk/(google|microsoft)#',
            file_get_contents(resource_path('js/Layouts/GuestLayout.vue')));
    }

    /** Judul pemasaran halaman tamu memakai istilah OHSE, bukan HSE. */
    public function test_halaman_tamu_menyebut_ohse(): void
    {
        foreach (['js/Layouts/GuestLayout.vue', 'js/tamu/bahasa.ts', 'js/Pages/Auth/Login.vue'] as $berkas) {
            $isi = preg_replace('/EQOHSEE|OHSE/', '', file_get_contents(resource_path($berkas)));
            $this->assertDoesNotMatchRegularExpression('/\bHSE\b/', $isi, "{$berkas} masih menulis HSE, bukan OHSE.");
        }

        $this->assertStringContainsString('Integrated Mining OHSE', file_get_contents(resource_path('js/Layouts/GuestLayout.vue')));
    }

    /** Kotak Cloudflare Turnstile di halaman tamu selalu bertema terang. */
    public function test_turnstile_halaman_tamu_bertema_terang(): void
    {
        foreach (['Login.vue', 'Register.vue', 'LupaSandi.vue'] as $berkas) {
            $isi = file_get_contents(resource_path("js/Pages/Auth/{$berkas}"));
            $this->assertMatchesRegularExpression('/<VerifikasiTurnstile[^>]*tema="light"/s', $isi,
                "{$berkas}: kotak Turnstile mengikuti tema perangkat, padahal halaman tamu selalu terang.");
        }

        // Pesan "terhalang" di halaman tamu juga tetap terang di tema gelap.
        $css = file_get_contents(resource_path('css/masuk.css'));
        $this->assertMatchesRegularExpression('/:root\[data-tema="gelap"\]\s+\.ms\s+\.eq-turnstile-halang\s*\{[^}]*background:\s*#FFFBEB/i', $css);
    }

    /** Halaman masuk tergambar dengan kerangka baru dan kunci Turnstile-nya. */
    public function test_halaman_masuk_tergambar(): void
    {
        config(['turnstile.situs' => 'kunci-uji']);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Auth/Login')->where('turnstile', 'kunci-uji'));
    }
}
