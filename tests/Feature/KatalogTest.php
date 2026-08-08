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

    public function test_modul_aktif_punya_rute_yang_terdaftar(): void
    {
        foreach (Modules::all() as $m) {
            if (($m['status'] ?? '') !== 'aktif') continue;

            $this->assertArrayHasKey('rute', $m, "Modul aktif '{$m['nama']}' belum punya rute.");
            $this->assertTrue(Route::has($m['rute']),
                "Modul '{$m['nama']}' menunjuk rute '{$m['rute']}' yang tidak terdaftar.");
        }
    }

    public function test_modul_belum_aktif_tidak_punya_rute(): void
    {
        // Tampilan memakai ketiadaan rute untuk menentukan kartu mana yang
        // dapat diklik; modul 'segera' yang punya rute akan tampil siap pakai.
        foreach (Modules::all() as $m) {
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
        $this->assertStringContainsString('#1F6FB8', Pillars::gradient('energy'));
        $this->assertStringStartsWith('linear-gradient', Pillars::gradient('tidak-ada'));
    }
}
