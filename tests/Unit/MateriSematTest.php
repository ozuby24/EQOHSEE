<?php

namespace Tests\Unit;

use App\Support\Materi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Jenis materi dan aturan sematan.
 *
 * Materi::semat() memutuskan apa yang boleh menjadi atribut `src` sebuah
 * <iframe> di halaman setiap peserta. Isinya berasal dari `materials.url`
 * — kolom yang diisi lewat halaman kelola, bukan kolom bentukan sistem.
 *
 * Yang dijaga di sini: alamat yang TIDAK cocok pola tidak pernah menjadi
 * iframe, melainkan kartu tautan biasa. Lepas, satu alamat `javascript:`
 * berjalan di dalam asal yang sama dengan aplikasinya, dan satu halaman
 * luar dapat menggambar formulir masuk palsu yang tampak berada di dalam
 * EQOHSEE.
 */
class MateriSematTest extends TestCase
{
    public static function ditolak(): array
    {
        return [
            'skema javascript'      => ['javascript:alert(1)'],
            'skema data'            => ['data:text/html,<script>alert(1)</script>'],
            'skema berkas'          => ['file:///etc/passwd'],
            'inang asing'           => ['https://contoh.test/video/abc'],

            /* Inang yang MEMUAT nama terdaftar tetapi bukan dia. Pemeriksaan
               dengan str_contains akan meloloskan ketiganya. */
            'inang berimbuhan'      => ['https://youtube.com.jahat.test/watch?v=abc'],
            'inang bersubdomain'    => ['https://youtube.com.evil.co/watch?v=abc'],
            'inang sebagai pengguna'=> ['https://youtube.com@jahat.test/watch?v=abc'],

            'youtube tanpa v'       => ['https://youtube.com/watch'],
            'youtube v kosong'      => ['https://youtube.com/watch?v='],
            'id bergaris miring'    => ['https://youtu.be/abc/../def'],
            'id bertanda kutip'     => ['https://youtu.be/abc%22onload%3Dalert(1)'],
            'kosong'                => [''],
            'bukan alamat'          => ['bukan alamat sama sekali'],
        ];
    }

    #[DataProvider('ditolak')]
    public function test_alamat_yang_tidak_boleh_disemat(string $url): void
    {
        $this->assertNull(Materi::semat($url), "Seharusnya ditolak: $url");
    }

    public static function diterima(): array
    {
        return [
            'youtube panjang' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                                  'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtube tanpa www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ',
                                    'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtube ponsel'  => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ',
                                  'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtu.be'        => ['https://youtu.be/dQw4w9WgXcQ',
                                  'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'vimeo'           => ['https://vimeo.com/123456789',
                                  'https://player.vimeo.com/video/123456789'],
            'vimeo pemutar'   => ['https://player.vimeo.com/video/123456789',
                                  'https://player.vimeo.com/video/123456789'],
        ];
    }

    #[DataProvider('diterima')]
    public function test_alamat_yang_boleh_disemat(string $url, string $harap): void
    {
        $this->assertSame($harap, Materi::semat($url));
    }

    public function test_alamat_semat_selalu_menuju_inang_yang_kita_pilih(): void
    {
        /* Bukan alamat aslinya yang diteruskan, melainkan alamat yang
           DISUSUN ULANG dari ID yang sudah disaring. Yang meneruskan
           alamat asli apa adanya akan lolos seluruh uji di atas dan
           tetap membawa parameter kueri apa pun yang menempel padanya. */
        $hasil = Materi::semat('https://www.youtube.com/watch?v=dQw4w9WgXcQ&autoplay=1&jahat=<script>');

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $hasil);
        $this->assertStringNotContainsString('jahat', (string) $hasil);
        $this->assertStringNotContainsString('autoplay', (string) $hasil);
    }

    public function test_jenis_yang_tidak_dikenal_jatuh_ke_berkas(): void
    {
        // Halaman kelola membatasi pilihannya, tetapi baris lama dibuat
        // sebelum pembatasan itu ada — dan jenis tak dikenal tidak boleh
        // menjatuhkan halaman materi.
        $this->assertSame('Berkas', Materi::label('entah-apa'));
        $this->assertSame('Berkas', Materi::label(null));
    }

    public function test_setiap_jenis_memakai_ikon_yang_memang_ada(): void
    {
        /* Ikon yang tidak dikenal IkonStat tergambar sebagai kotak
           bertitik — yang terbaca sebagai gambar gagal dimuat, bukan
           sebagai jenis yang belum berikon. Daftarnya dibaca dari
           berkas Vue-nya sendiri, bukan disalin ke sini: salinan akan
           tetap hijau justru ketika ikonnya dihapus dari sana. */
        $vue = file_get_contents(__DIR__.'/../../resources/js/Components/IkonStat.vue');

        preg_match('/const jalur: Record<string, string\[\]> = \{(.+?)\n\};/s', $vue, $m);
        $this->assertNotEmpty($m, 'Peta ikon tidak ditemukan di IkonStat.vue.');

        preg_match_all('/^\s{2}([a-z]+):\s*\[/m', $m[1], $nama);
        $tersedia = $nama[1];

        $this->assertNotEmpty($tersedia);

        foreach (Materi::JENIS as $kunci => [, $ikon]) {
            $this->assertContains($ikon, $tersedia,
                "Jenis '$kunci' memakai ikon '$ikon' yang tidak ada di IkonStat.");
        }
    }

    public function test_durasi_kosong_tidak_menjadi_nol_menit(): void
    {
        $this->assertNull(Materi::durasi(null));
        $this->assertNull(Materi::durasi(0));
        $this->assertSame('45 menit', Materi::durasi(45));
        $this->assertSame('1 jam', Materi::durasi(60));
        $this->assertSame('2 jam 15 menit', Materi::durasi(135));
    }
}
