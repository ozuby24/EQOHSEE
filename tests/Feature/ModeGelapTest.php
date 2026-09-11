<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Keterbacaan mode gelap.
 *
 * Tailwind menyusun tangga warna *-600 dan *-700 untuk dibaca di atas
 * putih, dan tangga *-50 sebagai latar yang sangat muda. Di mode gelap
 * kartunya menjadi gelap tetapi kelas-kelas itu tidak ikut berubah,
 * sehingga angka yang dicetak besar justru menghilang — terukur 1,64:1
 * pada text-stone-700 di atas kartu gelap, dan itu terjadi persis pada
 * mode yang dipakai orang saat bekerja malam di ruang kendali.
 *
 * Diuji secara statis, tanpa peramban: tiap kelas bernada terang yang
 * benar-benar dipakai halaman harus punya pasangannya pada blok
 * [data-tema="gelap"]. Pemeriksaan kontras yang sesungguhnya menuntut
 * piksel yang tergambar, dan itu terlalu berat untuk dijalankan pada
 * tiap perubahan — sedangkan cacat yang sebenarnya selalu berbentuk
 * sama: kelas dipakai, pasangannya lupa ditulis.
 */
class ModeGelapTest extends TestCase
{
    private function gaya(): string
    {
        return file_get_contents(resource_path('views/partials/eq-visual.blade.php'))
             . file_get_contents(resource_path('css/app.css'));
    }

    /** Seluruh kelas Tailwind yang dipakai berkas Vue di bawah Pages dan Components. */
    private function kelasDipakai(string $pola): array
    {
        $ketemu = [];

        foreach (['js/Pages', 'js/Components'] as $sub) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path($sub)));
            foreach ($it as $f) {
                if (!$f->isFile() || $f->getExtension() !== 'vue') continue;
                if (str_contains($f->getFilename(), '.bak-')) continue;

                // Halaman cetak selalu di atas kertas putih; mode gelap
                // tidak berlaku di sana, dan memaksanya justru salah.
                if (str_contains($f->getPathname(), '/Pages/Print/')) continue;

                preg_match_all($pola, file_get_contents($f->getPathname()), $c);
                foreach ($c[0] as $k) $ketemu[$k] = true;
            }
        }

        return array_keys($ketemu);
    }

    public function test_warna_teks_bernada_gelap_punya_pasangan_mode_gelap(): void
    {
        $gaya = $this->gaya();

        // *-600 dan *-700 dirancang untuk latar putih.
        $dipakai = $this->kelasDipakai('/\btext-(?:stone|slate|gray|zinc|neutral|sky|violet|emerald|amber|orange|red|blue|green|teal|indigo|purple|rose)-(?:600|700|800|900)\b/');

        $tanpaPasangan = [];
        foreach ($dipakai as $k) {
            if (!str_contains($gaya, '.'.$k.'{') && !str_contains($gaya, '.'.$k.',')) {
                $tanpaPasangan[] = $k;
            }
        }

        sort($tanpaPasangan);
        $this->assertSame([], $tanpaPasangan,
            'Kelas ini dipakai halaman tetapi tidak punya aturan mode gelap: '
            .implode(', ', $tanpaPasangan));
    }

    /**
     * Tinta gelap tidak boleh dipatok lewat gaya SEBARIS.
     *
     * Gaya sebaris mengalahkan aturan [data-tema="gelap"] betapa pun
     * tegasnya aturan itu ditulis — tidak ada selektor yang dapat
     * menyaingi `style="color:#292524"`. Akibatnya bukan warna yang
     * kurang tepat melainkan angka yang HILANG: tinta hampir hitam di
     * atas kartu gelap.
     *
     * Terjadi pada Grafik/Meter.vue, dan karena komponen itu dipakai
     * bersama, angka pokok pada empat dasbor sekaligus — SMKP,
     * Keselamatan Operasi, Energi, dan Kesiapan Alat — tidak terbaca
     * pada mode gelap. Tidak ada galat; hanya kartu yang tampak kosong.
     *
     * Yang dilarang hanya tinta GELAP. Abu-abu penanda "tidak ada data"
     * (#A8A29E dan sekerabatnya) sama terbacanya pada kedua tema dan
     * memang harus tetap sama di keduanya.
     */
    public function test_komponen_grafik_tidak_memaku_tinta_gelap_sebaris(): void
    {
        $langgar = [];

        foreach (glob(resource_path('js/Grafik/*.vue')) as $berkas) {
            $isi = preg_replace('#/\*.*?\*/#s', '', file_get_contents($berkas)) ?? '';

            /* Hanya yang berada di dalam :style / v-bind:style. Warna
               yang sama di dalam <style> atau sebagai isian SVG bukan
               soal: keduanya masih dapat ditimpa. */
            preg_match_all('/:style="[^"]*"/', $isi, $c);

            foreach ($c[0] as $gaya) {
                if (! preg_match_all('/#([0-9A-Fa-f]{6})/', $gaya, $w)) continue;

                foreach ($w[1] as $hex) {
                    $r = hexdec(substr($hex, 0, 2));
                    $g = hexdec(substr($hex, 2, 2));
                    $b = hexdec(substr($hex, 4, 2));

                    /* Terang relatif kasar; yang dicari tinta yang
                       memang gelap, bukan warna keadaan yang pekat. */
                    $terang = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;

                    if ($terang < 0.30) $langgar[] = basename($berkas)." :: #{$hex}";
                }
            }
        }

        sort($langgar);

        $this->assertSame([], $langgar,
            "Tinta gelap dipatok pada gaya sebaris; mode gelap tidak dapat menimpanya:\n  "
            .implode("\n  ", array_unique($langgar)));
    }

    public function test_latar_bernada_terang_punya_pasangan_mode_gelap(): void
    {
        $gaya = $this->gaya();

        // *-50 adalah latar yang sangat muda; dibiarkan apa adanya ia
        // tetap krem di tengah halaman gelap, dan teks di atasnya —
        // yang sudah ikut dipetakan ke tangga terang — menjadi lebih
        // sulit dibaca daripada sebelum diperbaiki.
        $dipakai = $this->kelasDipakai('/\bbg-(?:red|amber|emerald|sky|blue|green|violet|orange|rose)-50\b/');

        $tanpaPasangan = [];
        foreach ($dipakai as $k) {
            if (!str_contains($gaya, '.'.$k.'{') && !str_contains($gaya, '.'.$k.',')) {
                $tanpaPasangan[] = $k;
            }
        }

        sort($tanpaPasangan);
        $this->assertSame([], $tanpaPasangan,
            'Latar muda ini dipakai halaman tetapi tidak digelapkan pada mode gelap: '
            .implode(', ', $tanpaPasangan));
    }

    /**
     * Teks beralfa rendah di atas navy.
     *
     * Nilai di bawah .5 pada latar segelap bilah samping jatuh di bawah
     * ambang keterbacaan. Yang diperiksa hanya bilah samping dan kakinya,
     * tempat latarnya memang selalu gelap dan diketahui.
     */
    public function test_tidak_ada_teks_beralfa_sangat_rendah_di_bilah_samping(): void
    {
        $isi = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        preg_match_all('/\btext-white\/(\d{1,2})\b/', $isi, $c);

        $terlalu = array_values(array_unique(array_filter(
            $c[1],
            fn ($a) => (int) $a < 50,
        )));

        sort($terlalu);
        $this->assertSame([], $terlalu,
            'text-white/'.implode(', text-white/', $terlalu).' terlalu redup untuk dibaca di atas navy.');
    }
}
