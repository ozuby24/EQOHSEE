<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * `:global(...)` di gaya scoped tidak boleh diikuti pemilih lain.
 *
 * Penyusun gaya scoped Vue MENGGANTI seluruh pemilih dengan isi
 * `:global()`-nya; sisanya dibuang tanpa peringatan. Jadi
 *
 *     :global([data-tema='gelap']) .eq-muat-keping { background: … }
 *
 * keluar sebagai `[data-tema=gelap] { background: … }` — aturannya jatuh
 * pada <html>, bukan pada komponennya. Begitulah 92 aturan mode gelap di
 * sepuluh komponen (tur selamat datang, materi belajar, kurikulum,
 * pengumuman, dialog register, monitor hazard, galeri, isian sandi,
 * Turnstile, indikator muat) tidak pernah berlaku: komponennya tetap
 * bergaya terang di tengah halaman gelap, sementara <html> menerima
 * latar, warna teks, dan bayangan milik belasan komponen sekaligus.
 *
 * Bentuk yang benar cukup `[data-tema='gelap'] .kelas`: Vue hanya
 * menandai bagian terakhir pemilih, jadi leluhurnya tetap boleh berada
 * di luar komponen.
 */
class PemilihGlobalTest extends TestCase
{
    public function test_global_di_gaya_komponen_tidak_diikuti_pemilih_lain(): void
    {
        $akar = resource_path('js');
        $temuan = [];
        $diperiksa = 0;

        $berkas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar, \FilesystemIterator::SKIP_DOTS));

        foreach ($berkas as $f) {
            if ($f->getExtension() !== 'vue') continue;

            $isi = (string) file_get_contents($f->getPathname());
            if (!preg_match_all('#<style\b[^>]*>(.*?)</style>#s', $isi, $gaya, PREG_OFFSET_CAPTURE)) continue;

            foreach ($gaya[1] as [$css, $awal]) {
                $diperiksa++;

                // Komentar dikosongkan tanpa menggeser nomor baris.
                $css = preg_replace_callback('#/\*.*?\*/#s', fn ($m) => preg_replace('/[^\n]/', ' ', $m[0]), $css);

                // :global(…) lalu apa pun selain '{' atau ',' — termasuk
                // pseudo-kelas yang menempel, yang juga ikut dibuang.
                if (!preg_match_all('/:global\((?:[^()]|\([^()]*\))*\)\s*(?![\s{,]|$)/', $css, $m, PREG_OFFSET_CAPTURE)) continue;

                foreach ($m[0] as [$teks, $posisi]) {
                    $baris = substr_count(substr($isi, 0, $awal + $posisi), "\n") + 1;
                    $temuan[] = substr($f->getPathname(), strlen($akar) + 1).":{$baris}  ".trim($teks).'…';
                }
            }
        }

        $this->assertGreaterThan(50, $diperiksa, 'Blok <style> komponen tidak terbaca — pemeriksaannya kosong.');

        $this->assertSame([], $temuan,
            "Pemilih sesudah :global(...) dibuang penyusun Vue, jadi aturannya jatuh pada elemen yang salah:\n  "
            .implode("\n  ", $temuan)
            ."\nTulis `[data-tema='gelap'] .kelas` tanpa :global().");
    }

    /** Pemeriksaannya sendiri harus menangkap bentuk yang salah dan membiarkan yang benar. */
    public function test_pemeriksaannya_membedakan_bentuk_salah_dan_benar(): void
    {
        $pola = '/:global\((?:[^()]|\([^()]*\))*\)\s*(?![\s{,]|$)/';

        foreach ([
            ":global([data-tema='gelap']) .eq-tur { color: red; }",
            ":global(.gelap):hover { color: red; }",
            ":global(:is(.a, .b)) > .c { color: red; }",
        ] as $salah) {
            $this->assertMatchesRegularExpression($pola, $salah, "Tidak tertangkap: {$salah}");
        }

        foreach ([
            "[data-tema='gelap'] .eq-tur { color: red; }",
            ":global(.eq-pop-masuk) { opacity: 0; }",
            ":global(.a),\n:global(.b) { color: red; }",
            ":global(:is(.a, .b)) { color: red; }",
        ] as $benar) {
            $this->assertDoesNotMatchRegularExpression($pola, $benar, "Salah tangkap: {$benar}");
        }
    }
}
