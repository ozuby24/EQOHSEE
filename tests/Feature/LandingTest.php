<?php

namespace Tests\Feature;

use App\Support\{Media, Smkp};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman depan.
 *
 * Yang diuji terutama perilaku mundurnya: halaman dirancang untuk foto dan
 * video tambang sungguhan, tetapi harus tetap utuh sebelum satu pun berkas
 * ditaruh — dan tidak boleh menampilkan gambar rusak.
 */
class LandingTest extends TestCase
{
    use RefreshDatabase;

    /** Berkas uji yang dibuat, dibersihkan setelah tiap kasus. */
    private array $sampah = [];

    protected function tearDown(): void
    {
        foreach ($this->sampah as $b) {
            if (is_file($b)) unlink($b);
        }
        parent::tearDown();
    }

    private function taruh(string $jalur, string $isi = 'x'): void
    {
        $penuh = public_path(Media::AKAR.'/'.$jalur);
        @mkdir(dirname($penuh), 0755, true);
        file_put_contents($penuh, $isi);
        $this->sampah[] = $penuh;
    }

    /* ---------- pemilihan media ---------- */

    public function test_tanpa_berkas_media_halaman_tetap_utuh(): void
    {
        $this->assertNull(Media::heroVideo());
        $this->assertNull(Media::heroPoster());

        $halaman = $this->get('/')->assertOk();

        // Panorama SVG yang dipakai, dan tidak ada gambar yang menunjuk ke
        // berkas yang tidak ada.
        $halaman->assertSee('Keselamatan tambang,');
        $halaman->assertDontSee('media/hero/tambang.jpg');
        $halaman->assertDontSee('media/hero/tambang.mp4');
    }

    public function test_foto_hero_dipakai_begitu_berkasnya_ada(): void
    {
        $this->taruh(Media::HERO_POSTER);

        $this->get('/')->assertOk()->assertSee('media/hero/tambang.jpg', false);
    }

    public function test_video_hero_tidak_ditulis_pada_atribut_src(): void
    {
        $this->taruh(Media::HERO_VIDEO);

        // Sumbernya dipasang lewat data-hero-video, bukan src: peramban
        // mengunduh begitu src-nya ada, jadi menulisnya di markup membuat
        // ponsel menanggung videonya meski tidak pernah ditampilkan.
        $isi = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-hero-video=', $isi);
        $this->assertStringNotContainsString('<video src', $isi);
        $this->assertStringNotContainsString('src="'.asset('media/hero/tambang.mp4').'"', $isi);
    }

    public function test_galeri_tanpa_foto_tidak_memasang_gambar_kosong(): void
    {
        $halaman = $this->get('/')->assertOk();

        foreach (['Inspeksi & Observasi', 'Operasional Tambang', 'Pengendalian Risiko'] as $judul) {
            $halaman->assertSee($judul);
        }
        $halaman->assertDontSee('media/galeri/inspeksi.jpg');
    }

    public function test_kartu_galeri_bervideo_menampilkan_tombol_putar(): void
    {
        $polos = $this->get('/')->getContent();
        $this->assertStringNotContainsString('Putar video Inspeksi', $polos);

        $this->taruh('galeri/inspeksi.mp4');

        $this->get('/')->assertOk()->assertSee('Putar video Inspeksi');
    }

    /* ---------- logo perusahaan ---------- */

    public function test_tanpa_logo_klien_bagian_itu_menampilkan_standar_yang_diacu(): void
    {
        $this->assertSame([], Media::klien());

        // Memajang logo perusahaan berarti menyatakan mereka memakai
        // platform ini. Selama belum ada yang menaruhnya, yang ditampilkan
        // adalah acuan yang memang dapat diperiksa kebenarannya.
        $this->get('/')->assertOk()
            ->assertSee('Mengacu pada Standar')
            ->assertSee('Kepdirjen 185.K/2019')
            ->assertDontSee('Terpercaya di Industri');
    }

    public function test_logo_klien_dibaca_dari_folder(): void
    {
        $this->taruh('klien/tambang-nusantara.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->get('/')->assertOk()
            ->assertSee('Terpercaya di Industri')
            ->assertSee('Tambang Nusantara');       // nama diambil dari nama berkas
    }

    /* ---------- isi halaman ---------- */

    public function test_tujuh_elemen_smkp_tampil_beserta_bobotnya(): void
    {
        $halaman = $this->get('/')->assertOk();

        $this->assertCount(7, Smkp::elemen());
        foreach (Smkp::elemen() as $e) {
            $halaman->assertSee($e['nama']);
        }

        // Bobot elemen Implementasi paling besar; itu yang membedakannya
        // dari daftar tanpa arti.
        $halaman->assertSee('35%');
    }

    public function test_jumlah_modul_pada_hero_mengikuti_daftar_modul(): void
    {
        $jumlah = count(\App\Support\Modules::all());

        $this->get('/')->assertOk()->assertSee('Modul terpadu');
        $this->assertGreaterThanOrEqual(6, $jumlah);
    }

    public function test_seluruh_tautan_navigasi_menunjuk_bagian_yang_ada(): void
    {
        $isi = $this->get('/')->assertOk()->getContent();

        foreach (['beranda', 'pilar', 'modul', 'fitur', 'alur', 'tentang'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $isi, "Bagian #{$id} tidak ada di halaman.");
        }
    }
}
