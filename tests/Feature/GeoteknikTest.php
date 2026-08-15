<?php

namespace Tests\Feature;

use App\Models\{Company, GeoBacaan, GeoInstrumen, GeoLereng, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pemantauan Kestabilan Lereng.
 *
 * Dua hal paling penting diuji di sini, dan keduanya menyangkut kapan
 * sebuah peringatan boleh muncul:
 *
 *  - Gejala lapangan harus memicu peringatan sejak masih draf. Menahan
 *    tanda bahaya sampai ada yang sempat menyetujuinya adalah kekeliruan
 *    yang tidak dapat diperbaiki setelah lerengnya runtuh.
 *
 *  - Laju dan perkiraan waktu runtuh justru TIDAK boleh dihitung dari
 *    bacaan yang belum ditinjau, sebab salah baca prisma akan
 *    mengosongkan pit tanpa sebab.
 */
class GeoteknikTest extends TestCase
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

    private function lereng(array $ganti = []): GeoLereng
    {
        return GeoLereng::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'HW-01', 'nama' => 'Highwall Pit A',
            'jenis' => 'highwall', 'status' => 'aktif', 'fk_rencana' => 1.5,
            'kajian_oleh' => 'PT Geoteknik Uji', 'kajian_tanggal' => '2026-08-01',
            'interval_kajian_hari' => 365,
        ], $ganti));
    }

    private function alat(GeoLereng $l, array $ganti = []): GeoInstrumen
    {
        return $l->instrumen()->create(array_merge([
            'kode' => 'PR-01', 'jenis' => 'prisma', 'status' => 'siap',
        ], $ganti));
    }

    /** Menanam deret perpindahan kumulatif, satu bacaan per hari. */
    private function deret(GeoLereng $l, ?GeoInstrumen $alat, array $perpindahan, string $status = 'disetujui'): void
    {
        foreach ($perpindahan as $i => $d) {
            $b = GeoBacaan::create([
                'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
                'geo_instrumen_id' => $alat?->id,
                'tanggal' => Carbon::parse('2026-08-01')->addDays($i)->toDateString(),
                'perpindahan_mm' => $d,
            ]);

            if ($status !== 'draf') {
                $b->forceFill(['status' => $status])->save();
            }
        }
    }

    /* ---------- gerakan ---------- */

    public function test_laju_dihitung_dari_selisih_perpindahan_kumulatif(): void
    {
        $l = $this->lereng();
        $this->deret($l, $this->alat($l), [0, 3, 6, 9]);

        $g = $l->fresh()->gerakan();

        $this->assertSame(3.0, $g['laju']);
        // 3 mm/hari sudah melewati ambang waspada bawaan (2 mm/hari).
        $this->assertSame('waspada', $g['tingkat']);
    }

    public function test_lereng_yang_menderas_naik_ke_tingkat_yang_sesuai(): void
    {
        $l = $this->lereng();
        // Laju terakhir 64 mm/hari, di atas ambang awas bawaan (50).
        $this->deret($l, $this->alat($l), [0, 2, 6, 14, 30, 62, 126]);

        $g = $l->fresh()->gerakan();

        $this->assertSame('awas', $g['tingkat']);
        $this->assertSame('menderas', $g['tren']['arah']);
    }

    public function test_ambang_khusus_lereng_dipakai_alih_alih_bawaan(): void
    {
        // Timbunan tanah boleh merayap jauh lebih cepat tanpa gagal.
        $l = $this->lereng([
            'jenis' => 'timbunan',
            'ambang_waspada_mm_hari' => 30, 'ambang_siaga_mm_hari' => 80, 'ambang_awas_mm_hari' => 200,
        ]);
        $this->deret($l, $this->alat($l), [0, 15, 30, 45]);

        // 15 mm/hari sudah "siaga" pada ambang bawaan, tetapi masih
        // normal bagi lereng ini.
        $this->assertSame('normal', $l->fresh()->gerakan()['tingkat']);
    }

    public function test_bacaan_belum_ditinjau_tidak_ikut_menghitung_laju(): void
    {
        $l = $this->lereng();
        $alat = $this->alat($l);
        $this->deret($l, $alat, [0, 50, 100, 150], 'draf');

        // Seluruhnya masih draf: tidak ada laju sama sekali.
        $this->assertNull($l->fresh()->gerakan()['laju']);
    }

    /* ---------- peringatan ---------- */

    public function test_gejala_lapangan_memicu_peringatan_tanpa_menunggu_persetujuan(): void
    {
        // Inti modul ini. Bacaan masih draf, belum diajukan, belum
        // ditinjau siapa pun — peringatannya tetap harus muncul.
        $l = $this->lereng();
        GeoBacaan::create([
            'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
            'geo_instrumen_id' => $this->alat($l)->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 1,
            'ada_gejala' => true, 'gejala' => 'Retakan baru 3 m di crest',
        ]);

        $this->actingAs($this->pengawas)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'gejala-lapangan-')
                        && str_contains($x['ket'], 'Retakan baru')
                ))
            );
    }

    public function test_peringatan_gejala_menyebut_bahwa_ia_tidak_menunggu_tinjauan(): void
    {
        $l = $this->lereng();
        GeoBacaan::create([
            'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
            'geo_instrumen_id' => $this->alat($l)->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 1, 'ada_gejala' => true, 'gejala' => 'Gugur batu',
        ]);

        $this->actingAs($this->pengawas)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_contains($x['ket'], 'sengaja tidak menunggu')
                ))
            );
    }

    public function test_penyimpangan_geometri_diperingatkan(): void
    {
        $l = $this->lereng(['sudut_rencana_deg' => 45, 'sudut_aktual_deg' => 52]);
        $this->alat($l);

        $this->actingAs($this->pengawas)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'geometri-')
                ))
            );
    }

    public function test_lereng_lebih_landai_daripada_rancangan_bukan_penyimpangan(): void
    {
        // Tergali lebih landai berada di sisi aman; menyebutnya
        // menyimpang membuat peringatannya berhenti dibaca.
        $l = $this->lereng(['sudut_rencana_deg' => 45, 'sudut_aktual_deg' => 38]);

        $this->assertSame([], $l->penyimpanganGeometri());
    }

    public function test_berm_yang_lebih_sempit_daripada_rancangan_adalah_penyimpangan(): void
    {
        // Berm terbalik arahnya terhadap sudut dan tinggi: yang
        // berbahaya justru yang kurang, sebab lebar berm itulah yang
        // menangkap material gugur.
        $l = $this->lereng(['lebar_berm_rencana_m' => 8, 'lebar_berm_aktual_m' => 5]);

        $hal = array_column($l->penyimpanganGeometri(), 'hal');
        $this->assertContains('Lebar berm', $hal);
    }

    public function test_kajian_yang_kedaluwarsa_diperingatkan(): void
    {
        $l = $this->lereng(['kajian_tanggal' => '2025-01-01', 'interval_kajian_hari' => 180]);
        $this->alat($l);

        $this->assertLessThan(0, $l->sisaHariKajian());

        $this->actingAs($this->pengawas)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'kajian-kedaluwarsa-')
                ))
            );
    }

    public function test_lereng_tanpa_alat_pantau_diperingatkan(): void
    {
        $this->lereng();

        $this->actingAs($this->pengawas)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'tanpa-instrumen-')
                ))
            );
    }

    /* ---------- alur tinjauan ---------- */

    public function test_pengaju_tidak_boleh_menyetujui_bacaannya_sendiri(): void
    {
        $l = $this->lereng();
        $b = GeoBacaan::create([
            'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
            'geo_instrumen_id' => $this->alat($l)->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 5,
        ]);

        $this->actingAs($this->pengawas)->post(route('geoteknik.ajukan', $b));
        $this->actingAs($this->pengawas)->post(route('geoteknik.setujui', $b))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $b->fresh()->status);
    }

    public function test_ktt_dapat_menyetujui_dan_bacaannya_lalu_terkunci(): void
    {
        $l = $this->lereng();
        $b = GeoBacaan::create([
            'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
            'geo_instrumen_id' => $this->alat($l)->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 5,
        ]);

        $this->actingAs($this->pengawas)->post(route('geoteknik.ajukan', $b));
        $this->actingAs($this->ktt)->post(route('geoteknik.setujui', $b));

        $b->refresh();
        $this->assertSame('disetujui', $b->status);
        $this->assertFalse($b->dapatDiubah());
    }

    /* ---------- penyimpanan ---------- */

    public function test_alat_milik_lereng_lain_ditolak(): void
    {
        // Tanpa penjagaan ini, perpindahan satu lereng masuk ke deret
        // waktu lereng lain dan lajunya melompat tanpa ada yang bergerak.
        $a = $this->lereng(['kode' => 'HW-01']);
        $b = $this->lereng(['kode' => 'HW-02']);
        $alatB = $this->alat($b, ['kode' => 'PR-B']);

        $this->actingAs($this->pengawas)->post(route('geoteknik.bacaan.simpan'), [
            'geo_lereng_id' => $a->id, 'geo_instrumen_id' => $alatB->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 5,
        ])->assertSessionHasErrors('geo_instrumen_id');

        $this->assertSame(0, GeoBacaan::count());
    }

    public function test_dua_bacaan_alat_yang_sama_pada_satu_hari_ditolak(): void
    {
        $l = $this->lereng();
        $alat = $this->alat($l);

        $kirim = fn () => $this->actingAs($this->pengawas)->post(route('geoteknik.bacaan.simpan'), [
            'geo_lereng_id' => $l->id, 'geo_instrumen_id' => $alat->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 5,
        ]);

        $kirim();
        $kirim()->assertSessionHasErrors('tanggal');

        $this->assertSame(1, GeoBacaan::count());
    }

    public function test_uraian_gejala_tanpa_centang_tetap_dianggap_bergejala(): void
    {
        // Gejala yang diuraikan tetapi tidak ditandai tidak akan pernah
        // memicu peringatan — itu kehilangan yang paling mahal di modul ini.
        $l = $this->lereng();

        $this->actingAs($this->pengawas)->post(route('geoteknik.bacaan.simpan'), [
            'geo_lereng_id' => $l->id, 'tanggal' => '2026-08-20',
            'perpindahan_mm' => 2, 'gejala' => 'Rembesan di toe',
        ]);

        $this->assertTrue(GeoBacaan::first()->ada_gejala);
    }

    public function test_ambang_yang_tidak_menaik_ditolak(): void
    {
        // Ambang terbalik membuat lereng melewati "awas" sebelum "waspada".
        $this->actingAs($this->pengawas)->post(route('geoteknik.lereng.simpan'), [
            'kode' => 'HW-9', 'nama' => 'Uji', 'jenis' => 'highwall', 'status' => 'aktif',
            'ambang_waspada_mm_hari' => 50, 'ambang_siaga_mm_hari' => 10, 'ambang_awas_mm_hari' => 5,
        ])->assertSessionHasErrors(['ambang_siaga_mm_hari', 'ambang_awas_mm_hari']);
    }

    /* ---------- halaman & laporan ---------- */

    public function test_halaman_memuat_ringkasan_dan_daftar_lereng(): void
    {
        $l = $this->lereng();
        $this->deret($l, $this->alat($l), [0, 3, 6, 9]);

        $this->actingAs($this->pengawas)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Geoteknik/Halaman')
                ->where('ringkas.lereng', 1)
                ->has('lereng', 1)
                ->has('tautan.cetak')
            );
    }

    public function test_laporan_hanya_memuat_bacaan_yang_disetujui(): void
    {
        $l = $this->lereng();
        $alat = $this->alat($l);
        $this->deret($l, $alat, [0, 3, 6], 'disetujui');

        GeoBacaan::create([
            'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
            'tanggal' => '2026-08-25', 'perpindahan_mm' => 99,
        ]);

        $this->actingAs($this->ktt)->get(route('geoteknik.cetak'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Geoteknik')
                ->where('dasar.disetujui', 3)
                ->where('dasar.belumDitinjau', 1)
                ->has('dok.nomor')
            );
    }

    public function test_tautan_hapus_yang_dikirim_halaman_benar_benar_bekerja(): void
    {
        // Pola __ID__ pernah menghasilkan alamat /bacaan/0/5 yang tidak
        // pernah cocok dengan rute mana pun.
        $l = $this->lereng();
        $b = GeoBacaan::create([
            'company_id' => $this->company->id, 'geo_lereng_id' => $l->id,
            'tanggal' => '2026-08-20', 'perpindahan_mm' => 5,
        ]);

        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);

        $pola = null;
        $this->actingAs($admin)->get(route('geoteknik.bacaan'))
            ->assertInertia(function (Assert $p) use (&$pola) {
                $pola = $p->toArray()['props']['tautan']['bacaanHapus'];
            });

        $this->assertNotNull($pola);
        $this->actingAs($admin)->delete(str_replace('__ID__', (string) $b->id, $pola))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(0, GeoBacaan::count());
    }

    public function test_tindak_lanjut_geoteknik_tercatat_pada_modulnya_sendiri(): void
    {
        $this->actingAs($this->pengawas)->post(route('geoteknik.tindak.simpan'), [
            'judul' => 'Turunkan sudut jenjang HW-01', 'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $t = TindakLanjut::first();
        $this->assertSame('geoteknik', $t->modul);
    }

    public function test_perusahaan_lain_tidak_melihat_lereng_orang(): void
    {
        $this->lereng();

        $lain = Company::create(['name' => 'Tambang Lain']);
        $orangLain = User::factory()->create(['company_id' => $lain->id]);

        $this->actingAs($orangLain)->get(route('geoteknik.index'))
            ->assertInertia(fn (Assert $p) => $p->has('lereng', 0));
    }
}
