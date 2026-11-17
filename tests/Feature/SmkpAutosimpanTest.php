<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Support\SmkpAutosimpan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Simpan otomatis formulir Audit SMKP (port DBEST #16): jawaban JSON,
 * pemeriksaan versi agar tidak saling menimpa, dan tombol Simpan biasa
 * yang tetap berjalan seperti sebelumnya.
 */
class SmkpAutosimpanTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private const OTO = ['X-Autosimpan' => '1', 'Accept' => 'application/json'];

    private function audit(): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);
    }

    public function test_simpan_otomatis_menjawab_json_dan_menolak_versi_usang(): void
    {
        $this->siapkanGrup();
        $a = $this->audit();
        $u = $this->auditor($this->induk);

        $versi = '';
        $this->actingAs($u)->get("/smkp/{$a->id}/rencana")->assertInertia(function (Inertia $p) use (&$versi) {
            $p->has('versiSimpan');
            $versi = $p->toArray()['props']['versiSimpan'];
        });
        $this->assertSame(SmkpAutosimpan::versi($a->fresh(), 'rencana'), $versi);

        // Simpan otomatis pertama: JSON, versi baru.
        $r = $this->postJson("/smkp/{$a->id}/rencana", ['tujuan' => 'Tujuan A', '_versi' => $versi, '_sunting' => 'tabA'], self::OTO)
            ->assertOk()->assertJsonPath('ok', true);
        $baru = $r->json('versi');
        $this->assertNotSame($versi, $baru);
        $this->assertSame('Tujuan A', $a->fresh()->rencana['tujuan']);

        // Jawaban sebelumnya tidak sampai (versi tertinggal) tetapi sesi sunting sama: bukan bentrok.
        $this->postJson("/smkp/{$a->id}/rencana", ['tujuan' => 'Tujuan A2', '_versi' => $versi, '_sunting' => 'tabA'], self::OTO)->assertOk();

        // Tab lain dengan versi usang: ditolak 409, isi tidak tertimpa.
        $this->postJson("/smkp/{$a->id}/rencana", ['tujuan' => 'Tujuan B', '_versi' => $versi, '_sunting' => 'tabB'], self::OTO)
            ->assertStatus(409)->assertJsonPath('jenis', 'bentrok');
        $this->assertSame('Tujuan A2', $a->fresh()->rencana['tujuan']);

        // Tombol Simpan biasa tetap menimpa dan kembali ke halaman.
        $this->post("/smkp/{$a->id}/rencana", ['tujuan' => 'Tujuan B'])->assertRedirect("/smkp/{$a->id}/rencana");
        $this->assertSame('Tujuan B', $a->fresh()->rencana['tujuan']);
    }

    public function test_penilaian_per_elemen_tidak_bentrok_dengan_elemen_lain(): void
    {
        $this->siapkanGrup();
        $a = $this->audit();
        $u = $this->auditor($this->induk);
        $vI = SmkpAutosimpan::versi($a, 'nilai', 'I');

        // Rekan menilai elemen II.
        $a->forceFill(['hasil' => ['II.1' => ['v' => 2]]])->save();
        $this->assertSame($vI, SmkpAutosimpan::versi($a->fresh(), 'nilai', 'I'));

        $this->actingAs($u)->postJson("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => '3', 'ket' => '', 'bukti' => '', 'ulang' => false]], '_versi' => $vI], self::OTO)
            ->assertOk()->assertJsonPath('ok', true);
        $this->assertSame(2, $a->fresh()->hasil['II.1']['v']);
    }

    public function test_simpan_otomatis_tunduk_pada_validasi_dan_hak(): void
    {
        $this->siapkanGrup();
        $a = $this->audit();

        $this->actingAs($this->auditi($this->induk))->postJson("/smkp/{$a->id}/laporan", ['_versi' => ''], self::OTO)->assertForbidden();
        $this->actingAs($this->auditor($this->induk))->postJson("/smkp/{$a->id}/rencana", ['tanggal_mulai' => 'bukan-tanggal'], self::OTO)
            ->assertStatus(422);
    }
}
