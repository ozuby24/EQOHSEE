<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, User};
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmkpAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_daftar_audit_dapat_dibuka(): void
    {
        $this->actingAs($this->admin())
            ->get(route('smkp.index'))
            ->assertOk()
            ->assertSee('Audit Sistem Manajemen Keselamatan Pertambangan');
    }

    public function test_periode_audit_dapat_dibuat(): void
    {
        $company = Company::create(['name' => 'PT Uji Tambang']);

        $this->actingAs($this->admin())->post(route('smkp.store'), [
            'company_id' => $company->id,
            'tahun'      => 2026,
            'judul'      => 'Audit Internal SMKP 2026',
            'status'     => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('smkp_audits', [
            'tahun' => 2026, 'judul' => 'Audit Internal SMKP 2026',
        ]);
    }

    public function test_periode_ganda_untuk_perusahaan_sama_ditolak(): void
    {
        $company = Company::create(['name' => 'PT Uji Tambang']);
        SmkpAudit::create(['company_id' => $company->id, 'tahun' => 2026, 'status' => 'draft']);

        $this->actingAs($this->admin())->post(route('smkp.store'), [
            'company_id' => $company->id,
            'tahun'      => 2026,
            'status'     => 'draft',
        ])->assertSessionHasErrors('tahun');
    }

    public function test_menyimpan_penilaian_satu_elemen_tidak_menyentuh_elemen_lain(): void
    {
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'draft', 'hasil' => [
            'VII.1' => ['v' => 4, 'ket' => '', 'bukti' => ''],
        ]]);

        $this->actingAs($this->admin())->post(route('smkp.nilai.simpan', [$audit, 'I']), [
            'k' => [
                'I.1' => ['v' => 1, 'ket' => 'Belum melibatkan pekerja', 'bukti' => 'Notulen rapat'],
                'I.2' => ['v' => 4],
            ],
        ])->assertRedirect();

        $audit->refresh();

        $this->assertSame(1.0, $audit->nilai('I.1'));
        $this->assertSame('Belum melibatkan pekerja', $audit->ket('I.1'));
        $this->assertSame(4.0, $audit->nilai('I.2'));

        // Elemen VII yang tidak dikirim harus tetap utuh.
        $this->assertSame(4.0, $audit->nilai('VII.1'));

        // Status draft naik jadi berjalan setelah penilaian pertama.
        $this->assertSame('berjalan', $audit->status);
    }

    public function test_nilai_asing_diabaikan(): void
    {
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'draft', 'hasil' => []]);

        $this->actingAs($this->admin())->post(route('smkp.nilai.simpan', [$audit, 'I']), [
            'k' => ['I.1' => ['v' => 'sangat-sesuai-sekali']],
        ]);

        $this->assertNull($audit->refresh()->nilai('I.1'));
    }

    public function test_nilai_melebihi_maksimum_dijepit(): void
    {
        // Formulir yang dikirim langsung tidak boleh menaikkan capaian
        // melebihi nilai maksimum butirnya. I.1 bernilai maksimum 4.
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'draft', 'hasil' => []]);

        $this->actingAs($this->admin())->post(route('smkp.nilai.simpan', [$audit, 'I']), [
            'k' => ['I.1' => ['v' => 999], 'I.2' => ['v' => -3]],
        ]);

        $audit->refresh();
        $this->assertSame(4.0, $audit->nilai('I.1'));
        $this->assertSame(0.0, $audit->nilai('I.2'));
    }

    public function test_butir_dapat_ditandai_tidak_berlaku(): void
    {
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'draft', 'hasil' => []]);

        // III.2.2 Kepala Tambang Bawah Tanah tidak berlaku bagi tambang terbuka.
        $this->actingAs($this->admin())->post(route('smkp.nilai.simpan', [$audit, 'III']), [
            'k' => ['III.2.2' => ['v' => 'N/A']],
        ]);

        $this->assertSame('N/A', $audit->refresh()->nilai('III.2.2'));
    }

    public function test_ketidaksesuaian_dapat_diangkat_jadi_tindakan_perbaikan(): void
    {
        // I.1 maks 4 -> nilai 1 = 25% (Mayor); I.2 maks 4 -> 2 = 50% (Minor);
        // I.3 maks 3 -> 3 = 100% (Kesesuaian, bukan temuan).
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'berjalan', 'hasil' => [
            'I.1' => ['v' => 1, 'ket' => 'Tidak ada bukti', 'bukti' => ''],
            'I.2' => ['v' => 2, 'ket' => '', 'bukti' => ''],
            'I.3' => ['v' => 3, 'ket' => '', 'bukti' => ''],
        ]]);

        $this->actingAs($this->admin())
            ->post(route('smkp.temuan.angkat', $audit))
            ->assertRedirect();

        // Hanya mayor + minor yang jadi temuan, bukan yang sesuai.
        $this->assertSame(2, $audit->findings()->count());
        $this->assertDatabaseHas('smkp_findings', ['kode_kriteria' => 'I.1', 'jenis' => 'mayor']);
        $this->assertDatabaseMissing('smkp_findings', ['kode_kriteria' => 'I.3']);

        // Diangkat dua kali tidak menggandakan.
        $this->actingAs($this->admin())->post(route('smkp.temuan.angkat', $audit));
        $this->assertSame(2, $audit->findings()->count());
    }

    public function test_menutup_temuan_mengisi_tanggal_selesai(): void
    {
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'berjalan', 'hasil' => []]);
        $t = $audit->findings()->create([
            'kode_kriteria' => 'I.1.1', 'jenis' => 'mayor',
            'uraian' => 'Uji', 'status' => 'Open',
        ]);

        $this->actingAs($this->admin())->put(route('smkp.temuan.simpan', [$audit, $t]), [
            'status'   => 'Closed',
            'tindakan' => 'Sudah diperbaiki',
        ])->assertRedirect();

        $t->refresh();
        $this->assertSame('Closed', $t->status);
        $this->assertNotNull($t->tanggal_selesai);
    }

    public function test_semua_halaman_modul_dapat_dirender(): void
    {
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'berjalan', 'hasil' => [
            'I.1.1' => ['n' => 'mayor', 'ket' => 'catatan', 'bukti' => 'dokumen'],
            'I.2.1' => ['n' => 'sesuai', 'ket' => '', 'bukti' => ''],
            'IV.2.1'=> ['n' => 'na', 'ket' => '', 'bukti' => ''],
        ]]);
        $audit->findings()->create([
            'kode_kriteria' => 'I.1.1', 'jenis' => 'mayor',
            'uraian' => 'Uji temuan', 'status' => 'Open',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('smkp.show', $audit))->assertOk();
        $this->actingAs($admin)->get(route('smkp.temuan', $audit))->assertOk();
        $this->actingAs($admin)->get(route('smkp.laporan', $audit))->assertOk();
        $this->actingAs($admin)->get(route('smkp.edit', $audit))->assertOk();
        $this->actingAs($admin)->get(route('smkp.create'))->assertOk();

        // Seluruh tujuh elemen punya halaman penilaian yang dapat dibuka.
        foreach (Smkp::elemen() as $e) {
            $this->actingAs($admin)
                ->get(route('smkp.nilai', [$audit, $e['kode']]))
                ->assertOk()
                ->assertSee($e['nama']);
        }
    }

    public function test_elemen_tidak_dikenal_menghasilkan_404(): void
    {
        $audit = SmkpAudit::create(['tahun' => 2026, 'status' => 'draft', 'hasil' => []]);

        $this->actingAs($this->admin())
            ->get(route('smkp.nilai', [$audit, 'XYZ']))
            ->assertNotFound();
    }

    public function test_tamu_tidak_dapat_mengakses_modul(): void
    {
        $this->get(route('smkp.index'))->assertRedirect(route('login'));
    }
}
