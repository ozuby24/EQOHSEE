<?php

namespace Tests\Feature;

use App\Models\Miners\{Induksi, Kompetensi, Mcu, Pekerja};
use App\Models\{SmkpAudit, SmkpFinding};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Kotak cari SMKP dan Miners tidak peduli besar-kecil huruf.
 *
 * `like` di SQLite dan MySQL (kolasi *_ci) memang tidak peka huruf, tetapi
 * di PostgreSQL peka — "tambang" tidak menemukan "Audit Tambang Utara".
 * Di SQLite uji ini selalu lolos; ia berguna saat suite dijalankan pada
 * PostgreSQL (lihat App\Support\Db::like()).
 */
class PencarianTanpaPekaHurufTest extends TestCase
{
    use RefreshDatabase, SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    public function test_daftar_audit_dan_tindak_lanjut_smkp(): void
    {
        $a = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->iup->id, 'tahun' => 2026, 'judul' => 'Audit Tambang Utara', 'ketua_auditor' => 'Ani Lestari',
            'status' => 'berjalan', 'tahap' => 2, 'hasil' => [],
        ]);
        SmkpFinding::create(['audit_id' => $a->id, 'kode_kriteria' => 'II.2', 'jenis' => 'minor', 'uraian' => 'Rambu Jalan Hauling Rusak', 'status' => 'Open']);

        foreach (['tambang UTARA', 'ani LESTARI', 'pt tambang uji'] as $q) {
            $this->actingAs($this->admin)->get('/smkp?q='.urlencode($q))->assertOk()
                ->assertInertia(fn (Inertia $p) => $p->where('audits.data.0.id', $a->id)->has('audits.data', 1));
        }

        $this->get('/smkp/tindak-lanjut?status=semua&q='.urlencode('rambu jalan'))->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->has('daftar.data', 1));
    }

    public function test_riwayat_mcu_induksi_dan_kompetensi_miners(): void
    {
        $kini = Waktu::kini()->startOfDay();
        Mcu::withoutGlobalScopes()->create(['company_id' => $this->mitra->id, 'tanggal' => $kini, 'kepada' => 'Klinik Sehat',
            'perihal' => 'Permohonan Sertifikat MCU', 'status' => 'selesai', 'user_id' => $this->pengaju->id]);
        Induksi::withoutGlobalScopes()->create(['company_id' => $this->mitra->id, 'tanggal' => $kini, 'perihal' => 'Induksi Karyawan Baru',
            'status' => 'selesai', 'user_id' => $this->pengaju->id, 'iup_id' => $this->iup->id]);
        $pk = Pekerja::withoutGlobalScopes()->create(['company_id' => $this->mitra->id, 'nama' => 'Budi Santoso', 'nik' => '6401000000000077', 'status' => 'aktif']);
        Kompetensi::create(['company_id' => $this->mitra->id, 'pekerja_id' => $pk->id, 'nama' => 'Operator Dump Truck']);

        $this->actingAs($this->admin)->get('/miners/riwayat/mcu?cari='.urlencode('permohonan SERTIFIKAT'))->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->has('baris.data', 1));
        $this->get('/miners/riwayat/induksi?cari='.urlencode('induksi KARYAWAN'))->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->has('baris.data', 1));
        $this->get('/miners/riwayat/authority?cari='.urlencode('operator DUMP'))->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->has('baris', 1));
        $this->get('/miners/riwayat/authority?cari='.urlencode('budi SANTOSO'))->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->has('baris', 1));

        $this->assertSame([$pk->id], Pekerja::withoutGlobalScopes()->cari('BUDI santoso')->pluck('id')->all());
    }
}
