<?php

namespace Tests\Feature;

use App\Models\{Company, EnvAudit, EnvAuditRtp, EnvAuditScore, User};
use App\Support\{AuditLingkungan, RtpLingkungan, Waktu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\TestCase;

/**
 * Rencana Tindak Perbaikan Audit Kinerja Lingkungan (mengikuti DBEST):
 * terbit dari nilai verifikasi ≤ 2, ditutup saat nilai naik, verifikasi
 * auditor, dan aturan target.
 */
class RtpLingkunganTest extends TestCase
{
    use RefreshDatabase;

    private Company $pt;
    private EnvAudit $audit;
    private string $kode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pt = Company::create(['name' => 'PT Mitra RTP', 'code' => 'MR']);
        $this->audit = EnvAudit::withoutGlobalScopes()->create([
            'company_id' => $this->pt->id, 'kode' => 'AKL-2026-RTP', 'tahun' => 2026, 'judul' => 'Audit RTP',
        ]);
        $this->kode = AuditLingkungan::kriteria('a')[0]['kode'];
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function auditee(): User
    {
        return User::factory()->auditi($this->pt->id)->create();
    }

    private function nilai(int $v): void
    {
        $this->post("/audit-lingkungan/{$this->audit->id}/nilai", ['bagian' => 'a', 'nilai' => [
            $this->kode => ['nilai' => $v, 'verifikasi' => $v, 'keterangan' => null],
        ]])->assertSessionHasNoErrors();
    }

    public function test_nilai_verifikasi_dua_atau_kurang_menerbitkan_rtp_dan_nilai_tiga_menghapusnya(): void
    {
        $this->actingAs($this->admin());

        $this->nilai(1);
        $r = EnvAuditRtp::where('audit_id', $this->audit->id)->where('kode', $this->kode)->firstOrFail();
        $this->assertSame('buka', $r->status);
        $this->assertSame(1, $r->nilai);
        $this->assertNotSame('', (string) $r->temuan);

        // Belum disentuh auditee → dihapus saat nilai menjadi penuh.
        $this->nilai(3);
        $this->assertSame(0, EnvAuditRtp::count());

        // Sudah diisi → ditutup, bukan dihapus; turun lagi → dibuka kembali.
        $this->nilai(2);
        EnvAuditRtp::first()->update(['rencana' => 'Perbaiki dokumen izin', 'status' => 'proses']);
        $this->nilai(3);
        $this->assertSame('selesai', EnvAuditRtp::first()->status);
        $this->assertStringContainsString('Ditutup otomatis', (string) EnvAuditRtp::first()->catatan_penutup);
        $this->nilai(0);
        $this->assertSame('proses', EnvAuditRtp::first()->status);
        $this->assertSame('Perbaiki dokumen izin', EnvAuditRtp::first()->rencana);
    }

    public function test_alur_auditee_mengajukan_dan_auditor_menyetujui_dengan_nilai_baru(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->nilai(1);
        $r = EnvAuditRtp::firstOrFail();

        $auditee = $this->auditee();
        $this->actingAs($auditee)->get("/audit-lingkungan/{$this->audit->id}/rtp")->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->component('AuditLingkungan/Rtp')
                ->where('bisaVerifikasi', false)->where('rtp.0.kode', $this->kode));

        $this->put("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}", [
            'rencana' => 'Lengkapi SK izin', 'pic' => 'Budi', 'target' => Waktu::kini()->addDays(10)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('proses', $r->fresh()->status);

        $this->post("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}/bukti", [
            'berkas' => [UploadedFile::fake()->create('sk-izin.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->assertSame(EnvAuditRtp::MENUNGGU, $r->fresh()->verifikasi);

        // Auditee tidak dapat memverifikasi.
        $this->post("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}/verifikasi", ['keputusan' => 'disetujui', 'nilai' => 3])->assertForbidden();

        // Tolak wajib beralasan.
        $this->actingAs($this->admin())->post("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}/verifikasi", ['keputusan' => 'ditolak'])
            ->assertSessionHasErrors('catatan');
        $this->post("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}/verifikasi", ['keputusan' => 'ditolak', 'catatan' => 'SK belum dilegalisir'])
            ->assertSessionHasNoErrors();
        $this->assertSame(EnvAuditRtp::DITOLAK, $r->fresh()->verifikasi);

        $this->post("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}/verifikasi", ['keputusan' => 'disetujui', 'nilai' => 3])
            ->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame('selesai', $r->status);
        $this->assertSame(EnvAuditRtp::DISETUJUI, $r->verifikasi);
        $this->assertSame(3, EnvAuditScore::where('audit_id', $this->audit->id)->where('kode', $this->kode)->value('verifikasi'));

        // Menyimpan ulang nilai rendah tidak membuka diam-diam RTP yang sudah disetujui.
        $this->nilai(1);
        $this->assertSame('selesai', $r->fresh()->status);
    }

    public function test_nilai_tidak_naik_bila_diverifikasi_sesudah_target(): void
    {
        $this->actingAs($this->admin());
        $this->nilai(1);
        $r = EnvAuditRtp::firstOrFail();
        $r->update(['target_tanggal' => Waktu::kini()->subDays(2)->toDateString(), 'rencana' => 'x']);

        // Dari lembar audit: kenaikan ditahan, nilai lama dipertahankan.
        $this->nilai(3);
        $this->assertSame(1, EnvAuditScore::where('kode', $this->kode)->value('verifikasi'));
        $this->assertNotSame('selesai', $r->fresh()->status);

        // Menurunkan selalu boleh.
        $this->nilai(0);
        $this->assertSame(0, EnvAuditScore::where('kode', $this->kode)->value('verifikasi'));

        // Dari verifikasi RTP: disetujui, tetapi nilainya bertahan.
        $this->post("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}/verifikasi", ['keputusan' => 'disetujui', 'nilai' => 3])
            ->assertSessionHasNoErrors();
        $this->assertSame('selesai', $r->fresh()->status);
        $this->assertSame(0, EnvAuditScore::where('kode', $this->kode)->value('verifikasi'));
    }

    public function test_target_yang_sudah_lewat_hanya_diubah_auditor(): void
    {
        $this->actingAs($this->admin());
        $this->nilai(2);
        $r = EnvAuditRtp::firstOrFail();
        $r->update(['target_tanggal' => Waktu::kini()->subDay()->toDateString()]);

        $this->actingAs($this->auditee())->put("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}", [
            'rencana' => 'x', 'target' => Waktu::kini()->addMonth()->toDateString(),
        ])->assertSessionHasErrors('target');

        $this->actingAs($this->admin())->put("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}", [
            'rencana' => 'x', 'target' => Waktu::kini()->addMonth()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    public function test_rtp_perusahaan_lain_tidak_terjangkau(): void
    {
        $this->actingAs($this->admin());
        $this->nilai(1);
        $r = EnvAuditRtp::firstOrFail();

        $lain = Company::create(['name' => 'PT Lain', 'code' => 'PLN']);
        $orang = User::factory()->create(['company_id' => $lain->id]);
        $this->actingAs($orang)->get("/audit-lingkungan/{$this->audit->id}/rtp")->assertNotFound();
        $this->put("/audit-lingkungan/{$this->audit->id}/rtp/{$r->id}", ['rencana' => 'x'])->assertNotFound();
    }

    public function test_migrasi_menerbitkan_rtp_untuk_nilai_lama(): void
    {
        EnvAuditScore::create(['audit_id' => $this->audit->id, 'kode' => $this->kode, 'nilai' => 1, 'verifikasi' => 1]);
        $this->assertSame(0, EnvAuditRtp::count());

        (require database_path('migrations/2026_11_16_000004_terbitkan_rtp_lingkungan_lama.php'))->up();

        $this->assertSame(1, EnvAuditRtp::where('kode', $this->kode)->where('status', 'buka')->count());
    }

    public function test_aturan_boleh_naik(): void
    {
        $this->assertTrue(RtpLingkungan::bolehNaik(null, '2026-11-16'));
        $this->assertTrue(RtpLingkungan::bolehNaik('2026-11-16', '2026-11-16'));
        $this->assertFalse(RtpLingkungan::bolehNaik('2026-11-15', '2026-11-16'));
        $this->assertTrue(RtpLingkungan::bolehNaik('2026-11-15', '2026-11-16', '2026-11-14'));
    }
}
