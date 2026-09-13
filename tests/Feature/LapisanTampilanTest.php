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

    /**
     * SATU pita berfoto di puncak halaman, bukan dua.
     *
     * Sampul modul — foto, geo tag, jam, dan cuaca — sempat berdiri
     * sebagai bilahnya sendiri tepat di atas kop. Hasilnya dua pita
     * berfoto bertumpuk setinggi hampir separuh layar sebelum satu baris
     * isi pun terlihat, dan keduanya menjawab hal yang sama: di mana
     * kita, sedang melihat apa. Pada halaman awal PJP nama modulnya
     * bahkan tercetak empat kali dalam satu layar.
     *
     * Yang dijaga di sini bukan selera melainkan bentuknya: sampulnya
     * masuk KE DALAM kop lewat prop, dan tidak ada komponen kedua yang
     * menggambarnya sendiri. Kop sudah memiliki foto, tirai, dan aturan
     * tingginya; pita kedua yang meniru ketiganya berarti dua tempat
     * yang harus dijaga tetap sama selamanya.
     */
    public function test_kerangka_menggambar_satu_pita_berfoto(): void
    {
        $isi = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        $this->assertStringContainsString(':gambar="sampul', $isi,
            'Sampul modul tidak lagi masuk ke dalam kop — fotonya hilang, '
            .'atau digambar pita lain di luar kop.');

        $this->assertStringNotContainsString('<SampulModul', $isi,
            'Pita sampul terpisah kembali digambar di atas kop: dua pita berfoto '
            .'bertumpuk, dan nama modulnya tercetak berkali-kali dalam satu layar.');
    }

    /**
     * Garis aksen di bawah semboyan selebar TULISANNYA.
     *
     * Semula `width: 78%` — 78% dari kotak semboyan, bukan dari
     * tulisannya. Keduanya hanya sama panjang bila semboyannya muat satu
     * baris; begitu ia membungkus, kotaknya melebar sampai batas maksimum
     * sementara barisnya tetap pendek. Terukur di peramban pada enam
     * modul:
     *
     *   "Proven On Paper"        tulisan 197,2  garis 153,8   44 px kurang
     *   "Clean Water Downstream" tulisan 148,0  garis 191,9   44 px lebih
     *   "Systems That Hold"      tulisan 220,2  garis 171,7   49 px kurang
     *
     * Melesetnya ke DUA arah, jadi tidak ada satu pun kelipatan tetap
     * yang membetulkan keduanya sekaligus — dan dari 27 semboyan modul
     * yang terpanjang 36 karakter, sehingga "buat saja semuanya satu
     * baris" juga bukan jalan keluar. Lebarnya karena itu diukur dari
     * kotak baris tulisannya dan dikirim lewat peubah CSS.
     *
     * Diuji sebagai gaya, bukan sebagai piksel: pengukuran piksel yang
     * sesungguhnya menuntut peramban, dan uji yang menuntut peramban akan
     * dimatikan orang pada hari ia mulai rewel. Yang dijaga di sini
     * bentuk aturannya — dan bentuk itulah yang dahulu salah.
     */
    public function test_garis_semboyan_selebar_tulisannya(): void
    {
        $isi = file_get_contents(resource_path('js/Components/KopHalaman.vue'));

        $i = strpos($isi, '.kop-tagline::after');
        $this->assertNotFalse($i, 'Aturan .kop-tagline::after hilang — garis aksennya tidak lagi digambar.');

        $blok = substr($isi, $i, strpos($isi, '}', $i) - $i);

        $this->assertStringContainsString('var(--kop-garis', $blok,
            'Garis semboyan tidak lagi memakai lebar hasil ukur. Lebar tetap atau '
            .'persentase hanya cocok pada semboyan satu baris; yang membungkus akan '
            .'meleset puluhan piksel, dan ke dua arah.');

        $this->assertDoesNotMatchRegularExpression('/width:\s*\d+(\.\d+)?%/', $blok,
            'Lebar persentase kembali dipakai pada garis semboyan — itu persentase '
            .'dari KOTAKNYA, bukan dari tulisannya.');
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

    /**
     * Tiap kunci `keadaan` pada kelas data grafik harus ada pada palet.
     *
     * Grafik batang dan donat mewarnai barisnya dengan
     * `KEADAAN[b.keadaan]`. Kunci yang tidak ada memulangkan
     * `undefined`, dan `undefined` yang dijahit ke dalam
     * `linear-gradient(90deg, undefined 0%, …)` menghasilkan isian yang
     * TIDAK VALID — batangnya digambar tanpa warna sama sekali, grafik
     * lainnya tetap benar, dan tidak ada satu pun galat di konsol.
     *
     * Terjadi sekali pada BelajarGrafik: 'awas' dan 'buruk' ditulis di
     * sisi PHP sementara palet menyebutnya 'ingat', 'serius', 'gawat'.
     *
     * DUA BERKAS SAJA, dan itu disengaja. `keadaan` adalah kata yang
     * dipakai ulang di aplikasi ini untuk hal yang sama sekali lain —
     * keadaan hari pada roster bernilai 'kerja' dan 'libur', keadaan
     * izin bernilai 'aman'. Menyapu seluruh app/ akan menuntut kata
     * domain itu tunduk pada palet warna grafik, dan penjaga yang
     * menuntut yang keliru akan dimatikan orang, bukan diperbaiki.
     * Kedua berkas di bawah seluruh isinya memang data grafik.
     */
    public function test_kunci_keadaan_pada_kelas_grafik_ada_pada_palet_warna(): void
    {
        $warna = file_get_contents(resource_path('js/Grafik/warna.ts'));

        preg_match('/export const KEADAAN[^{]*\\{(.*?)\\}/s', $warna, $blok);
        $this->assertNotEmpty($blok, 'Palet KEADAAN tidak ditemukan pada warna.ts.');

        preg_match_all('/^\\s*([a-z]+)\\s*:/m', $blok[1], $m);
        $dikenal = $m[1];

        $this->assertNotEmpty($dikenal, 'Palet KEADAAN terbaca kosong.');

        $lepas = [];

        foreach (['DasborGrafik.php', 'BelajarGrafik.php'] as $nama) {
            $jalur = app_path('Support/'.$nama);
            if (! is_file($jalur)) continue;

            $isi = file_get_contents($jalur);

            /* Pengindeksan larik dibuang lebih dulu: `$p['keadaan']`
               membaca kunci, ia tidak menuliskannya, dan menghitungnya
               sebagai nilai membuat penjaga ini menuduh kodenya sendiri. */
            $isi = preg_replace("/\\[\\s*'[a-z_]+'\\s*\\]/", '[]', $isi);

            preg_match_all("/'keadaan'\\s*=>\\s*([^\\n]+)/", $isi, $baris);

            foreach ($baris[1] as $sisi) {
                preg_match_all("/'([a-z]+)'/", $sisi, $kunci);

                foreach ($kunci[1] as $k) {
                    if (in_array($k, $dikenal, true)) continue;

                    $lepas[] = $nama.": '".$k."'";
                }
            }
        }

        $lepas = array_values(array_unique($lepas));

        $this->assertSame([], $lepas,
            "Kunci keadaan yang tidak ada pada palet warna. Batangnya akan "
            ."digambar TANPA WARNA, tanpa galat apa pun:\n  "
            .implode("\n  ", $lepas)
            ."\n\nYang dikenal: ".implode(', ', $dikenal));
    }

    /**
     * Pengaman geser-ke-samping harus `clip`, bukan `hidden`.
     *
     * Keduanya sama-sama memotong yang meluber ke samping, dan justru
     * karena itu bedanya tidak pernah terlihat pada tangkapan layar
     * mana pun. Yang berbeda: `overflow-x:hidden` pada html atau body
     * menjadikan halaman WADAH GULIR tersendiri, dan `position:sticky`
     * di dalam wadah gulir yang tidak pernah benar-benar bergulir tidak
     * punya apa pun untuk dilekati.
     *
     * Bilah samping menanggung akibatnya. Ia menyatakan dirinya
     * `lg:sticky lg:top-0 lg:h-screen` — "dipaku setinggi layar, kakinya
     * selalu terlihat" — lalu ikut hanyut bersama halaman: top 0 pada
     * puncak, −600 sesudah digulir 600, dan −1611 di dasar halaman.
     * Yang terlihat pengguna: bilah samping ada di bagian atas halaman
     * dan HILANG SAMA SEKALI di bagian bawah, meninggalkan kolom kosong
     * selebar 248px.
     *
     * Tidak ada galat, tidak ada uji yang gagal, dan tangkapan layar
     * pada puncak halaman — satu-satunya yang biasanya diambil —
     * terlihat benar sepenuhnya.
     */
    public function test_pengaman_geser_samping_tidak_mematikan_sticky(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/html\s*,\s*body\s*\{[^}]*overflow-x:\s*clip/', $css,
            'Pengaman geser-ke-samping pada html/body harus overflow-x:clip.');

        $this->assertDoesNotMatchRegularExpression(
            '/html\s*,\s*body\s*\{[^}]*overflow-x:\s*hidden/', $css,
            'overflow-x:hidden pada html/body menjadikan halaman wadah gulir dan '
            .'mematikan position:sticky di SELURUH aplikasi — bilah sampingnya ikut '
            .'hanyut sampai hilang di bagian bawah halaman. Pakai clip.');
    }

    /**
     * Bilah samping memang menyatakan dirinya melekat setinggi layar.
     *
     * Penjaga di atas menjaga sebabnya; yang ini menjaga bahwa masih ada
     * yang bergantung padanya. Tanpa pasangan ini, `sticky` pada bilah
     * samping dapat hilang tanpa suara dan penjaga overflow di atas
     * berdiri menjaga sesuatu yang sudah tidak ada.
     */
    public function test_bilah_samping_dipaku_setinggi_layar(): void
    {
        $vue = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        $this->assertMatchesRegularExpression(
            '/id="eqSidebar"[^>]*lg:sticky/s', $vue,
            'Bilah samping tidak lagi lg:sticky.');

        $this->assertMatchesRegularExpression(
            '/id="eqSidebar"[^>]*lg:h-screen/s', $vue,
            'Bilah samping tidak lagi setinggi layar; melekat tanpa tinggi tetap '
            .'membuat kakinya tetap tidak terlihat.');
    }

    /**
     * Tiap komponen yang dipakai template HARUS dikenal script-nya.
     *
     * `<script setup>` menyelesaikan nama komponen dari lingkup
     * skripnya. Nama yang tidak ada di sana TIDAK menimbulkan galat:
     * Vue menganggapnya elemen kustom, menuliskannya apa adanya ke DOM
     * — `<kopcetak dok="[object Object]"></kopcetak>` — dan peramban
     * menggambarnya sebagai kotak kosong setinggi nol.
     *
     * Persis itu yang terjadi pada SEPULUH lembar cetak sekaligus:
     * KopCetak dipakai tanpa diimpor, dan kop dokumen terkendali —
     * nama perusahaan, nomor dokumen, tanggal terbit, nomor revisi —
     * hilang dari seluruh laporan angkutan, biaya, geoteknik, izin
     * kerja, konservasi, lingkungan, operasi, peledakan, pemeliharaan,
     * dan penirisan. Halamannya tetap tampil rapi, tetap lolos seluruh
     * uji yang ada, dan tetap dapat dicetak; yang hilang hanya satu
     * hal — keterangan MILIK SIAPA lembar itu, pada berkas yang
     * diserahkan ke luar.
     *
     * Yang diperiksa keberadaan namanya di dalam <script>, bukan bentuk
     * impornya: komponen boleh datang dari `import`, dari `defineProps`,
     * maupun dibangun dengan `h()` seperti pada Print/Smkp.vue.
     */
    public function test_setiap_komponen_yang_dipakai_template_dikenal_skripnya(): void
    {
        /* Elemen SVG dan MathML berhuruf besar bukan komponen Vue. Ia
           ditulis persis begitu oleh spesifikasinya, dan menuntutnya
           diimpor berarti menuntut yang mustahil. */
        $bawaan = ['Fragment', 'Teleport', 'Transition', 'TransitionGroup', 'KeepAlive',
                   'Suspense', 'Component', 'Slot', 'Template'];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js')));

        $lepas = [];

        foreach ($it as $f) {
            if (! $f->isFile() || $f->getExtension() !== 'vue') continue;
            if (str_contains($f->getFilename(), '.bak')) continue;

            $isi = file_get_contents($f->getPathname());

            // Template dan skrip dipisah: nama komponen di dalam komentar
            // skrip tidak boleh dihitung sebagai pemakaian.
            if (! preg_match('/<template>(.*)<\/template>/s', $isi, $t)) continue;

            $skrip = preg_replace('/<template>.*<\/template>/s', '', $isi);

            preg_match_all('/<([A-Z][A-Za-z0-9_]*)[\s\/>]/', $t[1], $m);

            foreach (array_unique($m[1]) as $nama) {
                if (in_array($nama, $bawaan, true)) continue;

                // `\b` supaya "Kop" tidak dianggap mengenalkan "KopCetak".
                if (preg_match('/\b'.preg_quote($nama, '/').'\b/', $skrip)) continue;

                $lepas[] = $this->nama($f).' memakai <'.$nama.'>';
            }
        }

        $this->assertSame([], $lepas,
            "Komponen dipakai template tetapi tidak dikenal skripnya. Vue TIDAK "
            ."akan mengeluh — ia menuliskannya sebagai elemen kustom yang tidak "
            ."menggambar apa pun:\n  ".implode("\n  ", $lepas));
    }
}
