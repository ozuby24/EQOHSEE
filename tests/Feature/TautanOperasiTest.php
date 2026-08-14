<?php

namespace Tests\Feature;

use App\Models\{Company, MineMapLayer, MineOperationalRecord, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tautan yang dibangun halaman harus benar-benar mengenai rutenya.
 *
 * Tombol hapus pada modul ini tidak pernah bekerja sejak awal: controller
 * mengirim route(..., ['record' => 0]) dan halaman menyambung "/{id}" di
 * belakangnya, sehingga yang dipanggil adalah /records/0/5 — id-nya
 * menempel pada nol alih-alih menggantikannya. Seluruh tes yang ada lolos
 * karena semuanya menembak rute backend langsung dan tidak satu pun
 * memakai tautan yang benar-benar dikirim ke browser.
 *
 * Tes di sini menutup celah itu: ia mengambil tautan dari muatan halaman,
 * menyisipkan id dengan cara yang sama seperti halaman, lalu memanggilnya
 * sungguhan.
 */
class TautanOperasiTest extends TestCase
{
    use RefreshDatabase;

    private function props(User $u): array
    {
        return $this->actingAs($u)->get(route('operasi.index'))
            ->assertOk()->viewData('page')['props'];
    }

    /** Sama persis dengan helper `untuk()` di Operasi/Halaman.vue. */
    private function untuk(string $pola, int|string $id): string
    {
        return str_replace('__ID__', (string) $id, $pola);
    }

    public function test_setiap_tautan_ber_id_memakai_penanda_bukan_angka(): void
    {
        $tautan = $this->props(User::factory()->create())['tautan'];

        foreach (['recordHapus', 'recordAjukan', 'recordSetujui', 'recordTolak', 'layerHapus'] as $kunci) {
            $this->assertArrayHasKey($kunci, $tautan);
            $this->assertStringContainsString('__ID__', $tautan[$kunci],
                "Tautan '{$kunci}' harus memakai penanda __ID__ agar id menggantikannya, bukan menempel di belakangnya.");
        }
    }

    public function test_tautan_hapus_record_benar_benar_menghapus(): void
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => $company->id]);

        $rec = MineOperationalRecord::create([
            'company_id' => $company->id, 'tanggal' => now()->toDateString(), 'shift' => 'siang',
            'material' => 'Batubara', 'produksi_ton' => 100, 'overburden_bcm' => 200,
            'jarak_angkut_km' => 2, 'jam_operasi' => 8, 'jam_delay' => 1,
        ]);

        $url = $this->untuk($this->props($admin)['tautan']['recordHapus'], $rec->id);

        $this->actingAs($admin)->delete($url)->assertRedirect();
        $this->assertDatabaseMissing('mine_operational_records', ['id' => $rec->id]);
    }

    public function test_tautan_alur_benar_benar_memindahkan_status(): void
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $operator = User::factory()->create(['company_id' => $company->id]);
        $ktt = User::factory()->create(['company_id' => $company->id, 'lms_role' => 'ktt']);

        $rec = MineOperationalRecord::create([
            'company_id' => $company->id, 'tanggal' => now()->toDateString(), 'shift' => 'siang',
            'material' => 'Batubara', 'produksi_ton' => 100, 'overburden_bcm' => 200,
            'jarak_angkut_km' => 2, 'jam_operasi' => 8, 'jam_delay' => 1,
        ]);

        $tautan = $this->props($operator)['tautan'];

        $this->actingAs($operator)
            ->post($this->untuk($tautan['recordAjukan'], $rec->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $rec->fresh()->status);

        $this->actingAs($ktt)
            ->post($this->untuk($tautan['recordSetujui'], $rec->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('disetujui', $rec->fresh()->status);
    }

    public function test_tautan_hapus_layer_benar_benar_menghapus(): void
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => $company->id]);

        $layer = MineMapLayer::create([
            'company_id' => $company->id, 'nama' => 'Pit 1', 'tipe' => 'pit',
            'geojson' => '{"type":"FeatureCollection","features":[]}',
            'warna' => '#84cc16', 'status' => 'aktif',
        ]);

        $url = $this->untuk($this->props($admin)['tautan']['layerHapus'], $layer->id);

        $this->actingAs($admin)->delete($url)->assertRedirect();
        $this->assertDatabaseMissing('mine_map_layers', ['id' => $layer->id]);
    }
}
