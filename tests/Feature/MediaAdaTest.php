<?php

namespace Tests\Feature;

use App\Support\Media;
use Tests\TestCase;

/**
 * Berkas media yang disebut kode harus benar-benar ada.
 *
 * Media::url() sengaja mengembalikan null bila berkasnya belum ditaruh,
 * dan tampilan memakai `v-if`/`@if` di atasnya. Itu perilaku yang benar:
 * halaman tetap utuh walau gambarnya belum diunggah.
 *
 * Justru karena itu berkas yang HILANG tidak menimbulkan galat apa pun.
 * Gambarnya tidak muncul, tata letaknya tetap rapi, dan tidak ada satu
 * baris pun di log. Yang menyadarinya adalah orang yang membuka
 * halamannya dan ingat bahwa dulu ada gambar di sana.
 *
 * Persis itu yang terjadi: satu commit membersihkan delapan berkas
 * galeri yang dikiranya tidak terpakai, sementara dua di antaranya
 * masih disebut — `galeri/budaya.jpg` oleh hero dasbor, dan
 * `galeri/operasional.mp4` oleh halaman depan. Yang pertama tidak punya
 * cadangan sama sekali, sehingga hero-nya kehilangan gambarnya begitu
 * cabang itu diterbitkan ke server, dan berkasnya ikut terhapus oleh
 * `git reset --hard` karena memang dilacak Git.
 */
class MediaAdaTest extends TestCase
{
    /** @return list<string> direktori yang dipindai */
    private function sumber(): array
    {
        return [app_path(), resource_path('views'), resource_path('js'), config_path()];
    }

    /**
     * Jalur media yang disebut sebagai teks tetap di dalam kode.
     *
     * @return array<string,list<string>> jalur => berkas yang menyebutnya
     */
    private function disebut(): array
    {
        $temu = [];

        foreach ($this->sumber() as $akar) {
            if (!is_dir($akar)) continue;

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));

            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                if (!in_array($f->getExtension(), ['php', 'vue', 'js', 'ts'], true)) continue;

                $isi = file_get_contents($f->getPathname());

                // Media::url('galeri/budaya.jpg') dan Media::ada('...')
                preg_match_all(
                    "/Media::(?:url|ada)\(\s*'([^']+\.[a-z0-9]{2,4})'\s*\)/i",
                    $isi, $c
                );

                foreach ($c[1] as $jalur) {
                    $temu[$jalur][] = str_replace(base_path().'/', '', $f->getPathname());
                }
            }
        }

        return $temu;
    }

    public function test_berkas_media_yang_disebut_kode_benar_benar_ada(): void
    {
        $disebut = $this->disebut();

        $this->assertNotEmpty($disebut,
            'Tidak satu pun panggilan Media::url() ditemukan — pemindainya yang rusak, '
            .'bukan medianya yang bersih.');

        $hilang = [];

        foreach ($disebut as $jalur => $penyebut) {
            if (Media::ada($jalur)) continue;

            $hilang[] = $jalur.'  ← '.implode(', ', array_unique($penyebut));
        }

        sort($hilang);

        $this->assertSame([], $hilang,
            "Berkas media disebut kode tetapi tidak ada di public/".Media::akar()."/.\n"
            ."Gambarnya tidak akan muncul, dan tidak ada galat apa pun yang menandainya:\n  "
            .implode("\n  ", $hilang));
    }

    /**
     * Jalur yang dipakai sebagai tetapan kelas Media juga harus ada —
     * hero, poster, dan latar halaman masuk tidak lewat teks tetap di
     * pemanggilnya, melainkan lewat konstanta.
     */
    public function test_berkas_hero_dan_halaman_masuk_ada(): void
    {
        $ref = new \ReflectionClass(Media::class);
        $hilang = [];

        foreach ($ref->getConstants() as $nama => $nilai) {
            if (!is_string($nilai)) continue;
            if (!preg_match('/\.[a-z0-9]{2,4}$/i', $nilai)) continue;

            if (!Media::ada($nilai)) $hilang[] = "{$nama} = {$nilai}";
        }

        sort($hilang);

        $this->assertSame([], $hilang,
            "Tetapan media menunjuk berkas yang tidak ada:\n  ".implode("\n  ", $hilang));
    }
}
