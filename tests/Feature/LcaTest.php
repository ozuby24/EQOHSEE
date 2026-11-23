<?php

namespace Tests\Feature;

use App\Models\{Company, EnergyEquipment, EnergyFuelLog, EnergyPowerLog, EnergyProduction, User};
use App\Models\Lca\{Faktor, Hasil, Inventori, Jejak, Studi};
use App\Support\Lca\{Katalog, Mesin, Pustaka};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Life Cycle Assessment.
 *
 * Mesin LCIA (konversi, t·km, alokasi, daur ulang, DQR tertimbang),
 * impor dari Energy Performance, simulasi skenario, alur verifikasi
 * yang mengunci studi, versi, dan batas perusahaan.
 */
class LcaTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $u;
    private User $peninjau;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 08:00:00');
        Pustaka::pasang();
        $this->c = Company::create(['name' => 'Tambang Uji']);
        $this->u = User::factory()->create(['company_id' => $this->c->id]);
        $this->peninjau = User::factory()->create(['company_id' => $this->c->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function studi(array $ganti = []): Studi
    {
        $this->actingAs($this->u)->post('/lca/studi', array_merge([
            'judul' => 'Batubara FOB', 'produk' => 'Batubara GAR 4.200', 'fasilitas' => 'Pit A', 'wilayah' => 'ID-KAL',
            'fu_nilai' => 1, 'fu_label' => 'ton batubara', 'aliran_ref_kg' => 1000, 'batas_sistem' => 'cradle_to_gate',
            'metode' => 'IPCC_2021_GWP100', 'alokasi' => 'massa', 'cutoff_pct' => 1, 'periode_mulai' => '2025-01-01',
            'periode_selesai' => '2025-12-31', 'volume_tahunan' => 1000000, 'tujuan' => 'Deklarasi pembeli',
        ], $ganti))->assertRedirect();

        return Studi::latest('id')->firstOrFail();
    }

    private function f(string $kode): Faktor { return Faktor::withoutGlobalScopes()->where('kode', $kode)->firstOrFail(); }

    private function baris(Studi $s, string $kode, float $jumlah, string $satuan, string $tahap, array $lain = []): void
    {
        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/inventori", array_merge([
            'faktor_id' => $this->f($kode)->id, 'tahap' => $tahap, 'jumlah' => $jumlah, 'satuan' => $satuan,
            'p_reliabilitas' => 1, 'p_kelengkapan' => 2, 'p_temporal' => 1, 'p_geografis' => 2, 'p_teknologi' => 2,
        ], $lain))->assertRedirect();
    }

    public function test_pratinjau_mengonversi_satuan_dan_menghitung_tkm(): void
    {
        $solar = $this->f('EF-SOLAR-B40');
        $h = Mesin::pratinjau($solar, ['jumlah' => 2, 'satuan' => 'kL', 'p_reliabilitas' => 1, 'p_kelengkapan' => 1, 'p_temporal' => 1, 'p_geografis' => 1, 'p_teknologi' => 1]);
        $this->assertEqualsWithDelta(2000 * 2.21, $h['gwp'], 0.001);
        $this->assertSame(1.0, $h['dqr']);

        $tongkang = $this->f('EF-TONGKANG');
        $t = Mesin::pratinjau($tongkang, ['jumlah' => 1000, 'satuan' => 'kg', 'jarak_km' => 280]);
        $this->assertEqualsWithDelta(280.0, $t['jumlah_normal'], 1e-6);          // 1 t × 280 km
        $this->assertEqualsWithDelta(280 * 0.028, $t['gwp'], 1e-6);
    }

    public function test_alokasi_dan_daur_ulang_mengurangi_dampak(): void
    {
        $baja = $this->f('EF-BAJA');
        $penuh = Mesin::pratinjau($baja, ['jumlah' => 100, 'satuan' => 'kg']);
        $kurang = Mesin::pratinjau($baja, ['jumlah' => 100, 'satuan' => 'kg', 'alokasi_pct' => 50, 'daur_ulang_pct' => 40]);
        $this->assertEqualsWithDelta($penuh['gwp'] * 0.5 * (1 - 0.4 * 0.65), $kurang['gwp'], 1e-6);
    }

    public function test_total_studi_dan_dqr_tertimbang(): void
    {
        $s = $this->studi();
        $this->baris($s, 'EF-SOLAR-B40', 2, 'L', 'ekstraksi');
        $this->baris($s, 'EF-EL-KAL', 3, 'kWh', 'olah', ['p_reliabilitas' => 4, 'p_kelengkapan' => 4, 'p_temporal' => 4, 'p_geografis' => 4, 'p_teknologi' => 4]);
        $s->refresh();

        $g1 = 2 * 2.21; $g2 = 3 * 1.02;
        $this->assertEqualsWithDelta($g1 + $g2, $s->total_gwp, 1e-6);
        $this->assertEqualsWithDelta((1.6 * $g1 + 4 * $g2) / ($g1 + $g2), $s->dqr, 0.01);
        $this->assertEqualsWithDelta($g1, Hasil::where('studi_id', $s->id)->where('kategori', 'gwp')->where('tahap', 'ekstraksi')->value('nilai'), 1e-6);
        $this->assertSame(count(Katalog::DAMPAK) * count(Katalog::TAHAP), Hasil::where('studi_id', $s->id)->count());
    }

    public function test_impor_energi_membagi_aktivitas_dengan_produksi(): void
    {
        $s = $this->studi();
        $alat = EnergyEquipment::create(['company_id' => $this->c->id, 'kode' => 'HD-1', 'nama' => 'HD785', 'kategori' => 'hauling', 'aktif' => true]);
        EnergyFuelLog::withoutGlobalScopes()->create(['equipment_id' => $alat->id, 'tanggal' => '2025-03-01', 'liter' => 20000, 'status' => 'terverifikasi']);
        EnergyPowerLog::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'tanggal' => '2025-03-01', 'area' => 'crusher', 'sumber' => 'pln', 'kwh' => 50000, 'status' => 'terverifikasi']);
        EnergyProduction::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'tanggal' => '2025-03-01', 'ton' => 10000, 'status' => 'terverifikasi']);

        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/impor-energi")->assertRedirect();
        $hauling = Inventori::where('studi_id', $s->id)->where('tahap', 'angkut')->first();
        $this->assertEqualsWithDelta(2.0, $hauling->jumlah, 1e-6);            // 20.000 L ÷ 10.000 t
        $this->assertSame('energi', $hauling->sumber_data);
        $this->assertEqualsWithDelta(5.0, Inventori::where('studi_id', $s->id)->where('satuan', 'kWh')->value('jumlah'), 1e-6);

        // Impor ulang mengganti, tidak menumpuk.
        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/impor-energi");
        $this->assertSame(2, Inventori::where('studi_id', $s->id)->where('sumber_data', 'energi')->count());
    }

    public function test_simulasi_plts_menurunkan_hanya_baris_listrik(): void
    {
        $s = $this->studi();
        $this->baris($s, 'EF-SOLAR-B40', 2, 'L', 'ekstraksi');
        $this->baris($s, 'EF-EL-KAL', 10, 'kWh', 'olah');

        $r = $this->actingAs($this->u)->postJson("/lca/studi/{$s->id}/simulasi", ['ebt' => 100])->assertOk()->json();
        $this->assertEqualsWithDelta(2 * 2.21 + 10 * 0.045, $r['proyeksi'], 1e-4);
        $this->assertEqualsWithDelta(10 * (1.02 - 0.045), $r['tuas']['ebt'], 1e-4);
    }

    public function test_verifikasi_mengunci_studi_dan_pembuat_tidak_boleh_memverifikasi(): void
    {
        $s = $this->studi();
        foreach ([['EF-SOLAR-B40', 2, 'L', 'ekstraksi'], ['EF-TRUK-JALAN', 60, 'tkm', 'angkut'], ['EF-EL-KAL', 3, 'kWh', 'olah']] as $b) $this->baris($s, ...$b);
        $this->assertTrue(Mesin::siap($s->fresh()));

        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/status", ['aksi' => 'ajukan'])->assertRedirect();
        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/status", ['aksi' => 'verifikasi']);
        $this->assertSame('tinjau', $s->fresh()->status, 'Pembuat studi tidak boleh memverifikasi sendiri.');

        $this->actingAs($this->peninjau)->post("/lca/studi/{$s->id}/status", ['aksi' => 'verifikasi', 'catatan' => 'OK'])->assertRedirect();
        $this->assertSame('terverifikasi', $s->fresh()->status);

        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/inventori", ['faktor_id' => $this->f('EF-AIR')->id, 'tahap' => 'olah', 'jumlah' => 1,
            'satuan' => 'm3', 'p_reliabilitas' => 1, 'p_kelengkapan' => 1, 'p_temporal' => 1, 'p_geografis' => 1, 'p_teknologi' => 1])->assertForbidden();
        $this->assertGreaterThan(0, Jejak::where('studi_id', $s->id)->where('aksi', 'verifikasi')->count());
    }

    public function test_studi_belum_lengkap_tidak_dapat_diajukan(): void
    {
        $s = $this->studi();
        $this->baris($s, 'EF-SOLAR-B40', 2, 'L', 'ekstraksi');
        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/status", ['aksi' => 'ajukan'])->assertSessionHas('galat');
        $this->assertSame('draf', $s->fresh()->status);
    }

    public function test_versi_baru_menyalin_inventori_dan_menunjuk_induk(): void
    {
        $s = $this->studi();
        $this->baris($s, 'EF-SOLAR-B40', 2, 'L', 'ekstraksi');
        $this->actingAs($this->u)->post("/lca/studi/{$s->id}/versi")->assertRedirect();

        $v = Studi::latest('id')->first();
        $this->assertSame('1.1', $v->versi);
        $this->assertSame($s->id, $v->induk_id);
        $this->assertEqualsWithDelta($s->fresh()->total_gwp, $v->total_gwp, 1e-6);
    }

    public function test_perusahaan_lain_tidak_dapat_membuka_studi_dan_faktornya(): void
    {
        $s = $this->studi();
        $lain = Company::create(['name' => 'Lain']);
        $orangLain = User::factory()->create(['company_id' => $lain->id]);
        $khusus = Faktor::create(['company_id' => $this->c->id, 'kode' => 'EF-KHUSUS', 'nama' => 'Khusus', 'kategori' => 'energi',
            'tahap_bawaan' => 'olah', 'scope' => 1, 'satuan' => 'kg', 'gwp' => 1, 'sumber' => 'uji']);

        $this->actingAs($orangLain)->get("/lca/studi/{$s->id}")->assertNotFound();
        $this->actingAs($orangLain)->get('/lca/faktor')->assertInertia(fn (Assert $p) => $p
            ->where('faktor', fn ($f) => collect($f)->where('kode', 'EF-KHUSUS')->isEmpty() && collect($f)->where('kode', 'EF-SOLAR-B40')->isNotEmpty()));
    }

    public function test_pustaka_tidak_menimpa_faktor_yang_sudah_dicocokkan(): void
    {
        $f = $this->f('EF-EL-KAL');
        $f->forceFill(['gwp' => 0.95, 'perlu_cocok' => false])->save();
        Pustaka::pasang();
        $this->assertSame(0.95, $f->fresh()->gwp);
    }

    public function test_halaman_modul_terbuka(): void
    {
        $s = $this->studi();
        $this->baris($s, 'EF-SOLAR-B40', 2, 'L', 'ekstraksi');
        foreach (['/lca', '/lca/studi', '/lca/faktor', "/lca/studi/{$s->id}"] as $url) {
            $this->actingAs($this->u)->get($url)->assertOk();
        }
        $this->actingAs($this->u)->get("/lca/studi/{$s->id}/ekspor/csv")->assertOk();
    }
}
