<?php

namespace Tests\Feature;

use App\Models\{AksesAudit, Company, EnvAudit, SmkpAudit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Aturan Akses Audit per perusahaan (port DBEST #16): auditor & pengunggah
 * yang ditugaskan, aturan independensi, dan aturan bawaan bila kosong.
 */
class AturanAksesAuditTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private function audit(Company $c): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create(['company_id' => $c->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);
    }

    public function test_tanpa_penugasan_aturan_bawaan_berlaku(): void
    {
        $this->siapkanGrup();
        $a = $this->audit($this->pjp);
        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/rencana", ['tujuan' => 'x'])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_auditor_ditugaskan_membatasi_auditor_lain_dan_menjangkau_luar_grup(): void
    {
        $this->siapkanGrup();
        $a = $this->audit($this->pjp);
        $adm = $this->admin();
        $ditugaskan = $this->auditor($this->induk);
        $lainnya = $this->auditor($this->induk);

        // Auditor dari perusahaan di luar grup.
        $luar = Company::create(['name' => 'PT Konsultan Audit', 'code' => 'KA', 'kategori' => 'induk']);
        $tamu = User::factory()->auditor($luar->id)->create();

        $this->actingAs($adm)->post('/admin/akses-audit', ['company_id' => $this->pjp->id, 'user_id' => $ditugaskan->id, 'peran' => 'auditor'])->assertSessionHasNoErrors();
        $this->post('/admin/akses-audit', ['company_id' => $this->pjp->id, 'user_id' => $tamu->id, 'peran' => 'auditor'])->assertSessionHasNoErrors();

        $this->actingAs($ditugaskan)->post("/smkp/{$a->id}/rencana", ['tujuan' => 'boleh'])->assertRedirect();
        $this->actingAs($lainnya)->post("/smkp/{$a->id}/rencana", ['tujuan' => 'tidak'])->assertForbidden();
        $this->assertSame('boleh', $a->fresh()->rencana['tujuan']);

        // Auditor luar grup kini menjangkau audit itu — dan hanya itu.
        $this->actingAs($tamu)->get("/smkp/{$a->id}")->assertOk();
        $this->actingAs($tamu)->post("/smkp/{$a->id}/rencana", ['tujuan' => 'tamu'])->assertRedirect();
        $indukAudit = $this->audit($this->induk);
        $this->actingAs($tamu)->get("/smkp/{$indukAudit->id}")->assertNotFound();

        // Administrator selalu boleh.
        $this->actingAs($adm)->post("/smkp/{$a->id}/rencana", ['tujuan' => 'admin'])->assertRedirect();
    }

    public function test_pengunggah_ditugaskan_dan_aturan_independensi(): void
    {
        $this->siapkanGrup();
        $adm = $this->admin();
        $auditi1 = $this->auditi($this->pjp);
        $auditi2 = $this->auditi($this->pjp);
        $a = $this->audit($this->pjp);
        $akl = EnvAudit::withoutGlobalScopes()->create(['company_id' => $this->pjp->id, 'kode' => 'AKL-X', 'tahun' => 2026, 'judul' => 'AKL']);

        $this->actingAs($adm)->post('/admin/akses-audit', ['company_id' => $this->pjp->id, 'user_id' => $auditi1->id, 'peran' => 'pengunggah'])->assertSessionHasNoErrors();

        // Independensi: akun yang sama tidak boleh menjadi auditor perusahaan itu juga.
        $aud = $this->auditor($this->induk);
        $this->post('/admin/akses-audit', ['company_id' => $this->pjp->id, 'user_id' => $aud->id, 'peran' => 'pengunggah'])->assertSessionHasNoErrors();
        $this->post('/admin/akses-audit', ['company_id' => $this->pjp->id, 'user_id' => $aud->id, 'peran' => 'auditor'])->assertSessionHasErrors('user_id');
        // Akun bukan auditor tidak dapat ditugaskan sebagai auditor.
        $this->post('/admin/akses-audit', ['company_id' => $this->induk->id, 'user_id' => $auditi2->id, 'peran' => 'auditor'])->assertSessionHasErrors('user_id');

        // Pengunggah yang tidak ditugaskan ditolak mengunggah bukti.
        $this->actingAs($auditi2)->post("/smkp/{$a->id}/bukti", [])->assertForbidden();
        $this->actingAs($auditi1)->post("/smkp/{$a->id}/bukti", [])->assertSessionHasErrors();

        $this->actingAs($adm)->get('/admin/akses-audit')->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Admin/AksesAudit')
            ->where('baris', fn ($b) => collect($b)->firstWhere('id', $this->pjp->id)['pengunggah'][0]['nama'] === $auditi1->name));

        // Cabut → kembali ke aturan bawaan.
        $this->delete('/admin/akses-audit/'.AksesAudit::where('user_id', $auditi1->id)->value('id'))->assertRedirect();
        $this->delete('/admin/akses-audit/'.AksesAudit::where('user_id', $aud->id)->value('id'))->assertRedirect();
        $this->actingAs($auditi2)->post("/smkp/{$a->id}/bukti", [])->assertSessionHasErrors();
        $this->assertSame(0, AksesAudit::count());
        $this->assertNotNull($akl->id);
    }

    public function test_halaman_hanya_administrator(): void
    {
        $this->siapkanGrup();
        $this->actingAs($this->auditor($this->induk))->get('/admin/akses-audit')->assertForbidden();
    }
}
