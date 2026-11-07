<?php

namespace Tests\Feature;

use App\Mail\PengingatTindakLanjut as SuratPengingat;
use App\Models\{ActivityLog, Company, Pengaturan, SmkpAudit, SmkpFinding, SmkpPengingat, User};
use App\Services\PengingatTindakLanjut;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Pengingat email batas waktu tindak lanjut ke akun auditi (bawaan 2 bulan
 * & 1 bulan sebelum target). Server email: MAIL_* (.env) EQOHSEE.
 */
class PengingatTindakLanjutTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private SmkpAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
        // Server email dianggap siap; Mail::fake menangkap kirimannya.
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->audit = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->pjp->id, 'tahun' => 2026, 'status' => 'selesai', 'tahap' => 3, 'hasil' => [],
        ]);
    }

    private function temuan(string $target, array $isi = [], ?SmkpAudit $audit = null): SmkpFinding
    {
        return SmkpFinding::withoutGlobalScopes()->create($isi + [
            'audit_id' => ($audit ?? $this->audit)->id, 'nomor' => 'KTS-01', 'kode_kriteria' => 'II.2.1', 'jenis' => 'minor',
            'uraian' => 'Identifikasi bahaya belum mencakup seluruh area kerja', 'status' => 'Open', 'target_selesai' => $target,
        ]);
    }

    private function jalankan(string $tanggal): void
    {
        $this->travelTo(CarbonImmutable::parse($tanggal.' 07:15'));
        $this->artisan('smkp:pengingat-tindak-lanjut')->assertSuccessful();
    }

    public function test_dua_dan_satu_bulan_sebelum_target_ke_akun_auditi_perusahaan(): void
    {
        $a1 = $this->auditi($this->pjp);
        $a2 = $this->auditi($this->pjp);
        $this->auditi($this->pjp)->forceFill(['active' => false])->save();
        $this->auditi($this->induk); // PJP sudah punya auditi: IUP induknya tidak ikut dikirimi
        $this->auditor($this->pjp);
        $t = $this->temuan('2026-12-15');
        $this->temuan('2026-12-15', ['nomor' => 'KTS-02', 'status' => 'Closed']);

        $this->jalankan('2026-10-14');
        Mail::assertNothingSent();

        $this->jalankan('2026-10-15');
        Mail::assertSent(SuratPengingat::class, 1);
        Mail::assertSent(SuratPengingat::class, function (SuratPengingat $m) use ($a1, $a2) {
            $m->assertSeeInHtml('KTS-01');
            $m->assertSeeInHtml('15 Desember 2026');
            $m->assertSeeInHtml("/smkp/{$this->audit->id}/temuan?buka=");

            return count($m->to) === 2 && $m->hasTo($a1->email) && $m->hasTo($a2->email)
                && count($m->temuan) === 1 && $m->temuan[0]['jadwal'] === '2 bulan' && $m->temuan[0]['sisa'] === 61;
        });

        // Dijalankan ulang di hari yang sama: tidak terkirim dua kali.
        $this->jalankan('2026-10-15');
        Mail::assertSent(SuratPengingat::class, 1);

        $this->jalankan('2026-11-15');
        Mail::assertSent(SuratPengingat::class, 2);
        $this->assertSame(['1b', '2b'], $t->pengingat()->orderBy('jadwal')->pluck('jadwal')->all());
        $this->assertSame("{$a1->email}, {$a2->email}", SmkpPengingat::where('jadwal', '1b')->value('penerima'));

        // Setelah target lewat tidak ada pengingat lagi.
        $this->jalankan('2026-12-20');
        Mail::assertSent(SuratPengingat::class, 2);
        $this->assertTrue(ActivityLog::where('module', 'pengingat')->where('detail', '1 email terkirim untuk 1 temuan')->exists());
    }

    public function test_jadwal_tersusul_cukup_satu_email_dan_target_diundur_berlaku_lagi(): void
    {
        $this->auditi($this->pjp);
        $t = $this->temuan('2026-11-05');

        // Temuan baru dengan target 3 minggu lagi: jadwal 2 bulan dilewati, hanya pengingat 1 bulan.
        $this->jalankan('2026-10-16');
        Mail::assertSent(SuratPengingat::class, 1);
        Mail::assertSent(SuratPengingat::class, fn (SuratPengingat $m) => $m->temuan[0]['jadwal'] === '1 bulan');
        $this->assertNull(SmkpPengingat::where('jadwal', '2b')->sole()->dikirim_pada);

        // Target diundur: jadwal dihitung ulang untuk target baru.
        $t->update(['target_selesai' => '2027-01-20']);
        $this->jalankan('2026-10-17');
        Mail::assertSent(SuratPengingat::class, 1);
        $this->jalankan('2026-11-20');
        Mail::assertSent(SuratPengingat::class, 2);
        Mail::assertSent(SuratPengingat::class, fn (SuratPengingat $m) => $m->temuan[0]['target'] === '20 Januari 2027' && $m->temuan[0]['jadwal'] === '2 bulan');
    }

    public function test_pjp_tanpa_auditi_dikirim_ke_auditi_iup_induk_dan_data_demo_tidak_pernah(): void
    {
        $induk = $this->auditi($this->induk);
        $this->temuan('2026-12-15');

        $demo = Company::create(['name' => 'PT Demo Uji', 'code' => 'DMU', 'kategori' => 'induk']);
        $demo->forceFill(['demo' => true])->save();
        $this->auditi($demo);   // EQOHSEE: akun tidak berpenanda demo; perusahaannya yang demo
        $auditDemo = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $demo->id, 'tahun' => 2026, 'status' => 'selesai', 'tahap' => 3, 'hasil' => []]);
        $this->temuan('2026-12-15', [], $auditDemo);

        $this->jalankan('2026-10-15');
        Mail::assertSent(SuratPengingat::class, 1);
        Mail::assertSent(SuratPengingat::class, fn (SuratPengingat $m) => $m->hasTo($induk->email) && $m->perusahaan->is($this->pjp));
    }

    public function test_tanpa_akun_auditi_dicoba_lagi_dan_tidak_dikirim_bila_dimatikan_atau_email_belum_siap(): void
    {
        $this->temuan('2026-12-15');

        $this->jalankan('2026-10-15');
        Mail::assertNothingSent();
        $this->assertSame(0, SmkpPengingat::count());
        $this->assertTrue(ActivityLog::where('module', 'pengingat')->where('detail', 'like', '%belum ada akun auditi: PT Jasa Uji%')->exists());

        $this->auditi($this->pjp);
        Pengaturan::simpan([PengingatTindakLanjut::AKTIF => '0']);
        $this->jalankan('2026-10-16');
        Mail::assertNothingSent();

        Pengaturan::simpan([PengingatTindakLanjut::AKTIF => '1']);
        config(['mail.default' => 'log']);
        $this->jalankan('2026-10-16');
        Mail::assertNothingSent();

        config(['mail.default' => 'smtp']);
        $this->jalankan('2026-10-16');
        Mail::assertSent(SuratPengingat::class, 1);
    }
}
