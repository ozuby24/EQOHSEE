<?php

namespace Tests\Feature;

use App\Models\Miners\SimperUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Detail karyawan (`detail_manpower` Safe Track): bagian per tahap,
 * unit SIMPER termasuk penambahan yang disetujui, tautan cetak dan
 * verifikasi, dan batas akses pengguna biasa.
 */
class MinersRincianPekerjaTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    #[Test]
    public function rincian_memuat_seluruh_tahap_dan_unit_penambahan(): void
    {
        $s = $this->simperTerbit();
        SimperUnit::withoutGlobalScopes()->create(['simper_id' => $s->id, 'kendaraan_id' => $s->unit()->first()->kendaraan_id,
            'authority' => 'R', 'asal' => 'penambahan']);

        $this->actingAs($this->ohse)->get('/miners/'.$s->pekerja_id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Miners/Rincian')
                ->has('mcu', 1)->has('induksi', 1)->has('permit', 1)->has('simper', 1)
                ->has('simper.0.unit', 2)
                ->where('simper.0.unit.1.asal', SimperUnit::ASAL['penambahan'])
                ->where('permit.0.cetak', route('miners.kartu.cetak', $s->permit_id))
                ->whereNot('permit.0.verifikasi', null)
                ->whereNot('simper.0.verifikasi', null));
    }

    #[Test]
    public function pengguna_biasa_hanya_membuka_data_miliknya(): void
    {
        $s = $this->simperTerbit();   // diajukan oleh $this->pengaju

        $this->actingAs($this->pengaju)->get('/miners/'.$s->pekerja_id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('permit.0.cetak', null));

        $lain = User::factory()->create(['company_id' => $this->mitra->id]);
        $this->actingAs($lain)->get('/miners/'.$s->pekerja_id)->assertForbidden();
    }
}
