<?php

namespace Tests\Feature;

use App\Models\{MinerbaConservationAction, MinerbaConservationRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KonservasiMinerbaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(array $atribut = []): User
    {
        $user = User::factory()->create($atribut);
        $this->actingAs($user);

        return $user;
    }

    public function test_dashboard_konservasi_mengirim_ringkasan_inertia(): void
    {
        $this->masuk(['is_admin' => true]);

        // Angka hanya terhitung setelah ditinjau; membuat baris lalu
        // langsung mengharapkannya di KPI adalah anggapan lama.
        $rec = MinerbaConservationRecord::create([
            'periode' => '2026-01-31', 'lokasi' => 'Pit A', 'komoditas' => 'Batubara',
            'target_produksi' => 1000, 'produksi_aktual' => 900, 'material_digali' => 1200,
            'recovery_percent' => 75, 'kehilangan_material' => 50, 'dilusi' => 30,
            'stok_akhir' => 200,
        ]);
        $rec->ajukan();
        $rec->setujui(User::factory()->create(['lms_role' => 'ktt']));

        $this->get(route('konservasi.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Konservasi/Halaman')
                ->where('mode', 'dashboard')
                ->where('ringkas.produksi_aktual', 900)
                ->where('ringkas.capaian_target', 90)
                ->where('ringkas.recovery', 75)
                ->has('perKomoditas', 1));
    }

    public function test_record_konservasi_dapat_disimpan_dengan_validasi_angka(): void
    {
        $this->masuk();

        $this->post(route('konservasi.record.simpan'), [
            'periode' => '2026-02-28', 'lokasi' => 'Pit Selatan', 'komoditas' => 'Nikel',
            'satuan' => 'ton', 'target_produksi' => 500, 'produksi_aktual' => 450,
            'material_digali' => 600, 'recovery_percent' => 75, 'kehilangan_material' => 20,
            'dilusi' => 10, 'stok_akhir' => 100, 'mineral_ikutan' => 'Kobalt',
            'status' => 'draft',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('konservasi_minerba_records', [
            'lokasi' => 'Pit Selatan', 'komoditas' => 'Nikel', 'company_id' => null,
        ]);
    }

    public function test_recovery_di_luar_rentang_ditolak(): void
    {
        $this->masuk();

        $this->post(route('konservasi.record.simpan'), [
            'periode' => '2026-02-28', 'lokasi' => 'Pit', 'komoditas' => 'Batubara',
            'satuan' => 'ton', 'target_produksi' => 1, 'produksi_aktual' => 1,
            'material_digali' => 1, 'recovery_percent' => 120, 'kehilangan_material' => 0,
            'dilusi' => 0, 'stok_akhir' => 0, 'status' => 'draft',
        ])->assertSessionHasErrors('recovery_percent');

        $this->assertSame(0, MinerbaConservationRecord::count());
    }

    public function test_tindak_lanjut_dapat_dibuat_dan_statusnya_diubah(): void
    {
        $this->masuk();

        $this->post(route('konservasi.action.simpan'), [
            'judul' => 'Optimasi recovery washing plant', 'kategori' => 'recovery',
            'prioritas' => 'tinggi', 'status' => 'rencana', 'target_selesai' => '2026-05-31',
        ])->assertSessionHasNoErrors();

        $action = MinerbaConservationAction::firstOrFail();
        $this->put(route('konservasi.action.ubah', $action), ['status' => 'berjalan'])
            ->assertSessionHasNoErrors();

        $this->assertSame('berjalan', $action->fresh()->status);
    }
}
