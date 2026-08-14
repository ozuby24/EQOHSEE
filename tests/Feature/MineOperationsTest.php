<?php

namespace Tests\Feature;

use App\Models\{Company, MineMapLayer, MineOperationalRecord, MineOperationalTarget, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MineOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_tower_menghitung_kinerja_dan_alert(): void
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        MineOperationalTarget::create([
            'company_id' => $company->id, 'tahun' => 2026, 'bulan' => 2,
            'target_produksi_ton' => 1000, 'target_overburden_bcm' => 2500,
            'target_strip_ratio' => 2, 'target_jarak_km' => 5,
        ]);
        // Angka hanya terhitung setelah melewati tinjauan. Membuat baris
        // lalu langsung mengharapkannya muncul di KPI adalah anggapan lama,
        // ketika status masih dapat disebut sendiri oleh pengirim data.
        $rec = MineOperationalRecord::create([
            'company_id' => $company->id, 'tanggal' => '2026-02-05', 'shift' => 'siang',
            'pit' => 'Pit A', 'material' => 'Batubara', 'produksi_ton' => 800,
            'overburden_bcm' => 2200, 'jarak_angkut_km' => 6, 'jam_operasi' => 7,
            'jam_delay' => 2,
        ]);
        $rec->ajukan();
        $rec->setujui(User::factory()->create(['lms_role' => 'ktt']));

        $props = $this->get(route('operasi.index', ['dari' => '2026-02-01', 'sampai' => '2026-02-28']))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(800.0, $props['ringkas']['produksi']);
        $this->assertSame(1, $props['ringkas']['jumlah_record']);
        $this->assertSame(80.0, $props['ringkas']['capaian_produksi']);
        $this->assertNotEmpty($props['alerts']);
        $this->assertSame('Pit A', $props['perPit'][0]['nama']);
    }

    public function test_input_operasi_menolak_total_jam_di_atas_satu_hari(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('operasi.record.simpan'), [
            'tanggal' => '2026-02-05', 'shift' => 'siang', 'material' => 'Batubara',
            'produksi_ton' => 100, 'overburden_bcm' => 200, 'jarak_angkut_km' => 3,
            'jumlah_truk' => 2, 'jumlah_excavator' => 1, 'jam_operasi' => 20, 'jam_delay' => 5,
            'status' => 'draft',
        ])->assertSessionHasErrors('jam_delay');

        $this->assertDatabaseCount('mine_operational_records', 0);
    }

    public function test_target_dan_geojson_menyimpan_data_dengan_pemilik_pengguna(): void
    {
        $company = Company::create(['name' => 'Perusahaan Terbatas']);
        $other = Company::create(['name' => 'Perusahaan Lain']);
        $this->actingAs(User::factory()->create(['company_id' => $company->id]));

        $this->post(route('operasi.target.simpan'), [
            'company_id' => $other->id, 'tahun' => 2026, 'bulan' => 3,
            'target_produksi_ton' => 1500, 'target_overburden_bcm' => 4000,
        ])->assertSessionHasNoErrors();

        $this->post(route('operasi.layer.simpan'), [
            'company_id' => $other->id, 'nama' => 'Pit A', 'tipe' => 'pit',
            'geojson' => '{"type":"FeatureCollection","features":[]}',
            'warna' => '#84cc16', 'status' => 'aktif',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mine_operational_targets', ['company_id' => $company->id, 'bulan' => 3]);
        $this->assertDatabaseHas('mine_map_layers', ['company_id' => $company->id, 'nama' => 'Pit A']);
        $this->assertSame(1, MineMapLayer::count());
    }

    public function test_tamu_tidak_dapat_membuka_operasi_tambang(): void
    {
        foreach (['operasi.index', 'operasi.data', 'operasi.target', 'operasi.gis'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
