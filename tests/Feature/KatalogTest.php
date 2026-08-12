<?php

namespace Tests\Feature;

use App\Support\{Modules, Pillars};
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Katalog modul dan registry pilar saling merujuk lewat nama dan slug.
 * Rujukan seperti itu mudah melenceng diam-diam saat salah satunya diubah,
 * dan melencengnya tidak menimbulkan galat — hanya tampilan yang salah.
 * Uji berikut menjaga keduanya tetap sinkron.
 */
class KatalogTest extends TestCase
{
    public function test_setiap_modul_menunjuk_pilar_yang_ada(): void
    {
        $slug = Pillars::slugs();

        foreach (Modules::all() as $m) {
            $this->assertArrayHasKey('pilar', $m, "Modul '{$m['nama']}' belum punya pilar utama.");
            $this->assertContains($m['pilar'], $slug,
                "Modul '{$m['nama']}' menunjuk pilar '{$m['pilar']}' yang tidak terdaftar.");
        }
    }

    public function test_nama_modul_pada_registry_pilar_ada_di_katalog(): void
    {
        $katalog = array_column(Modules::all(), 'nama');

        foreach (Pillars::all() as $slug => $p) {
            foreach ($p['modul'] as $nama) {
                $this->assertContains($nama, $katalog,
                    "Pilar '{$slug}' menyebut modul '{$nama}' yang tidak ada di katalog.");
            }
        }
    }

    /**
     * Tiap modul aktif harus benar-benar dapat dibuka.
     *
     * Bukan sekadar ada kuncinya, melainkan sampai di ujungnya: rutenya
     * harus terdaftar. Modul yang tertulis di katalog tetapi tidak punya
     * alamat lolos seluruh pemeriksaan lain dan baru ketahuan saat ada
     * yang mengkliknya.
     */
    public function test_modul_aktif_dapat_dibuka(): void
    {
        foreach (Modules::all() as $m) {
            if (($m['status'] ?? '') !== 'aktif') continue;

            $this->assertArrayHasKey('rute', $m, "Modul aktif '{$m['nama']}' belum punya rute.");
            $this->assertTrue(Route::has($m['rute']),
                "Modul '{$m['nama']}' menunjuk rute '{$m['rute']}' yang tidak terdaftar.");
        }
    }


    /**
     * Modul yang belum aktif tidak boleh punya rute.
     *
     * Tampilan memakai ketiadaan rute untuk menentukan kartu mana yang
     * dapat diklik; modul 'segera' yang punya rute akan tampil siap pakai.
     *
     * Kosakata status ikut diperiksa supaya uji ini tetap memeriksa
     * sesuatu ketika seluruh modul kebetulan sudah aktif — tanpa itu,
     * perulangannya melewati semua baris dan ujinya lulus tanpa arti,
     * persis pada saat katalognya paling mudah salah ketik.
     */
    public function test_modul_belum_aktif_tidak_punya_rute(): void
    {
        foreach (Modules::all() as $m) {
            $this->assertContains($m['status'] ?? '', ['aktif', 'segera'],
                "Modul '{$m['nama']}' berstatus '{$m['status']}' yang tidak dikenal tampilan.");

            if (($m['status'] ?? '') === 'aktif') continue;

            $this->assertArrayNotHasKey('rute', $m,
                "Modul '{$m['nama']}' berstatus segera tetapi punya rute.");
        }
    }

    public function test_forModule_membaca_katalog_bukan_menebak_nama(): void
    {
        // LMS pernah terbaca sebagai Safety karena pencocokan potongan nama.
        $this->assertSame('quality', Pillars::forModule('LMS — Learning Center'));
        $this->assertSame('safety',  Pillars::forModule('Safety Maturity Level'));
        $this->assertSame('engineering', Pillars::forModule('Nama Yang Tidak Dikenal'));
    }

    public function test_setiap_pilar_lengkap_untuk_ditampilkan(): void
    {
        foreach (Pillars::all() as $slug => $p) {
            foreach (['nama','ket','deep','warna','light','ringkas','cakupan','modul'] as $kunci) {
                $this->assertArrayHasKey($kunci, $p, "Pilar '{$slug}' kehilangan '{$kunci}'.");
            }

            foreach (['deep','warna','light'] as $k) {
                $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $p[$k],
                    "Warna '{$k}' pilar '{$slug}' bukan hex 6 digit.");
            }

            $this->assertNotEmpty($p['cakupan'], "Pilar '{$slug}' tidak punya cakupan kerja.");
            foreach ($p['cakupan'] as $c) {
                $this->assertCount(2, $c, "Cakupan pilar '{$slug}' harus berpasangan judul dan isi.");
            }
        }
    }

    public function test_gradien_pilar_terbentuk_dan_punya_cadangan(): void
    {
        $this->assertStringContainsString(Pillars::get('energy')['warna'], Pillars::gradient('energy'));
        $this->assertStringStartsWith('linear-gradient', Pillars::gradient('tidak-ada'));
    }

    /* ---------- delapan aspek ---------- */

    public function test_eqohsee_membawa_delapan_aspek(): void
    {
        // Tujuh mengikuti ejaan namanya, satu lagi — Konservasi Minerba —
        // berdiri di luar akronim sebagai kewajiban tersendiri.
        $this->assertCount(8, Pillars::all());
    }

    public function test_tiap_huruf_eqohsee_menunjuk_aspek_yang_berbeda(): void
    {
        // Sebelumnya O dan H sama-sama menunjuk Occupational Health, sehingga
        // satu huruf tidak membawa aspek sendiri.
        $huruf = ['energy','quality','occhealth','hygiene','safety','environment','engineering'];

        $this->assertSame($huruf, array_unique($huruf), 'Dua huruf tidak boleh menunjuk aspek yang sama.');
        foreach ($huruf as $slug) {
            $this->assertNotNull(Pillars::get($slug), "Aspek '{$slug}' belum terdaftar.");
        }
    }

    public function test_setiap_aspek_lengkap_identitasnya(): void
    {
        foreach (Pillars::all() as $slug => $p) {
            foreach (['nama','ket','deep','warna','light','ringkas','cakupan','modul'] as $kunci) {
                $this->assertNotEmpty($p[$kunci] ?? null, "Aspek '{$slug}' belum punya '{$kunci}'.");
            }
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $p['warna'], "Warna '{$slug}' tidak sah.");
        }
    }

    public function test_palet_aspek_tetap_bertahan_pada_tiga_warna(): void
    {
        // Dahulu tiap aspek berwarna sendiri — delapan hue pada satu layar.
        // Delapan warna berhenti membedakan apa pun: yang tersisa hanya
        // pelangi, dan mereknya ikut hilang di dalamnya. Paletnya kini
        // hanya jingga, cyan, dan hijau yang berulang.
        $warna = array_values(array_unique(array_column(Pillars::all(), 'warna')));

        $this->assertLessThanOrEqual(3, count($warna),
            'Palet aspek tidak boleh melebar lagi menjadi pelangi.');
    }

    public function test_tiap_aspek_dibedakan_namanya_bukan_warnanya(): void
    {
        // Karena warna kini berulang, nama aspeklah yang membedakan — dan
        // nama itu harus benar-benar tampil di kartu modul, bukan sekadar
        // tersimpan di registry.
        $nama = array_column(Pillars::all(), 'nama');

        $this->assertSame($nama, array_unique($nama), 'Dua aspek tidak boleh bernama sama.');

        $this->get('/')->assertOk()->assertSee('Pilar '.Pillars::get('energy')['nama']);
    }
}
