<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Judul, uraian, dan penemuan oleh mesin — semuanya dari SERVER.
 *
 * Aplikasi ini memakai Inertia tanpa SSR, jadi setiap <title> dipasang
 * JavaScript sesudah halaman tiba. Bagi orang itu tidak terasa sama
 * sekali, dan justru itu yang membuatnya bertahan begitu lama: di layar
 * judulnya selalu benar.
 *
 * Yang tidak menjalankan JavaScript melihat sesuatu yang lain — pratinjau
 * tautan di WhatsApp, LinkedIn, Telegram, dan perayap yang belum sempat
 * menggambar halaman. Karena itu seluruh uji di sini membaca HTML MENTAH
 * dari tanggapan, bukan halaman yang sudah hidup.
 */
class PenemuanTest extends TestCase
{
    use RefreshDatabase;

    private function kepala(string $alamat): string
    {
        return $this->get($alamat)->assertOk()->getContent();
    }

    /* ───────── judul dan uraian ───────── */

    public function test_halaman_pendaratan_punya_judul_tanpa_javascript(): void
    {
        $isi = $this->kepala('/');

        $this->assertMatchesRegularExpression('~<title[^>]*>\s*\S.*?</title>~s', $isi,
            'Halaman pendaratan datang tanpa judul bagi yang tidak menjalankan JavaScript.');
        $this->assertStringContainsString('EQOHSEE', $isi);
    }

    public function test_halaman_pendaratan_punya_uraian(): void
    {
        preg_match('/name="description" content="([^"]+)"/', $this->kepala('/'), $m);

        $this->assertNotEmpty($m[1] ?? '', 'Tidak ada meta description.');
        $this->assertGreaterThan(70, strlen($m[1]),
            'Uraiannya terlalu pendek untuk menjadi cuplikan yang berguna.');
        $this->assertLessThan(200, strlen($m[1]),
            'Uraiannya terpotong di hasil pencarian.');
    }

    /**
     * Pratinjau tautan harus lengkap: judul, uraian, DAN gambar.
     *
     * Tanpa ketiganya, tautan yang dibagikan di WhatsApp muncul sebagai
     * alamat telanjang. Bagi produk yang menyebar dari mulut ke mulut
     * antar-perusahaan, di situlah kesan pertamanya dibentuk.
     */
    public function test_pratinjau_tautan_lengkap(): void
    {
        $isi = $this->kepala('/');

        foreach (['og:title', 'og:description', 'og:image', 'og:url', 'og:type'] as $tag) {
            $this->assertStringContainsString('property="'.$tag.'"', $isi, "$tag hilang.");
        }

        foreach (['twitter:card', 'twitter:title', 'twitter:image'] as $tag) {
            $this->assertStringContainsString('name="'.$tag.'"', $isi, "$tag hilang.");
        }
    }

    public function test_gambar_pratinjau_benar_benar_ada(): void
    {
        preg_match('/property="og:image" content="([^"]+)"/', $this->kepala('/'), $m);

        $jalur = public_path(parse_url($m[1], PHP_URL_PATH));

        $this->assertFileExists($jalur, 'og:image menunjuk ke berkas yang tidak ada.');

        [$lebar, $tinggi] = getimagesize($jalur);
        $this->assertSame(1200, $lebar, 'Gambar pratinjau bukan 1200x630; sebagian layanan memotongnya.');
        $this->assertSame(630, $tinggi);
    }

    /* ───────── apa yang boleh diindeks ───────── */

    /**
     * Halaman verifikasi sertifikat TIDAK boleh terindeks.
     *
     * Ia sengaja terbuka tanpa login — itulah gunanya, agar pemberi kerja
     * dapat memeriksa keaslian sertifikat. Tetapi halamannya menyebut NAMA
     * ORANG beserta nomor sertifikat dan nama perusahaannya. Dibiarkan
     * terindeks, ia menjadi direktori karyawan yang dapat dicari — akibat
     * yang tidak pernah dimaksudkan siapa pun.
     */
    public function test_verifikasi_sertifikat_tidak_boleh_terindeks(): void
    {
        $isi = $this->kepala('/verifikasi/KODE-UJI');

        $this->assertStringContainsString('name="robots" content="noindex', $isi,
            'Halaman verifikasi sertifikat dapat terindeks beserta nama orang di dalamnya.');
    }

    public function test_halaman_di_balik_login_tidak_boleh_terindeks(): void
    {
        $pengguna = User::factory()->create(['email_verified_at' => now()]);

        $isi = $this->actingAs($pengguna)->get('/dashboard')->getContent();

        $this->assertStringContainsString('name="robots" content="noindex', $isi);
    }

    public function test_halaman_publik_justru_boleh_terindeks(): void
    {
        foreach (['/', '/login', '/register'] as $alamat) {
            $this->assertStringNotContainsString('content="noindex', $this->kepala($alamat),
                "$alamat seharusnya boleh diindeks.");
        }
    }

    /* ───────── robots.txt ───────── */

    public function test_robots_menutup_seluruh_modul(): void
    {
        $isi = $this->get('/robots.txt')->assertOk()->getContent();

        /* Ditegaskan terhadap tabel modulnya sendiri, bukan terhadap
           daftar yang ditulis di dalam uji ini. Daftar yang ditulis di
           sini akan menua bersama robots.txt-nya, dan keduanya menua
           tanpa memberi tahu siapa pun. */
        foreach (Menu::petaAlamat() as [$pola,]) {
            $awalan = '/'.trim(rtrim($pola[0], '*'), '/');
            $this->assertStringContainsString('Disallow: '.$awalan, $isi,
                "Modul $awalan terbuka bagi perayap.");
        }
    }

    public function test_robots_tidak_menutup_aset_yang_dibutuhkan_perayap(): void
    {
        /* Google menggambar halaman sebelum menilainya. Halaman yang tidak
           dapat mengambil CSS-nya dinilai sebagai halaman yang rusak. */
        $isi = $this->get('/robots.txt')->getContent();

        foreach (['/build/', '/media/', '/brand/'] as $aset) {
            $this->assertStringContainsString('Allow: '.$aset, $isi);
        }
    }

    public function test_robots_menyebut_sitemap(): void
    {
        $this->assertStringContainsString('Sitemap:', $this->get('/robots.txt')->getContent());
    }

    /* ───────── sitemap ───────── */

    public function test_sitemap_hanya_memuat_halaman_yang_boleh_diindeks(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<urlset', $xml);
        preg_match_all('~<loc>(.*?)</loc>~', $xml, $m);

        $this->assertNotEmpty($m[1], 'Sitemap kosong.');

        foreach ($m[1] as $alamat) {
            $jalur = parse_url($alamat, PHP_URL_PATH) ?: '/';
            $isi   = $this->get($jalur)->getContent();

            $this->assertStringNotContainsString('content="noindex', $isi,
                "Sitemap memuat $jalur yang justru ber-noindex — dua pesan yang bertentangan.");
        }
    }

    public function test_sitemap_berbentuk_xml_yang_sah(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        libxml_use_internal_errors(true);
        $this->assertNotFalse(simplexml_load_string($xml), 'Sitemap bukan XML yang sah.');
    }

    /* ───────── data terstruktur ───────── */

    public function test_data_terstruktur_hanya_di_halaman_pendaratan(): void
    {
        $this->assertStringContainsString('application/ld+json', $this->kepala('/'));
        $this->assertStringNotContainsString('application/ld+json', $this->kepala('/login'),
            'Tiap alamat menyatakan dirinya aplikasi tersendiri.');
    }

    public function test_data_terstruktur_berbentuk_json_yang_sah(): void
    {
        preg_match('~<script type="application/ld\+json"[^>]*>(.*?)</script>~s', $this->kepala('/'), $m);

        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data, 'Data terstruktur bukan JSON yang sah.');
        $this->assertSame('SoftwareApplication', $data['@type']);

        /* Tidak ada penilaian, jumlah pengguna, maupun harga. Penanda
           semacam itu harus sesuai dengan yang tampak di halaman, dan yang
           tidak sesuai mengundang sanksi manual — jauh lebih mahal
           daripada bintang yang tidak pernah dipasang. */
        $this->assertArrayNotHasKey('aggregateRating', $data);
        $this->assertArrayNotHasKey('offers', $data);
    }

    /* ───────── kanonik ───────── */

    public function test_alamat_kanonik_membuang_kueri(): void
    {
        preg_match('/rel="canonical" href="([^"]+)"/', $this->kepala('/?utm_source=wa&utm_campaign=x'), $m);

        $this->assertStringNotContainsString('utm_', $m[1] ?? '',
            'Alamat kanonik membawa parameter kampanye; satu halaman tercatat sebagai banyak alamat.');
    }

    /* ───────── tidak ada yang datang dari luar ───────── */

    /**
     * Tak satu pun berkas halaman berasal dari host lain.
     *
     * Ini bukan soal keamanan pasokan saja. Aplikasi ini dipakai di site
     * tambang, di jaringan yang sering menutup akses keluar, dan tiap
     * berkas yang datang dari luar adalah satu bagian halaman yang bisa
     * hilang tanpa jejak di sana.
     *
     * Dua sudah pernah ditemukan dengan cara yang sama: Chart.js dari
     * cdn.jsdelivr.net — grafiknya kosong — dan Inter dari
     * fonts.googleapis.com, yang gagalnya jauh lebih halus. Huruf yang
     * tidak termuat hanya menggeser tata letak sedikit; tidak ada yang
     * melaporkannya sebagai kerusakan, yang tercatat hanya kesan bahwa
     * aplikasinya berantakan kalau dibuka di lapangan.
     *
     * Ujinya dipasang di sini supaya yang ketiga tidak perlu ditemukan
     * dengan cara yang sama lagi.
     */
    public function test_tidak_ada_berkas_dari_host_luar(): void
    {
        foreach (['/', '/login', '/register'] as $alamat) {
            $isi = $this->kepala($alamat);

            preg_match_all('~(?:src|href)="(https?://[^"]+)"~', $isi, $m);

            $luar = array_filter($m[1], function (string $u) {
                $host = parse_url($u, PHP_URL_HOST);
                return $host !== null && $host !== parse_url(config('app.url'), PHP_URL_HOST);
            });

            /* og:image dan tautan kanonik memang beralamat penuh, tetapi
               keduanya menunjuk ke host aplikasi sendiri, jadi tidak
               tersaring di atas. Yang tersisa benar-benar dari luar. */
            $this->assertSame([], array_values($luar),
                $alamat.' memuat berkas dari host luar: '.implode(', ', $luar));
        }
    }

    public function test_huruf_disajikan_sendiri(): void
    {
        foreach (['inter-latin', 'inter-latin-ext', 'playfair-latin', 'playfair-latin-ext'] as $berkas) {
            $this->assertFileExists(public_path("fonts/$berkas.woff2"));
        }

        $css = file_get_contents(resource_path('css/fonts.css'));

        $this->assertStringNotContainsString('gstatic.com', $css);
        $this->assertStringContainsString('/fonts/inter-latin.woff2', $css);

        /* Nama berkas harus menyusul ISI-nya. Percobaan pertama menamainya
           menurut urutan unduhan, dan pasangan Playfair tertukar: berkas
           bernama "latin" memuat glif latin-ext. Secara fungsi tidak
           terlihat — pemetaannya tetap taat asas — tetapi namanya
           berbohong kepada yang membacanya berikutnya. */
        preg_match_all('~url\(/fonts/\w+-(latin(?:-ext)?)\.woff2\)[^}]*?unicode-range: (U\+[0-9A-F]{4})~s',
            $css, $pasangan, PREG_SET_ORDER);

        $this->assertNotEmpty($pasangan);

        foreach ($pasangan as [, $subset, $awal]) {
            $this->assertSame($awal === 'U+0000' ? 'latin' : 'latin-ext', $subset,
                'Nama berkas huruf tidak sesuai dengan subset yang dimuatnya.');
        }
    }

    /**
     * Halaman tidak lagi mengunduh seluruh 147 halaman untuk membuka satu.
     *
     * Ditegaskan atas hasil build, bukan atas kode sumbernya: yang sampai
     * ke pengguna adalah berkas di public/build, dan `eager: true` yang
     * kembali secara tidak sengaja hanya terlihat di sana.
     */
    public function test_halaman_dipecah_menjadi_berkas_terpisah(): void
    {
        $manifest = public_path('build/manifest.json');

        if (!file_exists($manifest)) {
            $this->markTestSkipped('Belum ada hasil build; jalankan npm run build.');
        }

        $berkas = glob(public_path('build/assets/*.js'));

        $this->assertGreaterThan(50, count($berkas),
            'Seluruh halaman masih menyatu dalam satu berkas.');

        $terbesar = max(array_map('filesize', $berkas));

        $this->assertLessThan(800 * 1024, $terbesar,
            'Berkas terbesar '.round($terbesar / 1024).' KB — pemecahan halaman tidak berjalan.');
    }
}
