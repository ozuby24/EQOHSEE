<?php

namespace Tests\Feature;

use App\Models\{Company, MineMapLayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layer peta yang terukur.
 *
 * Sebelumnya sebuah layer hanyalah gumpalan JSON: tidak ada yang dapat
 * menjawab berapa hektare pit-nya atau berapa yang sudah direklamasi,
 * padahal keduanya sudah terkandung di dalam koordinat yang tersimpan.
 */
class PetaTambangTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    /** Kotak 0,01° × 0,01° dekat khatulistiwa ≈ 123,5 ha. */
    private function kotak(float $lon = 110, float $lat = -2): string
    {
        return json_encode(['type' => 'Polygon', 'coordinates' => [[
            [$lon, $lat], [$lon + 0.01, $lat], [$lon + 0.01, $lat + 0.01], [$lon, $lat + 0.01], [$lon, $lat],
        ]]]);
    }

    private function layer(string $tipe, string $status = 'aktif', ?string $geo = null): MineMapLayer
    {
        return MineMapLayer::create([
            'company_id' => $this->company->id, 'nama' => ucfirst($tipe), 'tipe' => $tipe,
            'geojson' => $geo ?? $this->kotak(), 'warna' => '#84cc16', 'status' => $status,
        ]);
    }

    /* ---------- pengukuran otomatis ---------- */

    public function test_ukuran_dihitung_saat_disimpan(): void
    {
        $l = $this->layer('pit');

        $this->assertEqualsWithDelta(123.57, $l->hektare(), 0.5);
        $this->assertGreaterThan(4000, $l->keliling_m);
        $this->assertSame(1, $l->jumlah_fitur);
        $this->assertNotNull($l->titik_lon);
    }

    public function test_ukuran_dihitung_ulang_saat_geometrinya_berubah(): void
    {
        $l = $this->layer('pit');
        $awal = $l->luas_m2;

        // Kotak dua kali lebih lebar.
        $l->update(['geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [[
            [110, -2], [110.02, -2], [110.02, -1.99], [110, -1.99], [110, -2],
        ]]])]);

        $this->assertEqualsWithDelta($awal * 2, $l->fresh()->luas_m2, $awal * 0.01,
            'Ukuran yang tidak ikut berubah akan menempel pada bentuk yang sudah lain.');
    }

    public function test_ukuran_tidak_dapat_diisi_dari_payload(): void
    {
        $this->actingAs($this->user)->post(route('operasi.layer.simpan'), [
            'nama' => 'Pit palsu', 'tipe' => 'pit', 'geojson' => $this->kotak(),
            'warna' => '#84cc16', 'status' => 'aktif',
            'luas_m2' => 999_999_999,     // diabaikan
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(123.57, MineMapLayer::firstOrFail()->hektare(), 0.5,
            'Luas harus berasal dari koordinatnya, bukan dari isian.');
    }

    public function test_geojson_rusak_tidak_menggagalkan_penyimpanan_model(): void
    {
        $l = MineMapLayer::create([
            'company_id' => $this->company->id, 'nama' => 'Rusak', 'tipe' => 'pit',
            'geojson' => '{"type":"Point","coordinates":[110,-2]}', 'warna' => '#84cc16', 'status' => 'draft',
        ]);

        $this->assertSame(0.0, $l->luas_m2);
        $this->assertSame(1, $l->jumlah_fitur);
    }

    /* ---------- kemajuan area ---------- */

    private function kemajuan(): array
    {
        return $this->actingAs($this->user)->get(route('operasi.gis'))
            ->assertOk()->viewData('page')['props']['kemajuan'];
    }

    public function test_luas_terganggu_menjumlahkan_bukaan_tambang(): void
    {
        $this->layer('pit');
        $this->layer('disposal', 'aktif', $this->kotak(111, -2));

        $this->assertEqualsWithDelta(247.14, $this->kemajuan()['terganggu'], 1.0);
    }

    public function test_layer_draf_dan_arsip_tidak_ikut_dihitung(): void
    {
        $this->layer('pit');
        $this->layer('pit', 'draft', $this->kotak(111, -2));
        $this->layer('pit', 'arsip', $this->kotak(112, -2));

        $this->assertEqualsWithDelta(123.57, $this->kemajuan()['terganggu'], 0.5,
            'Survei lama yang diarsipkan tidak boleh terus menambah luas terganggu.');
    }

    public function test_capaian_reklamasi_dinyatakan_terhadap_luas_terganggu(): void
    {
        $this->layer('pit');                                      // 123,57 ha terganggu
        $this->layer('reklamasi', 'aktif', $this->kotak(111, -2)); // 123,57 ha direklamasi

        $k = $this->kemajuan();

        $this->assertEqualsWithDelta(100.0, $k['persen'], 1.0);
        $this->assertEqualsWithDelta(0.0, $k['sisa'], 1.0);
    }

    public function test_jalan_angkut_dilaporkan_sebagai_panjang_bukan_luas(): void
    {
        MineMapLayer::create([
            'company_id' => $this->company->id, 'nama' => 'Haul road utama', 'tipe' => 'haul_road',
            'geojson' => json_encode(['type' => 'LineString', 'coordinates' => [[110, -2], [110, -1.98]]]),
            'warna' => '#f59e0b', 'status' => 'aktif',
        ]);

        $k = $this->kemajuan();

        $this->assertEqualsWithDelta(2.22, $k['panjangJalanKm'], 0.05);
        $this->assertSame(0.0, $k['terganggu'], 'Jalan memanjang, bukan meluas.');
    }

    public function test_luas_dikelompokkan_per_tipe(): void
    {
        $this->layer('pit');
        $this->layer('disposal', 'aktif', $this->kotak(111, -2));

        $perTipe = collect($this->kemajuan()['perTipe']);

        $this->assertCount(2, $perTipe);
        $this->assertEqualsWithDelta(123.57, $perTipe->firstWhere('tipe', 'pit')['hektare'], 0.5);
    }

    /* ---------- muatan halaman ---------- */

    public function test_geojson_hanya_dikirim_pada_mode_peta(): void
    {
        $this->layer('pit');

        $gis = $this->actingAs($this->user)->get(route('operasi.gis'))
            ->viewData('page')['props']['layers'][0];
        $dasbor = $this->actingAs($this->user)->get(route('operasi.index'))
            ->viewData('page')['props']['layers'][0];

        $this->assertArrayHasKey('geojson', $gis);
        $this->assertArrayNotHasKey('geojson', $dasbor,
            'Koordinat tidak perlu ikut ke halaman yang tidak menggambar peta.');
        $this->assertArrayHasKey('hektare', $dasbor,
            'Tetapi luasnya perlu, dan itu sudah terhitung.');
    }

    public function test_tanggal_dan_sumber_survei_tersimpan(): void
    {
        $this->actingAs($this->user)->post(route('operasi.layer.simpan'), [
            'nama' => 'Pit A Agustus', 'tipe' => 'pit', 'geojson' => $this->kotak(),
            'warna' => '#84cc16', 'status' => 'aktif',
            'tanggal_survey' => '2026-08-10', 'sumber_survey' => 'Drone RTK',
        ])->assertSessionHasNoErrors();

        $l = MineMapLayer::firstOrFail();
        $this->assertSame('2026-08-10', $l->tanggal_survey->toDateString());
        $this->assertSame('Drone RTK', $l->sumber_survey);
    }
}
