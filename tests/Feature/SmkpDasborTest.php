<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, SmkpFinding};
use App\Support\{Smkp, SmkpDasbor, Waktu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Dasbor Audit SMKP v2 (mengikuti dasbor DBEST): KPI, pembanding
 * antartahun, analisis temuan, ekspor — dan batas perusahaannya.
 */
class SmkpDasborTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    /** Nilai penuh (4) untuk seluruh butir — nilai akhir 100%. */
    private function hasilPenuh(int $v = 4): array
    {
        $h = [];
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $ada = false;
                foreach ($s['subsub'] ?? [] as $x) { $h[$x['kode']] = ['v' => min($v, (int) ($x['maks'] ?? 4))]; $ada = true; }
                if (!$ada) $h[$s['kode']] = ['v' => min($v, (int) ($s['maks'] ?? 4))];
            }
        }

        return $h;
    }

    private function audit(Company $c, int $tahun, array $hasil = [], array $extra = []): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => $tahun, 'status' => 'berjalan', 'tahap' => 2, 'hasil' => $hasil,
        ] + $extra);
    }

    public function test_dasbor_terbuka_dan_menghitung_kpi_serta_pembanding(): void
    {
        $this->siapkanGrup();
        $tahun = (int) Waktu::kini()->year;

        $kini = $this->audit($this->induk, $tahun, $this->hasilPenuh());
        $this->audit($this->induk, $tahun - 1, $this->hasilPenuh(2));
        SmkpFinding::create(['audit_id' => $kini->id, 'nomor' => 'IU-MYR-01', 'kode_kriteria' => 'IV.1.1', 'jenis' => 'mayor', 'uraian' => 'x',
            'status' => 'Open', 'target_selesai' => Waktu::kini()->subDays(3)->toDateString()]);
        SmkpFinding::create(['audit_id' => $kini->id, 'nomor' => 'IU-MNR-01', 'kode_kriteria' => 'IV.1.2', 'jenis' => 'minor', 'uraian' => 'y',
            'status' => 'Closed', 'tanggal_selesai' => Waktu::kini()->toDateString()]);

        $this->actingAs($this->admin())->get('/smkp/dasbor')->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Dasbor')
            ->where('dasbor.tahun', $tahun)
            ->where('dasbor.banding', $tahun - 1)
            ->where('dasbor.kpi.audit', 1)
            ->where('dasbor.kpi.rata', 100)
            ->where('dasbor.kpi.temuan.total', 2)
            ->where('dasbor.kpi.temuan.buka', 1)
            ->where('dasbor.kpi.temuan.telat', 1)
            ->where('dasbor.kpi.temuan.persenTutup', 50)
            ->where('dasbor.perusahaan.0.nama', 'PT Induk Uji')
            ->where('dasbor.perusahaan.0.lalu', fn ($v) => $v !== null && $v < 100)
            ->where('dasbor.analisis.top.semua.0.k', 'IV.1')
            ->where('dasbor.analisis.status.semua', ['selesai' => 1, 'proses' => 0, 'telat' => 1])
            ->where('dasbor.tindakan.jumlahTelat', 1)
            ->has('dasbor.sorotan.0.ruas'));

        // Tanpa pembanding.
        $this->get("/smkp/dasbor?tahun={$tahun}&banding=tidak")->assertInertia(fn (Inertia $p) => $p
            ->where('dasbor.banding', null)->where('dasbor.perusahaan.0.lalu', null));
    }

    public function test_dasbor_hanya_menghitung_audit_dalam_jangkauan(): void
    {
        $this->siapkanGrup();
        $tahun = (int) Waktu::kini()->year;
        $lain = Company::create(['name' => 'PT Lain Grup', 'code' => 'LG', 'kategori' => 'induk']);

        $this->audit($this->induk, $tahun, $this->hasilPenuh());
        $this->audit($lain, $tahun, $this->hasilPenuh(1));

        // Auditor perusahaan induk tidak melihat audit perusahaan di luar grupnya.
        $this->actingAs($this->auditor($this->induk))->get('/smkp/dasbor')->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->where('dasbor.kpi.audit', 1)
            ->where('dasbor.perusahaan', fn ($pt) => collect($pt)->pluck('nama')->all() === ['PT Induk Uji']));

        $this->actingAs($this->admin())->get('/smkp/dasbor')->assertInertia(fn (Inertia $p) => $p->where('dasbor.kpi.audit', 2));
    }

    public function test_ekspor_dasbor_excel(): void
    {
        $this->siapkanGrup();
        $tahun = (int) Waktu::kini()->year;
        $this->audit($this->induk, $tahun, $this->hasilPenuh());

        $r = $this->actingAs($this->admin())->get("/smkp/dasbor/ekspor?tahun={$tahun}&banding=tidak");
        $r->assertOk();
        $this->assertStringContainsString('dasbor-smkp-'.$tahun.'.xlsx', (string) $r->headers->get('content-disposition'));
        $this->assertStringStartsWith('PK', $r->streamedContent());
    }

    public function test_susun_tanpa_audit_tidak_galat(): void
    {
        $d = SmkpDasbor::susun(2026, null, [], [], [], [], [2026 => null], '2026-11-16');

        $this->assertSame(0, $d['kpi']['audit']);
        $this->assertNull($d['kpi']['rata']);
        $this->assertCount(7, $d['elemen']);
        $this->assertSame([], $d['sorotan']);
        $this->assertSame(['selesai' => 0, 'proses' => 0, 'telat' => 0], $d['analisis']['status']['semua']);
    }

    public function test_menu_smkp_memuat_dasbor(): void
    {
        $this->siapkanGrup();
        $this->actingAs($this->admin())->get('/smkp/dasbor')->assertInertia(fn (Inertia $p) => $p->where('kop', false));
    }
}
