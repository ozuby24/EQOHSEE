<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Setiap kartu berlatar putih harus punya pasangan latar mode gelap —
 * pada ELEMEN YANG SAMA.
 *
 * ModeGelapTest yang sudah ada menjaga warna TEKS. Yang lolos darinya
 * adalah pasangan sebaliknya: teksnya benar diterangkan, tetapi latar
 * kartunya tetap putih, sehingga tulisan #E8EFF2 duduk di atas #FFFFFF
 * pada 1,16:1.
 *
 * Dua cara ia terjadi, dan keduanya pernah terjadi sekaligus:
 *
 * 1. Pemilihnya menyasar WADAH, bukan kartunya. `.eq-modul` terdaftar
 *    padahal latar putihnya ada pada `.eq-modul a`. Wadahnya menjadi
 *    gelap — sehingga sepintas daftar itu tampak lengkap — sementara
 *    kartu di atasnya tetap putih.
 *
 * 2. Pemilihnya tidak pernah didaftarkan sama sekali.
 *    `.eq-admin-angka` berbagi satu baris deklarasi dengan
 *    `.eq-kategori a` yang terdaftar, dan ikut terlewat begitu saja.
 *
 * Keduanya tidak menimbulkan galat, tidak tertangkap uji sisi server,
 * dan tidak terlihat sama sekali pada mode terang — yang dipakai orang
 * yang menuliskannya. Yang melihatnya adalah orang yang bekerja malam
 * di ruang kendali.
 *
 * Diuji dengan mencocokkan teks pemilih, bukan nama kelas: pencocokan
 * nama kelas akan menganggap `.eq-modul` sudah mewakili `.eq-modul a`,
 * dan justru itulah cacat nomor satu di atas.
 */
class ModeGelapLatarTest extends TestCase
{
    private const BERKAS = 'views/partials/eq-visual.blade.php';

    private function gaya(): string
    {
        return file_get_contents(resource_path(self::BERKAS));
    }

    /** Samakan bentuk pemilih supaya `a > b` dan `a b` dapat dibandingkan. */
    private function rapikan(string $pemilih): string
    {
        $p = preg_replace('/\s*>\s*/', ' ', trim($pemilih));
        $p = preg_replace('/\s+/', ' ', $p ?? '');

        return trim($p ?? '');
    }

    /**
     * @param  string  $isi
     * @return list<array{pemilih:string,badan:string}>
     */
    private function aturan(string $isi): array
    {
        // Komentar dibuang lebih dulu: contoh kode di dalamnya bukan aturan.
        $isi = preg_replace('#/\*.*?\*/#s', '', $isi) ?? $isi;

        preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $isi, $c, PREG_SET_ORDER);

        return array_map(fn ($m) => ['pemilih' => trim($m[1]), 'badan' => $m[2]], $c);
    }

    public function test_setiap_kartu_putih_punya_pasangan_latar_gelap(): void
    {
        $aturan = $this->aturan($this->gaya());

        // Pemilih mode gelap yang benar-benar menetapkan latar.
        $gelap = [];
        foreach ($aturan as $a) {
            if (!str_contains($a['pemilih'], '[data-tema="gelap"]')) continue;
            if (!preg_match('/(^|;|\s)background\s*:/i', $a['badan'])) continue;

            foreach (explode(',', $a['pemilih']) as $satu) {
                $bersih = str_replace(':root[data-tema="gelap"]', '', $satu);
                $bersih = preg_replace('/^\s*main\s+/', '', $this->rapikan($bersih));

                $gelap[$this->rapikan($bersih ?? '')] = true;
            }
        }

        $tanpaPasangan = [];

        foreach ($aturan as $a) {
            if (str_contains($a['pemilih'], '[data-tema="gelap"]')) continue;
            if (!preg_match('/background\s*:\s*(#fff\b|#ffffff\b|white\b)/i', $a['badan'])) continue;

            foreach (explode(',', $a['pemilih']) as $satu) {
                $p = $this->rapikan($satu);

                if ($p === '') continue;

                /* Keadaan sesaat tidak dituntut: :hover dan kawannya
                   hanya berlaku selagi kursor di atasnya, dan warnanya
                   memang ditentukan terpisah. */
                if (preg_match('/:(hover|focus|active|visited|checked|disabled)/', $p)) continue;

                $p = preg_replace('/^\s*main\s+/', '', $p) ?? $p;

                if (!isset($gelap[$p])) $tanpaPasangan[] = $p;
            }
        }

        $tanpaPasangan = array_values(array_unique($tanpaPasangan));
        sort($tanpaPasangan);

        $this->assertSame([], $tanpaPasangan,
            "Kartu berlatar putih tanpa pasangan latar mode gelap pada elemen yang sama.\n"
            ."Teks sudah diterangkan oleh --eq-judul, sehingga hasilnya tulisan nyaris putih\n"
            ."di atas kartu putih:\n  ".implode("\n  ", $tanpaPasangan));
    }
}
