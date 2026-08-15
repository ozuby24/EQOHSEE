<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penjaga penerimaan prop pada halaman Vue.
 *
 * Sepuluh halaman modul pernah merender kosong seluruhnya, dan tidak
 * satu pun uji yang ada menangkapnya. Sebabnya satu baris yang terbaca
 * benar:
 *
 *     defineProps<{ mode: string; [key: string]: any }>()
 *
 * Terbaca seolah "terima prop apa saja". Yang sebenarnya terjadi:
 * penyusun Vue tidak dapat menurunkan nama prop dari sebuah index
 * signature, sehingga hanya `mode` yang terdaftar. Seluruh prop lain
 * jatuh ke $attrs, dan karena halaman-halaman itu berakar jamak
 * (<Head> beserta pembungkusnya) atribut itu tidak tersangkut di mana
 * pun. Halaman terbuka, bilah samping tampil, judul benar — dan seluruh
 * isinya nol.
 *
 * Yang membuatnya bertahan lama justru bentuk pengujian yang dipakai:
 * assertInertia memeriksa prop yang DIKIRIM server, dan seluruhnya
 * memang benar. Yang salah adalah penerimaannya di sisi peramban, dan
 * di situ tidak ada uji sama sekali. Uji ini menutup celah itu dari
 * sisi berkasnya, sebab menjalankan peramban di CI tidak tersedia.
 */
class PropHalamanTest extends TestCase
{
    /** @return list<string> */
    private function halaman(): array
    {
        $dasar = resource_path('js/Pages');
        $keluar = [];

        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dasar));
        foreach ($iter as $berkas) {
            if ($berkas->isFile() && $berkas->getExtension() === 'vue') {
                $keluar[] = $berkas->getPathname();
            }
        }

        sort($keluar);

        return $keluar;
    }

    private function pendek(string $jalur): string
    {
        return str_replace(resource_path('js/Pages').'/', '', $jalur);
    }

    /**
     * Isi berkas tanpa komentar blok.
     *
     * Catatan yang menjelaskan jebakan ini menyebut bentuk yang salah
     * secara harfiah di dalam teksnya. Tanpa pembuangan komentar,
     * penjaga di bawah menuduh kesebelas halaman yang justru sudah
     * diperbaiki — dan penjaga yang tertipu oleh komentarnya sendiri
     * lebih buruk daripada tidak ada penjaga.
     */
    private function kode(string $jalur): string
    {
        return preg_replace('#/\*.*?\*/#s', '', file_get_contents($jalur));
    }

    #[Test]
    public function tidak_ada_halaman_yang_mendeklarasikan_prop_lewat_index_signature(): void
    {
        $langgar = [];

        foreach ($this->halaman() as $jalur) {
            // Hanya blok defineProps yang diperiksa; index signature di
            // tempat lain (mis. Record bebas pada state lokal) tidak ada
            // hubungannya dengan penerimaan prop.
            if (!preg_match_all('/defineProps<\{(.*?)\}>\(/s', $this->kode($jalur), $cocok)) continue;

            foreach ($cocok[1] as $badan) {
                if (preg_match('/\[\s*\w+\s*:\s*string\s*\]\s*:/', $badan)) {
                    $langgar[] = $this->pendek($jalur);
                    break;
                }
            }
        }

        $this->assertSame([], $langgar,
            "defineProps dengan index signature hanya mendaftarkan prop yang disebut namanya; "
            ."sisanya hilang diam-diam dan halaman merender kosong. Sebutkan tiap prop satu per satu, "
            ."atau ambil seluruhnya lewat usePage().props:\n- ".implode("\n- ", $langgar));
    }

    #[Test]
    public function halaman_yang_memakai_props_punya_sumber_propnya(): void
    {
        $langgar = [];

        foreach ($this->halaman() as $jalur) {
            $kode = $this->kode($jalur);

            if (!preg_match('/\bprops\.\w/', $kode)) continue;

            $punyaDefine = str_contains($kode, 'defineProps');
            $punyaUsePage = preg_match('/const\s+props\s*=\s*usePage/', $kode) === 1;

            if (!$punyaDefine && !$punyaUsePage) {
                $langgar[] = $this->pendek($jalur);
            }
        }

        $this->assertSame([], $langgar,
            "Halaman memakai props.* tetapi tidak pernah mengambilnya:\n- ".implode("\n- ", $langgar));
    }

    /**
     * Yang mengambil prop lewat usePage harus benar-benar mengimpornya.
     *
     * Bukan kemungkinan teoretis: saat perbaikan ini dikerjakan, empat
     * halaman berpindah ke usePage() tanpa impornya ikut ditambahkan.
     * Build tetap berhasil — Vite tidak menolak pengenal yang tidak
     * dikenal di dalam <script setup> — dan keempat halaman itu blank
     * putih di peramban dengan 'usePage is not defined' hanya di konsol.
     */
    #[Test]
    public function halaman_yang_memakai_use_page_mengimpornya(): void
    {
        $langgar = [];

        foreach ($this->halaman() as $jalur) {
            $kode = $this->kode($jalur);

            if (!str_contains($kode, 'usePage')) continue;

            if (!preg_match("/import\s*\{[^}]*\busePage\b[^}]*\}\s*from\s*'@inertiajs\/vue3'/", $kode)) {
                $langgar[] = $this->pendek($jalur);
            }
        }

        $this->assertSame([], $langgar,
            "usePage dipakai tanpa diimpor — halaman akan blank putih:\n- ".implode("\n- ", $langgar));
    }
}
