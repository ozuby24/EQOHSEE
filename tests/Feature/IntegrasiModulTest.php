<?php

namespace Tests\Feature;

use App\Models\{Document, Procedure, SmkpAudit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsolidasi lintas modul: dokumen terkendali menaut ke prosedur LMS,
 * dan kriteria audit dibuktikan oleh dokumen.
 *
 * Temuan SMKP sengaja TIDAK menaut ke Hazard Report — ketidaksesuaian
 * sistem dan bahaya fisik lapangan adalah dua konteks berbeda.
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
