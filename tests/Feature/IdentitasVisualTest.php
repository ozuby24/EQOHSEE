<?php

namespace Tests\Feature;

use App\Support\Menu;
use App\Support\Modules;
use App\Support\Pillars;
use Tests\TestCase;

/**
 * Penjaga identitas visual: lambang, ikon, dan keterjangkauan modul.
 *
 * Cacat yang ditutup berkas ini semuanya diam — tidak satu pun melempar
 * galat, sehingga seluruhnya lolos sampai ada yang membuka halamannya
 * dan memperhatikan. Dua pilar memakai ikon yang sama, sebuah modul
 * lengkap tanpa jalan masuk dari bilah samping, dan kelas warna yang
 * tidak pernah dihasilkan Tailwind: ketiganya tampak wajar di kode.
 */
class IdentitasVisualTest extends TestCase
{
    /** Nama ikon yang benar-benar digambar IkonPilar.vue. */
    private function ikonTerdaftar(): array
    {
        $vue = file_get_contents(resource_path('js/Components/IkonPilar.vue'));

        $this->assertNotFalse($vue, 'IkonPilar.vue tidak terbaca.');

        // Kunci pada literal `jalur`, mis. "  bolt: [" di awal baris.
        preg_match_all('/^\s{2}([a-z]+):\s*\[/m', $vue, $c);

        return $c[1];
    }

    public function test_setiap_pilar_memakai_ikon_yang_benar_benar_digambar(): void
    {
        $terdaftar = $this->ikonTerdaftar();
        $this->assertNotEmpty($terdaftar, 'Tidak ada ikon terbaca dari IkonPilar.vue.');

        $hilang = [];
        foreach (Pillars::all() as $slug => $p) {
            if (!in_array($p['ikon'] ?? '', $terdaftar, true)) {
                $hilang[] = "{$slug} → ".($p['ikon'] ?? '(kosong)');
            }
        }

        // Ikon yang tidak terdaftar tidak menjatuhkan halaman: komponennya
        // menggambar lingkaran cadangan, dan pilarnya tampil tanpa penanda
        // apa pun yang membedakannya.
        $this->assertSame([], $hilang,
            'Pilar menunjuk ikon yang tidak ada di IkonPilar.vue: '.implode(', ', $hilang));
    }

    public function test_tidak_ada_dua_pilar_berbagi_ikon_yang_sama(): void
    {
        $per = [];
        foreach (Pillars::all() as $slug => $p) {
            $per[$p['ikon']][] = $slug;
        }

        $kembar = array_filter($per, fn ($s) => count($s) > 1);

        // Quality pernah memakai tetes air dan Hygiene memakai jantung yang
        // sama dengan Occupational Health — pada satu grid delapan kartu,
        // dua ikon kembar menghapus satu-satunya pembeda yang cepat dibaca.
        $this->assertSame([], array_map(fn ($s) => implode(' = ', $s), $kembar),
            'Pilar berikut berbagi ikon yang sama.');
    }

    public function test_ikon_modul_saling_berbeda(): void
    {
        $per = [];
        foreach (Modules::all() as $m) {
            $per[$m['ikon']][] = $m['nama'];
        }

        $kembar = array_values(array_map(
            fn ($s) => implode(' = ', $s),
            array_filter($per, fn ($s) => count($s) > 1)
        ));

        $this->assertSame([], $kembar, 'Modul berikut berbagi jalur ikon yang sama.');
    }

    public function test_jalur_ikon_modul_berupa_perintah_svg_yang_wajar(): void
    {
        foreach (Modules::all() as $m) {
            $this->assertMatchesRegularExpression('/^[Mm]\s?-?[\d.]/', $m['ikon'],
                "Ikon {$m['nama']} tidak diawali perintah moveto.");
        }
    }

    /**
     * Modul yang punya halaman harus punya jalan masuk dari bilah samping.
     *
     * Water & Dewatering dan Maintenance sempat lengkap — rute, halaman,
     * alur persetujuan — tetapi tidak terdaftar di Menu. Keduanya hanya
     * dapat dicapai dari kartu halaman depan; sekali masuk ke aplikasi,
     * tidak ada jalan menuju ke sana sama sekali.
     */
    public function test_setiap_modul_aktif_dapat_dicapai_dari_bilah_samping(): void
    {
        $ruteMenu = [];
        foreach (Menu::all() as $m) {
            foreach ($m['groups'] as $butir) {
                foreach ($butir as $b) $ruteMenu[] = $b[1];
            }
        }

        $tanpaJalanMasuk = [];
        foreach (Modules::all() as $m) {
            if (($m['rute'] ?? null) && !in_array($m['rute'], $ruteMenu, true)) {
                $tanpaJalanMasuk[] = "{$m['nama']} ({$m['rute']})";
            }
        }

        $this->assertSame([], $tanpaJalanMasuk,
            'Modul berikut tidak punya butir menu: '.implode(', ', $tanpaJalanMasuk));
    }

    /**
     * Membuka halaman sebuah modul harus memunculkan menu modul itu.
     *
     * Modul yang tidak dikenali modulAktif() jatuh ke 'lms', sehingga
     * pengguna berada di halaman Penirisan sambil melihat daftar menu
     * Learning Center — tanpa satu pun galat muncul.
     */
    public function test_alamat_modul_memilih_menu_modulnya_sendiri(): void
    {
        $salah = [];

        foreach (Menu::all() as $kunci => $m) {
            $rute = Menu::ruteAwal($m);
            if (!$rute || !app('router')->has($rute)) continue;

            $jalur = parse_url(route($rute), PHP_URL_PATH) ?: '/';

            // modulAktif() membaca lewat fasad Request, dan fasad menyimpan
            // instance yang sudah pernah diselesaikan — mengikat ulang di
            // container saja tidak terbaca. swap() mengganti keduanya.
            \Illuminate\Support\Facades\Request::swap(\Illuminate\Http\Request::create($jalur));

            $dapat = Menu::modulAktif();
            if ($dapat !== $kunci) $salah[] = "{$jalur} → {$dapat}, seharusnya {$kunci}";
        }

        $this->assertSame([], $salah, implode('; ', $salah));
    }

    /**
     * Kelas warna yang dipakai tampilan harus benar-benar ada tokennya.
     *
     * `text-cam-orange` tertulis di delapan berkas, tetapi palet hanya
     * mengenal `cam-lime` — Tailwind melewati kelas yang tokennya tidak
     * ada tanpa mengeluh, jadi huruf Q pada logotype tampil putih dan
     * cincin kartu pilar terpilih tidak pernah muncul.
     */
    public function test_kelas_warna_cam_yang_dipakai_punya_token(): void
    {
        $config = file_get_contents(base_path('tailwind.config.js'));
        preg_match('/\bcam:\s*\{(.*?)\n\s{16}\}/s', $config, $blok);
        $this->assertNotEmpty($blok, 'Blok palet cam tidak ditemukan di tailwind.config.js.');

        preg_match_all("/^\s+'?([a-z-]+)'?:\s*'#/m", $blok[1], $t);
        $token = $t[1];
        $this->assertContains('orange', $token, 'Token cam.orange belum terdaftar.');

        $dipakai = [];
        foreach (['js' => resource_path('js'), 'views' => resource_path('views')] as $akar) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar));
            foreach ($it as $f) {
                if (!$f->isFile()) continue;
                if (!in_array($f->getExtension(), ['vue', 'php'], true)) continue;
                // Salinan cadangan bertanggal bukan berkas yang dirender.
                if (str_contains($f->getFilename(), '.bak-')) continue;

                preg_match_all('/\bcam-([a-z]+(?:-[a-z]+)?)\b/', file_get_contents($f->getPathname()), $c);
                foreach ($c[1] as $nama) {
                    $dipakai[$nama] ??= str_replace(base_path().'/', '', $f->getPathname());
                }
            }
        }

        $tanpaToken = [];
        foreach ($dipakai as $nama => $berkas) {
            if (!in_array($nama, $token, true)) $tanpaToken[] = "cam-{$nama} ({$berkas})";
        }

        sort($tanpaToken);
        $this->assertSame([], $tanpaToken,
            'Kelas berikut tidak menghasilkan CSS apa pun: '.implode(', ', $tanpaToken));
    }

    /**
     * Lambang lama tidak boleh dipakai lagi oleh tampilan mana pun.
     *
     * Ada dua identitas di public/brand: heksagon jingga-perak yang berlaku,
     * dan lambang gunung navy-emas yang digantikannya. Berkas lamanya sengaja
     * tidak dihapus — tetapi selama masih ada di sana, ia akan terpakai lagi
     * oleh siapa pun yang menebak nama berkas dari daftar direktori, dan
     * hasilnya adalah dua merek berbeda pada dua layar berurutan.
     */
    public function test_lambang_lama_tidak_dipakai_tampilan_mana_pun(): void
    {
        $pensiun = ['eqohsee-mark.svg', 'eqohsee-mark-white.svg',
                    'eqohsee-logo.svg', 'eqohsee-logo-white.svg'];

        $terpakai = [];

        foreach (['js', 'views'] as $sub) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path($sub)));
            foreach ($it as $f) {
                if (!$f->isFile() || str_contains($f->getFilename(), '.bak-')) continue;
                if (!in_array($f->getExtension(), ['vue', 'php'], true)) continue;

                preg_match_all("#brand/([A-Za-z0-9._-]+)#", file_get_contents($f->getPathname()), $c);
                foreach ($c[1] as $berkas) {
                    if (in_array($berkas, $pensiun, true)) {
                        $terpakai[] = $berkas.' di '.str_replace(base_path().'/', '', $f->getPathname());
                    }
                }
            }
        }

        $this->assertSame([], array_unique($terpakai),
            'Lambang lama dipakai lagi: '.implode('; ', array_unique($terpakai)));
    }

    /**
     * Tidak ada karakter hiasan yang dipakai sebagai pengganti ikon.
     *
     * Dashboard pernah menggambar satu belah ketupat "◈" yang ditulis
     * harfiah pada tiga tempat, sehingga lima kartu ringkasan dan enam
     * kartu modul semuanya memakai lambang yang sama — tidak satu pun
     * membedakan apa pun. Nama ikonnya sudah dikirim App\Support\Ikon
     * dan bahkan tercantum pada tipe ModuleItem, tetapi tidak dibaca.
     */
    public function test_tidak_ada_karakter_hiasan_sebagai_pengganti_ikon(): void
    {
        // Diambil dari yang benar-benar pernah dipakai sebagai ikon palsu.
        $palsu = ['◈', '◆', '◇', '▣', '❖'];

        $temuan = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('js')));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'vue') continue;
            // Berkas ikon sendiri boleh menyebutnya di dalam keterangan.
            if (str_contains($f->getFilename(), 'Ikon')) continue;

            $isi = file_get_contents($f->getPathname());
            foreach ($palsu as $c) {
                if (str_contains($isi, $c)) {
                    $temuan[] = $c.' di '.str_replace(base_path().'/', '', $f->getPathname());
                }
            }
        }

        $this->assertSame([], $temuan, implode('; ', $temuan));
    }

    /**
     * Tiap butir galeri menunjuk aspek yang benar-benar ada.
     *
     * Aspeknya menentukan warna kartunya; yang tidak dikenali jatuh ke
     * warna kosong tanpa satu pun galat, dan kartunya tampil abu-abu di
     * antara kartu berwarna.
     */
    public function test_aspek_galeri_semuanya_pilar_yang_terdaftar(): void
    {
        $pilar = array_keys(\App\Support\Pillars::all());

        $asing = [];
        foreach (\App\Support\Media::galeri() as $g) {
            if (!in_array($g['aspek'], $pilar, true)) $asing[] = $g['aspek'];
        }

        $this->assertSame([], $asing, 'Aspek tak dikenal: '.implode(', ', $asing));
    }

    /**
     * Berkas media yang didaftarkan harus benar-benar ada, atau slotnya
     * memang belum diisi sama sekali.
     *
     * Yang dijaga di sini bukan keberadaan berkasnya — pemasangan baru
     * memang belum punya rekaman — melainkan kecocokan nama: slot yang
     * salah eja lolos tanpa galat dan hanya tampil sebagai kotak kosong.
     */
    public function test_setiap_butir_galeri_punya_gambar_dan_video_yang_sepasang(): void
    {
        $timpang = [];
        foreach (\App\Support\Media::galeri() as $g) {
            $adaGambar = \App\Support\Media::ada($g['gambar']);
            $adaVideo  = \App\Support\Media::ada($g['video'] ?? null);

            // Video tanpa posternya menampilkan kotak hitam sebelum
            // pemutarannya dimulai; poster tanpa video hanya foto diam.
            if ($adaVideo && !$adaGambar) $timpang[] = $g['video'].' tanpa '.$g['gambar'];
        }

        $this->assertSame([], $timpang, implode('; ', $timpang));
    }

    /** Lambang yang dirujuk tampilan harus benar-benar ada di public/brand. */
    public function test_berkas_lambang_yang_dirujuk_benar_benar_ada(): void
    {
        $hilang = [];

        foreach (['js', 'views'] as $sub) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path($sub)));
            foreach ($it as $f) {
                if (!$f->isFile() || str_contains($f->getFilename(), '.bak-')) continue;
                if (!in_array($f->getExtension(), ['vue', 'php'], true)) continue;

                preg_match_all("#brand/([A-Za-z0-9._-]+\.(?:svg|png|jpg))#", file_get_contents($f->getPathname()), $c);
                foreach ($c[1] as $berkas) {
                    if (!is_file(public_path('brand/'.$berkas))) {
                        $hilang[] = $berkas.' di '.str_replace(base_path().'/', '', $f->getPathname());
                    }
                }
            }
        }

        $this->assertSame([], array_unique($hilang), implode('; ', array_unique($hilang)));
    }
}
