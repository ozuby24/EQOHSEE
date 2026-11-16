<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding};
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Audit perusahaan grup bernama manual (port DBEST #21): lengkap seperti
 * audit biasa, tetapi terpisah dari daftar, dasbor, tindak lanjut lintas
 * audit, dan hanya untuk administrator.
 */
class SmkpGrupTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private function buat(string $jenis = 'iujp'): SmkpAudit
    {
        $this->actingAs($this->admin())->post('/smkp/grup', [
            'perusahaan_luar' => 'PT Bara Nusantara Jaya', 'jenis_luar' => $jenis, 'tahun' => 2026, 'status' => 'berjalan',
            'ketua_auditor' => 'Rina',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return SmkpAudit::withoutGlobalScopes()->where('lingkup', 'grup')->firstOrFail();
    }

    public function test_audit_grup_berjalan_seperti_audit_biasa(): void
    {
        $this->siapkanGrup();
        $a = $this->buat();
        $this->assertNull($a->company_id);
        $this->assertSame('PT Bara Nusantara Jaya', $a->company->name);
        $this->assertSame('pjp', $a->company->kategori, 'IUJP memakai rubrik/kalimat jasa pertambangan.');

        foreach (["/smkp/{$a->id}", "/smkp/{$a->id}/tahap-1", "/smkp/{$a->id}/rencana", "/smkp/{$a->id}/elemen/I",
                  "/smkp/{$a->id}/temuan", "/smkp/{$a->id}/laporan", "/smkp/{$a->id}/rencana/cetak", "/smkp/{$a->id}/berita-acara"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/smkp/{$a->id}")->assertInertia(fn (Inertia $p) => $p->where('audit.perusahaan.nama', 'PT Bara Nusantara Jaya'));

        $this->post("/smkp/{$a->id}/rencana", ['tujuan' => 'Menilai'])->assertSessionHasNoErrors();

        // Nomor temuan memakai inisial perusahaan grup, bukan kosong.
        $this->assertSame('BNJ', $a->fresh()->kodeNomorTemuan());

        // Mengubah identitas lewat halaman grup; /ubah dialihkan ke sana.
        $this->get("/smkp/{$a->id}/ubah")->assertRedirect("/smkp/grup?ubah={$a->id}");
        $this->put("/smkp/grup/{$a->id}", ['perusahaan_luar' => 'PT Bara Nusantara Jaya', 'jenis_luar' => 'iup', 'tahun' => 2026, 'status' => 'selesai'])
            ->assertSessionHasNoErrors();
        $this->assertSame('induk', $a->fresh()->company->kategori);

        // Satu audit per perusahaan grup per tahun.
        $this->post('/smkp/grup', ['perusahaan_luar' => 'PT Bara Nusantara Jaya', 'jenis_luar' => 'iup', 'tahun' => 2026, 'status' => 'draft'])
            ->assertSessionHasErrors('tahun');
    }

    public function test_audit_grup_terpisah_dari_daftar_dasbor_dan_tindak_lanjut(): void
    {
        $this->siapkanGrup();
        $a = $this->buat();
        $hasil = [];
        foreach (Smkp::elemen() as $e) foreach ($e['sub'] as $s) $hasil[$s['kode']] = ['v' => 1];
        $a->forceFill(['hasil' => $hasil])->save();
        SmkpFinding::create(['audit_id' => $a->id, 'kode_kriteria' => 'I.1', 'jenis' => 'mayor', 'uraian' => 'x', 'status' => 'Open', 'target_selesai' => '2020-01-01']);

        $this->get('/smkp')->assertInertia(fn (Inertia $p) => $p->where('audits.total', 0));
        $this->get('/smkp/dasbor?tahun=2026')->assertInertia(fn (Inertia $p) => $p->where('dasbor.kpi.audit', 0));
        $this->get('/smkp/tindak-lanjut?status=semua')->assertInertia(fn (Inertia $p) => $p->where('daftar.total', 0));
        $this->get('/smkp/grup')->assertOk()->assertInertia(fn (Inertia $p) => $p->component('Smkp/Grup')
            ->where('ringkas.audit', 1)->where('ringkas.terbuka', 1)->where('baris.0.perusahaan', 'PT Bara Nusantara Jaya'));
    }

    public function test_hanya_administrator(): void
    {
        $this->siapkanGrup();
        $a = $this->buat();
        $u = $this->auditor($this->induk);
        $this->actingAs($u)->get('/smkp/grup')->assertForbidden();
        $this->actingAs($u)->post('/smkp/grup', ['perusahaan_luar' => 'X', 'jenis_luar' => 'iup', 'tahun' => 2026, 'status' => 'draft'])->assertForbidden();
        $this->actingAs($u)->get("/smkp/{$a->id}")->assertNotFound();
    }
}
