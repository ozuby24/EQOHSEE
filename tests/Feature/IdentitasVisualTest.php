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
