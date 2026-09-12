<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga lapisan tampilan baru: kop bergambar dan ubin angka.
 *
 * Ketiga cacat yang ditutup berkas ini sudah pernah terjadi sekali, dan
 * tidak satu pun melempar galat. Kop yang kehilangan tirainya menaruh
 * teks putih di atas foto matahari terbenam dan membuat judulnya lenyap
 * pada sepertiga gambar yang terang. Nada ubin yang lupa diberi pasangan
 * mode gelap tampil sebagai kotak muda menyilaukan di tengah kartu yang
 * gelap. Dan label yang lupa dijadikan block duduk berdempetan dengan
 * kolom isiannya — bukan di atasnya — di enam puluh lima tempat
 * sekaligus, pada halaman yang tetap berfungsi sepenuhnya.
 *
 * Semuanya diperiksa secara statis. Yang dijaga bukan rupa halamannya,
 * melainkan hal-hal yang bila hilang tidak menimbulkan tanda apa pun.
 */
class LapisanTampilanTest extends TestCase
{
    private function kop(): string
    {
        return file_get_contents(resource_path('js/Components/KopHalaman.vue'));
    }

    private function ubin(): string
    {
        return file_get_contents(resource_path('js/Components/UbinAngka.vue'));
    }

    /** @return list<\SplFileInfo> */
    private function halamanHris(): array
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js/Pages/Hris')));

        $berkas = [];
        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'vue') continue;
            if (str_contains($f->getFilename(), '.bak-')) continue;
            $berkas[] = clone $f;
        }

        sort($berkas);

        return $berkas;
    }

    public function test_kop_memakai_tirai_gelap_di_atas_fotonya(): void
    {
        $kop = $this->kop();

        // Teksnya putih tanpa syarat. Tanpa tirai yang menggelapkan
        // fotonya, judulnya hilang persis pada bagian gambar yang paling
        // terang — dan gambar yang dipakai adalah matahari terbenam.
        $this->assertStringContainsString('kop-tirai', $kop,
            'Kop kehilangan lapisan tirainya; teks putih akan ditaruh langsung di atas foto.');

        $this->assertMatchesRegularExpression('/\.kop-tirai\s*\{[^}]*linear-gradient/s', $kop,
            'kop-tirai ada tetapi tidak menggambar gradien apa pun.');

        $this->assertMatchesRegularExpression('/\.kop-tirai\s*\{[^}]*rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*\.?9/s', $kop,
            'Tirai kop tidak cukup pekat di sisi teksnya.');
    }

    public function test_kop_tidak_mewarisi_latar_temanya(): void
    {
        // Kop selalu gelap, pada kedua tema. Bila latarnya dibiarkan
        // bening, di mode terang ia menjadi kotak putih dengan tulisan
        // putih di atasnya.
        $this->assertMatchesRegularExpression('/\.kop\s*\{[^}]*background:/s', $this->kop(),
            'Kop tidak menyatakan latarnya sendiri.');
    }

    public function test_tagline_kop_disembunyikan_dari_pembaca_layar(): void
    {
        // Taglinenya hiasan; dibacakan, ia menyela judul halaman dengan
        // dua kata Inggris yang tidak menjelaskan apa pun.
        //
        // Diperiksa pada TAGNYA, bukan di mana pun dalam berkas: versi
        // pertama uji ini mencari kedua kata itu berdekatan, dan yang
        // ditemukannya adalah komentar di atas gayanya yang menyebut
        // "karena itu pula ia aria-hidden" — kalimat yang tetap benar
        // sesudah atributnya dicabut dari elemennya.
        preg_match('/<[a-z]+[^>]*\bkop-tagline\b[^>]*>/', $this->kop(), $tag);

        $this->assertNotEmpty($tag, 'Elemen tagline tidak ditemukan di KopHalaman.vue.');

        $this->assertStringContainsString('aria-hidden', $tag[0],
            'Tagline kop tidak ditandai aria-hidden: '.$tag[0]);
    }

    public function test_setiap_nada_ubin_punya_pasangan_mode_gelap(): void
    {
        $ubin = $this->ubin();

        // Daftar nadanya diambil dari tipe propnya, bukan dari gayanya:
        // nada yang boleh dipakai halaman adalah yang disebut di sana,
        // dan justru nada yang baru ditambahkan ke tipe tetapi belum
        // ditulis gayanya yang perlu ketahuan.
        preg_match("/nada\?: ([^;]+);/", $ubin, $tipe);
        $this->assertNotEmpty($tipe, 'Tipe prop nada tidak terbaca dari UbinAngka.vue.');

        preg_match_all("/'([a-z]+)'/", $tipe[1], $nada);
        $this->assertNotEmpty($nada[1], 'Tidak ada nada ubin yang terbaca.');

        $hilang = [];
        foreach ($nada[1] as $n) {
            foreach ([
                'terang' => '/^\.ubin-'.$n.'\s*\{/m',
                'gelap'  => '/^:root\[data-tema="gelap"\]\s+\.ubin-'.$n.'\s*\{/m',
            ] as $tema => $pola) {
                if (! preg_match($pola, $ubin)) $hilang[] = "{$n} ({$tema})";
            }
        }

        // Nada tanpa gaya tidak menggagalkan apa pun: yang kurang
        // pasangan gelapnya tampil sebagai kotak muda menyilaukan, dan
        // yang tak bergaya sama sekali tampil polos tanpa satu tanda pun
        // bahwa nadanya diabaikan.
        $this->assertSame([], $hilang,
            'Nada ubin tanpa gaya: '.implode(', ', $hilang));
    }

    public function test_bilah_ubin_tidak_digambar_tanpa_angka_yang_sah(): void
    {
        $ubin = $this->ubin();

        // `Number("Rp 48.052.520")` bernilai NaN, dan lebar NaN menggambar
        // bilah kosong yang terbaca sebagai nol persen — sebuah angka yang
        // salah yang tampak persis seperti angka yang benar.
        $this->assertStringContainsString('Number.isFinite', $ubin,
            'UbinAngka tidak memeriksa kesahihan angkanya sebelum menggambar bilah.');

        $this->assertStringNotContainsString('Number(angka) / dari', $ubin,
            'UbinAngka masih membagi angka terformat langsung.');
    }

    public function test_setiap_halaman_hris_memakai_kop_halaman(): void
    {
        $tanpa = [];

        foreach ($this->halamanHris() as $f) {
            $isi = file_get_contents($f->getPathname());

            // Komponen bersama di bawah Hris/ boleh tanpa kop; yang
            // dijaga adalah halaman, yaitu berkas yang punya <Head>.
            if (! str_contains($isi, '<Head')) continue;

            if (! str_contains($isi, 'KopHalaman')) {
                $tanpa[] = basename(dirname($f->getPathname())).'/'.$f->getFilename();
            }
        }

        $this->assertSame([], $tanpa,
            'Halaman HRIS tanpa KopHalaman: '.implode(', ', $tanpa));
    }

    public function test_label_kolom_isian_berdiri_di_atas_kolomnya(): void
    {
        $liar = [];

        foreach ($this->halamanHris() as $f) {
            $isi = file_get_contents($f->getPathname());

            // Kolom isian bertipe inline-block. Sebuah <span> tanpa
            // `block` duduk sebaris dengan kolomnya, berdempetan tanpa
            // sela — dan `mt-1` pada kolomnya tidak berbuat apa-apa.
            preg_match_all(
                '/<span class="(?![^"]*\bblock\b)[^"]*"[^>]*>[^<]*<\/span>\s*\n\s*<(?:input|select|textarea)\b/',
                $isi, $c, PREG_OFFSET_CAPTURE);

            foreach ($c[0] as [$cocok, $pos]) {
                $baris = substr_count(substr($isi, 0, $pos), "\n") + 1;
                $liar[] = basename(dirname($f->getPathname())).'/'.$f->getFilename().':'.$baris;
            }
        }

        $this->assertSame([], $liar,
            'Label yang tidak block, sehingga duduk sebaris dengan kolom isiannya: '
            .implode(', ', $liar));
    }
}
