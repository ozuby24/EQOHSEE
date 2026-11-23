<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Models\Perancah\{Inspeksi, Tag, Unit};
use App\Support\Perancah\{Kalkulator, Katalog, Kelayakan, Penanda};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Scaffolding Management.
 *
 * Yang paling dijaga: warna tag tidak dapat dibuat hijau kecuali kajian
 * LAYAK dan inspeksi lulus penuh, satu butir kritis gagal langsung
 * merah, dan tag yang lewat 7 hari terbaca kedaluwarsa walau penjadwal
 * belum berjalan. Lalu rumus kalkulatornya, batas perusahaan, dan
 * verifikasi QR tanpa masuk.
 */
class PerancahTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 08:00:00');
        $this->c = Company::create(['name' => 'Tambang Uji']);
        $this->u = User::factory()->create(['company_id' => $this->c->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function spek(array $ganti = []): array
    {
        return array_merge([
            'judul' => 'Penggantian liner chute', 'jenis' => 'ringlock', 'kelas_beban' => 'sedang', 'panjang_m' => 6, 'lebar_m' => 1.8,
            'tinggi_m' => 8, 'jumlah_dek' => 1, 'zona' => 'CHPP & Crusher', 'jenis_tanah' => 'beton', 'jumlah_ikatan' => 4,
            'angin_kmh' => 20, 'ada_guardrail' => true, 'ada_toeboard' => true, 'ada_outrigger' => false, 'lat' => -2.9, 'lng' => 115.4,
        ], $ganti);
    }

    private function daftarkan(array $ganti = []): Unit
    {
        $this->actingAs($this->u)->post('/perancah', $this->spek($ganti))->assertRedirect();

        return Unit::latest('id')->firstOrFail();
    }

    private function periksa(string $semua = 'lulus', array $ganti = []): array
    {
        $isian = [];
        foreach (Katalog::PERIKSA as $b) $isian[$b['kode']] = $b['kode'] === 'INS-04' ? 'na' : $semua;
        return array_merge($isian, $ganti);
    }

    public function test_kalkulator_mengikuti_rumus_fsd(): void
    {
        $h = Kalkulator::hitung(['jenis' => 'ringlock', 'kelas_beban' => 'sedang', 'panjang_m' => 8, 'lebar_m' => 2, 'tinggi_m' => 7, 'jumlah_dek' => 2]);

        $this->assertSame(4, $h['bay']);              // ceil(8 / 2,0)
        $this->assertSame(4, $h['lift']);             // ceil(7 / 2,0)
        $this->assertSame(10, $h['standard']);        // (4+1) × 2
        $this->assertSame(40, $h['ledger']);          // 4 × 2 × (4+1)
        $this->assertSame(41, $h['transom']);         // 5×5 + 4×2×2
        $this->assertSame(16, $h['bracing']);         // ceil(4/2) × 4 × 2
        $this->assertSame(64, $h['plank']);           // ceil(2/0,25) × 4 × 2
        $this->assertSame(5760.0, $h['beban_hidup_kg']); // 16 m² × 240 × 1,5
        $this->assertEqualsWithDelta(52 / $h['beban_kaki_kn'], $h['faktor_keamanan'], 0.01);
        $this->assertEqualsWithDelta($h['beban_kaki_kn'] / 0.12, $h['tekanan_tanah_kn_m2'], 0.01);
    }

    public function test_kajian_menolak_ikatan_kurang_dan_tanah_lunak(): void
    {
        $s = ['jenis' => 'ringlock', 'kelas_beban' => 'sedang', 'panjang_m' => 12, 'lebar_m' => 1.5, 'tinggi_m' => 14, 'jumlah_dek' => 2,
              'jenis_tanah' => 'padat', 'jumlah_ikatan' => 6, 'angin_kmh' => 20, 'ada_guardrail' => true, 'ada_toeboard' => true];
        $k = Kelayakan::nilai($s, Kalkulator::hitung($s));
        $this->assertSame('tidak_layak', $k['putusan']);
        $this->assertSame('gagal', collect($k['kriteria'])->firstWhere('kode', 'K-02')['status']);

        $s2 = ['jenis' => 'tube_coupler', 'kelas_beban' => 'berat', 'panjang_m' => 6, 'lebar_m' => 1.5, 'tinggi_m' => 5.5, 'jumlah_dek' => 2,
               'jenis_tanah' => 'lunak', 'jumlah_ikatan' => 2, 'angin_kmh' => 20, 'ada_guardrail' => true, 'ada_toeboard' => true];
        $this->assertSame('gagal', collect(Kelayakan::nilai($s2, Kalkulator::hitung($s2))['kriteria'])->firstWhere('kode', 'K-03')['status']);
    }

    public function test_tinggi_di_atas_15_meter_bersyarat_dan_wajib_pe(): void
    {
        $s = $this->spek(['tinggi_m' => 16.5, 'jumlah_ikatan' => 10]);
        $k = Kelayakan::nilai($s, Kalkulator::hitung($s));

        $this->assertSame('bersyarat', $k['putusan']);
        $this->assertTrue($k['perlu_pe']);
    }

    public function test_pendaftaran_menerbitkan_tag_merah_sampai_inspeksi_awal(): void
    {
        $u = $this->daftarkan();

        $this->assertSame('merah', $u->status_tag);
        $this->assertStringContainsString('Belum diinspeksi', Tag::where('unit_id', $u->id)->where('aktif', true)->first()->alasan_tolak);
        $this->assertNotNull($u->hitung);
        $this->assertSame('layak', $u->kajianTerakhir->putusan);
    }

    public function test_inspeksi_lulus_penuh_menerbitkan_hijau_tujuh_hari(): void
    {
        $u = $this->daftarkan();
        $this->actingAs($this->u)->post("/perancah/{$u->id}/inspeksi", [
            'jenis' => 'awal', 'inspektur' => 'Agus', 'inspektur_sertifikat' => 'SIO-1', 'periksa' => $this->periksa(),
        ])->assertRedirect();

        $u->refresh();
        $this->assertSame('hijau', $u->status_tag);
        $this->assertTrue($u->inspeksi_berikut->equalTo(now()->addDays(7)));
        $this->assertSame(1, Tag::where('unit_id', $u->id)->where('aktif', true)->count(), 'Tag lama harus dinonaktifkan.');
        $this->assertSame(2, Tag::where('unit_id', $u->id)->count(), 'Tag lama disimpan, tidak dihapus.');
    }

    public function test_satu_butir_kritis_gagal_langsung_merah(): void
    {
        $u = $this->daftarkan();
        $this->actingAs($this->u)->post("/perancah/{$u->id}/inspeksi", [
            'jenis' => 'awal', 'inspektur' => 'Agus', 'inspektur_sertifikat' => 'SIO-1',
            'periksa' => $this->periksa('lulus', ['INS-09' => 'gagal']), 'tindakan_perbaikan' => 'Rapatkan plank.',
        ])->assertRedirect();

        $this->assertSame('merah', $u->fresh()->status_tag);
        $this->assertSame(1, Inspeksi::where('unit_id', $u->id)->first()->gagal_kritis);
    }

    public function test_butir_minor_gagal_atau_syarat_membuat_kuning(): void
    {
        $u = $this->daftarkan();
        $this->actingAs($this->u)->post("/perancah/{$u->id}/inspeksi", [
            'jenis' => 'awal', 'inspektur' => 'Agus', 'inspektur_sertifikat' => 'SIO-1',
            'periksa' => $this->periksa('lulus', ['INS-12' => 'gagal']), 'tindakan_perbaikan' => 'Bersihkan dek.',
        ]);
        $this->assertSame('kuning', $u->fresh()->status_tag);
    }

    public function test_kajian_tidak_layak_tetap_merah_walau_inspeksi_lulus(): void
    {
        $u = $this->daftarkan(['panjang_m' => 12, 'lebar_m' => 1.5, 'tinggi_m' => 14, 'jumlah_ikatan' => 2, 'jenis_tanah' => 'padat']);
        $this->actingAs($this->u)->post("/perancah/{$u->id}/inspeksi", [
            'jenis' => 'awal', 'inspektur' => 'Agus', 'inspektur_sertifikat' => 'SIO-1', 'periksa' => $this->periksa(),
        ]);
        $this->assertSame('merah', $u->fresh()->status_tag);
    }

    public function test_inspeksi_wajib_menilai_seluruh_butir_dan_menulis_perbaikan(): void
    {
        $u = $this->daftarkan();
        $isian = $this->periksa(); unset($isian['INS-16']);
        $this->actingAs($this->u)->post("/perancah/{$u->id}/inspeksi", [
            'jenis' => 'awal', 'inspektur' => 'A', 'inspektur_sertifikat' => 'S', 'periksa' => $isian,
        ])->assertSessionHasErrors('periksa');

        $this->actingAs($this->u)->post("/perancah/{$u->id}/inspeksi", [
            'jenis' => 'awal', 'inspektur' => 'A', 'inspektur_sertifikat' => 'S', 'periksa' => $this->periksa('lulus', ['INS-01' => 'gagal']),
        ])->assertSessionHasErrors('tindakan_perbaikan');
        $this->assertSame(0, Inspeksi::count());
    }

    public function test_lewat_tujuh_hari_terbaca_kedaluwarsa_lalu_penjadwal_menerbitkan_merah(): void
    {
        $u = $this->daftarkan();
        Penanda::inspeksi($u, ['jenis' => 'awal', 'inspektur' => 'A', 'inspektur_sertifikat' => 'S', 'periksa' => $this->periksa()]);
        Carbon::setTestNow(now()->addDays(8));

        $this->assertSame('kedaluwarsa', $u->fresh()->statusEfektif());
        $this->artisan('perancah:kedaluwarsa')->assertSuccessful();
        $this->assertSame('merah', $u->fresh()->status_tag);
        $this->assertStringContainsString('KEDALUWARSA', Tag::where('unit_id', $u->id)->where('aktif', true)->first()->label);
    }

    public function test_ubah_spesifikasi_menurunkan_tag_ke_merah(): void
    {
        $u = $this->daftarkan();
        Penanda::inspeksi($u, ['jenis' => 'awal', 'inspektur' => 'A', 'inspektur_sertifikat' => 'S', 'periksa' => $this->periksa()]);
        $this->assertSame('hijau', $u->fresh()->status_tag);

        $this->actingAs($this->u)->put("/perancah/{$u->id}", $this->spek(['tinggi_m' => 10]))->assertRedirect();
        $this->assertSame('merah', $u->fresh()->status_tag);
    }

    public function test_bongkar_mencabut_tag_aktif(): void
    {
        $u = $this->daftarkan();
        $this->actingAs($this->u)->post("/perancah/{$u->id}/bongkar")->assertRedirect();

        $this->assertSame('dibongkar', $u->fresh()->status_tag);
        $this->assertSame(0, Tag::where('unit_id', $u->id)->where('aktif', true)->count());
        $this->actingAs($this->u)->get("/perancah/{$u->id}/inspeksi")->assertForbidden();
    }

    public function test_pratinjau_hitung_sama_dengan_yang_tersimpan(): void
    {
        $r = $this->actingAs($this->u)->postJson('/perancah/hitung', $this->spek())->assertOk()->json();
        $u = $this->daftarkan();

        $this->assertEquals($r['hitung']['faktor_keamanan'], $u->hitung->faktor_keamanan);
        $this->assertSame($r['kajian']['putusan'], $u->kajianTerakhir->putusan);
    }

    public function test_perusahaan_lain_tidak_dapat_membuka_unit(): void
    {
        $u = $this->daftarkan();
        $lain = User::factory()->create(['company_id' => Company::create(['name' => 'Lain'])->id]);

        $this->actingAs($lain)->get("/perancah/{$u->id}")->assertNotFound();
        $this->actingAs($lain)->post("/perancah/{$u->id}/inspeksi", [])->assertNotFound();
    }

    public function test_verifikasi_qr_tanpa_masuk_menampilkan_tag_yang_berlaku(): void
    {
        $u = $this->daftarkan();
        $lama = Tag::where('unit_id', $u->id)->first();
        Penanda::inspeksi($u, ['jenis' => 'awal', 'inspektur' => 'A', 'inspektur_sertifikat' => 'S', 'periksa' => $this->periksa()]);

        auth()->logout();
        $this->get('/scafftag/'.$lama->token)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Perancah/Verifikasi')->where('tag.berlaku', false)->where('tag.warna', 'hijau'));
        $this->get('/scafftag/'.str_repeat('a', 32))->assertOk()->assertInertia(fn (Assert $p) => $p->where('ada', false));
    }

    public function test_halaman_modul_terbuka(): void
    {
        $u = $this->daftarkan();
        Penanda::inspeksi($u, ['jenis' => 'awal', 'inspektur' => 'A', 'inspektur_sertifikat' => 'S', 'periksa' => $this->periksa()]);
        $tag = Tag::where('unit_id', $u->id)->where('aktif', true)->first();

        foreach (['/perancah', '/perancah/peta', '/perancah/baru', "/perancah/{$u->id}", "/perancah/{$u->id}/ubah",
                  "/perancah/{$u->id}/inspeksi", "/perancah/tag/{$tag->kode}"] as $url) {
            $this->actingAs($this->u)->get($url)->assertOk();
        }
        $this->actingAs($this->u)->get('/perancah/ekspor/geojson')->assertOk()->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('features.0.properties.kode', $u->kode);
        $this->actingAs($this->u)->get('/perancah/ekspor/csv')->assertOk();
    }
}
