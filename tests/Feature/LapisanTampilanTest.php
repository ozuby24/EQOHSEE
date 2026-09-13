<?php

namespace Tests\Feature;

use App\Support\Menu;
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

    private function nama(\SplFileInfo $f): string
    {
        return basename(dirname($f->getPathname())).'/'.$f->getFilename();
    }

    /**
     * Seluruh halaman Vue, kecuali yang memang di luar kerangka.
     *
     * Halaman cetak digambar di atas kertas dan tidak pernah memakai
     * kerangka aplikasi; halaman masuk dan halaman tamu pun tidak.
     * Ikut diperiksa, ketiganya akan dituntut memakai kop yang memang
     * tidak berlaku bagi mereka.
     *
     * @return list<\SplFileInfo>
     */
    private function halamanVue(): array
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js/Pages')));

        $berkas = [];
        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'vue') continue;
            if (str_contains($f->getFilename(), '.bak-')) continue;

            $jalur = $f->getPathname();
            if (str_contains($jalur, '/Pages/Print/')) continue;
            if (str_contains($jalur, '/Pages/Auth/')) continue;

            $berkas[] = clone $f;
        }

        sort($berkas);

        return $berkas;
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

    public function test_kerangka_merender_kop_halaman(): void
    {
        // Kop adalah kerangka, sama seperti bilah atas dan bilah
        // samping. Digambar di sini, dua ratusan halaman mendapatkannya
        // sekaligus dan seluruhnya setinggi, sejarak, dan seukuran yang
        // sama — dan halaman baru mendapatkannya tanpa satu baris pun
        // ditulis.
        $this->assertStringContainsString('<KopHalaman',
            file_get_contents(resource_path('js/Layouts/AppLayout.vue')),
            'Kerangka tidak lagi menggambar kop; seluruh halaman kehilangan kopnya sekaligus.');
    }

    public function test_tidak_ada_halaman_yang_menggambar_kopnya_sendiri(): void
    {
        $liar = [];

        foreach ($this->halamanVue() as $f) {
            $isi = file_get_contents($f->getPathname());

            if (! str_contains($isi, '<KopHalaman')) continue;

            // Halaman yang memang perlu kop khusus menolak kop kerangka
            // dengan `kop: false` dari controllernya, dan menyebutkannya
            // di berkasnya sendiri supaya yang membacanya tahu mengapa
            // ada dua kop yang mungkin.
            if (str_contains($isi, 'kop: false')) continue;

            $liar[] = $this->nama($f);
        }

        $this->assertSame([], $liar,
            'Halaman menggambar kopnya sendiri di atas kop kerangka — judulnya tercetak dua kali: '
            .implode(', ', $liar));
    }

    public function test_tidak_ada_halaman_yang_mencetak_ulang_judulnya(): void
    {
        $liar = [];

        foreach ($this->halamanVue() as $f) {
            $isi = file_get_contents($f->getPathname());

            // Kop kerangka sudah mencetak `judul` dan `subjudul`.
            // Halaman yang mencetaknya lagi menampilkan kalimat yang
            // sama dua kali dengan jarak dua sentimeter — dan itu
            // terbaca sebagai galat penyusunan, bukan sebagai
            // penekanan.
            // Dua bentuk: gema langsung `{{ judul }}`, dan peta judul per
            // mode yang isinya sama persis dengan label butir menunya —
            // `{{ judul[props.mode] }}` pada sepuluh Halaman.vue, dan
            // `{{ titles[props.mode] }}` pada dua yang lain.
            $gema = '/<h[12][^>]*>\{\{ (?:props\.)?(?:judul|titles)'
                  .'(?:\[props\.mode\])?[^<]*\}\}<\/h[12]>/';

            if (! preg_match($gema, $isi)) continue;

            $liar[] = $this->nama($f);
        }

        $this->assertSame([], $liar,
            'Halaman mencetak ulang judul yang sudah ada di kop: '.implode(', ', $liar));
    }

    public function test_tidak_ada_halaman_yang_menggambar_spanduk_sendiri(): void
    {
        $liar = [];

        foreach ($this->halamanVue() as $f) {
            $isi = file_get_contents($f->getPathname());

            // `.eq-hero` adalah spanduk besar berfoto — bentuk yang sama
            // dengan kop kerangka. Keduanya pada satu halaman menumpuk
            // dua spanduk setinggi separuh layar, dengan sapaan yang
            // sama tercetak tiga kali: di bilah atas, di kop, dan di
            // spanduk. Satu tindakan utamanya kini muat di dalam kop.
            if (! preg_match('/class="[^"]*\beq-hero\b/', $isi)) continue;
            if (str_contains($isi, 'kop: false')) continue;

            $liar[] = $this->nama($f);
        }

        $this->assertSame([], $liar,
            'Halaman menggambar spanduknya sendiri di bawah kop kerangka: '.implode(', ', $liar));
    }

    public function test_kisi_ubin_tidak_memakai_titik_henti_viewport(): void
    {
        $liar = [];

        foreach ($this->halamanVue() as $f) {
            $isi = file_get_contents($f->getPathname());

            if (! str_contains($isi, 'UbinAngka')) continue;

            /* TITIK HENTI TAILWIND MENGUKUR LAYAR, BUKAN RUANG YANG ADA.
               Ubinnya duduk di kolom isi yang sudah dipotong bilah
               samping selebar 248px, sehingga `lg:` — 1024px — hanya
               menyisakan sekitar 700px. `lg:grid-cols-5` di situ
               membaginya menjadi ubin selebar 129px, labelnya membungkus
               tiga baris, dan angkanya terdorong jauh ke bawah: sepuluh
               dari lima belas ubin pada satu halaman, tanpa satu pun
               galat. Kesalahan yang sama terulang pada bilah pilnya.

               `.ubin-kisi` memakai auto-fit dengan lebar terkecil, dan
               karena itu tidak punya titik henti yang perlu dicocokkan
               dengan lebar bilah samping. */
            /* Yang diperiksa hanya kisi yang BENAR-BENAR BERISI UBIN.
               Kisi kartu — dua kolom berisi panel besar — memang tepat
               memakai titik henti viewport, dan menandainya di sini
               hanya akan membuat penjaga ini diabaikan orang. */
            preg_match_all('/class="([^"]*\bgrid\b[^"]*)"(.{0,260})/s', $isi, $c);

            foreach ($c[1] as $i => $kelas) {
                if (! str_contains($c[2][$i], '<UbinAngka')) continue;
                if (! preg_match('/\b(?:sm|md|lg|xl):grid-cols-\d+/', $kelas)) continue;

                $liar[] = $this->nama($f).': '.trim($kelas);
            }
        }

        $this->assertSame([], $liar,
            'Kisi berisi ubin memakai titik henti viewport alih-alih .ubin-kisi: '
            .implode(' | ', $liar));
    }

    /**
     * BATAS PENJAGA DI ATAS, disebutkan supaya tidak disalahpahami.
     *
     * Judul yang diketik sebagai TEKS TETAP di dalam `<h2>` — "Performa
     * SMKP", "Register Temuan" — tidak dapat dibandingkan dengan judul
     * yang dikirim controllernya tanpa menjalankan halamannya. Tiga
     * puluh delapan halaman semacam itu ditemukan dengan menyapu
     * seluruh 216 alamat Inertia di peramban dan membandingkan
     * `.kop-judul` dengan tiap `<h2>` di badan halaman, bukan dengan
     * berkas uji ini.
     *
     * Lulusnya uji di atas karena itu TIDAK berarti tidak ada judul
     * ganda sama sekali; ia hanya berarti tidak ada yang berbentuk gema
     * prop yang dapat dilihat dari kodenya.
     */
    public function test_penjaga_judul_ganda_hanya_melihat_gema_prop(): void
    {
        $contoh = '<h2 class="x">Performa SMKP</h2>';

        $gema = '/<h[12][^>]*>\{\{ (?:props\.)?(?:judul|titles)'
              .'(?:\[props\.mode\])?[^<]*\}\}<\/h[12]>/';

        $this->assertSame(0, preg_match($gema, $contoh),
            'Penjaga ternyata menangkap judul teks tetap — catatan batas ini sudah usang.');
    }

    public function test_butir_menu_sekelompok_tidak_berlabel_kembar(): void
    {
        // Bilah pindah di beranda modul meratakan seluruh grup menjadi
        // satu baris pil, dan butir bernama sama dibedakan dengan
        // menambahkan nama grupnya — "Riwayat · MCU" di samping
        // "Pendaftaran · MCU".
        //
        // Pembeda itu HANYA BEKERJA BILA GRUPNYA MEMANG BERBEDA. Dua
        // butir berlabel sama di dalam satu grup menghasilkan dua pil
        // yang sama persis menuju dua tempat berbeda, dan tidak ada
        // satu pun tanda di layar yang menyebutkan bedanya.
        $kembar = [];

        foreach (Menu::all() as $kunci => $modul) {
            foreach ($modul['groups'] ?? [] as $nama => $butir) {
                $label = array_map(fn ($b) => $b[0], $butir);
                $ulang = array_keys(array_filter(array_count_values($label), fn ($n) => $n > 1));

                foreach ($ulang as $l) $kembar[] = "{$kunci}/{$nama}: {$l}";
            }
        }

        $this->assertSame([], $kembar,
            'Butir menu berlabel kembar di dalam satu grup: '.implode(', ', $kembar));
    }

    public function test_setiap_modul_punya_semboyan_dan_kutipan(): void
    {
        // Keduanya dipakai kerangka pada TIAP halaman modul itu. Yang
        // kosong tidak menjatuhkan apa pun — kop kehilangan taglinenya
        // dan halamannya kehilangan penutupnya, diam-diam, dan hanya
        // pada modul yang kebetulan terlupakan.
        $kurang = [];

        foreach (Menu::all() as $kunci => $modul) {
            foreach (['semboyan', 'kutipan'] as $k) {
                if (trim((string) ($modul[$k] ?? '')) === '') $kurang[] = "{$kunci}.{$k}";
            }
        }

        $this->assertSame([], $kurang,
            'Modul tanpa semboyan atau kutipan: '.implode(', ', $kurang));
    }

    public function test_label_kolom_isian_berdiri_di_atas_kolomnya(): void
    {
        $liar = [];

        foreach ($this->halamanVue() as $f) {
            $isi = file_get_contents($f->getPathname());

            // Kolom isian bertipe inline-block. Sebuah <span> tanpa
            // `block` duduk sebaris dengan kolomnya, berdempetan tanpa
            // sela — dan `mt-1` pada kolomnya tidak berbuat apa-apa.
            preg_match_all(
                '/<span class="(?![^"]*\bblock\b)[^"]*"[^>]*>[^<]*<\/span>\s*\n\s*<(?:input|select|textarea)\b/',
                $isi, $c, PREG_OFFSET_CAPTURE);

            foreach ($c[0] as [$cocok, $pos]) {
                $baris = substr_count(substr($isi, 0, $pos), "\n") + 1;
                $liar[] = $this->nama($f).':'.$baris;
            }
        }

        $this->assertSame([], $liar,
            'Label yang tidak block, sehingga duduk sebaris dengan kolom isiannya: '
            .implode(', ', $liar));
    }

    /**
     * Kaki bilah samping tidak boleh ikut menanggung kekurangan ruang.
     *
     * #eqSidebar memotong (`overflow:hidden`), dan tiga anak kaki bilah
     * tidak dapat menyusut: tombol bantuan `flex:none`, kartu semboyan
     * berbatas bawah `min-height`, baris hak cipta `flex:none`. Bila
     * kotak kakinya sendiri diberi flex-shrink 1, ia menyusut di bawah
     * tinggi isinya, isinya meluber, dan pemotongnya membuang selisih
     * itu diam-diam.
     *
     * Terukur sekali pada 1440x820: kaki 152px memuat isi 244px, kartu
     * semboyan terpotong di tengah kalimat, dan baris hak cipta beserta
     * tombol lipat terhampar seluruhnya di luar layar pada 843–886px.
     * Tidak ada galat, tidak ada uji yang gagal.
     *
     * Yang diperiksa bukan angkanya, melainkan syaratnya: selama
     * pemotongnya ada, kakinya harus tidak-menyusut.
     */
    public function test_kaki_bilah_samping_tidak_menyusut_di_dalam_pemotong(): void
    {
        $css = file_get_contents(resource_path('views/partials/eq-visual.blade.php'));

        $this->assertMatchesRegularExpression(
            '/#eqSidebar\{[^}]*overflow:\s*hidden/', $css,
            'Bilah samping tidak lagi memotong isinya — penjaga ini kehilangan dasarnya '
            .'dan perlu ditinjau ulang, bukan dihapus begitu saja.');

        preg_match('/\.eq-sisi-kaki\{([^}]*)\}/', $css, $c);

        $this->assertNotEmpty($c, 'Aturan .eq-sisi-kaki tidak ditemukan.');

        $deklarasi = preg_replace('/\s+/', '', $c[1]);

        $this->assertTrue(
            str_contains($deklarasi, 'flex:10auto')
            || preg_match('/flex-shrink:0/', $deklarasi) === 1,
            'Kaki bilah samping boleh memuai, tidak boleh menyusut: pakai `flex:1 0 auto` '
            .'(atau flex-shrink:0). Yang tertulis sekarang: "'.trim($c[1]).'".');
    }

    /**
     * Tombol selebar isinya, bukan selebar barisnya.
     *
     * `.eq-btn-utama` pernah membawa `flex:1`, dimaksudkan bagi baris
     * berisi dua tombol yang membagi lebarnya rata. Aturan itu berlaku
     * pada SETIAP wadah flex, dan sebagian besar tombol utama tidak duduk
     * di baris semacam itu — ia duduk di samping penyaring, di samping
     * kalimat keterangan, atau sendirian di ujung baris.
     *
     * Terukur dengan menyapu 244 halaman di peramban: 57 memuat tombol
     * yang memuai jauh melampaui isinya. "Terbitkan" pada Kalender Regu
     * selebar 444px berdampingan dengan "Susun baseline" selebar 129px.
     * Tidak ada galat, tidak ada uji yang gagal — yang terlihat hanya
     * baris yang kehilangan proporsinya.
     *
     * Baris yang memang hendak membagi rata menyebutkannya lewat
     * `.eq-btn-baris`.
     */
    public function test_tombol_utama_tidak_memuai_memenuhi_barisnya(): void
    {
        $css = file_get_contents(resource_path('views/partials/eq-visual.blade.php'));

        preg_match('/\n\.eq-btn-utama\{([^}]*)\}/', $css, $c);

        $this->assertNotEmpty($c, 'Aturan .eq-btn-utama tidak ditemukan.');

        $deklarasi = preg_replace('/\s+/', '', $c[1]);

        $this->assertStringNotContainsString('flex:1', $deklarasi,
            'Tombol utama tidak boleh memuai memenuhi barisnya. Baris yang hendak '
            .'membagi rata memakai .eq-btn-baris pada wadahnya.');

        $this->assertStringContainsString('.eq-btn-baris', $css,
            'Jalan keluar bagi baris yang sengaja dibagi rata harus tetap ada — '
            .'tanpa itu, `flex:1` akan dipasang kembali pada kelas tombolnya.');
    }

    /**
     * Judul kolom pada lembar cetak harus boleh membungkus.
     *
     * `th{white-space:nowrap}` berlaku bagi tabel layar, yang dibungkus
     * `.tabel-scroll` dan karena itu dapat digeser mendatar. Lembar cetak
     * tidak punya jalan keluar itu: judul yang tidak boleh membungkus
     * MELUBER menimpa judul kolom sebelahnya, dan yang tercetak di atas
     * kertas berbunyi "Nomor KetidakseKriteria".
     *
     * Terukur pada Formulir Rencana Tindak Lanjut sebelum perbaikan: th
     * selebar 101px memuat judul selebar 128px, dan selisih 27px itu
     * jatuh ke kolom tetangganya. Tidak ada galat, tidak ada uji yang
     * gagal — hanya berkas resmi yang tidak dapat dibaca.
     */
    public function test_judul_kolom_lembar_cetak_boleh_membungkus(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\bth\s*\{[^}]*white-space:\s*nowrap/', $css,
            'Aturan nowrap bagi tabel layar tidak lagi ada — penjaga ini kehilangan '
            .'dasarnya dan perlu ditinjau ulang, bukan dihapus begitu saja.');

        $this->assertMatchesRegularExpression(
            '/\.lembar\s+th\s*\{[^}]*white-space:\s*normal/', $css,
            'Lembar cetak tidak dapat digulir mendatar; judul kolomnya harus boleh '
            .'membungkus, jika tidak ia meluber menimpa kolom sebelahnya.');
    }
}
