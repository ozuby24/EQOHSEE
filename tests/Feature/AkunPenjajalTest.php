<?php

namespace Tests\Feature;

use App\Console\Commands\PasangDemo;
use App\Models\{Company, User};
use App\Support\{DataContoh, Menu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Dua akun penjajal: `demo@contoh.test` dan `test@contoh.test`.
 *
 * Keduanya ada untuk MENCOBA situsnya. Akun KTT dan PJO tiap perusahaan
 * contoh sudah ada lebih dulu, tetapi keduanya pengguna biasa — modul
 * Administrasi tertutup bagi mereka, dan siapa pun yang hendak memeriksa
 * "apakah tiap fitur jalan" karena itu berakhir meminjam akun
 * administrator sungguhan.
 *
 * Yang dijaga berkas ini bukan "akunnya dibuat", melainkan tiga hal yang
 * membuatnya berguna dan satu hal yang membuatnya berbahaya:
 *
 *   — keduanya benar-benar DAPAT MASUK dengan sandi yang dicetak
 *     perintahnya (akun penjajal yang tidak dapat masuk tidak menguji
 *     apa pun),
 *   — perannya sungguh BERBEDA, sehingga yang satu melihat modul admin
 *     dan yang lain tidak,
 *   — yang biasa duduk di perusahaan yang BERISI, bukan di halaman
 *     kosong,
 *   — dan keduanya IKUT TERHAPUS oleh `--hapus`.
 */
class AkunPenjajalTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'rahasia123';

    /** Dibaca dari perintahnya, bukan diketik ulang: diketik ulang, uji
     *  ini akan tetap hijau justru pada saat surelnya berubah. */
    private const SUREL = [PasangDemo::PENJAJAL_ADMIN, PasangDemo::PENJAJAL_BIASA];

    private function pasang(): void
    {
        $this->artisan('demo:pasang --hanya=ABG')->assertSuccessful();
    }

    private function penjajal(string $surel): User
    {
        return User::withoutGlobalScopes()->where('email', $surel)->firstOrFail();
    }

    /* ═══════════ keduanya ada, dan dapat dipakai ═══════════ */

    /**
     * Sandi yang dicetak perintahnya benar-benar sandi akunnya.
     *
     * Ini satu-satunya uji yang gagal bila `akun()` suatu hari lupa
     * memasang sandi pada akun baru: akunnya tetap ada, tetap aktif,
     * tetap terlihat benar di tabel users — dan tidak seorang pun dapat
     * masuk dengannya. Kegagalannya diam, dan yang menemukannya adalah
     * orang yang sedang mencoba situsnya untuk pertama kali.
     */
    public function test_keduanya_dapat_masuk_dengan_sandi_yang_dicetak(): void
    {
        $this->pasang();

        foreach (self::SUREL as $surel) {
            $u = $this->penjajal($surel);

            $this->assertTrue(Hash::check(self::SANDI, $u->password),
                "Sandi {$surel} bukan sandi yang dicetak perintahnya.");

            $this->assertTrue((bool) $u->active, "{$surel} tidak aktif.");
            $this->assertNotNull($u->email_verified_at,
                "{$surel} belum terverifikasi — tertahan di halaman verifikasi.");
        }
    }

    /**
     * Perannya sungguh berbeda, bukan dua salinan bernama lain.
     *
     * Dua akun penjajal yang sama perannya tidak memeriksa apa pun yang
     * tidak diperiksa satu akun. Yang membuat pasangan ini berguna
     * justru selisihnya.
     */
    public function test_perannya_berbeda_dan_administrator_lintas_perusahaan(): void
    {
        $this->pasang();

        $admin = $this->penjajal(PasangDemo::PENJAJAL_ADMIN);
        $biasa = $this->penjajal(PasangDemo::PENJAJAL_BIASA);

        $this->assertTrue($admin->isAdmin(), 'Akun demo bukan administrator.');
        $this->assertFalse($biasa->isAdmin(), 'Akun test justru administrator.');

        /* Tanpa company_id, dan itu disengaja: pemilih "Semua
           perusahaan" hanya dapat dicoba oleh akun yang memang tidak
           terikat satu perusahaan. Diberi company_id, pilihan itu
           menjadi satu-satunya fitur yang tidak dapat diperiksa dari
           akun yang dibuat untuk memeriksa fitur. */
        $this->assertNull($admin->company_id,
            'Akun demo terikat satu perusahaan; pemilih "Semua perusahaan" '
            .'tidak dapat dicoba dengannya.');

        $this->assertNotNull($biasa->company_id,
            'Akun test tidak terikat perusahaan mana pun — halamannya kosong.');
    }

    /**
     * Yang biasa duduk di perusahaan yang ADA ISINYA.
     *
     * Inilah setengah dari yang diminta: akun untuk mencoba fitur, yang
     * begitu dibuka memperlihatkan halaman kosong, membuat tiap fitur
     * terlihat rusak dengan cara yang sama persis seperti fitur yang
     * memang rusak.
     */
    public function test_akun_biasa_duduk_di_perusahaan_berisi(): void
    {
        $this->pasang();

        $c = Company::withoutGlobalScopes()
            ->findOrFail($this->penjajal(PasangDemo::PENJAJAL_BIASA)->company_id);

        $this->assertTrue((bool) $c->demo, 'Akun test tidak duduk di perusahaan contoh.');

        $this->assertGreaterThan(0, DataContoh::hitungIsi($c),
            'Perusahaan akun test tidak berisi apa pun. Akun penjajal yang '
            .'membuka halaman kosong membuat fitur yang sehat tidak dapat '
            .'dibedakan dari fitur yang rusak.');
    }

    /**
     * Administrator melihat modul yang memang hanya untuk administrator.
     *
     * Dibaca dari Menu::untuk() — sumber yang sama yang dipakai bilah
     * sampingnya, bukan daftar kedua yang ditulis ulang di sini.
     */
    public function test_hanya_administrator_yang_ditawari_modul_admin(): void
    {
        $this->pasang();

        $adminModul = array_keys(Menu::untuk($this->penjajal(PasangDemo::PENJAJAL_ADMIN)));
        $biasaModul = array_keys(Menu::untuk($this->penjajal(PasangDemo::PENJAJAL_BIASA)));

        $adaModulAdmin = false;

        foreach (Menu::all() as $k => $m) {
            if (!($m['admin'] ?? false)) {
                continue;
            }

            $adaModulAdmin = true;

            $this->assertContains($k, $adminModul, "Modul admin '{$k}' tidak terlihat akun demo.");
            $this->assertNotContains($k, $biasaModul, "Modul admin '{$k}' terlihat akun test.");
        }

        $this->assertTrue($adaModulAdmin,
            'Tidak ada satu pun modul bertanda admin, sehingga uji ini tidak '
            .'membandingkan apa pun.');
    }

    /* ═══════════ dan keduanya dapat dibersihkan ═══════════ */

    /**
     * `--hapus` membuang keduanya.
     *
     * Terukur sebelum ini dijaga: akun `demo@contoh.test` SELAMAT dari
     * `--hapus`. `hapus()` membuang akun lewat company_id perusahaan
     * yang dihapusnya, dan akun itu sengaja tidak punya company_id —
     * sehingga yang tertinggal adalah akun ADMINISTRATOR berkata sandi
     * contoh yang seragam dan lemah, pada pemasangan yang pemiliknya
     * baru saja diberi tahu sudah bersih.
     */
    public function test_hapus_membuang_kedua_akun_penjajal(): void
    {
        Company::create(['name' => 'PT Penjaga', 'code' => 'PJG']);

        $this->pasang();

        $this->artisan('demo:pasang --hanya=ABG --hapus --paksa')->assertSuccessful();

        foreach (self::SUREL as $surel) {
            $this->assertNull(User::withoutGlobalScopes()->where('email', $surel)->first(),
                "{$surel} selamat dari --hapus dan masih dapat masuk dengan sandi contoh.");
        }
    }

    /**
     * `--hapus` tidak menerbitkan ulang apa yang baru saja dihapusnya.
     *
     * Uji di atas lolos juga bila keduanya dihapus lalu dibuat lagi
     * pada perintah yang SAMA — asal urutannya kebetulan benar. Yang
     * dijaga di sini urutannya sendiri: pembuatan akun penjajal berada
     * di percabangan yang tidak dijalani `--hapus`.
     */
    public function test_hapus_tidak_menerbitkan_ulang_akun_penjajal(): void
    {
        Company::create(['name' => 'PT Penjaga', 'code' => 'PJG']);

        $this->pasang();

        $this->artisan('demo:pasang --hanya=ABG --hapus --paksa')->assertSuccessful();

        $this->assertSame(0, User::withoutGlobalScopes()
            ->where('email', 'like', '%@contoh.test')->count(),
            'Masih ada akun bersurel contoh.test sesudah --hapus.');
    }

    /* ═══════════ aman diulang ═══════════ */

    /**
     * Sandi yang sudah diganti orang tidak dikembalikan diam-diam.
     *
     * `demo:pasang` dijalankan ulang tiap kali data contohnya
     * disegarkan. Pada pemasangan pratinjau yang terbuka ke jaringan,
     * akun ini tidak lagi sekadar akun contoh — dan sandi yang kembali
     * ke bawaannya tanpa sepatah kata pun adalah pintu yang terbuka
     * kembali sendiri.
     */
    public function test_menjalankan_ulang_tidak_menimpa_sandi_yang_diganti(): void
    {
        $this->pasang();

        $u = $this->penjajal(PasangDemo::PENJAJAL_ADMIN);
        $u->password = 'sandi-baru-yang-panjang';
        $u->save();

        $this->pasang();

        $this->assertTrue(
            Hash::check('sandi-baru-yang-panjang', $this->penjajal(PasangDemo::PENJAJAL_ADMIN)->password),
            'Sandi akun penjajal kembali ke bawaannya hanya karena data contohnya disegarkan.');
    }

    /** Dijalankan dua kali tidak menghasilkan akun berganda. */
    public function test_menjalankan_ulang_tidak_menggandakan_akunnya(): void
    {
        $this->pasang();
        $this->pasang();

        foreach (self::SUREL as $surel) {
            $this->assertSame(1, User::withoutGlobalScopes()->where('email', $surel)->count(),
                "{$surel} tergandakan oleh pemasangan ulang.");
        }
    }

    /**
     * Keduanya tiba dengan pengenalan situs BELUM selesai.
     *
     * Sambutan bagi akun baru itu sendiri salah satu fitur yang hendak
     * dicoba dari akun ini. Ditandai selesai sejak lahir, ia menjadi
     * satu-satunya fitur yang tidak dapat diperiksa dari akun yang
     * dibuat untuk memeriksa fitur.
     */
    public function test_keduanya_masih_dapat_melihat_pengenalan_situs(): void
    {
        $this->pasang();

        foreach (self::SUREL as $surel) {
            $this->assertNull($this->penjajal($surel)->tur_selesai_pada,
                "{$surel} sudah ditandai selesai melihat pengenalan, sehingga "
                .'sambutan akun baru tidak dapat dicoba dari akun penjajal.');
        }
    }
}
