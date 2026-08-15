<?php

namespace Tests\Feature;

use App\Models\{Company, KoObject, TindakLanjut, User, WorkOrder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Maintenance disetarakan dengan Operasi dan Konservasi: verifikasi,
 * peringatan bertindakan, tindak lanjut, dan laporan cetak.
 *
 * Verifikasinya sengaja TIDAK bekerja seperti alur tinjauan data
 * produksi. Di sana, yang belum disetujui dikeluarkan dari hitungan
 * sehingga capaian terlihat lebih kecil. Di sini akibatnya terbalik:
 * mengeluarkan waktu henti yang belum diverifikasi membuat ketersediaan
 * terlihat lebih BAGUS persis ketika verifikasinya tertinggal. Alat yang
 * rusak tetap rusak walau laporannya belum ditandatangani.
 */
class MaintenanceLengkapTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $montir;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-31 12:00:00');
        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->montir = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);

        KoObject::create([
            'company_id' => $this->company->id, 'kode' => 'DT-001', 'nama' => 'Dump Truck 001',
            'kategori' => 'Alat Angkut', 'kritikalitas' => 'Tinggi', 'status_operasi' => 'Beroperasi',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function wo(array $ganti = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'company_id' => $this->company->id, 'jenis' => 'korektif', 'prioritas' => 'sedang',
            'status' => 'selesai', 'gejala' => 'Overheat',
            'dilaporkan_pada' => '2026-08-01 06:00:00',
            'mulai_pada' => '2026-08-01 14:00:00',
            'selesai_pada' => '2026-08-01 18:00:00',
            'ditutup_oleh' => $this->montir->id,
        ], $ganti));
    }

    private function props(string $rute = 'maintenance.index'): array
    {
        return $this->actingAs($this->montir)
            ->get(route($rute, ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()->viewData('page')['props'];
    }

    /* ---------- asimetri yang disengaja ---------- */

    public function test_waktu_henti_belum_diverifikasi_tetap_dihitung(): void
    {
        $this->wo();   // 12 jam henti, belum diverifikasi

        $k = $this->props()['keandalan'];

        $this->assertSame(12.0, $k['jamHenti'],
            'Mengeluarkannya akan membuat ketersediaan terlihat lebih bagus justru saat verifikasi tertinggal.');
        $this->assertLessThan(100.0, $k['ketersediaan']);
    }

    public function test_yang_belum_diverifikasi_disebutkan_sebagai_peringatan(): void
    {
        $this->wo();

        $kode = collect($this->props()['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('wo-belum-diverifikasi'));
    }

    /* ---------- verifikasi ---------- */

    public function test_penutup_tidak_dapat_memverifikasi_pekerjaannya_sendiri(): void
    {
        // KTT yang menutup sendiri: memegang hak, tetapi ia pelapornya.
        $wo = $this->wo(['ditutup_oleh' => $this->ktt->id]);

        $this->actingAs($this->ktt)
            ->post(route('maintenance.verifikasi', $wo))
            ->assertSessionHasErrors('alur');

        $this->assertNull($wo->fresh()->diverifikasi_pada);
    }

    public function test_montir_biasa_tidak_dapat_memverifikasi(): void
    {
        $wo = $this->wo();

        $this->actingAs($this->montir)
            ->post(route('maintenance.verifikasi', $wo))
            ->assertSessionHasErrors('alur');
    }

    public function test_ktt_dapat_memverifikasi_penutupan_orang_lain(): void
    {
        $wo = $this->wo();

        $this->actingAs($this->ktt)
            ->post(route('maintenance.verifikasi', $wo))
            ->assertSessionHasNoErrors();

        $wo->refresh();
        $this->assertSame($this->ktt->id, $wo->diverifikasi_oleh);
        $this->assertNotNull($wo->diverifikasi_pada);
    }

    public function test_yang_sudah_diverifikasi_terkunci_dari_perubahan(): void
    {
        $wo = $this->wo();
        $wo->verifikasi($this->ktt);

        $this->actingAs($this->montir)
            ->put(route('maintenance.status', $wo), ['status' => 'dibuka'])
            ->assertSessionHasErrors('alur');

        $this->assertSame('selesai', $wo->fresh()->status);
    }

    public function test_admin_dapat_membatalkan_verifikasi_untuk_menyunting_ulang(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);
        $wo = $this->wo();
        $wo->verifikasi($this->ktt);

        $this->actingAs($admin)->post(route('maintenance.batalVerifikasi', $wo))->assertSessionHasNoErrors();
        $this->assertNull($wo->fresh()->diverifikasi_pada);

        $this->actingAs($admin)
            ->put(route('maintenance.status', $wo), ['status' => 'dikerjakan'])
            ->assertSessionHasNoErrors();
    }

    public function test_yang_masih_terbuka_belum_menunggu_verifikasi(): void
    {
        $wo = $this->wo(['status' => 'dikerjakan', 'selesai_pada' => null]);

        $this->assertFalse($wo->menungguVerifikasi(),
            'Yang belum ditutup belum ada penutupannya untuk diverifikasi.');
        $this->assertFalse($wo->dapatDiverifikasiOleh($this->ktt));
    }

    /* ---------- peringatan ---------- */

    public function test_setiap_peringatan_menyebutkan_tindakan(): void
    {
        $this->wo();

        foreach ($this->props()['alerts'] as $a) {
            $this->assertArrayHasKey('kode', $a);
            $this->assertNotEmpty($a['saran'] ?? null,
                "Peringatan '{$a['kode']}' tidak menyebutkan apa yang harus dikerjakan.");
        }
    }

    public function test_menunggu_terlalu_lama_memunculkan_peringatan(): void
    {
        // 12 jam henti, 4 jam kerja → 67 % menunggu.
        $this->wo();

        $kode = collect($this->props()['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('menunggu-terlalu-lama'));
    }

    /* ---------- tindak lanjut ---------- */

    public function test_tindak_lanjut_menandai_peringatan_yang_ditangani(): void
    {
        $this->wo();

        $this->actingAs($this->montir)->post(route('maintenance.tindak.simpan'), [
            'kode_pemicu' => 'menunggu-terlalu-lama',
            'judul' => 'Tinjau stok filter dan seal', 'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $props = $this->props();

        $this->assertContains('menunggu-terlalu-lama', (array) $props['kodeDitangani']);
        $this->assertCount(1, $props['tindak']);
    }

    public function test_tindak_lanjut_memakai_tabel_bersama(): void
    {
        $this->actingAs($this->montir)->post(route('maintenance.tindak.simpan'), [
            'judul' => 'Ganti pola pelumasan', 'prioritas' => 'sedang',
        ]);

        $this->assertSame('maintenance', TindakLanjut::firstOrFail()->modul);
    }

    /* ---------- laporan cetak ---------- */

    public function test_laporan_membawa_kop_dokumen_terkendali(): void
    {
        $this->actingAs($this->montir)->get(route('maintenance.cetak'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Maintenance')
                ->where('dok.jenis', 'LAPORAN')
                ->where('dok.judul', 'LAPORAN KEANDALAN DAN PEMELIHARAAN ARMADA')
                ->has('dok.nomor'));
    }

    public function test_laporan_menyebutkan_berapa_yang_belum_diverifikasi(): void
    {
        $this->wo();                                   // belum diverifikasi
        $wo2 = $this->wo(['gejala' => 'Ban pecah']);
        $wo2->verifikasi($this->ktt);

        $props = $this->actingAs($this->montir)->get(route('maintenance.cetak'))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(2, $props['dasar']['order']);
        $this->assertSame(1, $props['dasar']['diverifikasi']);
        $this->assertSame(1, $props['dasar']['belumDiverifikasi']);
    }

    public function test_laporan_tetap_memuat_yang_belum_diverifikasi(): void
    {
        $this->wo();

        $props = $this->actingAs($this->montir)->get(route('maintenance.cetak'))
            ->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['orders'],
            'Laporan yang menyanjung dirinya sendiri lebih berbahaya daripada yang mengaku belum lengkap.');
        $this->assertGreaterThan(0, $props['keandalan']['jamHenti']);
    }

    public function test_laporan_menuntut_login(): void
    {
        $this->get(route('maintenance.cetak'))->assertRedirect();
    }
}
