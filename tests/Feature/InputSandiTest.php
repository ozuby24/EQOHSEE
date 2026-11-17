<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kolom sandi selalu lewat satu komponen.
 *
 * Yang dijaga bukan tampilannya melainkan bahwa tidak ada halaman yang
 * kembali menulis kolom sandinya sendiri. Kolom telanjang tidak
 * menimbulkan galat apa pun — ia hanya kehilangan tombol intip, dan
 * kehilangan itu paling mungkin terjadi pada halaman yang baru ditulis,
 * yaitu tempat yang paling jarang dibuka orang untuk memeriksa.
 */
class InputSandiTest extends TestCase
{
    /** @return array<string,string> jalur => isi, seluruh berkas Vue halaman dan komponen. */
    private function berkasVue(): array
    {
        $isi = [];

        foreach (['js/Pages', 'js/Components'] as $sub) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path($sub)));

            foreach ($it as $f) {
                if (! $f->isFile() || $f->getExtension() !== 'vue') continue;
                if (str_contains($f->getFilename(), '.bak-')) continue;

                $isi[str_replace(resource_path().'/', '', $f->getPathname())]
                    = file_get_contents($f->getPathname());
            }
        }

        return $isi;
    }

    public function test_tidak_ada_halaman_yang_menulis_kolom_sandi_telanjang(): void
    {
        $langgar = [];

        foreach ($this->berkasVue() as $jalur => $isi) {
            if ($jalur === 'js/Components/InputSandi.vue') continue;

            if (str_contains($isi, 'type="password"')) $langgar[] = $jalur;
        }

        sort($langgar);

        $this->assertSame([], $langgar,
            "Kolom sandi ditulis langsung, bukan lewat <InputSandi>:\n  "
            .implode("\n  ", $langgar)
            ."\nKolom semacam itu kehilangan tombol intip, dan pada papan ketik ponsel "
            ."juga kehilangan penahan koreksi otomatis.");
    }

    /**
     * Tombolnya bertype="button", dan itu bukan kerapian.
     *
     * Tombol di dalam <form> tanpa type bawaannya SUBMIT. Tanpa baris
     * itu, menekan "lihat" mengirimkan formulirnya — pada halaman masuk
     * berarti satu percobaan masuk dengan sandi setengah diketik, yang
     * ikut menghabiskan jatah batas laju.
     */
    public function test_tombol_intip_tidak_mengirim_formulir(): void
    {
        $isi = file_get_contents(resource_path('js/Components/InputSandi.vue'));

        $this->assertMatchesRegularExpression(
            '/<button\s+type="button"/',
            $isi,
            'Tombol intip kehilangan type="button"; menekannya akan mengirim formulirnya.');
    }

    /**
     * Papan ketik ponsel tidak boleh mengoreksi sandi yang sedang diintip.
     *
     * Begitu type berubah menjadi `text`, papan ketik memperlakukannya
     * sebagai tulisan biasa: huruf pertama dibesarkan, kata yang tidak
     * dikenalnya diperbaiki. Sandinya berubah tanpa pemiliknya
     * menyadari, dan gejalanya sama persis dengan salah ketik biasa.
     */
    public function test_koreksi_otomatis_papan_ketik_ditahan(): void
    {
        $isi = file_get_contents(resource_path('js/Components/InputSandi.vue'));

        foreach (['autocapitalize="off"', 'autocorrect="off"', 'spellcheck="false"'] as $atribut) {
            $this->assertStringContainsString($atribut, $isi,
                "InputSandi kehilangan {$atribut}; sandi yang diintip di ponsel akan "
                ."diam-diam dikoreksi papan ketiknya.");
        }
    }

    /**
     * Tombolnya tetap dapat dicapai Tab.
     *
     * Sempat ditulis tabindex="-1" agar urutan Tab langsung menuju
     * tombol kirim — dan itu berarti yang tidak memakai tetikus tidak
     * dapat mengintip sandinya sama sekali. Tepat orang yang paling
     * terbantu fitur ini yang kehilangan aksesnya.
     */
    public function test_tombol_intip_tetap_terjangkau_papan_ketik(): void
    {
        $isi = file_get_contents(resource_path('js/Components/InputSandi.vue'));

        $this->assertStringNotContainsString('tabindex="-1"', explode('<template>', $isi)[1] ?? '',
            'Tombol intip dikeluarkan dari urutan Tab; pengguna papan ketik tidak lagi '
            .'dapat mengintip sandinya.');
    }
}
