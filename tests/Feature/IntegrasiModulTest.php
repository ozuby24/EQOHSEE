<?php

namespace Tests\Feature;

use App\Models\{Company, Document, HazardReport, Procedure, SmkpAudit, SmkpFinding, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsolidasi lintas modul: pekerjaan tidak boleh berhenti di batas modul.
 */
class IntegrasiModulTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function audit(array $ganti = []): SmkpAudit
    {
        return SmkpAudit::create(array_merge([
            'tahun' => 2026, 'status' => 'berjalan', 'hasil' => [],
        ], $ganti));
    }

    public function test_temuan_smkp_dapat_dinaikkan_menjadi_hazard_report(): void
    {
        $pt    = Company::create(['name' => 'PT Uji Tambang']);
        $audit = $this->audit(['company_id' => $pt->id]);

        $temuan = $audit->findings()->create([
            'kode_kriteria' => 'IV.2.1',
            'jenis'         => 'mayor',
            'uraian'        => 'SOP pekerjaan berisiko tinggi belum tersedia.',
            'status'        => 'Open',
        ]);

        $this->actingAs($this->admin())
            ->post(route('smkp.temuan.hazard', [$audit, $temuan]))
            ->assertRedirect();

        $laporan = HazardReport::first();
        $this->assertNotNull($laporan, 'Hazard Report harus terbentuk.');
        $this->assertSame($laporan->id, $temuan->refresh()->hazard_report_id);
        $this->assertSame($pt->id, $laporan->company_id, 'Perusahaan ikut terbawa.');
        $this->assertStringContainsString('IV.2.1', $laporan->deskripsi, 'Jejak kriteria asal ikut tercatat.');
    }

    public function test_temuan_mayor_menjadi_risiko_tinggi_minor_menjadi_sedang(): void
    {
        $audit = $this->audit();
        $admin = $this->admin();

        $mayor = $audit->findings()->create(['kode_kriteria'=>'I.1.1','jenis'=>'mayor','uraian'=>'A','status'=>'Open']);
        $minor = $audit->findings()->create(['kode_kriteria'=>'I.1.2','jenis'=>'minor','uraian'=>'B','status'=>'Open']);

        $this->actingAs($admin)->post(route('smkp.temuan.hazard', [$audit, $mayor]));
        $this->actingAs($admin)->post(route('smkp.temuan.hazard', [$audit, $minor]));

        $this->assertSame('Tinggi', $mayor->refresh()->hazardReport->risiko);
        $this->assertSame('Sedang', $minor->refresh()->hazardReport->risiko);
    }

    public function test_temuan_tidak_digandakan_bila_dinaikkan_dua_kali(): void
    {
        $audit  = $this->audit();
        $temuan = $audit->findings()->create(['kode_kriteria'=>'I.1.1','jenis'=>'mayor','uraian'=>'A','status'=>'Open']);
        $admin  = $this->admin();

        $this->actingAs($admin)->post(route('smkp.temuan.hazard', [$audit, $temuan]));
        $this->actingAs($admin)->post(route('smkp.temuan.hazard', [$audit, $temuan]));

        $this->assertSame(1, HazardReport::count(), 'Menaikkan dua kali tidak boleh menggandakan laporan.');
    }

    public function test_temuan_milik_audit_lain_ditolak(): void
    {
        $a = $this->audit(['tahun' => 2026]);
        $b = $this->audit(['tahun' => 2025]);
        $temuan = $b->findings()->create(['kode_kriteria'=>'I.1.1','jenis'=>'mayor','uraian'=>'A','status'=>'Open']);

        $this->actingAs($this->admin())
            ->post(route('smkp.temuan.hazard', [$a, $temuan]))
            ->assertNotFound();

        $this->assertSame(0, HazardReport::count());
    }

    public function test_dokumen_dapat_ditautkan_ke_prosedur_lms(): void
    {
        $p = Procedure::create(['code' => 'SOP-001', 'title' => 'Prosedur LOTO']);

        $d = Document::create([
            'kode' => 'DOK-001', 'judul' => 'Prosedur LOTO', 'jenis' => 'Prosedur',
            'status' => 'berlaku', 'revisi' => 0, 'procedure_id' => $p->id,
        ]);

        $this->assertSame('Prosedur LOTO', $d->procedure->title);
        $this->assertTrue($p->documents->contains($d), 'Relasi harus terbaca dua arah.');
    }

    public function test_menghapus_prosedur_tidak_ikut_menghapus_dokumen(): void
    {
        $p = Procedure::create(['code' => 'SOP-001', 'title' => 'Prosedur LOTO']);
        $d = Document::create([
            'kode' => 'DOK-001', 'judul' => 'Prosedur LOTO', 'jenis' => 'Prosedur',
            'status' => 'berlaku', 'revisi' => 0, 'procedure_id' => $p->id,
        ]);

        $p->delete();

        // Dokumen terkendali adalah rekaman audit — tidak boleh ikut terhapus.
        $this->assertNotNull($d->fresh(), 'Dokumen harus tetap ada.');
        $this->assertNull($d->fresh()->procedure_id, 'Tautannya saja yang dilepas.');
    }

    public function test_temuan_dapat_menunjuk_dokumen_sebagai_bukti(): void
    {
        $audit = $this->audit();
        $doc = Document::create([
            'kode'=>'DOK-002','judul'=>'Manual SMKP','jenis'=>'Manual',
            'status'=>'berlaku','revisi'=>0,
        ]);

        $t = $audit->findings()->create([
            'kode_kriteria'=>'VI.1.1','jenis'=>'minor','uraian'=>'X',
            'status'=>'Open','document_id'=>$doc->id,
        ]);

        $this->assertSame('Manual SMKP', $t->document->judul);
    }
}
