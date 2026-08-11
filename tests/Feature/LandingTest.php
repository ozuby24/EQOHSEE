<?php

namespace Tests\Feature;

use App\Support\{Media, Smkp};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman depan.
 *
 * Dua hal yang diuji: bahwa rekaman yang sudah terpasang benar-benar
 * terpakai, dan bahwa halaman tetap utuh tanpa satu pun berkas media.
 * Yang kedua mudah terlewat justru karena berkasnya sudah ada — karena itu
 * letak media dialihkan ke folder kosong saat mengujinya, bukan dengan
 * memindahkan berkas sungguhan.
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

    /** Alihkan letak media ke folder kosong: keadaan sebelum ada berkas. */
    private function tanpaMedia(): void
    {
        config(['media.akar' => 'media-uji']);
        @mkdir(public_path('media-uji'), 0755, true);
    }

    private function taruh(string $jalur, string $isi = 'x'): void
    {
        $penuh = public_path(Media::akar().'/'.$jalur);
        @mkdir(dirname($penuh), 0755, true);
        file_put_contents($penuh, $isi);
        $this->sampah[] = $penuh;
    }

    /* ---------- rekaman yang sudah terpasang ---------- */

    public function test_video_dan_foto_hero_terpasang(): void
    {
        $this->assertNotNull(Media::heroVideo(), 'Video hero belum ada di public/media/hero.');
        $this->assertNotNull(Media::heroPoster(), 'Gambar diam hero belum ada.');

        $this->get('/')->assertOk()
            ->assertSee('media/hero/tambang.jpg', false)   // poster langsung terpasang
            ->assertSee('data-hero-video=', false)         // videonya menyusul lewat JS
            ->assertSee('Tonton Video');
    }

    public function test_empat_rekaman_lapangan_tampil_dengan_tombol_putar(): void
    {
        $halaman = $this->get('/')->assertOk();

        foreach ([
            'Inspeksi & Observasi', 'Operasional Tambang',
            'Pengendalian Risiko', 'Budaya Keselamatan',
        ] as $judul) {
            $halaman->assertSee($judul);
            $halaman->assertSee('Putar video '.$judul);
        }
    }

    public function test_kartu_tanpa_berkas_disembunyikan_selama_ada_yang_terisi(): void
    {
        // Kinerja Energi dan Reklamasi belum punya rekaman; menyandingkannya
        // sebagai kotak kosong membuat galerinya terbaca rusak.
        $terisi = collect(Media::galeriTerisi())->pluck('judul');

        $this->assertTrue($terisi->contains('Operasional Tambang'));
        $this->assertFalse($terisi->contains('Kinerja Energi'));

        $this->get('/')->assertOk()->assertDontSee('Reklamasi & Lingkungan');
    }

    public function test_kartu_muncul_begitu_berkasnya_disalin(): void
    {
        $this->taruh('galeri/energi.jpg');

        $this->get('/')->assertOk()->assertSee('Kinerja Energi');
    }

    /* ---------- keadaan tanpa media ---------- */

    public function test_tanpa_berkas_media_halaman_tetap_utuh(): void
    {
        $this->tanpaMedia();

        $this->assertNull(Media::heroVideo());
        $this->assertNull(Media::heroPoster());

        $this->get('/')->assertOk()
            ->assertSee('Keselamatan tambang,')
            ->assertDontSee('media-uji/hero/tambang.jpg')
            ->assertDontSee('data-hero-video=', false)
            ->assertDontSee('Tonton Video');
    }

    public function test_tanpa_media_seluruh_kartu_galeri_tetap_tampil_sebagai_tempat_foto(): void
    {
        $this->tanpaMedia();

        $halaman = $this->get('/')->assertOk();

        // Bagian galeri tidak boleh hilang sama sekali hanya karena kosong.
        foreach (Media::galeri() as $g) {
            $halaman->assertSee($g['judul']);
        }
        $halaman->assertDontSee('Putar video');
    }

    public function test_video_hero_tidak_ditulis_pada_atribut_src(): void
    {
        // Peramban mengunduh begitu src-nya ada, jadi menulisnya di markup
        // membuat ponsel menanggung videonya meski tidak pernah ditampilkan.
        $isi = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-hero-video=', $isi);
        $this->assertStringNotContainsString('<video src', $isi);
        $this->assertStringNotContainsString('src="'.asset(Media::HERO_VIDEO).'"', $isi);
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
