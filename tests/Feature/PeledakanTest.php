<?php

namespace Tests\Feature;

use App\Models\{Company, LedakHasil, LedakRencana, LedakTitik, LedakUkur, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengeboran dan Peledakan.
 *
 * Modul ini satu-satunya yang persetujuannya MENDAHULUI pekerjaannya,
 * dan itulah yang paling penting dijaga: hasil hanya boleh dicatat pada
 * rencana yang izinnya sudah keluar. Membiarkan hasil tercatat pada
 * rencana yang belum disetujui berarti aplikasi ikut membuat jejak yang
 * menyamarkan peledakan tanpa izin.
 *
 * Yang kedua: misfire memicu peringatan sejak masih draf. Bahan peledak
 * yang gagal meledak tertinggal di dalam tumpukan material, dan alat
 * gali berikutnya yang akan menemukannya.
 */
class PeledakanTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $juru;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-15 08:00:00');

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->juru = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt  = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Rancangan yang wajar: tidak menimbulkan temuan geometri. */
    private function rencana(array $ganti = []): LedakRencana
    {
        return LedakRencana::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'BL-01',
            'tanggal_rencana' => '2026-08-10', 'faktor_batuan' => 7,
            'diameter_lubang_mm' => 150, 'burden_m' => 4, 'spasi_m' => 5,
            'kedalaman_m' => 11.2, 'subdrill_m' => 1.2, 'stemming_m' => 3.2,
            'tinggi_jenjang_m' => 10, 'jumlah_lubang' => 40, 'pola' => 'selang-seling',
            'kekuatan_relatif' => 100, 'isi_per_lubang_kg' => 60, 'isi_per_tunda_kg' => 60,
        ], $ganti));
    }

    private function titik(array $ganti = []): LedakTitik
    {
        return LedakTitik::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'RMH-01', 'nama' => 'Permukiman Sungai',
            'jenis' => 'permukiman', 'ppv_ambang_mm_s' => 5, 'acuan_ambang' => 'Izin lingkungan', 'aktif' => true,
        ], $ganti));
    }

    private function setujuiRencana(LedakRencana $r): void
    {
        $this->actingAs($this->juru)->post(route('peledakan.rencana.ajukan', $r));
        $this->actingAs($this->ktt)->post(route('peledakan.rencana.setujui', $r));
    }

    /* ---------- persetujuan mendahului pekerjaan ---------- */

    public function test_hasil_ditolak_pada_rencana_yang_belum_disetujui(): void
    {
        $r = $this->rencana();

        $this->actingAs($this->juru)->post(route('peledakan.hasil.simpan', $r), [
            'waktu_ledak' => '2026-08-10 14:00', 'volume_bcm' => 8000,
        ])->assertSessionHasErrors('alur');

        $this->assertSame(0, LedakHasil::count());
    }

    public function test_hasil_diterima_setelah_rencana_disetujui(): void
    {
        $r = $this->rencana();
        $this->setujuiRencana($r);

        $this->actingAs($this->juru)->post(route('peledakan.hasil.simpan', $r), [
            'waktu_ledak' => '2026-08-10 14:00', 'volume_bcm' => 8000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, LedakHasil::count());
    }

    public function test_penyusun_tidak_boleh_menyetujui_rencananya_sendiri(): void
    {
        $r = $this->rencana();

        $this->actingAs($this->juru)->post(route('peledakan.rencana.ajukan', $r));
        $this->actingAs($this->juru)->post(route('peledakan.rencana.setujui', $r))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $r->fresh()->status);
    }

    /* ---------- misfire tidak menunggu tinjauan ---------- */

    public function test_misfire_memicu_peringatan_tanpa_menunggu_tinjauan(): void
    {
        $r = $this->rencana();
        $this->setujuiRencana($r);
        $this->titik();

        LedakHasil::create([
            'company_id' => $this->company->id, 'ledak_rencana_id' => $r->id,
            'waktu_ledak' => '2026-08-10 14:00', 'volume_bcm' => 8000,
            'ada_misfire' => true, 'misfire_lubang' => 3,
        ]);

        $this->actingAs($this->juru)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'misfire-')
                        && str_contains($x['ket'], 'sengaja tidak menunggu')
                ))
            );
    }

    public function test_jumlah_lubang_misfire_menandai_kejadiannya(): void
    {
        // Angka yang diisi tetapi kotaknya tidak dicentang tidak akan
        // pernah memicu peringatan — itu kehilangan yang paling mahal.
        $r = $this->rencana();
        $this->setujuiRencana($r);

        $this->actingAs($this->juru)->post(route('peledakan.hasil.simpan', $r), [
            'waktu_ledak' => '2026-08-10 14:00', 'volume_bcm' => 8000, 'misfire_lubang' => 2,
        ]);

        $this->assertTrue(LedakHasil::first()->ada_misfire);
    }

    /* ---------- getaran ---------- */

    public function test_perkiraan_getaran_melampaui_ambang_diperingatkan_sebelum_diledakkan(): void
    {
        $t = $this->titik(['ppv_ambang_mm_s' => 3]);
        $r = $this->rencana(['isi_per_tunda_kg' => 400]);

        // Jarak dicatat lewat pengukuran; di sini dipakai untuk
        // menyatakan seberapa dekat titiknya dari muka peledakan.
        LedakUkur::create([
            'company_id' => $this->company->id, 'ledak_rencana_id' => $r->id,
            'ledak_titik_id' => $t->id, 'jarak_m' => 150, 'ppv_mm_s' => 0.001,
        ]);

        $this->actingAs($this->juru)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'getaran-perkiraan-')
                        && str_contains($x['saran'], 'Turunkan isi per tundaan')
                ))
            );
    }

    public function test_getaran_terukur_melampaui_ambang_diperingatkan(): void
    {
        $t = $this->titik(['ppv_ambang_mm_s' => 5]);
        $r = $this->rencana();

        LedakUkur::create([
            'company_id' => $this->company->id, 'ledak_rencana_id' => $r->id,
            'ledak_titik_id' => $t->id, 'jarak_m' => 200, 'ppv_mm_s' => 18.4,
        ]);

        $this->actingAs($this->juru)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'getaran-terukur-')
                ))
            );
    }

    public function test_titik_tanpa_ambang_izin_memakai_bawaan_dan_ditandai(): void
    {
        $t = $this->titik(['ppv_ambang_mm_s' => null]);

        $this->assertFalse($t->ambangDitetapkan());
        $this->assertSame(5.0, $t->ambang());   // bawaan permukiman

        $this->actingAs($this->juru)->get(route('peledakan.titik'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => $x['kode'] === 'ambang-bawaan'
                ))
            );
    }

    /* ---------- geometri ---------- */

    public function test_stemming_terlalu_pendek_diperingatkan_sebelum_diledakkan(): void
    {
        $this->rencana(['stemming_m' => 1.5]);
        $this->titik();

        $this->actingAs($this->juru)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'geometri-')
                        && str_contains($x['judul'], 'Stemming')
                ))
            );
    }

    public function test_peringatan_geometri_berhenti_setelah_peledakan_terjadi(): void
    {
        // Peringatan pencegahan pada peledakan yang sudah terjadi hanya
        // menambah kebisingan; yang masih dapat diubah tinggal tidak ada.
        $r = $this->rencana(['stemming_m' => 1.5]);
        $this->setujuiRencana($r);
        LedakHasil::create([
            'company_id' => $this->company->id, 'ledak_rencana_id' => $r->id,
            'waktu_ledak' => '2026-08-10 14:00', 'volume_bcm' => 8000,
        ]);

        $this->actingAs($this->juru)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->every(
                    fn ($x) => !str_starts_with($x['kode'], 'geometri-')
                ))
            );
    }

    public function test_kedalaman_kurang_dari_jenjang_ditambah_subdrill_ditolak(): void
    {
        $this->actingAs($this->juru)->post(route('peledakan.rencana.simpan'), [
            'kode' => 'BL-9', 'tanggal_rencana' => '2026-08-20', 'faktor_batuan' => 7,
            'diameter_lubang_mm' => 150, 'burden_m' => 4, 'spasi_m' => 5,
            'kedalaman_m' => 8, 'subdrill_m' => 1.2, 'stemming_m' => 3.2,
            'tinggi_jenjang_m' => 10, 'jumlah_lubang' => 40, 'pola' => 'selang-seling',
            'kekuatan_relatif' => 100, 'isi_per_lubang_kg' => 60, 'isi_per_tunda_kg' => 60,
        ])->assertSessionHasErrors('kedalaman_m');
    }

    public function test_stemming_sepanjang_lubang_ditolak(): void
    {
        $this->actingAs($this->juru)->post(route('peledakan.rencana.simpan'), [
            'kode' => 'BL-8', 'tanggal_rencana' => '2026-08-20', 'faktor_batuan' => 7,
            'diameter_lubang_mm' => 150, 'burden_m' => 4, 'spasi_m' => 5,
            'kedalaman_m' => 11.2, 'subdrill_m' => 1.2, 'stemming_m' => 12,
            'tinggi_jenjang_m' => 10, 'jumlah_lubang' => 40, 'pola' => 'selang-seling',
            'kekuatan_relatif' => 100, 'isi_per_lubang_kg' => 60, 'isi_per_tunda_kg' => 60,
        ])->assertSessionHasErrors('stemming_m');
    }

    /* ---------- hitungan pada model ---------- */

    public function test_powder_factor_rencana_dan_nyata_dipisahkan(): void
    {
        $r = $this->rencana();
        $this->setujuiRencana($r);
        LedakHasil::create([
            'company_id' => $this->company->id, 'ledak_rencana_id' => $r->id,
            'waktu_ledak' => '2026-08-10 14:00', 'volume_bcm' => 6000,
        ]);
        $r->refresh();

        // Rencana: 40 lubang × 4×5×10 m = 8000 m³ untuk 2400 kg = 0,3
        $this->assertSame(0.3, $r->powderFactorRencana());
        // Nyata: volume yang benar-benar terbongkar lebih kecil, jadi
        // powder factor nyatanya lebih tinggi.
        $this->assertSame(0.4, $r->powderFactorNyata());
    }

    /* ---------- halaman & laporan ---------- */

    public function test_halaman_memuat_ringkasan_dan_tetapan(): void
    {
        $this->rencana();
        $this->titik();

        $this->actingAs($this->juru)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Peledakan/Halaman')
                ->where('ringkas.rencana', 1)
                ->has('tetapan.dapatDipakai')
                ->has('tautan.cetak')
            );
    }

    public function test_laporan_hanya_memuat_rencana_yang_disetujui(): void
    {
        $a = $this->rencana(['kode' => 'BL-A']);
        $this->setujuiRencana($a);
        $this->rencana(['kode' => 'BL-B']);

        $this->actingAs($this->ktt)->get(route('peledakan.cetak'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Peledakan')
                ->where('dasar.disetujui', 1)
                ->where('dasar.belumDitinjau', 1)
                ->has('dok.nomor')
            );
    }

    public function test_tautan_hapus_yang_dikirim_halaman_benar_benar_bekerja(): void
    {
        $r = $this->rencana();
        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);

        $pola = null;
        $this->actingAs($admin)->get(route('peledakan.rencana'))
            ->assertInertia(function (Assert $p) use (&$pola) {
                $pola = $p->toArray()['props']['tautan']['rencanaHapus'];
            });

        $this->assertNotNull($pola);
        $this->actingAs($admin)->delete(str_replace('__ID__', (string) $r->id, $pola))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, LedakRencana::count());
    }

    public function test_tindak_lanjut_tercatat_pada_modulnya_sendiri(): void
    {
        $this->actingAs($this->juru)->post(route('peledakan.tindak.simpan'), [
            'judul' => 'Perbaiki stemming BL-01', 'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $this->assertSame('peledakan', TindakLanjut::first()->modul);
    }

    public function test_perusahaan_lain_tidak_melihat_rencana_orang(): void
    {
        $this->rencana();

        $lain = Company::create(['name' => 'Tambang Lain']);
        $orangLain = User::factory()->create(['company_id' => $lain->id]);

        $this->actingAs($orangLain)->get(route('peledakan.index'))
            ->assertInertia(fn (Assert $p) => $p->has('rencana', 0));
    }
}
