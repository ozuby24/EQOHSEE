<?php

namespace Tests\Feature;

use App\Support\TanyaJawab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FAQPage yang diterbitkan harus sama persis dengan yang digambar.
 *
 * ── KENAPA INI PANTAS DIUJI ──
 *
 * Google menjatuhkan sanksi manual atas data terstruktur yang tidak
 * sesuai dengan isi yang terlihat di halaman. Sanksinya menimpa seluruh
 * domain, bukan satu halaman, dan pulihnya menuntut peninjauan manual
 * yang memakan minggu.
 *
 * Kegagalannya sendiri tidak terlihat dari mana pun: halaman tetap
 * tergambar benar, skemanya tetap sah menurut validator, dan tidak ada
 * satu pun galat. Yang berbeda hanya isinya — dan yang membandingkan
 * keduanya bukan manusia, melainkan perayap yang datang berminggu-minggu
 * kemudian.
 *
 * Daftar tanya jawab dan skemanya kini dibangun dari satu sumber, jadi
 * keduanya TIDAK DAPAT berbeda. Tes ini menjaga sifat itu tetap ada:
 * seseorang yang kelak menambahkan daftar kedua "supaya skemanya lebih
 * ringkas" akan dihentikan di sini, bukan oleh surel dari Search
 * Console.
 */
class TanyaJawabSkemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function skema_memuat_persis_pertanyaan_yang_sama(): void
    {
        $daftar = TanyaJawab::semua();
        $skema  = TanyaJawab::dataTerstruktur();

        $this->assertSame('FAQPage', $skema['@type']);
        $this->assertCount(count($daftar), $skema['mainEntity']);

        foreach ($daftar as $i => $butir) {
            $this->assertSame($butir['t'], $skema['mainEntity'][$i]['name']);
            $this->assertSame($butir['j'], $skema['mainEntity'][$i]['acceptedAnswer']['text']);
        }
    }

    #[Test]
    public function halaman_depan_menerbitkan_faqpage(): void
    {
        $isi = $this->get('/')->assertOk()->getContent();

        $blok = $this->blokLd($isi);
        $tipe = array_column($blok, '@type');

        $this->assertContains('FAQPage', $tipe);
        $this->assertContains('SoftwareApplication', $tipe);

        $faq = $blok[array_search('FAQPage', $tipe, true)];
        $this->assertCount(count(TanyaJawab::semua()), $faq['mainEntity']);
    }

    #[Test]
    public function pertanyaan_yang_diterbitkan_juga_dikirim_ke_layarnya(): void
    {
        /* Inti perkaranya: bukan sekadar skemanya ada, melainkan bahwa
           halaman yang memuatnya memang menggambar pertanyaan itu. */
        $isi = $this->get('/')->assertOk()->getContent();

        $faq = collect($this->blokLd($isi))->firstWhere('@type', 'FAQPage');

        foreach ($faq['mainEntity'] as $t) {
            $this->assertStringContainsString(
                e($t['name']),
                $isi,
                'Pertanyaan diterbitkan sebagai FAQPage tetapi tidak dikirim ke layarnya: '.$t['name'],
            );
        }
    }

    #[Test]
    public function halaman_lain_tidak_ikut_menerbitkannya(): void
    {
        /* FAQPage di halaman yang tidak memuat akordeonnya persis
           pelanggaran yang disanksikan. Katalog tidak punya bagian itu. */
        $tipe = array_column($this->blokLd($this->get('/katalog')->assertOk()->getContent()), '@type');

        $this->assertNotContains('FAQPage', $tipe);
    }

    /** @return array<int, array<string, mixed>> */
    private function blokLd(string $html): array
    {
        preg_match_all(
            '~<script type="application/ld\+json"[^>]*>(.*?)</script>~s',
            $html,
            $cocok,
        );

        return array_map(
            fn (string $j): array => json_decode(html_entity_decode($j), true) ?? [],
            $cocok[1],
        );
    }
}
