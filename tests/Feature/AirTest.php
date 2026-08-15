<?php

namespace Tests\Feature;

use App\Models\{Company, KoObject, TindakLanjut, User, WaterLog, WaterSump};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengelolaan Air dan Penirisan.
 *
 * Yang paling penting diuji adalah perkiraan luapannya: satu perubahan
 * satuan yang salah meleset sepuluh kali lipat tanpa menimbulkan galat.
 */
class AirTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $pengawas;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 08:00:00');

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->pengawas = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function kolam(array $ganti = []): WaterSump
    {
        return WaterSump::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'SUMP-01', 'nama' => 'Sump Pit A',
            'jenis' => 'sump', 'kapasitas_m3' => 10_000, 'luas_tangkapan_ha' => 20,
            'koefisien_limpasan' => 0.8, 'status' => 'aktif',
        ], $ganti));
    }

    private function catat(WaterSump $s, array $ganti = [], bool $setujui = false): WaterLog
    {
        $log = WaterLog::create(array_merge([
            'company_id' => $this->company->id, 'water_sump_id' => $s->id,
            'tanggal' => '2026-08-10', 'curah_hujan_mm' => 20, 'volume_m3' => 4000,
            'debit_masuk_m3' => 500, 'debit_keluar_m3' => 800, 'jam_pompa' => 4, 'energi_kwh' => 200,
        ], $ganti));

        if ($setujui) { $log->ajukan($this->pengawas); $log->setujui($this->ktt); }

        return $log;
    }

    private function props(string $rute = 'air.index'): array
    {
        return $this->actingAs($this->pengawas)
            ->get(route($rute, ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()->viewData('page')['props'];
    }

    /* ---------- perkiraan luapan ---------- */

    public function test_daya_tampung_dinyatakan_dalam_milimeter_hujan(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['volume_m3' => 4000]);

        $k = collect($this->props()['kolam'])->firstWhere('kode', 'SUMP-01');

        // Ruang 6.000 m³ ÷ (20 ha × 10 × 0,8) = 37,5 mm
        $this->assertSame(37.5, $k['hujanTertampung'],
            'Inilah satu-satunya angka yang dapat langsung dibandingkan dengan ramalan cuaca.');
    }

    public function test_kolam_hampir_penuh_memunculkan_peringatan(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['volume_m3' => 9000]);

        $kode = collect($this->props()['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('kolam-hampir-penuh-'.$s->id));
    }

    public function test_simulasi_memakai_pompa_yang_siap_bukan_yang_terpasang(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['volume_m3' => 4000]);

        $s->pumps()->create(['nama' => 'P1', 'kapasitas_m3_jam' => 500, 'status' => 'siap']);
        $s->pumps()->create(['nama' => 'P2', 'kapasitas_m3_jam' => 500, 'status' => 'rusak']);

        $k = collect($this->props()['kolam'])->firstWhere('kode', 'SUMP-01');

        $this->assertSame(500.0, $k['pompaSiap']);
        $this->assertSame(1000.0, $k['pompaTerpasang'],
            'Kolam yang separuh pompanya rusak tidak boleh tampak aman.');
    }

    public function test_pompa_rusak_memunculkan_peringatan_ke_modul_pemeliharaan(): void
    {
        $s = $this->kolam();
        $s->pumps()->create(['nama' => 'P1', 'kapasitas_m3_jam' => 500, 'status' => 'rusak']);

        $a = collect($this->props()['alerts'])->firstWhere('kode', 'pompa-rusak-'.$s->id);

        $this->assertNotNull($a);
        $this->assertStringContainsString('Pemeliharaan', $a['saran']);
    }

    /* ---------- pompa memakai registri KO ---------- */

    public function test_pompa_dapat_menunjuk_alat_registri_keselamatan_operasi(): void
    {
        $s = $this->kolam();
        $alat = KoObject::create([
            'company_id' => $this->company->id, 'kode' => 'PMP-01', 'nama' => 'Pompa Multiflo',
            'kategori' => 'Pompa', 'kritikalitas' => 'Tinggi', 'status_operasi' => 'Beroperasi',
        ]);

        $this->actingAs($this->pengawas)->post(route('air.pompa.simpan', $s), [
            'ko_object_id' => $alat->id, 'kapasitas_m3_jam' => 600, 'status' => 'siap',
        ])->assertSessionHasNoErrors();

        $this->assertSame('PMP-01', $s->pumps()->first()->label());
    }

    public function test_pompa_tanpa_alat_maupun_nama_ditolak(): void
    {
        $s = $this->kolam();

        $this->actingAs($this->pengawas)->post(route('air.pompa.simpan', $s), [
            'kapasitas_m3_jam' => 600, 'status' => 'siap',
        ])->assertSessionHasErrors('nama');
    }

    /* ---------- alur tinjauan ---------- */

    public function test_catatan_lahir_sebagai_draf_dan_belum_terhitung(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['debit_keluar_m3' => 800]);

        $props = $this->props();

        $this->assertSame(0.0, (float) $props['ringkas']['keluar'], 'Draf belum masuk hitungan.');
        $this->assertCount(1, $props['catatan'], 'Tetapi tetap terlihat oleh pengajunya.');
    }

    public function test_setelah_disetujui_baru_terhitung(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['debit_keluar_m3' => 800], setujui: true);

        $this->assertSame(800.0, (float) $this->props()['ringkas']['keluar']);
    }

    public function test_satu_kolam_satu_catatan_per_hari(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['tanggal' => '2026-08-12']);

        $this->actingAs($this->pengawas)->post(route('air.catatan.simpan'), [
            'water_sump_id' => $s->id, 'tanggal' => '2026-08-12',
            'curah_hujan_mm' => 10, 'volume_m3' => 100, 'debit_masuk_m3' => 0,
            'debit_keluar_m3' => 0, 'jam_pompa' => 0,
        ])->assertSessionHasErrors('tanggal');

        $this->assertSame(1, WaterLog::count(),
            'Catatan ganda akan menggandakan debit dan curah hujan pada seluruh hitungan.');
    }

    /* ---------- kualitas air ---------- */

    public function test_sampel_boleh_dikosongkan_pada_hari_tanpa_pengambilan(): void
    {
        $s = $this->kolam();

        $this->actingAs($this->pengawas)->post(route('air.catatan.simpan'), [
            'water_sump_id' => $s->id, 'tanggal' => '2026-08-15',
            'curah_hujan_mm' => 5, 'volume_m3' => 100, 'debit_masuk_m3' => 0,
            'debit_keluar_m3' => 0, 'jam_pompa' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertFalse(WaterLog::firstOrFail()->adaSampelAir());
    }

    public function test_tss_melampaui_baku_mutu_memunculkan_peringatan(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['tss_mgl' => 900], setujui: true);

        $this->assertTrue(collect($this->props()['alerts'])->pluck('kode')->contains('tss-melampaui'));
    }

    public function test_debit_per_jam_memakai_jam_pompa_bukan_jam_kalender(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['debit_keluar_m3' => 800, 'jam_pompa' => 4], setujui: true);

        $this->assertSame(200.0, (float) $this->props()['ringkas']['debitPerJam'],
            'Pompa yang jarang dinyalakan tidak boleh terlihat lemah.');
    }

    /* ---------- tindak lanjut & laporan ---------- */

    public function test_tindak_lanjut_memakai_tabel_bersama(): void
    {
        $this->actingAs($this->pengawas)->post(route('air.tindak.simpan'), [
            'judul' => 'Keruk sediment pond', 'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $this->assertSame('air', TindakLanjut::firstOrFail()->modul);
    }

    public function test_laporan_membawa_kop_dan_hanya_memuat_yang_disetujui(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['tanggal' => '2026-08-05', 'debit_keluar_m3' => 800], setujui: true);
        $this->catat($s, ['tanggal' => '2026-08-06', 'debit_keluar_m3' => 9999]);   // draf

        $this->actingAs($this->pengawas)->get(route('air.cetak', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Air')
                ->where('dok.judul', 'LAPORAN PENGELOLAAN AIR DAN PENIRISAN TAMBANG')
                ->where('dasar.disetujui', 1)
                ->where('dasar.belumDitinjau', 1)
                ->where('ringkas.keluar', 800)
                ->has('catatan', 1));
    }

    public function test_setiap_peringatan_menyebutkan_tindakan(): void
    {
        $s = $this->kolam();
        $this->catat($s, ['volume_m3' => 9500]);

        foreach ($this->props()['alerts'] as $a) {
            $this->assertNotEmpty($a['saran'] ?? null, "Peringatan '{$a['kode']}' tanpa saran.");
        }
    }

    public function test_tidak_terlihat_oleh_perusahaan_lain(): void
    {
        $s = $this->kolam();
        $this->catat($s, [], setujui: true);

        $lain = User::factory()->create(['company_id' => Company::create(['name' => 'Lain'])->id]);
        $props = $this->actingAs($lain)->get(route('air.index'))->assertOk()->viewData('page')['props'];

        $this->assertCount(0, $props['catatan']);
        $this->assertCount(0, $props['kolam']);
    }
}
