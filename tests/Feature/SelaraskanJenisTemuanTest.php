<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Perintah konsol smkp:selaraskan-temuan: koreksi massal CAR lama yang
 * jenisnya belum mengikuti aturan "rincian tidak pernah Mayor bila
 * sub-elemen Minor" (lihat Smkp::temuan()).
 */
class SelaraskanJenisTemuanTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function audit(array $hasil = []): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2,
            'ketua_auditor' => 'Ketua Uji', 'hasil' => $hasil,
        ]);
    }

    public function test_car_rincian_yang_beku_mayor_diselaraskan_jadi_minor(): void
    {
        // IV.1.1 penuh, IV.1.2 hampir penuh, IV.1.3 nol — agregat sub-elemen
        // IV.1 tetap Minor (≈55%), jadi IV.1.3 seharusnya Minor juga, bukan
        // Mayor. CAR dibuat langsung dengan jenis Mayor untuk meniru data
        // lama dari sebelum aturan ini berlaku.
        $a = $this->audit(['IV.1.1' => ['v' => 4], 'IV.1.2' => ['v' => 2], 'IV.1.3' => ['v' => 0]]);
        $f = $a->findings()->create(['kode_kriteria' => 'IV.1.3', 'jenis' => 'mayor', 'uraian' => 'Uji', 'status' => 'Open']);

        // CAR yang sudah Closed tidak boleh ikut diubah — statusnya sudah final.
        $tutup = $a->findings()->create(['kode_kriteria' => 'IV.1.2', 'jenis' => 'mayor', 'uraian' => 'Uji', 'status' => 'Closed']);

        Artisan::call('smkp:selaraskan-temuan', ['--dry-run' => true]);
        $this->assertSame('mayor', $f->fresh()->jenis, 'Dry-run tidak boleh mengubah data.');

        Artisan::call('smkp:selaraskan-temuan');

        $this->assertSame('minor', $f->fresh()->jenis);
        $this->assertSame('mayor', $tutup->fresh()->jenis, 'CAR yang sudah Closed tidak ikut diselaraskan.');
    }
}
