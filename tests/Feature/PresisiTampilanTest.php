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

    public function test_halaman_tamu_tidak_memakai_serif_atau_semboyan_inggris(): void
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
            foreach (['Safe Today', 'Sustainable Tomorrow', 'Innovation Always'] as $semboyan) {
                $this->assertStringNotContainsString($semboyan, $isi,
                    "{$nama} kembali memuat semboyan \"{$semboyan}\"; halaman depan sengaja tidak memakainya.");
            }
            $this->assertStringNotContainsString('bg-[#F57C00] hover:bg-[#DC6E00] text-white', $isi,
                "{$nama}: teks putih di atas jingga (kontras 2,7:1). Tombol jingga berteks ink.");
        }

        $this->assertStringContainsString("import '../../css/masuk.css'",
            file_get_contents(resource_path('js/Layouts/GuestLayout.vue')),
            'GuestLayout tidak memuat masuk.css — halaman tamu kembali ke huruf aplikasi.');
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
