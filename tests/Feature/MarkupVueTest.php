<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Komentar HTML tidak boleh berada di DALAM daftar atribut.
 *
 * Bentuk ini lolos semuanya kecuali peramban:
 *
 *     <div class="teks"
 *          <!-- alasan warnanya -->
 *          :class="...">
 *
 *  `npm run build` berhasil. `vue-tsc --noEmit` bersih. Lalu di
 *  peramban Vue mencoba memasang `<!--` sebagai nama atribut, melempar
 *  "'<!--' is not a valid attribute name", dan SELURUH halaman tidak
 *  tergambar — bukan satu elemen, melainkan seluruhnya, tanpa satu pun
 *  tanda pada berkas hasil bangun.
 *
 *  Terjadi sungguhan saat menuliskan alasan sebuah pilihan warna tepat
 *  di sebelah atribut yang dijelaskannya — tempat yang paling masuk
 *  akal untuk menaruhnya, dan justru satu-satunya tempat yang tidak
 *  boleh. Komentarnya harus berada di atas tag pembuka.
 */
class MarkupVueTest extends TestCase
{
    public function test_tidak_ada_komentar_di_dalam_tag(): void
    {
        $langgar = [];

        foreach (['js/Pages', 'js/Components', 'js/Layouts'] as $sub) {
            $akar = resource_path($sub);
            if (!is_dir($akar)) continue;

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));

            foreach ($it as $f) {
                if (!$f->isFile() || $f->getExtension() !== 'vue') continue;
                if (str_contains($f->getFilename(), '.bak-')) continue;

                $isi = file_get_contents($f->getPathname());

                // Hanya bagian <template>: komentar /* */ di dalam
                // <script> memang wajar, dan `<!--` tidak berarti apa pun
                // di sana.
                if (!preg_match('#<template>(.*)</template>#s', $isi, $t)) continue;

                /* Sebuah tag pembuka yang belum ditutup `>` tetapi sudah
                   memuat `<!--`. Pola sengaja tidak melewati `>` mana pun,
                   supaya komentar yang berada di ANTARA dua elemen — yang
                   sah — tidak ikut tertangkap. */
                if (preg_match_all('/<[a-zA-Z][^>]*?<!--/s', $t[1], $c)) {
                    foreach ($c[0] as $potong) {
                        $langgar[] = basename(dirname($f->getPathname())).'/'.$f->getFilename()
                            .': '.trim(preg_replace('/\s+/', ' ', mb_substr($potong, 0, 60)));
                    }
                }
            }
        }

        sort($langgar);

        $this->assertSame([], $langgar,
            "Komentar HTML berada di dalam daftar atribut. Vue akan memperlakukan `<!--`\n"
            ."sebagai nama atribut dan SELURUH halaman gagal tergambar — tanpa galat saat\n"
            ."membangun. Pindahkan komentarnya ke atas tag pembuka:\n  "
            .implode("\n  ", $langgar));
    }
}
