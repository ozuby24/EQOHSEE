<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Panel administrasi (Inertia).
 */
class AdminInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): User
    {
        $u = User::factory()->create(['is_admin' => true, 'name' => 'Admin Utama']);
        $this->actingAs($u);

        return $u;
    }

    private function perusahaan(array $x = []): Company
    {
        static $n = 0;
        $n++;

        return Company::create(array_merge([
            'name' => "PT Uji {$n}", 'code' => "UJI{$n}", 'parent' => '',
            'risk_class' => 'Tinggi', 'workers_employee' => 0, 'workers_sub' => 0, 'doc_revisi' => 0,
        ], $x));
    }

    private function props(string $rute): array
    {
        return $this->get(route($rute))->assertOk()->viewData('page')['props'];
    }

    public function test_halaman_admin_dirender_inertia(): void
    {
        $this->masuk();

        $peta = [
            'admin.system'            => 'Admin/Sistem',
            'admin.users.index'       => 'Admin/Pengguna/Daftar',
            'admin.users.create'      => 'Admin/Pengguna/Form',
            'admin.companies.index'   => 'Admin/Perusahaan/Daftar',
            'admin.companies.create'  => 'Admin/Perusahaan/Form',
        ];

        foreach ($peta as $rute => $komponen) {
            $this->get(route($rute))->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component($komponen)->has('judul')->has('subjudul'),
            );
        }
    }

    public function test_akun_yang_sedang_dipakai_ditandai_agar_tombol_hapusnya_tidak_digambar(): void
    {
        // Server tetap menolak penghapusan diri sendiri, tetapi memberi
        // tombol yang pasti gagal hanya membuat orang mencoba lalu
        // membaca pesan galat.
        $saya = $this->masuk();
        $lain = User::factory()->create(['name' => 'Orang Lain']);

        $daftar = collect($this->props('admin.users.index')['daftar'])->keyBy('id');

        $this->assertTrue($daftar[$saya->id]['diri']);
        $this->assertFalse($daftar[$lain->id]['diri']);
    }

    public function test_daftar_pengguna_tidak_memakai_nama_prop_bersama(): void
    {
        // 'pengguna' milik HandleInertiaRequests. Halaman yang memakainya
        // menimpa pengguna yang sedang masuk, dan tata letak yang
        // membaca pengguna.nama gagal merender seluruh halaman.
        $this->masuk();

        $p = $this->props('admin.users.index');

        $this->assertArrayHasKey('daftar', $p);
        $this->assertArrayHasKey('nama', (array) $p['pengguna'],
            "Prop 'pengguna' harus tetap berisi pengguna yang sedang masuk.");
    }

    public function test_perusahaan_tidak_boleh_menjadi_induk_dirinya_sendiri(): void
    {
        // Rantai induk yang menunjuk balik membuat pencarian logo induk
        // berputar tanpa henti.
        $this->masuk();
        $a = $this->perusahaan(['name' => 'PT Alpha']);
        $this->perusahaan(['name' => 'PT Beta']);

        $p = $this->get(route('admin.companies.edit', $a))->assertOk()->viewData('page')['props'];

        $nilai = array_column($p['opsi']['induk'], 'label');
        $this->assertNotContains('PT Alpha', $nilai);
        $this->assertContains('PT Beta', $nilai);
    }

    public function test_sistem_membawa_tren_tujuh_hari_yang_lengkap(): void
    {
        // Tujuh batang selalu digambar, termasuk hari tanpa aktivitas —
        // hari yang hilang dari sumbunya membuat grafiknya berbohong
        // tentang jaraknya.
        $this->masuk();
        ActivityLog::write('Uji', 'satu aktivitas');

        $p = $this->props('admin.system');

        $this->assertCount(7, $p['tren']);
        $this->assertSame(1, array_sum(array_column($p['tren'], 'jumlah')));
        $this->assertSame(1, end($p['tren'])['jumlah'], 'Aktivitas hari ini ada di batang terakhir.');
    }

    public function test_statistik_modul_membawa_ikon_tiap_baris(): void
    {
        $this->masuk();

        foreach ($this->props('admin.system')['modul'] as $m) {
            $this->assertNotEmpty($m['items']);
            foreach ($m['items'] as $x) {
                $this->assertNotEmpty($x['ikon'], "Baris '{$x['label']}' tanpa ikon.");
            }
        }
    }
}
