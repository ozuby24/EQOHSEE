<?php

namespace Tests\Feature;

use App\Support\TanyaJawab;
use Tests\TestCase;

/**
 * Penjaga presisi halaman depan dan halaman tamu (masuk, daftar, …).
 *
 * Tiap uji di sini menjaga satu cacat yang pernah terlihat di layar
 * pemakai — di tablet mendatar 1176×620 tangkapannya menunjukkan
 * ketiganya sekaligus: judul "danterbukti" tanpa spasi, tombol Masuk di
 * bawah lipatan, dan kartu temuan yang menjorok keluar foto.
 *
 * Diuji pada berkas sumbernya, bukan lewat peramban: yang dijaga adalah
 * nilai yang membuat cacatnya — bila nilainya kembali, cacatnya kembali.
 */
class PresisiTampilanTest extends TestCase
{
    private function aturan(string $css, string $pemilih): string
    {
        $tanpaKomentar = preg_replace('#/\*.*?\*/#s', '', $css);
        $this->assertMatchesRegularExpression('/'.preg_quote($pemilih, '/').'\s*\{([^}]*)\}/', $tanpaKomentar,
            "Aturan {$pemilih} tidak ditemukan.");
        preg_match('/'.preg_quote($pemilih, '/').'\s*\{([^}]*)\}/', $tanpaKomentar, $m);

        return $m[1];
    }

    public function test_judul_hero_tidak_merapatkan_kata_dan_baris(): void
    {
        $h1 = $this->aturan(file_get_contents(resource_path('css/landing.css')), '.ld-h1');

        preg_match('/letter-spacing:\s*(-?[\d.]+)em/', $h1, $ls);
        $this->assertNotEmpty($ls, '.ld-h1 kehilangan letter-spacing.');
        $this->assertGreaterThanOrEqual(-0.025, (float) $ls[1],
            'Spasi huruf judul hero kembali di bawah -.025em: spasi antarkata menyusut sampai "danterbukti".');

        $this->assertMatchesRegularExpression('/word-spacing:\s*\.?\d/', $h1,
            'Judul hero tanpa word-spacing — pada font-stretch 80% spasi kata tinggal ±3 px.');

        preg_match('/line-height:\s*([\d.]+)/', $h1, $lh);
        $this->assertGreaterThanOrEqual(1.0, (float) $lh[1],
            'Tinggi baris judul hero di bawah 1: ekor "g" menyentuh huruf di baris bawahnya.');

        $this->assertStringContainsString('vh', $h1,
            'Ukuran judul hero tidak lagi ikut tinggi layar — di tablet mendatar tombolnya jatuh di bawah lipatan.');
    }

    public function test_foto_hero_dibatasi_tinggi_layar_dan_kartu_di_dalam_bingkai(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/landing.css')));

        $this->assertMatchesRegularExpression('/\.ld-hero-bingkai\s*\{[^}]*max-height:[^}]*svh/', $css,
            'Foto hero tidak dibatasi tinggi layar: di layar lebar yang pendek ia lebih tinggi daripada layarnya.');

        $kartu = $this->aturan($css, '.ld-kartu-temuan');
        $this->assertDoesNotMatchRegularExpression('/left:\s*(clamp\()?-/', $kartu,
            'Kartu temuan kembali menjorok keluar bingkai foto (left negatif).');
    }

    public function test_menu_ponsel_dapat_digulir_di_layar_mendatar(): void
    {
        $menu = $this->aturan(file_get_contents(resource_path('css/landing.css')), '.ld-menu');
        $this->assertStringContainsString('overflow-y: auto', $menu,
            'Menu ponsel tidak dapat digulir: di ponsel mendatar (±375 px) tautan bawahnya di luar layar.');
        $this->assertMatchesRegularExpression('/max-height:\s*calc\(100dvh/', $menu);
    }

    public function test_ringkasan_pembelian_tidak_melekat_di_layar_pendek(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/landing.css')));
        $this->assertMatchesRegularExpression('/@media\s*\(max-height:\s*760px\)\s*\{\s*\.ld-ringkas\s*\{\s*position:\s*static/', $css,
            'Kartu ringkasan setinggi ±610 px melekat di layar 636 px dan menutupi tombol "Buat tagihan"-nya.');
    }

    public function test_rasio_foto_hero_layar_sempit_ditulis_sesudah_aturan_dasar(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(resource_path('css/landing.css')));
        $dasar = strpos($css, '.ld-hero-bingkai { position: relative; aspect-ratio: 4 / 4.3;');
        $sempit = strpos($css, '@media (max-width: 919px) { .ld-hero-bingkai { aspect-ratio: 16 / 11; } }');
        $this->assertNotFalse($dasar);
        $this->assertNotFalse($sempit, 'Rasio 16:11 untuk layar bertumpuk hilang.');
        $this->assertGreaterThan($dasar, $sempit,
            'Rasio foto hero untuk layar sempit ditulis sebelum aturan dasarnya dan kalah urutan (foto ponsel tetap 4:4,3).');
    }

    public function test_kisi_kartu_berkolom_pasti_tanpa_kartu_yatim(): void
    {
        $css = file_get_contents(resource_path('css/landing.css'));
        // auto-fit pada empat/tiga kartu menyisakan satu kartu sendirian di
        // baris terakhir pada sebagian lebar layar (3 + 1, 2 + 1).
        foreach (['.ld-angka-deret', '.ld-langkah', '.ld-jaminan', '.ld-kategori', '.ld-pilar-isi'] as $pemilih) {
            $this->assertStringNotContainsString('auto-fit', $this->aturan($css, $pemilih),
                "{$pemilih} kembali memakai auto-fit dan menyisakan kartu yatim di baris terakhir.");
        }
    }

    public function test_tombol_whatsapp_tidak_menutupi_layar_pertama(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Landing.vue'));
        $this->assertStringContainsString(':class="{ sembunyi: !apungTampak }"', $vue,
            'Tombol WhatsApp melayang tampil sejak layar pertama, di atas foto dan kartu hero.');
        $this->assertMatchesRegularExpression('/\.ld-apung\.sembunyi\s*\{[^}]*pointer-events:\s*none/',
            file_get_contents(resource_path('css/landing.css')),
            'Tombol WhatsApp yang tersembunyi masih dapat ditekan.');
    }

    public function test_deret_huruf_kerangka_berupa_kisi(): void
    {
        $deret = $this->aturan(file_get_contents(resource_path('css/landing.css')), '.ld-huruf-deret');
        $this->assertStringContainsString('display: grid', $deret,
            'Deret huruf EQOHSEE kembali memakai flex-wrap dan patah tidak rata di ponsel.');
    }

    public function test_halaman_tamu_berhuruf_merek_dan_tombolnya_terbaca(): void
    {
        $berkas = array_merge(
            [resource_path('js/Layouts/GuestLayout.vue')],
            glob(resource_path('js/Pages/Auth/*.vue')),
        );

        foreach ($berkas as $f) {
            $isi = file_get_contents($f);
            $nama = basename($f);

            $this->assertStringNotContainsString('font-serif', $isi,
                "{$nama} memakai font-serif — di peramban itu Georgia/Times, bukan huruf EQOHSEE.");
            $this->assertDoesNotMatchRegularExpression('/bg-\\[#F57C00\\][^"]*text-white/', $isi,
                "{$nama}: teks putih di atas jingga (kontras 2,7:1). Tombol jingga berteks ink.");
        }

        $tataLetak = file_get_contents(resource_path('js/Layouts/GuestLayout.vue'));
        $this->assertStringContainsString("import '../../css/masuk.css'", $tataLetak,
            'GuestLayout tidak memuat masuk.css — halaman tamu kembali ke huruf aplikasi.');
        foreach (['Safe Today', 'Sustainable Tomorrow', 'Innovation Always'] as $semboyan) {
            $this->assertStringNotContainsString($semboyan, $tataLetak,
                "Panel halaman masuk kembali memuat semboyan \"{$semboyan}\" — rancangan yang disetujui tanpa semboyan.");
        }

        // Halaman Daftar, Lupa sandi, dan lainnya diseragamkan dari masuk.css.
        $css = file_get_contents(resource_path('css/masuk.css'));
        foreach (['.ms .auth-judul', '.ms form .label', '.ms form .input', '.ms .auth-tombol'] as $pemilih) {
            $this->assertStringContainsString($pemilih, $css,
                "masuk.css tidak lagi menyeragamkan {$pemilih} — halaman tamu lain berbeda gaya dari halaman Masuk.");
        }
    }

    public function test_panel_foto_halaman_tamu_menempel_di_layar_lebar(): void
    {
        $tataLetak = file_get_contents(resource_path('js/Layouts/GuestLayout.vue'));
        $this->assertMatchesRegularExpression('/class="pendar-rekaman[^"]*lg:sticky[^"]*lg:h-\\[100dvh\\]/', $tataLetak,
            'Panel foto halaman tamu tidak menempel: pada formulir Daftar judulnya jatuh di bawah lipatan.');
    }

    public function test_halaman_masuk_merender_bahasa_baru(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p->component('Auth/Login'));
    }

    public function test_jawaban_sinyal_menyebut_mode_lapangan_dan_batasnya(): void
    {
        $sinyal = collect(TanyaJawab::semua())->firstWhere('t', 'Bagaimana kalau site tidak ada sinyal?');
        $this->assertNotNull($sinyal);

        $this->assertStringNotContainsString('belum ada perekaman luring', $sinyal['j'],
            'Jawaban sinyal masih menyangkal kerja luring, padahal mode lapangan mengantre laporan.');
        $this->assertStringContainsString('mode lapangan', $sinyal['j']);
        $this->assertStringContainsString('menuntut koneksi', $sinyal['j'],
            'Jawaban sinyal tidak lagi menyebut batasnya: modul lain tetap memerlukan jaringan.');
    }
}
