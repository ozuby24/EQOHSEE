<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Support\SmkpSusunan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Susunan kegiatan terstruktur Rencana Audit (port DBEST #16/#17):
 * kode kriteria baku, elemen bernomor di lembar cetak, unggah Excel/CSV.
 */
class SmkpSusunanTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private function audit(): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);
    }

    public function test_kode_kriteria_dari_teks_bebas_dan_daftar_elemen(): void
    {
        $this->assertSame(['I.1', 'I.5', 'III.2.1', 'IV'], SmkpSusunan::kode('1.5; III.2.1, i.1 dan IV'));
        $this->assertSame([], SmkpSusunan::kode('Rapat pembukaan 1'));
        $this->assertSame('3.2.1', SmkpSusunan::angka('III.2.1'));

        $el = SmkpSusunan::elemen('I.1, I.5, III.2.1');
        $this->assertSame('I', $el[0]['kode']);
        $this->assertStringStartsWith('Audit Elemen I ', $el[0]['judul']);
        $this->assertSame(['Sub Elemen 1.1, 1.5'], $el[0]['baris']);
        $this->assertSame(['Sub Sub Elemen 3.2.1'], $el[1]['baris']);
        $this->assertSame(['Seluruh sub elemen'], SmkpSusunan::elemen('IV')[0]['baris']);
    }

    public function test_susunan_terstruktur_tersimpan_dan_tercetak(): void
    {
        $this->siapkanGrup();
        $a = $this->audit();

        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/rencana", [
            'susunan' => [
                ['tanggal' => '2026-10-20', 'waktu' => '08.00–10.00', 'metode' => 'Peninjauan Dokumen, Rekaman, Wawancara', 'area' => 'Kantor',
                 'auditi' => 'Kepala Teknik Tambang', 'kriteria' => '1.5, I.1; 3.2.1', 'kegiatan' => '', 'auditor' => 'Rina'],
                ['tanggal' => '', 'waktu' => '', 'metode' => '', 'area' => '', 'auditi' => '', 'kriteria' => '', 'kegiatan' => '', 'auditor' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $s = $a->fresh()->rencana['susunan'];
        $this->assertCount(1, $s, 'Baris kosong dibuang.');
        $this->assertSame('I.1, I.5, III.2.1', $s[0]['kriteria']);

        $this->get("/smkp/{$a->id}/rencana/cetak")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->where('rencana.susunan.0.isi.metode', 'Peninjauan Dokumen, Rekaman, Wawancara :')
            ->where('rencana.susunan.0.isi.fungsi', 'Kepala Teknik Tambang')
            ->where('rencana.susunan.0.isi.elemen.0.baris', ['Sub Elemen 1.1, 1.5'])
            ->where('rencana.susunan.0.isi.elemen.1.kode', 'III'));
    }

    public function test_unggah_susunan_csv_dan_templat_excel(): void
    {
        $this->siapkanGrup();
        $a = $this->audit();
        $u = $this->auditor($this->induk);

        $csv = "Tanggal;Waktu;Metode;Area;Fungsi / Jabatan / Departemen;Kriteria;Kegiatan lain;Auditor\n"
             ."20/10/2026;08.00;Wawancara;PIT;KTT;II.1, 2.3;;Rina\n"
             .";;;;;;Rapat penutupan;Tim\n";
        $f = UploadedFile::fake()->createWithContent('susunan.csv', $csv);

        $this->actingAs($u)->post("/smkp/{$a->id}/rencana/susunan/impor", ['berkas' => $f], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('baris.0.tanggal', '2026-10-20')
            ->assertJsonPath('baris.0.kriteria', 'II.1, II.3')
            ->assertJsonPath('baris.1.kegiatan', 'Rapat penutupan');

        $r = $this->get("/smkp/{$a->id}/rencana/susunan/templat");
        $r->assertOk();
        $this->assertStringStartsWith('PK', $r->streamedContent());

        // Auditi tidak dapat mengunggah.
        $this->actingAs($this->auditi($this->induk))->post("/smkp/{$a->id}/rencana/susunan/impor", ['berkas' => $f], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
