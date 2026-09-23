<?php

namespace Tests\Feature;

use App\Models\{Company, EnvAudit, EnvAuditScore, EnvAuditSertifikat, Signatory, User};
use App\Support\AuditLingkungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Sertifikat Penghargaan Kinerja Lingkungan.
 *
 * Sertifikat adalah pernyataan kepada pihak luar. Yang dijaga di sini:
 * ia hanya terbit dari audit yang lengkap dan berpredikat, bunyinya
 * tidak berubah diam-diam sesudah terbit, satu audit tidak punya dua
 * sertifikat berlaku, dan halaman verifikasinya — yang dibuka siapa
 * pun dari QR — selalu menyatakan keadaan sebenarnya, termasuk sesudah
 * dicabut dan sesudah auditnya dihapus.
 */
class SertifikatLingkunganTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(?Company $induk = null): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Mitra Hijau {$i}", 'code' => "MH{$i}",
                                'parent_id' => $induk?->id, 'location' => 'Balikpapan']);
    }

    private function masuk(?Company $c = null, bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    /** Audit dengan seluruh kriteria terverifikasi $nilai, kecuali $kecuali (kode → nilai|null). */
    private function audit(Company $c, int $nilai = 3, array $kecuali = []): EnvAudit
    {
        $a = EnvAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id,
            'kode'       => 'AKL-2026-'.sprintf('%03d', ++self::$n),
            'tahun'      => 2026,
            'judul'      => 'Audit Kinerja Pengelolaan Lingkungan 2026',
            'lokasi'     => 'Site Utara',
        ]);

        foreach (AuditLingkungan::bagian() as $kunci => $_) {
            foreach (AuditLingkungan::kriteria($kunci) as $k) {
                $v = array_key_exists($k['kode'], $kecuali) ? $kecuali[$k['kode']] : $nilai;
                EnvAuditScore::create(['audit_id' => $a->id, 'kode' => $k['kode'], 'verifikasi' => $v]);
            }
        }

        return $a;
    }

    private function kodePertama(string $bagian): string
    {
        return AuditLingkungan::kriteria($bagian)[0]['kode'];
    }

    private function terbitkan(EnvAudit $a, array $x = [])
    {
        return $this->post(route('audit-lingkungan.sertifikat.terbitkan', $a), array_merge([
            'terbit' => '2026-09-23', 'berlaku' => '2027-09-22', 'tempat' => 'Balikpapan',
        ], $x));
    }

    /* ══════════════ terbit ══════════════ */

    public function test_terbit_dari_audit_lengkap_berpredikat(): void
    {
        $induk = Company::create(['name' => 'PT Cemerlang Asa Mandiri', 'code' => 'CAM']);
        $c = $this->perusahaan($induk);
        $this->masuk($c);
        $a = $this->audit($c);

        $this->terbitkan($a)->assertRedirect(route('audit-lingkungan.show', $a))->assertSessionHas('ok');

        $s = EnvAuditSertifikat::firstOrFail();
        $this->assertSame('AKL-SERT/CAM/2026/001', $s->nomor);
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{12}$/', $s->kode);
        $this->assertSame('ADITAMA', $s->predikat);
        $this->assertSame('EMAS', $s->peringkat);
        $this->assertSame(100.0, $s->skor);
        $this->assertSame($c->id, $s->company_id);
        $this->assertSame($c->name, $s->data['perusahaan']);
        $this->assertSame('PT Cemerlang Asa Mandiri', $s->data['penerbit']);
        $this->assertCount(6, $s->data['bagian']);
        $this->assertSame('Selesai', $a->fresh()->status);
    }

    public function test_tidak_terbit_bila_masih_ada_kriteria_belum_diverifikasi(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c, 3, [$this->kodePertama('f') => null]);

        $this->terbitkan($a)->assertRedirect()->assertSessionHas('galat', fn ($g) => str_contains($g, '1 dari'));
        $this->assertSame(0, EnvAuditSertifikat::count());
    }

    /** Skor 99-an, tetapi bagian A tidak penuh: predikat tidak terbit, sertifikat pun tidak. */
    public function test_tidak_terbit_tanpa_predikat_meski_skornya_tinggi(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c, 3, [$this->kodePertama('a') => 2]);

        $this->assertGreaterThan(90, $a->skor()['akhir']);

        $this->terbitkan($a)->assertSessionHas('galat', fn ($g) => str_contains($g, 'PENUH'));
        $this->assertSame(0, EnvAuditSertifikat::count());
    }

    public function test_satu_audit_hanya_satu_sertifikat_berlaku(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->terbitkan($a);
        $this->terbitkan($a)->assertSessionHas('galat', fn ($g) => str_contains($g, 'masih berlaku'));

        $this->assertSame(1, EnvAuditSertifikat::count());
    }

    public function test_tanggal_berlaku_tidak_boleh_mendahului_terbit(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->terbitkan($a, ['berlaku' => '2026-01-01'])->assertSessionHasErrors('berlaku');
    }

    public function test_penanda_tangan_perusahaan_lain_ditolak(): void
    {
        $c = $this->perusahaan();
        $lain = $this->perusahaan();
        $ttdLain = Signatory::withoutGlobalScopes()->create(['company_id' => $lain->id, 'name' => 'Orang Lain', 'is_active' => true]);
        $ttdSendiri = Signatory::withoutGlobalScopes()->create(['company_id' => $c->id, 'name' => 'Kepala Teknik', 'title' => 'KTT', 'is_active' => true]);

        $this->masuk($c, false);
        $a = $this->audit($c);

        $this->terbitkan($a, ['signatory_id' => $ttdLain->id])->assertSessionHasErrors('signatory_id');
        $this->assertSame(0, EnvAuditSertifikat::withoutGlobalScopes()->count());

        $this->terbitkan($a, ['signatory_id' => $ttdSendiri->id])->assertSessionHas('ok');
        $s = EnvAuditSertifikat::firstOrFail();
        $this->assertSame('Kepala Teknik', $s->data['ttdNama']);
        $this->assertSame('KTT', $s->data['ttdJabatan']);
    }

    /* ══════════════ potret ══════════════ */

    public function test_bunyi_sertifikat_tidak_berubah_saat_nilai_disunting(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);
        $this->terbitkan($a);

        EnvAuditScore::where('audit_id', $a->id)->where('kode', $this->kodePertama('c'))->update(['verifikasi' => 0]);

        $s = EnvAuditSertifikat::firstOrFail();
        $this->assertSame(100.0, $s->skor);
        $this->assertSame(100.0, (float) $s->data['akhir']);

        $this->get(route('audit-lingkungan.show', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AuditLingkungan/Ikhtisar')
                ->where('sertifikat.aktif.nomor', $s->nomor)
                ->where('sertifikat.aktif.berubah', true)
                ->where('sertifikat.layak', false));
    }

    public function test_ikhtisar_memberi_pratinjau_hanya_bila_layak(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $layak = $this->audit($c);
        $this->get(route('audit-lingkungan.show', $layak))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('sertifikat.layak', true)
            ->where('sertifikat.aktif', null)
            ->where('sertifikat.pratinjau.predikat', 'ADITAMA')
            ->where('sertifikat.pratinjau.tema', 'emas')
            ->where('sertifikat.pratinjau.bintang', 3));

        $belum = $this->audit($c, 2);   // 66,67 — HITAM? tidak: MERAH, tanpa predikat
        $this->get(route('audit-lingkungan.show', $belum))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('sertifikat.layak', false)
            ->where('sertifikat.pratinjau', null)
            ->has('sertifikat.alasan'));

        $this->assertSame(0, EnvAuditSertifikat::count(), 'Pratinjau tidak boleh menyimpan apa pun.');
    }

    /** Kop sertifikat memakai logo perusahaan; kalimatnya menyebut tanggal auditnya. */
    public function test_pratinjau_membawa_logo_dan_tanggal_audit(): void
    {
        $c = $this->perusahaan();
        $c->forceFill(['logo' => 'logo/mitra-hijau.png'])->save();
        $this->masuk($c);

        $a = $this->audit($c);
        $a->forceFill(['tanggal' => '2026-08-12'])->save();

        $this->get(route('audit-lingkungan.show', $a))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('sertifikat.urlLogo', route('personalia.perusahaan'))
            ->where('sertifikat.pratinjau.tanggalAudit', '12 Agustus 2026')
            ->where('sertifikat.pratinjau.logoPenerima', asset('storage/logo/mitra-hijau.png')));
    }

    public function test_tema_mengikuti_peringkat(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        /* Seluruhnya 3 kecuali bagian C, E, F bernilai 1: A dan B tetap
           penuh, skor turun ke rentang UTAMA/HIJAU atau PRATAMA/BIRU. */
        $kecuali = [];
        foreach (['c', 'e', 'f', 'd'] as $b) {
            foreach (AuditLingkungan::kriteria($b) as $k) $kecuali[$k['kode']] = 1;
        }
        $a = $this->audit($c, 3, $kecuali);
        $skor = $a->skor();

        $this->terbitkan($a)->assertSessionHas('ok');
        $s = EnvAuditSertifikat::firstOrFail();

        $this->assertSame($skor['peringkat']['nama'], $s->peringkat);
        $this->assertSame(EnvAuditSertifikat::TEMA[$s->peringkat], $s->tema());
        $this->assertNotSame('emas', $s->tema());
    }

    /* ══════════════ lembar ══════════════ */

    public function test_lembar_sertifikat_dirender_dan_dibatasi_perusahaan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, false);
        $a = $this->audit($c);
        $this->terbitkan($a);
        $s = EnvAuditSertifikat::firstOrFail();

        $this->get(route('audit-lingkungan.sertifikat.lihat', $s))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AuditLingkungan/Sertifikat')
                ->where('s.nomor', $s->nomor)
                ->where('s.tema', 'emas')
                ->where('s.bintang', 3)
                ->where('s.status', 'sah')
                ->where('s.urlVerifikasi', route('audit-lingkungan.verifikasi', $s->kode)));

        $this->masuk($this->perusahaan(), false);
        $this->get(route('audit-lingkungan.sertifikat.lihat', $s))->assertNotFound();
    }

    /* ══════════════ verifikasi publik ══════════════ */

    public function test_verifikasi_publik_dengan_kode_bukan_nomor(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);
        $this->terbitkan($a);
        $s = EnvAuditSertifikat::firstOrFail();

        auth()->logout();

        $this->get(route('audit-lingkungan.verifikasi', $s->kode))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('AuditLingkungan/Verifikasi')
                ->where('s.status', 'sah')
                ->where('s.perusahaan', $c->name)
                ->where('s.predikat', 'ADITAMA')
                ->missing('s.bagian')
                ->missing('s.urlVerifikasi'));

        // Kode yang diketik ulang dari kertas: huruf kecil, bertanda hubung.
        $this->get(route('audit-lingkungan.verifikasi', strtolower($s->kodeTampil())))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('s.nomor', $s->nomor));

        // Nomor urut dapat ditebak — tidak diterima.
        $this->get(route('audit-lingkungan.verifikasi', str_replace('/', '-', $s->nomor)))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('s', null));
        $this->get(route('audit-lingkungan.verifikasi', 'ABCDEFGHJKMN'))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('s', null));
    }

    public function test_sertifikat_kedaluwarsa_disebut_kedaluwarsa(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);
        $this->terbitkan($a, ['terbit' => '2024-01-10', 'berlaku' => '2025-01-09']);

        $s = EnvAuditSertifikat::firstOrFail();
        $this->assertSame('kedaluwarsa', $s->status());
        $this->assertSame('AKL-SERT/MH/2024/001', $s->nomor);   // tanpa pemilik izin: prefiks dari namanya sendiri
    }

    /* ══════════════ cabut & hapus ══════════════ */

    public function test_cabut_hanya_administrator_dan_verifikasi_menyatakan_dicabut(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, false);
        $a = $this->audit($c);
        $this->terbitkan($a);
        $s = EnvAuditSertifikat::firstOrFail();

        $this->post(route('audit-lingkungan.sertifikat.cabut', $s), ['alasan' => 'Temuan susulan'])->assertForbidden();

        $this->masuk($c, true);
        $this->post(route('audit-lingkungan.sertifikat.cabut', $s), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->post(route('audit-lingkungan.sertifikat.cabut', $s), ['alasan' => 'Temuan susulan di lapangan'])
            ->assertSessionHas('ok');

        $this->assertSame('dicabut', $s->fresh()->status());

        $this->get(route('audit-lingkungan.verifikasi', $s->kode))
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('s.status', 'dicabut')
                ->where('s.alasanCabut', 'Temuan susulan di lapangan'));

        // Sesudah dicabut, boleh terbit ulang — dengan nomor baru.
        $this->terbitkan($a)->assertSessionHas('ok');
        $this->assertSame(2, EnvAuditSertifikat::count());
        $this->assertSame(1, EnvAuditSertifikat::aktif()->count());
        $this->assertStringEndsWith('/002', EnvAuditSertifikat::aktif()->first()->nomor);
    }

    public function test_audit_bersertifikat_tidak_dihapus_dan_jejaknya_bertahan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, true);
        $a = $this->audit($c);
        $this->terbitkan($a);
        $s = EnvAuditSertifikat::firstOrFail();

        $this->delete(route('audit-lingkungan.destroy', $a))->assertSessionHas('galat');
        $this->assertNotNull($a->fresh());

        $this->post(route('audit-lingkungan.sertifikat.cabut', $s), ['alasan' => 'Audit dibatalkan']);
        $this->delete(route('audit-lingkungan.destroy', $a))->assertRedirect(route('audit-lingkungan.index'));
        $this->assertNull(EnvAudit::find($a->id));

        $this->assertNull($s->fresh()->audit_id);
        $this->get(route('audit-lingkungan.verifikasi', $s->kode))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('s.status', 'dicabut')->where('s.perusahaan', $c->name));
    }

    /** Kode audit tidak kembar sesudah ada audit yang dihapus. */
    public function test_kode_audit_tidak_kembar_sesudah_penghapusan(): void
    {
        $c = $this->perusahaan();
        foreach (['AKL-2026-001', 'AKL-2026-002', 'AKL-2026-003'] as $k) {
            EnvAudit::withoutGlobalScopes()->create(['company_id' => $c->id, 'kode' => $k, 'tahun' => 2026, 'judul' => 'x']);
        }
        EnvAudit::withoutGlobalScopes()->where('kode', 'AKL-2026-002')->delete();

        $this->assertSame('AKL-2026-004', EnvAudit::kodeBaru(2026));
        $this->assertSame('AKL-2027-001', EnvAudit::kodeBaru(2027));
    }

    public function test_daftar_menandai_audit_bersertifikat(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);
        $this->audit($c, 3, [$this->kodePertama('b') => null]);
        $this->terbitkan($a);

        $this->get(route('audit-lingkungan.index', ['tahun' => 2026]))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('daftar', 2)
                ->where('daftar', fn ($d) => collect($d)->whereNotNull('sertifikat')->count() === 1
                    && collect($d)->every(fn ($r) => count($r['bagianPersen']) === 6)));
    }
}
