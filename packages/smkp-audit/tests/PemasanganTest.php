<?php

namespace Eqohsee\SmkpAudit\Tests;

use Eqohsee\SmkpAudit\Adapters\JejakDiam;
use Eqohsee\SmkpAudit\Adapters\PerusahaanDariPengguna;
use Eqohsee\SmkpAudit\Contracts\BatasPerusahaan;
use Eqohsee\SmkpAudit\Contracts\PencatatJejak;
use Eqohsee\SmkpAudit\SmkpServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * Pemasangan modul pada sebuah aplikasi Laravel.
 *
 * Aplikasi di sini dibangkitkan sekadarnya — tanpa basis data, tanpa HTTP —
 * karena yang diuji memang bukan alurnya melainkan PEMASANGANNYA: setelan
 * ikut termuat, dua kontrak terikat ke adaptor bawaan, dan seluruh rute
 * modul benar-benar terdaftar dengan nama serta ruas yang dijanjikan
 * README. Ketiganya rusak tanpa menimbulkan galat apa pun — rute yang tidak
 * terdaftar baru terlihat sebagai 404 di tangan pemakai.
 */
class PemasanganTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(dirname(__DIR__));
        $this->app->instance('config', new Repository([]));
        Facade::setFacadeApplication($this->app);

        $this->app->register(SmkpServiceProvider::class);
        $this->app->boot();

        $this->segarkanNama($this->app);
    }

    /**
     * Menyegarkan daftar nama rute.
     *
     * Pada aplikasi Laravel penuh hal ini dilakukan RouteServiceProvider lewat
     * callback `booted`; aplikasi sekadarnya di sini tidak memuatnya, sehingga
     * pencarian rute berdasarkan nama akan menjawab null walau rutenya sudah
     * terdaftar. Yang ditambal karena itu harnessnya, bukan modulnya.
     */
    private function segarkanNama(Application $app): void
    {
        $app['router']->getRoutes()->refreshNameLookups();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Application::setInstance(null);

        parent::tearDown();
    }

    public function test_setelan_bawaan_ikut_termuat(): void
    {
        $this->assertSame('smkp',  $this->app['config']->get('smkp.rute.awalan'));
        $this->assertSame('smkp.', $this->app['config']->get('smkp.rute.nama'));
        $this->assertSame('Smkp/Halaman', $this->app['config']->get('smkp.inertia.halaman'));
    }

    public function test_dua_titik_sambung_terikat_ke_adaptor_bawaan(): void
    {
        // Bawaannya harus bekerja tanpa setelan apa pun: modul yang menuntut
        // kelas aplikasi induk sebelum dapat dijalankan tidak akan pernah
        // dicoba orang.
        $this->assertInstanceOf(PerusahaanDariPengguna::class, $this->app->make(BatasPerusahaan::class));
        $this->assertInstanceOf(JejakDiam::class, $this->app->make(PencatatJejak::class));
    }

    public function test_adaptor_dapat_ditukar_lewat_setelan(): void
    {
        $this->app['config']->set('smkp.jejak', \Eqohsee\SmkpAudit\Adapters\JejakLog::class);

        $this->assertInstanceOf(
            \Eqohsee\SmkpAudit\Adapters\JejakLog::class,
            $this->app->make(PencatatJejak::class)
        );
    }

    public function test_seluruh_rute_modul_terdaftar(): void
    {
        $rute = $this->app['router']->getRoutes();

        $harap = [
            'smkp.index', 'smkp.create', 'smkp.store', 'smkp.acuan',
            'smkp.show', 'smkp.edit', 'smkp.update', 'smkp.destroy',
            'smkp.laporan', 'smkp.tahap',
            'smkp.tahap1', 'smkp.tahap1.simpan', 'smkp.berita-acara',
            'smkp.rencana', 'smkp.rencana.simpan', 'smkp.rencana.cetak',
            'smkp.rapat', 'smkp.rapat.simpan', 'smkp.rapat.hapus', 'smkp.hadir.cetak',
            'smkp.temuan', 'smkp.temuan.angkat', 'smkp.temuan.simpan', 'smkp.temuan.hapus',
            'smkp.nilai', 'smkp.nilai.simpan',
        ];

        foreach ($harap as $nama) {
            $this->assertNotNull($rute->getByName($nama), "Rute {$nama} tidak terdaftar.");
        }
    }

    public function test_tiap_rute_menunjuk_aksi_yang_benar_ada(): void
    {
        // Nama aksi yang salah ketik tidak menggagalkan pendaftaran rute; ia
        // baru terlihat sebagai galat ketika halamannya dibuka pemakai.
        foreach ($this->app['router']->getRoutes() as $r) {
            if (!str_starts_with($r->uri(), 'smkp')) continue;

            [$kelas, $metode] = explode('@', $r->getActionName());

            $this->assertTrue(
                method_exists($kelas, $metode),
                "Rute {$r->uri()} menunjuk {$kelas}::{$metode}() yang tidak ada."
            );
        }
    }

    public function test_pintasan_menu_terdaftar_untuk_tiap_bagian(): void
    {
        $rute = $this->app['router']->getRoutes();

        foreach (['tahap1','rencana','rapat','temuan','berita','rencana-cetak','laporan'] as $bagian) {
            $r = $rute->getByName('smkp.ke.'.$bagian);
            $this->assertNotNull($r, "Pintasan smkp.ke.{$bagian} tidak terdaftar.");
            $this->assertSame($bagian, $r->defaults['bagian'] ?? null);
        }
    }

    public function test_rute_beruas_tetap_didaftarkan_sebelum_parameter_audit(): void
    {
        // 'buat' dan 'acuan' harus lebih dulu, jika tidak pengikat model akan
        // mencari audit bernomor "buat" dan menjawab 404.
        $urutan = [];
        foreach ($this->app['router']->getRoutes() as $r) {
            if (str_starts_with($r->uri(), 'smkp')) $urutan[] = $r->uri();
        }

        $this->assertLessThan(
            array_search('smkp/{smkp}', $urutan, true),
            array_search('smkp/buat', $urutan, true),
            'Rute smkp/buat harus terdaftar sebelum smkp/{smkp}.'
        );
    }

    public function test_awalan_rute_mengikuti_setelan(): void
    {
        // Facade menyimpan instans yang sudah pernah diselesaikan; tanpa
        // dibersihkan, Route di bawah masih menunjuk router aplikasi pertama.
        Facade::clearResolvedInstances();

        $ini = new Application(dirname(__DIR__));
        $ini->instance('config', new Repository(['smkp' => ['rute' => [
            'awalan' => 'audit-smkp',
            'nama'   => 'audit.',
            'daftar' => true,
        ]]]));
        Facade::setFacadeApplication($ini);

        $ini->register(SmkpServiceProvider::class);
        $ini->boot();
        $this->segarkanNama($ini);

        $rute = $ini['router']->getRoutes()->getByName('audit.index');

        $this->assertNotNull($rute, 'Nama rute harus mengikuti config.');
        $this->assertSame('audit-smkp', $rute->uri());
    }
}
