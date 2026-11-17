<?php

namespace Tests\Feature;

use App\Support\{IkonPadat, Menu, Modules};
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penjagaan ubin ikon berdimensi.
 *
 * Ketiga hal yang dijaga di sini semuanya pernah terjadi, dan tidak
 * satu pun menimbulkan galat: ubinnya tetap tergambar, halamannya tetap
 * termuat, dan uji apa pun tetap hijau. Yang berubah hanya tampilannya,
 * dan itu cuma terlihat kalau ada yang membuka halamannya.
 */
class IkonBerdimensiTest extends TestCase
{
    /** Berkas Vue yang menggambar ubin .ikon-3d. */
    private const HALAMAN = [
        'resources/js/Pages/Dasbor/Halaman.vue',
        'resources/js/Pages/Landing.vue',
    ];

    #[Test]
    public function setiap_modul_punya_glyph_padat(): void
    {
        /* Modul baru yang lupa didaftarkan di IkonPadat menggambar ubin
           berwarna yang KOSONG — tidak ada tanda apa pun bahwa ada yang
           kurang, kecuali ubinnya tampak polos di antara dua puluh enam
           ubin lain yang berisi. */
        $this->assertSame(
            [],
            IkonPadat::belumPunya(),
            'Modul ini belum punya glyph padat di App\Support\IkonPadat.',
        );
    }

    #[Test]
    public function setiap_modul_halaman_depan_menemukan_kuncinya(): void
    {
        $putus = [];

        foreach (Modules::all() as $m) {
            $kunci = Modules::kunci($m['nama']);

            if ($kunci === null || IkonPadat::untuk($kunci) === []) {
                $putus[] = $m['nama'].' => '.var_export($kunci, true);
            }
        }

        $this->assertSame([], $putus,
            'Nama modul di halaman depan tidak menemukan kunci modulnya. '
            .'Menamai ulang salah satunya memutus jembatan ini tanpa galat.');
    }

    #[Test]
    public function ubin_berdimensi_tidak_memakai_glyph_garis(): void
    {
        /* Inilah keluhan yang memulai semuanya: glyph GARIS di atas ubin
           bergradien menyusut jadi beberapa benang putih, dan ubinnya
           berhenti terbaca sebagai ikon. Yang benar di sana bentuk padat.

           Diperiksa dengan memotong isi tiap <span class="... ikon-3d">
           sampai </span> penutupnya, lalu menolak 'stroke=' di dalamnya. */
        $salah = [];

        foreach (self::HALAMAN as $berkas) {
            $isi = file_get_contents(base_path($berkas));
            $dari = 0;

            while (($p = strpos($isi, 'ikon-3d', $dari)) !== false) {
                $dari = $p + 7;

                $tutup = strpos($isi, '</span>', $p);
                if ($tutup === false) continue;

                $dalam = substr($isi, $p, $tutup - $p);

                if (str_contains($dalam, 'stroke=')) {
                    $baris = substr_count(substr($isi, 0, $p), "\n") + 1;
                    $salah[] = $berkas.':'.$baris;
                }
            }
        }

        $this->assertSame([], $salah,
            'Ubin .ikon-3d ini masih menggambar glyph garis. Pakai '
            .'<IkonPadat> — bentuk padat, bukan stroke.');
    }

    #[Test]
    public function cat_datar_tidak_menimpa_gradien_ubin(): void
    {
        /* .jual-tanda dan .jual-pilar-panel-tanda ditulis SESUDAH
           .ikon-3d dengan kekhususan yang sama, jadi latar datarnya
           menang hanya karena urutan barisnya. Yang tersisa di layar:
           bayangan tanpa badan.

           Diperiksa dengan membaca ATURANNYA, bukan dengan mencari
           potongan teks. Versi pertama uji ini mencari string
           '.jual-tanda:not(.ikon-3d){' di mana pun, dan lulus terus —
           string itu juga muncul di dalam aturan :hover yang sama
           sekali bukan yang dijaga. Uji yang hijau karena menemukan
           barang di tempat yang salah tidak menjaga apa pun. */
        $css = file_get_contents(base_path('resources/views/partials/eq-visual.blade.php'));

        $telanjang = [];

        foreach (self::aturan($css) as [$pemilih, $badan]) {
            if (! preg_match('/(^|[^-\w])background\s*:/', $badan)) continue;

            foreach (explode(',', $pemilih) as $satu) {
                /* Compound TERAKHIR-nya yang menentukan: pada
                   '.jual-kartu:hover .jual-tanda:not(.ikon-3d)' yang
                   dicat adalah '.jual-tanda:not(.ikon-3d)', dan itu
                   sudah mengecualikan ubin berdimensi. */
                $bagian = preg_split('/\s+/', trim($satu));
                $akhir  = (string) end($bagian);

                if (in_array($akhir, ['.jual-tanda', '.jual-pilar-panel-tanda'], true)) {
                    $telanjang[] = trim($satu);
                }
            }
        }

        $this->assertSame([], $telanjang,
            'Aturan ini mengecat latar datar pada ubin yang juga memakai '
            .'.ikon-3d, sehingga gradiennya tertimpa dan yang tersisa '
            .'hanya bayangan tanpa badan. Tambahkan :not(.ikon-3d).');
    }

    /**
     * Pemecah CSS seadanya: memulangkan [pemilih, badan] tiap aturan.
     *
     * Cukup untuk berkas ini — tidak ada @media bersarang di antara
     * aturan yang diperiksa — dan jauh lebih jujur daripada mencari
     * potongan teks yang bisa cocok di tempat yang salah.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function aturan(string $css): array
    {
        /* Komentar dibuang lebih dulu; isinya menyebut nama pemilih
           dan akan terbaca sebagai aturan sungguhan. */
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        $keluar = [];

        foreach (explode('}', $css) as $potong) {
            if (! str_contains($potong, '{')) continue;

            [$pemilih, $badan] = explode('{', $potong, 2);

            /* Sisa aturan sebelumnya ikut terbawa di depan pemilihnya;
               yang dipakai hanya bagian sesudah tanda kurung terakhir. */
            $pemilih = trim((string) substr($pemilih, (int) strrpos('}'.$pemilih, '}')));

            if ($pemilih !== '') $keluar[] = [$pemilih, $badan];
        }

        return $keluar;
    }

    #[Test]
    public function css_tidak_menyetel_tebal_garis_pada_glyph_ubin(): void
    {
        /* Hampir semua glyph padat memang diisi, jadi menyetel
           stroke-width pada .ikon-3d svg tampak tidak berakibat apa-apa
           — kecuali pada satu ikon. Batang beliung di Mine Operations
           digambar dengan GARIS setebal 2.5, dan CSS menang atas
           atribut presentasi pada SVG. Aturan itu menimpanya diam-diam;
           beliungnya jadi kurus dan tidak ada galat apa pun.

           Pernah ada di berkas ini, dan tidak ada yang menangkapnya
           sampai ikon beliungnya masuk. */
        $css = file_get_contents(base_path('resources/views/partials/eq-visual.blade.php'));

        $salah = [];

        foreach (self::aturan($css) as [$pemilih, $badan]) {
            if (! str_contains($pemilih, '.ikon-3d')) continue;
            if (preg_match('/(^|;)\s*stroke-width\s*:/', $badan)) $salah[] = $pemilih;
        }

        $this->assertSame([], $salah,
            'Aturan ini menyetel stroke-width pada glyph ubin berdimensi. '
            .'CSS menang atas atribut SVG, jadi bagian yang memang digambar '
            .'dengan garis — batang beliung Mine Operations — ikut tertimpa.');
    }

    #[Test]
    public function glyph_bergaris_membawa_tebalnya_sendiri(): void
    {
        /* Kebalikan dari uji di atas: tebalnya harus tetap ADA di
           datanya. Hilang dari sana, bagian itu ikut diisi dan berubah
           dari batang lurus menjadi coretan — persis yang terjadi saat
           set ini pertama diubah dari sprite-nya. */
        $beliung = IkonPadat::untuk('operasi');

        $bergaris = array_values(array_filter($beliung, fn ($j) => isset($j['garis'])));

        $this->assertCount(1, $bergaris,
            'Ikon Mine Operations kehilangan bagian yang digambar dengan garis.');
        $this->assertGreaterThan(0, $bergaris[0]['garis']);
    }

    #[Test]
    public function bilah_nada_kartu_modul_bertahan_di_mode_gelap(): void
    {
        /* Aturan latar mode gelap menyetel border-color untuk KEEMPAT
           sisi kartu. Tepi kirilah yang memikul seluruh penandaan nada,
           jadi tanpa pengembalian ini dasbor gelap kehilangan satu-
           satunya petunjuk mana modul yang gawat — dan tidak ada apa pun
           yang menandakan bahwa sesuatu hilang. */
        $css = file_get_contents(base_path('resources/views/partials/eq-visual.blade.php'));

        $adaPemerata = false;
        $adaPemulih  = false;

        foreach (self::aturan($css) as [$pemilih, $badan]) {
            if (! str_contains($pemilih, '[data-tema="gelap"]')) continue;

            if (str_contains($pemilih, '.eq-mdl') && preg_match('/(^|;)\s*border-color\s*:/', $badan)) {
                $adaPemerata = true;
            }

            if (str_contains($pemilih, '.eq-mdl') && str_contains($badan, 'border-left-color:var(--c)')) {
                $adaPemulih = true;
            }
        }

        if ($adaPemerata) {
            $this->assertTrue($adaPemulih,
                'Mode gelap meratakan border kartu modul tanpa mengembalikan '
                .'bilah nada di tepi kirinya (border-left-color:var(--c)).');

            return;
        }

        $this->assertFalse($adaPemerata);
    }

    #[Test]
    public function ringkasan_modul_membawa_jalur_padatnya(): void
    {
        /* Ubin yang sampai ke layar tanpa jalur padat menggambar kotak
           berwarna kosong. Diperiksa di Dasbor::ringkasanModul() dan
           bukan lewat permintaan HTTP supaya yang gagal menunjuk ke
           tempat yang salah, bukan ke seluruh halaman. */
        $ubin = [
            ['modul' => 'hris', 'nama' => 'Roster Terhalang', 'nilai' => 150,
             'total' => 300, 'rute' => 'hris.index', 'nada' => 'gawat'],
            ['modul' => 'energi', 'nama' => 'Energi', 'nilai' => 420,
             'total' => 420, 'rute' => 'energi.index', 'nada' => 'kabar'],
            ['modul' => 'biaya', 'nama' => 'Biaya', 'nilai' => 0,
             'total' => 12, 'rute' => 'biaya.index', 'nada' => 'baik'],
        ];

        foreach (\App\Support\Dasbor::ringkasanModul($ubin) as $m) {
            $this->assertNotEmpty(
                $m['ikonPadat'] ?? [],
                'Modul "'.$m['modul'].'" diringkas tanpa jalur padat.',
            );
        }
    }
}
