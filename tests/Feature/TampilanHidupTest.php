<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Setiap berkas Blade harus dapat dicapai dari titik masuk yang nyata.
 *
 * Aplikasi ini pernah menyimpan 104 berkas Blade yang tidak dapat dicapai
 * siapa pun. Nol controller memakai `return view()` — seluruhnya sudah
 * Inertia — tetapi berkasnya tetap ada, tetap terbaca seperti kode hidup,
 * dan tetap ikut dibaca oleh siapa pun yang mencari sesuatu.
 *
 * Kerugiannya nyata dan sudah terjadi berkali-kali:
 *
 *   Meta SEO disunting di landing.blade.php dan tidak pernah sampai ke
 *   mana pun, sebab halaman pendaratan yang hidup adalah komponen Vue.
 *
 *   Pemuat Chart.js dari CDN masih tertulis di partial yang tak seorang
 *   pun panggil, sehingga pencarian "dari mana skrip luar ini datang"
 *   berakhir pada berkas yang salah.
 *
 *   certificates/show.blade.php.bak-20260728 berisi templat sertifikat
 *   yang hampir sama dengan yang hidup, lengkap dengan tanda tangannya,
 *   dan yang membacanya tidak punya cara mengetahui mana yang dipakai.
 *
 * Semuanya kegagalan yang tidak menimbulkan galat: menyunting berkas mati
 * selalu "berhasil". Uji ini yang menggantikan galat yang tidak pernah
 * datang itu.
 */
class TampilanHidupTest extends TestCase
{
    public function test_setiap_berkas_blade_dapat_dicapai(): void
    {
        $semua  = $this->semuaTampilan();
        $hidup  = $this->telusuri(array_keys($this->titikMasuk()));

        $mati = array_diff(array_keys($semua), $hidup);

        $this->assertSame([], array_values($mati),
            "Berkas Blade berikut tidak dapat dicapai dari titik masuk mana pun:\n  "
            .implode("\n  ", $mati)
            ."\n\nBila memang masih dipakai, sebutkan pemanggilnya. Bila tidak, buang berkasnya.");
    }

    /** Dan titik masuknya sendiri harus benar-benar ada. */
    public function test_titik_masuk_menunjuk_berkas_yang_ada(): void
    {
        $semua = $this->semuaTampilan();

        foreach ($this->titikMasuk() as $nama => $dari) {
            $this->assertArrayHasKey($nama, $semua,
                "$dari memanggil view '$nama' yang berkasnya tidak ada.");
        }
    }

    /* ─────────── bantu ─────────── */

    /** @return array<string,string> nama titik => jalur berkas */
    private function semuaTampilan(): array
    {
        $akar = resource_path('views');
        $out  = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));
        foreach ($it as $f) {
            if (!$f->isFile() || !str_ends_with($f->getFilename(), '.blade.php')) continue;

            $rel = substr($f->getPathname(), strlen($akar) + 1);
            $out[str_replace(['/', '.blade.php'], ['.', ''], $rel)] = $f->getPathname();
        }

        return $out;
    }

    /**
     * View yang disebut dari PHP di luar resources/views.
     *
     * @return array<string,string> nama view => berkas yang menyebutnya
     */
    private function titikMasuk(): array
    {
        $out = [];

        foreach (['app', 'config', 'routes', 'bootstrap'] as $dir) {
            $jalur = base_path($dir);
            if (!is_dir($jalur)) continue;

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($jalur));
            foreach ($it as $f) {
                if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) continue;

                $isi = file_get_contents($f->getPathname());
                $dari = str_replace(base_path().'/', '', $f->getPathname());

                foreach ([
                    "/(?:view|make|markdown)\(\s*'([a-z0-9_.\-]+)'/i",
                    "/rootView[^=]*=\s*'([a-z0-9_.\-]+)'/",
                ] as $pola) {
                    if (!preg_match_all($pola, $isi, $m)) continue;
                    foreach ($m[1] as $v) $out[$v] ??= $dari;
                }
            }
        }

        /* Hanya yang benar-benar berupa berkas Blade. `view('ok')` pada
           pesan kilat dan sejenisnya ikut tertangkap pola di atas, dan
           menuntutnya berupa berkas akan menuduh yang tidak bersalah. */
        return array_intersect_key($out, $this->semuaTampilan());
    }

    /**
     * Telusuri @extends, @include, dan komponen <x-…> secara transitif.
     *
     * @param  list<string>  $mulai
     * @return list<string>
     */
    private function telusuri(array $mulai): array
    {
        $semua  = $this->semuaTampilan();
        $hidup  = [];
        $antre  = $mulai;

        while ($antre) {
            $v = array_pop($antre);
            if (isset($hidup[$v]) || !isset($semua[$v])) continue;

            $hidup[$v] = true;
            $isi = file_get_contents($semua[$v]);

            if (preg_match_all(
                "/@(?:extends|include|includeIf|includeWhen|includeFirst|each|component)\(\s*'([a-z0-9_.\-]+)'/i",
                $isi, $m
            )) {
                foreach ($m[1] as $x) $antre[] = $x;
            }

            /* <x-nama.bagian> memetakan ke components.nama.bagian. */
            if (preg_match_all('/<x-([a-z0-9.\-]+)/i', $isi, $m)) {
                foreach ($m[1] as $x) $antre[] = 'components.'.str_replace('-', '.', $x);
            }
        }

        return array_keys($hidup);
    }
}
