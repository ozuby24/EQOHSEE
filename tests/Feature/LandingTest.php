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

    private function props(): array
    {
        return $this->get('/')->assertOk()->viewData('page')['props'];
    }

    /* ---------- rekaman yang sudah terpasang ---------- */

    public function test_video_dan_foto_hero_terpasang(): void
    {
        $this->assertNotNull(Media::heroVideo(), 'Video hero belum ada di public/media/hero.');
        $this->assertNotNull(Media::heroPoster(), 'Gambar diam hero belum ada.');

        $hero = $this->props()['hero'];
        $this->assertSame(Media::heroVideo(), $hero['video']);
        $this->assertSame(Media::heroPoster(), $hero['poster']);
    }

    public function test_empat_rekaman_lapangan_tampil_dengan_tombol_putar(): void
    {
        $galeri = $this->props()['galeri'];

        foreach ([
            'Inspeksi & Observasi', 'Perencanaan & Survei Tambang',
            'Pemantauan Pajanan Kerja', 'Higiene Industri',
            'Pengujian Mutu', 'Reklamasi & Lingkungan',
        ] as $judul) {
            $item = collect($galeri)->firstWhere('judul', $judul);
            $this->assertNotNull($item);
            $this->assertNotNull($item['videoUrl']);
        }
    }

    public function test_kartu_tanpa_berkas_disembunyikan_selama_ada_yang_terisi(): void
    {
        // Seluruh butir kini punya rekaman, jadi yang diuji adalah
        // aturannya: butir tanpa berkas tidak boleh ikut tampil selama
        // ada butir lain yang terisi. Satu butir tambahan yang sengaja
        // tidak punya berkas dipakai sebagai pembanding.
        $terisi = collect(Media::galeriTerisi())->pluck('judul');

        $this->assertTrue($terisi->contains('Perencanaan & Survei Tambang'));
        $this->assertCount(count(Media::galeri()), $terisi);

        foreach (Media::galeri() as $g) {
            $this->assertTrue(
                Media::ada($g['gambar']) || Media::ada($g['video'] ?? null),
                "Butir {$g['judul']} tampil tanpa satu pun berkas.",
            );
        }
    }

    public function test_seluruh_butir_galeri_membawa_rekaman(): void
    {
        // Dulu hanya empat dari enam butir punya berkas. Kini keenamnya
        // terisi, dan yang dijaga adalah tidak ada yang tertinggal ketika
        // berkasnya diganti.
        foreach ($this->props()['galeri'] as $item) {
            $this->assertNotNull($item['videoUrl'], "{$item['judul']} tanpa video.");
            $this->assertNotNull($item['gambarUrl'], "{$item['judul']} tanpa gambar.");
        }
    }

    /* ---------- keadaan tanpa media ---------- */

    public function test_tanpa_berkas_media_halaman_tetap_utuh(): void
    {
        $this->tanpaMedia();

        $this->assertNull(Media::heroVideo());
        $this->assertNull(Media::heroPoster());

        $props = $this->props();
        $this->assertNull($props['hero']['video']);
        $this->assertNull($props['hero']['poster']);
    }

    public function test_tanpa_media_seluruh_kartu_galeri_tetap_tampil_sebagai_tempat_foto(): void
    {
        $this->tanpaMedia();

        $galeri = $this->props()['galeri'];

        // Bagian galeri tidak boleh hilang sama sekali hanya karena kosong.
        foreach (Media::galeri() as $g) {
            $this->assertTrue(collect($galeri)->pluck('judul')->contains($g['judul']));
        }
        $this->assertTrue(collect($galeri)->every(fn ($g) => $g['videoUrl'] === null));
    }

    public function test_video_hero_tidak_ditulis_pada_atribut_src(): void
    {
        // Video baru dipasang ketika modal dibuka; props hanya mengirim URL.
        $this->assertNotNull($this->props()['hero']['video']);
    }

    /* ---------- logo perusahaan ---------- */

    public function test_tanpa_logo_klien_bagian_itu_menampilkan_standar_yang_diacu(): void
    {
        $this->assertSame([], Media::klien());

        // Memajang logo perusahaan berarti menyatakan mereka memakai
        // platform ini. Selama belum ada yang menaruhnya, yang ditampilkan
        // adalah acuan yang memang dapat diperiksa kebenarannya.
        $props = $this->props();
        $this->assertSame([], $props['klien']);
        $this->assertTrue(collect($props['standar'])->pluck('kode')->contains('Kepdirjen 185.K/2019'));
        $this->assertFalse((bool) count($props['klien']));
    }

    public function test_logo_klien_dibaca_dari_folder(): void
    {
        $this->taruh('klien/tambang-nusantara.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $props = $this->props();
        $this->assertTrue(collect($props['klien'])->pluck('nama')->contains('Tambang Nusantara'));
    }

    /* ---------- isi halaman ---------- */

    public function test_tujuh_elemen_smkp_tampil_beserta_bobotnya(): void
    {
        $props = $this->props();

        $this->assertCount(7, Smkp::elemen());
        foreach (Smkp::elemen() as $e) {
            $this->assertTrue(collect($props['elemenSmkp'])->pluck('nama')->contains($e['nama']));
        }

        // Bobot elemen Implementasi paling besar; itu yang membedakannya
        // dari daftar tanpa arti.
        $this->assertTrue(collect($props['elemenSmkp'])->contains(fn ($e) => (int) $e['bobot'] === 35));
    }

    public function test_jumlah_modul_pada_hero_mengikuti_daftar_modul(): void
    {
        $jumlah = count(\App\Support\Modules::all());

        $this->assertSame($jumlah, count($this->props()['modul']));
        $this->assertGreaterThanOrEqual(6, $jumlah);
    }

    public function test_seluruh_tautan_navigasi_menunjuk_bagian_yang_ada(): void
    {
        $isi = file_get_contents(resource_path('js/Pages/Landing.vue'));

        foreach (['beranda', 'pilar', 'modul', 'fitur', 'alur', 'tentang'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $isi, "Bagian #{$id} tidak ada di halaman.");
        }
    }
}
