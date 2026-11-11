<?php

namespace Tests\Feature;

use App\Models\Miners\Kendaraan;
use App\Models\Miners\PermitPerpanjangan;
use App\Models\Miners\Simper;
use App\Models\Miners\SimperAjuan;
use App\Models\Miners\SimperUnit;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Pengganti empat uji MinersAlurTest yang dibuang di bagian 3 (matriks
 * kelas SIM dan jendela perpanjangan 30 hari). Yang dikunci di sini adalah
 * aturan Safe Track yang BARU:
 *
 *   · kelas SIM yang tidak cocok dengan unit bukan penolakan — unitnya
 *     tetap tercatat, detail SIMPER menampilkan peringatan;
 *   · perpanjangan Mine Permit dan SIMPER boleh diajukan kapan saja, tidak
 *     hanya dalam 30 hari terakhir masa berlakunya.
 */
class MinersAturanBaruTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    #[Test]
    public function kelas_sim_tidak_cocok_menjadi_peringatan_bukan_penolakan(): void
    {
        $s = $this->simperTerbit();     // SIM B1 Umum
        $k = Kendaraan::withoutGlobalScopes()->create(['nama' => 'Bus Karyawan', 'kode' => 'BS', 'kelas_simpol' => 'B2', 'aktif' => true]);
        SimperUnit::withoutGlobalScopes()->where('simper_id', $s->id)->update(['kendaraan_id' => $k->id]);

        $this->actingAs($this->ohse)->get("/miners/simper/{$s->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('unit.0.peringatan',
                fn ($teks) => is_string($teks) && str_contains($teks, 'SIM B2')));

        /* Tetap terbit dan tetap berlaku — peringatan tidak mencabut apa pun. */
        $this->assertTrue(Simper::withoutGlobalScopes()->find($s->id)->berlaku());
    }

    #[Test]
    public function kelas_sim_cocok_tanpa_peringatan(): void
    {
        $s = $this->simperTerbit();
        $k = Kendaraan::withoutGlobalScopes()->create(['nama' => 'Mobil Ringan', 'kode' => 'MR', 'kelas_simpol' => 'B1', 'aktif' => true]);
        SimperUnit::withoutGlobalScopes()->where('simper_id', $s->id)->update(['kendaraan_id' => $k->id]);

        $this->actingAs($this->ohse)->get("/miners/simper/{$s->id}")
            ->assertInertia(fn (Assert $p) => $p->where('unit.0.peringatan', null));
    }

    #[Test]
    public function perpanjangan_simper_boleh_di_luar_jendela_30_hari(): void
    {
        $s = $this->simperTerbit();     // SIM masih berlaku 2 tahun
        $this->assertGreaterThan(30, $s->sisaHari());

        $this->actingAs($this->pengaju)->post('/miners/simper-lanjutan', [
            'jenis' => 'perpanjangan', 'simper_id' => $s->id, 'no_simpol_baru' => 'SIM-BARU-1',
            'simpol_berlaku_sampai' => Waktu::kini()->addYears(5)->toDateString(), 'tanggal_mcu' => Waktu::kini()->toDateString(),
            'berkas_simpol' => $this->dokumenPdf('s.pdf'), 'berkas_mcu' => $this->dokumenPdf('m.pdf'), 'berkas_form' => $this->dokumenPdf('f.pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, SimperAjuan::withoutGlobalScopes()->where('jenis', 'perpanjangan')->count());
    }

    #[Test]
    public function perpanjangan_mine_permit_boleh_di_luar_jendela_30_hari(): void
    {
        $p = $this->permitTerbit();     // MCU masih berlaku 11 bulan
        $this->assertGreaterThan(30, $p->sisaHari());

        $this->actingAs($this->pengaju)->post('/miners/perpanjangan-permit', [
            'permit_id' => $p->id, 'tanggal' => Waktu::kini()->toDateString(),
            'berlaku_baru' => Waktu::kini()->addYear()->toDateString(), 'tanggal_mcu' => Waktu::kini()->toDateString(),
            'berkas_form' => $this->dokumenPdf('f.pdf'), 'berkas_mcu' => $this->dokumenPdf('m.pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, PermitPerpanjangan::withoutGlobalScopes()->where('permit_id', $p->id)->count());
    }
}
