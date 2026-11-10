<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding, User};
use App\Support\Perusahaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Hak akses peran dan batas jangkauan perusahaan pada modul audit SMKP
 * (porting AksesTest Aurorasys; uji halaman masuk, pendaftaran, dan
 * master data Aurorasys tidak ikut — itu milik platform EQOHSEE).
 */
class AksesSmkpTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function audit($c, int $tahun = 2026): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => $tahun, 'status' => 'berjalan', 'tahap' => 1, 'hasil' => [],
        ]);
    }

    public function test_jangkauan_induk_mencakup_pjp_tetapi_tidak_sister(): void
    {
        $j = $this->auditor($this->induk)->jangkauan();
        $this->assertContains($this->induk->id, $j);
        $this->assertContains($this->pjp->id, $j);
        $this->assertNotContains($this->sister->id, $j);

        $this->assertSame([$this->pjp->id], $this->auditor($this->pjp)->jangkauan());
        $this->assertNull($this->admin()->jangkauan());
    }

    public function test_audit_perusahaan_lain_tidak_terlihat(): void
    {
        $milikSister = $this->audit($this->sister);
        $milikPjp    = $this->audit($this->pjp);

        $u = $this->auditor($this->induk);
        $this->actingAs($u)->get("/smkp/{$milikSister->id}")->assertNotFound();
        $this->actingAs($u)->get("/smkp/{$milikPjp->id}")->assertOk();

        $this->actingAs($u)->get('/smkp')->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Index')->where('audits.total', 1));

        // PJP tidak melihat audit induknya, tidak pula audit sesama.
        $this->actingAs($this->auditor($this->pjp))->get('/smkp')->assertInertia(fn (Inertia $p) => $p->where('audits.total', 1));
    }

    public function test_pengguna_belum_ditempatkan_tidak_melihat_data_apa_pun(): void
    {
        $this->audit($this->induk);
        $u = $this->auditi(null);

        $this->actingAs($u)->get('/smkp')->assertInertia(fn (Inertia $p) => $p->where('audits.total', 0));
    }

    /** Audit tanpa perusahaan (sisa data lama) hanya terlihat administrator. */
    public function test_audit_tanpa_perusahaan_hanya_terlihat_administrator(): void
    {
        $yatim = SmkpAudit::withoutGlobalScopes()->create(['company_id' => null, 'tahun' => 2025, 'status' => 'draft', 'tahap' => 1, 'hasil' => []]);

        $this->actingAs($this->auditor($this->induk))->get("/smkp/{$yatim->id}")->assertNotFound();
        $this->actingAs($this->admin())->get("/smkp/{$yatim->id}")->assertOk();
    }

    /** Administrator yang sedang melihat satu perusahaan dibatasi seperti pengguna perusahaan itu. */
    public function test_administrator_yang_melihat_satu_perusahaan_dibatasi_jangkauannya(): void
    {
        $a = $this->audit($this->induk);
        $s = $this->audit($this->sister);
        SmkpFinding::withoutGlobalScopes()->create(['audit_id' => $s->id, 'nomor' => 'SU-MNR-01-2026', 'kode_kriteria' => 'I.1', 'jenis' => 'minor', 'uraian' => 'x', 'status' => 'Open']);
        $adm = $this->admin();

        $this->actingAs($adm)->withSession([Perusahaan::KUNCI => $this->induk->id]);
        $this->assertSame([$this->induk->id, $this->pjp->id], $adm->fresh()->jangkauan());
        $this->get("/smkp/{$s->id}")->assertNotFound();
        $this->get("/smkp/{$a->id}")->assertOk();
        $this->assertSame(0, SmkpFinding::count(), 'Temuan audit di luar perusahaan yang dilihat ikut tersaring.');
    }

    public function test_auditi_hanya_melihat_dan_menindaklanjuti_temuan(): void
    {
        $a = $this->audit($this->induk);
        $u = $this->auditi($this->induk);

        $this->actingAs($u)->get("/smkp/{$a->id}")->assertOk();
        $this->actingAs($u)->get('/smkp/buat')->assertForbidden();
        $this->actingAs($u)->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 4]]])->assertForbidden();
    }

    /** Peran audit EQOHSEE: 'auditor' mengisi audit; 'company' dan kosong adalah auditi. */
    public function test_peran_audit_dibaca_dari_kolom_audit_role(): void
    {
        $this->assertTrue(User::factory()->create(['audit_role' => 'auditor'])->bisaAudit());
        $this->assertTrue(User::factory()->create(['audit_role' => 'company'])->isAuditi());
        $this->assertTrue(User::factory()->create(['audit_role' => null])->isAuditi());
        $this->assertTrue(User::factory()->create(['is_admin' => true, 'audit_role' => null])->bisaAudit());
        $this->assertSame('Auditi', User::factory()->create(['audit_role' => 'company'])->labelPeran());
    }

    public function test_auditor_tidak_dapat_membuat_audit_untuk_perusahaan_di_luar_jangkauan(): void
    {
        $this->actingAs($this->auditor($this->induk))->post('/smkp', [
            'company_id' => $this->sister->id, 'tahun' => 2026, 'status' => 'draft',
        ])->assertForbidden();
        $this->assertSame(0, SmkpAudit::withoutGlobalScopes()->count());
    }

    public function test_hapus_audit_hanya_untuk_admin(): void
    {
        $a = $this->audit($this->induk);
        $u = $this->auditor($this->induk);

        $this->actingAs($u)->delete("/smkp/{$a->id}")->assertForbidden();
        $this->actingAs($this->admin())->delete("/smkp/{$a->id}")->assertRedirect('/smkp');
        $this->assertSame(0, SmkpAudit::withoutGlobalScopes()->count());
    }
}
