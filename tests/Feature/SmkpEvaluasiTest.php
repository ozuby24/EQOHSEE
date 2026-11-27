<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/** Halaman Evaluasi Audit: chart interaktif dari data audit yang sudah diinput. */
class SmkpEvaluasiTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    /** Seluruh butir penuh, kecuali butir elemen V yang separuh dan satu butir II nol. */
    private function hasil(float $faktorV = 0.5): array
    {
        $h = [];
        foreach (Smkp::butir() as $b) {
            $v = (float) $b['maks'];
            if ($b['elemen'] === 'V') $v = floor($b['maks'] * $faktorV);
            if ($b['kode'] === 'II.2.1') $v = 0;
            $h[$b['kode']] = ['v' => $v];
        }

        return $h;
    }

    public function test_evaluasi_membandingkan_dengan_audit_tahun_lalu(): void
    {
        $lalu = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2025, 'status' => 'selesai', 'tahap' => 3, 'hasil' => $this->hasil(0.25)]);
        $a = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2, 'hasil' => $this->hasil(0.5),
            'kinerja' => ['fr' => '0,85', 'sr' => '140', 'asr' => '120', 'mfr' => '300'],
        ]);
        $a->findings()->create(['kode_kriteria' => 'II.2.1', 'jenis' => 'mayor', 'uraian' => 'Komunikasi risiko belum berjalan', 'status' => 'Open', 'target_selesai' => '2020-01-01']);
        $a->findings()->create(['kode_kriteria' => 'V.1', 'jenis' => 'minor', 'uraian' => 'Rekaman belum lengkap', 'status' => 'Closed', 'tanggal_selesai' => now()->toDateString()]);

        $this->actingAs($this->auditor($this->induk))->get("/smkp/{$a->id}/evaluasi")->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->component('Smkp/Evaluasi')
                ->where('evaluasi.ringkas.lalu.tahun', 2025)
                ->where('evaluasi.ringkas.lalu.id', $lalu->id)
                ->has('evaluasi.elemen', 7)
                ->has('evaluasi.peta', 7)
                ->where('evaluasi.elemen.4.k', 'V')
                ->where('evaluasi.elemen.4.c', fn ($c) => $c > 0 && $c < 100)
                ->where('evaluasi.elemen.4.lalu', fn ($c) => $c !== null && $c < 50)
                ->where('evaluasi.temuan.total', 2)
                ->where('evaluasi.temuan.tutup', 1)
                ->where('evaluasi.temuan.telat', 1)
                ->where('evaluasi.kinerja.nilai.0.k', 'fr')
                ->where('evaluasi.kinerja.nilai.0.atas', true)
                ->where('evaluasi.kinerja.nilai.2.atas', false)
                ->where('evaluasi.kinerja.saran.kecelakaan', true)
                ->where('evaluasi.catatan', fn ($c) => count($c) >= 4)
                ->etc());

        $ev = $this->get("/smkp/{$a->id}/evaluasi")->viewData('page')['props']['evaluasi'];
        $hilang = array_column($ev['celah'], 'hilang');
        $this->assertNotEmpty($hilang);
        $this->assertSame($hilang, collect($hilang)->sortDesc()->values()->all(), 'Celah urut poin hilang terbanyak.');
        $this->assertSame(array_sum(array_map(fn ($e) => count($e['sub']), Smkp::elemen())), array_sum($ev['kategoriSub']),
            'Setiap sub elemen masuk tepat satu kategori.');
        $this->assertContains('II.2', array_column($ev['celah'], 'k'));
    }

    public function test_evaluasi_tanpa_audit_lalu_dan_batas_perusahaan(): void
    {
        $a = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);

        $this->actingAs($this->auditi($this->induk))->get("/smkp/{$a->id}/evaluasi")->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->where('evaluasi.ringkas.lalu', null)->where('evaluasi.ringkas.skor', null)->etc());

        $lain = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->sister->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);
        $this->actingAs($this->auditor($this->induk))->get("/smkp/{$lain->id}/evaluasi")->assertNotFound();
    }
}
