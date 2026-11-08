<?php

namespace Tests\Feature;

use App\Mail\PengingatTindakLanjut as SuratPengingat;
use App\Models\{ActivityLog, Pengaturan, SmkpAudit, SmkpFinding, User};
use App\Services\PengingatTindakLanjut;
use App\Support\{Smkp, SmkpTahap};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Sambungan modul audit SMKP (porting Aurorasys) dengan kerangka EQOHSEE:
 * menu, lonceng temuan lewat target, keterangan akun belum ditempatkan,
 * layar pengingat tindak lanjut, dan batas akun auditi per perusahaan.
 */
class IntegrasiSmkpTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private const SANDI = 'SandiKuat123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function audit($c, array $isi = []): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create($isi + [
            'company_id' => $c->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => SmkpTahap::LAPANGAN, 'hasil' => [],
        ]);
    }

    private function temuan(SmkpAudit $a, ?string $target, array $isi = []): SmkpFinding
    {
        return SmkpFinding::withoutGlobalScopes()->create($isi + [
            'audit_id' => $a->id, 'nomor' => 'KTS-'.uniqid(), 'kode_kriteria' => 'II.2.1', 'jenis' => 'minor',
            'uraian' => 'Identifikasi bahaya belum lengkap', 'status' => 'Open', 'target_selesai' => $target,
        ]);
    }

    /** @return array<string,array<string,mixed>> butir menu modul aktif, berkunci label */
    private function butirMenu($res): array
    {
        $butir = [];
        foreach ($res->viewData('page')['props']['menu']['grup'] as $g) {
            foreach ($g['butir'] as $b) $butir[$b['label']] = $b;
        }

        return $butir;
    }

    /* ───────────── Menu ───────────── */

    public function test_audit_baru_hanya_untuk_auditor_dan_administrator(): void
    {
        foreach ([[$this->admin(), true], [$this->auditor(), true], [$this->auditi($this->induk), false]] as [$u, $boleh]) {
            $menu = $this->butirMenu($this->actingAs($u)->get('/smkp'));
            $this->assertSame($boleh, isset($menu['Audit Baru']), $u->labelPeran());
            $this->assertArrayHasKey('Daftar Audit', $menu);
            $this->assertArrayHasKey('Tindak Lanjut Temuan', $menu);
        }

        // Butirnya tidak sekadar disembunyikan: halamannya memang tertutup bagi auditi.
        $this->actingAs($this->auditi($this->induk))->get('/smkp/buat')->assertForbidden();
    }

    public function test_daftar_audit_menyala_di_halaman_audit_dan_butir_khusus_menang(): void
    {
        $a = $this->audit($this->induk);
        $u = $this->auditor();

        $menu = $this->butirMenu($this->actingAs($u)->get("/smkp/{$a->id}/temuan"));
        $this->assertTrue($menu['Daftar Audit']['aktif'], 'Halaman satu audit berada di bawah Daftar Audit (Aurorasys).');

        $menu = $this->butirMenu($this->actingAs($u)->get('/smkp/buat'));
        $this->assertTrue($menu['Audit Baru']['aktif']);
        $this->assertFalse($menu['Daftar Audit']['aktif'], 'Hanya satu butir yang menyala.');

        $menu = $this->butirMenu($this->actingAs($u)->get('/smkp/tindak-lanjut'));
        $this->assertTrue($menu['Tindak Lanjut Temuan']['aktif']);
        $this->assertFalse($menu['Daftar Audit']['aktif']);

        $menu = $this->butirMenu($this->actingAs($u)->get('/smkp'));
        $this->assertSame(['Daftar Audit'], array_keys(array_filter($menu, fn ($b) => $b['aktif'])));

        // Hanya /smkp beranda modulnya — bilah pindah cepat tidak tergambar di tiap halaman audit.
        $this->actingAs($u)->get('/smkp')->assertInertia(fn (Inertia $p) => $p->where('menu.beranda', true));
        $this->actingAs($u)->get("/smkp/{$a->id}")->assertInertia(fn (Inertia $p) => $p->where('menu.beranda', false));
        $this->actingAs($u)->get('/smkp/acuan')->assertInertia(fn (Inertia $p) => $p->where('menu.beranda', false));
    }

    /* ───────────── Lonceng temuan lewat target ───────────── */

    public function test_temuan_lewat_target_dihitung_dalam_jangkauan_pengguna(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-08 10:00'));
        $iup = $this->audit($this->induk);
        $pjp = $this->audit($this->pjp);
        $sister = $this->audit($this->sister);

        $this->temuan($iup, '2026-11-01');                                  // lewat
        $this->temuan($pjp, '2026-10-01');                                  // lewat (PJP di bawah IUP)
        $this->temuan($sister, '2026-09-01');                               // lewat, di luar jangkauan IUP
        $this->temuan($iup, '2026-11-08');                                  // hari ini: belum lewat
        $this->temuan($iup, '2026-10-01', ['status' => 'Closed']);          // sudah ditutup
        $this->temuan($iup, null);                                          // tanpa target

        $cek = function (User $u, int $n) {
            $res = $this->actingAs($u)->get('/smkp');
            $res->assertInertia(fn (Inertia $p) => $p->where('peringatan', $n));
            $menu = $this->butirMenu($res);
            $this->assertSame($n ?: null, $menu['Tindak Lanjut Temuan']['lencana']);
        };

        $cek($this->auditor($this->induk), 2);
        $cek($this->auditi($this->pjp), 1);
        $cek($this->admin(), 3);
        $cek($this->auditi($this->sister), 1);

        // Titik pada pemilih modul terlihat juga dari modul lain.
        $res = $this->actingAs($this->auditor($this->induk))->get('/dashboard');
        $modul = collect($res->viewData('page')['props']['menu']['modul'])->firstWhere('kunci', 'smkp');
        $this->assertSame(2, $modul['lencana']);
    }

    /* ───────────── Akun belum ditempatkan ───────────── */

    public function test_akun_tanpa_perusahaan_ditandai_belum_ditempatkan(): void
    {
        $this->audit($this->induk);
        $lepas = User::factory()->create(['audit_role' => User::ROLE_AUDITOR, 'company_id' => null]);

        $this->actingAs($lepas)->get('/smkp')->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->where('pengguna.ditempatkan', false)->where('audits.total', 0));
        $this->actingAs($this->auditor($this->induk))->get('/smkp')->assertInertia(fn (Inertia $p) => $p
            ->where('pengguna.ditempatkan', true));
        $this->actingAs($this->admin())->get('/smkp')->assertInertia(fn (Inertia $p) => $p
            ->where('pengguna.ditempatkan', true));
    }

    /* ───────────── Layar pengingat tindak lanjut ───────────── */

    public function test_layar_pengingat_hanya_untuk_administrator(): void
    {
        foreach ([$this->auditor(), $this->auditi($this->induk)] as $u) {
            $this->actingAs($u)->get('/admin/pengingat')->assertForbidden();
            $this->actingAs($u)->post('/admin/pengingat', ['aktif' => false])->assertForbidden();
            $this->actingAs($u)->post('/admin/pengingat/kirim')->assertForbidden();
        }

        $this->actingAs($this->admin())->get('/admin/pengingat')->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Admin/Pengingat')
            ->where('pengingat.aktif', true)
            ->where('pengingat.jadwal', PengingatTindakLanjut::BAWAAN)
            ->where('pengingat.pilihan', PengingatTindakLanjut::JADWAL)
            ->where('pengingat.jam', '07.15'));

        // Pusat Kendali menautkannya.
        $this->actingAs($this->admin())->get('/admin/system')->assertInertia(fn (Inertia $p) => $p
            ->where('pintasan', fn ($d) => collect($d)->contains('url', route('admin.pengingat'))));
    }

    public function test_pengaturan_pengingat_tersimpan_dan_tercatat(): void
    {
        $adm = $this->admin();

        $this->actingAs($adm)->post('/admin/pengingat', ['aktif' => true, 'jadwal' => ''])->assertSessionHasErrors('jadwal');
        $this->actingAs($adm)->post('/admin/pengingat', ['aktif' => true, 'jadwal' => '1m,3b,asing'])
            ->assertSessionHas('ok', 'Pengingat aktif: 3 bulan & 1 minggu sebelum batas waktu tindak lanjut.');
        $this->assertSame(['3b', '1m'], PengingatTindakLanjut::jadwal());
        $this->assertTrue(PengingatTindakLanjut::aktif());

        $this->actingAs($adm)->post('/admin/pengingat', ['aktif' => false, 'jadwal' => ''])->assertSessionHasNoErrors();
        $this->assertFalse(PengingatTindakLanjut::aktif());
        $this->assertSame('0', Pengaturan::ambil(PengingatTindakLanjut::AKTIF));

        $this->assertSame(2, ActivityLog::where('module', 'pengingat')->where('action', 'Pengaturan pengingat')->count());
        $this->actingAs($adm)->get('/admin/pengingat')->assertInertia(fn (Inertia $p) => $p
            ->where('pengingat.aktif', false)->where('riwayat.0.detail', 'mati'));

        // Dimatikan: kirim manual ditolak dengan sebabnya.
        $this->actingAs($adm)->post('/admin/pengingat/kirim')->assertSessionHasErrors(['pengingat' => 'Pengingat tindak lanjut sedang dimatikan.']);
    }

    public function test_pratinjau_dan_kirim_sekarang_dari_layar_pengingat(): void
    {
        $adm = $this->admin();
        $a = $this->auditi($this->pjp);
        User::factory()->create(['company_id' => $this->pjp->id, 'audit_role' => null]);   // pengguna modul lain: bukan penerima
        $auditPjp = $this->audit($this->pjp, ['status' => 'selesai', 'tahap' => 3]);
        $this->temuan($auditPjp, '2026-12-15', ['nomor' => 'KTS-01']);
        $this->temuan($this->audit($this->sister, ['status' => 'selesai', 'tahap' => 3]), '2027-03-01', ['nomor' => 'KTS-02']);
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));

        // Server email belum siap (MAIL_MAILER=log): pratinjau tetap ada, kirim ditolak.
        config(['mail.default' => 'log']);
        $this->actingAs($adm)->get('/admin/pengingat')->assertInertia(fn (Inertia $p) => $p
            ->where('surel.siap', false)
            ->where('mendatang.0.tanggal', '2026-10-20')->where('mendatang.0.temuan', 'KTS-01')->where('mendatang.0.penerima', [$a->email])
            ->where('mendatang.1.tanggal', '2026-11-15')->where('mendatang.1.jadwal', '1 bulan')
            ->where('mendatang.2.tanggal', '2027-01-01')->where('mendatang.2.perusahaan', 'PT Saudara Uji')->where('mendatang.2.penerima', []));
        $this->actingAs($adm)->post('/admin/pengingat/kirim')->assertSessionHasErrors('pengingat');

        config(['mail.default' => 'smtp']);
        Mail::fake();
        $this->actingAs($adm)->post('/admin/pengingat/kirim')->assertSessionHas('ok');
        Mail::assertSent(SuratPengingat::class, fn (SuratPengingat $m) => $m->hasTo($a->email) && count($m->to) === 1);
        Mail::assertSent(SuratPengingat::class, 1);
        $this->actingAs($adm)->post('/admin/pengingat/kirim')
            ->assertSessionHas('ok', 'Tidak ada pengingat yang jatuh tempo hari ini.');
        Mail::assertSent(SuratPengingat::class, 1);
        $this->assertSame(1, ActivityLog::where('module', 'pengingat')->where('action', 'Pengingat tindak lanjut (manual)')->count());
    }

    /* ───────────── Batas akun auditi ───────────── */

    public function test_auditi_wajib_berperusahaan_dan_paling_banyak_dua_aktif_per_perusahaan(): void
    {
        $adm = $this->admin();
        $buat = fn (string $email, array $ubah = []) => $this->actingAs($adm)->post('/admin/users', array_merge([
            'name' => 'Auditi '.$email, 'email' => $email, 'audit_role' => User::ROLE_AUDITI,
            'company_id' => $this->pjp->id, 'active' => true, 'password' => self::SANDI,
        ], $ubah));

        $buat('tanpa@contoh.id', ['company_id' => ''])->assertSessionHasErrors('company_id');

        $buat('a1@contoh.id')->assertRedirect('/admin/users');
        $buat('a2@contoh.id')->assertRedirect('/admin/users');
        $buat('a3@contoh.id')->assertSessionHasErrors(['company_id' => 'PT Jasa Uji sudah memiliki 2 akun auditi aktif — batasnya 2 akun per IUP/PJP. Nonaktifkan salah satunya lebih dulu.']);
        $this->assertDatabaseMissing('users', ['email' => 'a3@contoh.id']);

        // Kuota per perusahaan: IUP induknya masih kosong; akun nonaktif, auditor, dan
        // pengguna tanpa peran audit tidak dihitung.
        $buat('a3@contoh.id', ['company_id' => $this->induk->id])->assertRedirect('/admin/users');
        $buat('a4@contoh.id', ['active' => false])->assertRedirect('/admin/users');
        $buat('auditor@contoh.id', ['audit_role' => User::ROLE_AUDITOR])->assertRedirect('/admin/users');
        $buat('lain@contoh.id', ['audit_role' => ''])->assertRedirect('/admin/users');

        $a4 = User::where('email', 'a4@contoh.id')->firstOrFail();
        $a3 = User::where('email', 'a3@contoh.id')->firstOrFail();
        $lain = User::where('email', 'lain@contoh.id')->firstOrFail();
        $ubah = fn (User $u, array $d) => $this->actingAs($adm)->put("/admin/users/{$u->id}", array_merge([
            'name' => $u->name, 'email' => $u->email, 'audit_role' => (string) $u->audit_role,
            'company_id' => $u->company_id, 'active' => (bool) $u->active,
        ], $d));

        // Mengaktifkan lagi, memindahkan, atau menjadikan auditi di perusahaan yang penuh ditolak.
        $ubah($a4, ['active' => true])->assertSessionHasErrors('company_id');
        $ubah($a3, ['company_id' => $this->pjp->id])->assertSessionHasErrors('company_id');
        $ubah($lain, ['audit_role' => User::ROLE_AUDITI])->assertSessionHasErrors('company_id');

        // Akun yang sudah terhitung tetap dapat diubah datanya; menonaktifkan membuka satu tempat.
        $a1 = User::where('email', 'a1@contoh.id')->firstOrFail();
        $ubah($a1, ['name' => 'Auditi Satu', 'phone' => '0812'])->assertSessionHasNoErrors();
        $ubah($a1, ['active' => false])->assertSessionHasNoErrors();
        $ubah($a4, ['active' => true])->assertSessionHasNoErrors();
        $this->assertSame(2, User::where('company_id', $this->pjp->id)->where('audit_role', User::ROLE_AUDITI)->where('active', true)->count());

        // Formulir memberi tahu kuotanya.
        $this->actingAs($adm)->get('/admin/users/create')->assertInertia(fn (Inertia $p) => $p
            ->component('Admin/Pengguna/Form')
            ->where('maksAuditi', User::MAKS_AUDITI)
            ->where("auditiAktif.{$this->pjp->id}", 2)->where("auditiAktif.{$this->induk->id}", 1)
            ->where('opsi.audit', fn ($o) => collect($o)->pluck('label')->all() === ['Auditor', 'Auditi (unggah bukti & tindak lanjut)']));
        $this->actingAs($adm)->get('/admin/users')->assertInertia(fn (Inertia $p) => $p
            ->where('daftar', fn ($d) => collect($d)->firstWhere('email', 'a3@contoh.id')['auditRole'] === 'Auditi'));
    }

    /* ───────────── Hari kerja dibuka kembali ───────────── */

    public function test_keluar_dari_pelaporan_melepas_hari_kerja_yang_dibekukan(): void
    {
        $u = $this->auditor($this->induk);
        $a = $this->audit($this->induk, ['tahap' => SmkpTahap::PERMULAAN, 'status' => 'berjalan']);
        $kecukupan = [];
        foreach (Smkp::elemen() as $e) $kecukupan[$e['kode']] = ['status' => SmkpTahap::LENGKAP, 'ket' => ''];
        $this->actingAs($u)->post("/smkp/{$a->id}/tahap-1", ['kecukupan' => $kecukupan, 'permulaan' => ['mandays_dasar' => '20', 'jumlah_auditor' => '2']])
            ->assertSessionHasNoErrors();
        $a->forceFill(['tahap' => SmkpTahap::PELAPORAN, 'status' => 'selesai', 'mandays_tetap' => ['total' => 99.0, 'aturan' => 'uji']])->save();
        $this->assertEquals(99.0, $a->fresh()->mandays()['total']);

        // Dibuka kembali ke Tahap I (tidak menuntut kesiapan Tahap II).
        $this->actingAs($u)->post("/smkp/{$a->id}/tahap", ['tahap' => SmkpTahap::PERMULAAN])->assertSessionHasNoErrors();
        $a->refresh();
        $this->assertNull($a->mandays_tetap);
        $this->assertSame('berjalan', $a->status);
        $this->assertEquals(10.0, $a->mandays()['total'], '20 hari-orang dibagi 2 auditor.');
    }
}
