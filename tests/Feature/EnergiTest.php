<?php

namespace Tests\Feature;

use App\Models\{EnergyBaseline, EnergyEquipment, EnergyFuelLog, EnergyFuelRecon,
                EnergyOpportunity, EnergyPowerLog, EnergyProduction, User};
use App\Support\Energi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Energy Performance Center.
 *
 * Yang diuji terutama bukan tampilannya, melainkan aritmetikanya: seluruh
 * halaman menurunkan angkanya saat dibaca, jadi satu salah bagi akan
 * menyebar ke sepuluh halaman sekaligus.
 */
class EnergiTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function unit(array $atribut = []): EnergyEquipment
    {
        return EnergyEquipment::create(array_merge([
            'kode'     => 'HD785-'.fake()->unique()->numberBetween(10, 99),
            'nama'     => 'Dump Truck Komatsu',
            'kategori' => 'hauling',
        ], $atribut));
    }

    /** Satu hari lengkap: solar, listrik, dan produksinya. */
    private function hari(string $tanggal, EnergyEquipment $unit, float $liter, float $hm, float $ton): void
    {
        EnergyFuelLog::create([
            'equipment_id' => $unit->id, 'tanggal' => $tanggal,
            'liter' => $liter, 'hm' => $hm, 'ton' => $ton,
        ]);
        EnergyProduction::create(['tanggal' => $tanggal, 'ton' => $ton]);
    }

    /** Rentang yang memuat seluruh data uji. */
    private function rentang(): array
    {
        return ['dari' => '2026-01-01', 'sampai' => '2026-01-31'];
    }

    /* ---------- konversi ---------- */

    public function test_seluruh_sumber_dibawa_ke_gigajoule_sebelum_dijumlahkan(): void
    {
        $k = Energi::konsolidasi(1000, 5000, 100);

        // 1000 × 0,0358 + 5000 × 0,0036 + 100 × 0,0373
        $this->assertEqualsWithDelta(35.8 + 18.0 + 3.73, $k['gj'], 0.0001);
        $this->assertEqualsWithDelta(2.68 + 4.35 + 0.202, $k['tco2e'], 0.0001);
        $this->assertSame(['Solar', 'Listrik', 'Gas'], array_keys($k['rincian']));
    }

    public function test_intensitas_tanpa_produksi_bernilai_nol_bukan_tak_hingga(): void
    {
        $this->assertSame(0.0, Energi::intensitas(500.0, 0.0));
        $this->assertSame(0.0, Energi::rasio(1000.0, 0.0));
    }

    public function test_penurunan_positif_berarti_membaik(): void
    {
        $this->assertEqualsWithDelta(20.0, Energi::penurunan(10.0, 8.0), 0.0001);
        $this->assertEqualsWithDelta(-20.0, Energi::penurunan(10.0, 12.0), 0.0001);
    }

    /* ---------- status efisiensi ---------- */

    public function test_status_dinilai_terhadap_acuan_bukan_angka_mutlak(): void
    {
        // Excavator berat pada 40 L/HM tetap efisien bila acuannya 39.
        $this->assertSame('efisien', Energi::statusEfisiensi(40, 39)['kode']);

        // Unit kecil pada 12 L/HM justru boros bila acuannya 9.
        $this->assertSame('boros', Energi::statusEfisiensi(12, 9)['kode']);

        $this->assertSame('pantau', Energi::statusEfisiensi(11, 10)['kode']);
        $this->assertSame('belum', Energi::statusEfisiensi(10, 0)['kode']);
    }

    public function test_faktor_beban_dan_efisiensi_genset(): void
    {
        // 4800 kWh pada puncak 200 kW selama 24 jam = 4800/4800 = 100%.
        $this->assertEqualsWithDelta(100.0, Energi::faktorBeban(4800, 200, 24), 0.0001);
        $this->assertEqualsWithDelta(0.0, Energi::faktorBeban(4800, 0, 24), 0.0001);

        $this->assertEqualsWithDelta(3.5, Energi::efisiensiGenset(350, 100), 0.0001);
    }

    /* ---------- baseline ---------- */

    public function test_kemajuan_diukur_pada_rentang_baseline_ke_target(): void
    {
        $b = EnergyBaseline::create(['tahun' => 2026, 'baseline_gj_ton' => 10, 'target_gj_ton' => 8]);

        $this->assertEqualsWithDelta(0.5, $b->kemajuan(9.0), 0.0001);   // separuh jalan
        $this->assertEqualsWithDelta(1.0, $b->kemajuan(7.0), 0.0001);   // melampaui, tetap 1
        $this->assertEqualsWithDelta(0.0, $b->kemajuan(11.0), 0.0001);  // memburuk, tetap 0
        $this->assertEqualsWithDelta(20.0, $b->penurunanTarget(), 0.0001);
    }

    public function test_target_yang_lebih_boros_daripada_baseline_ditolak(): void
    {
        $this->masuk();

        $this->post(route('energi.baseline.simpan'), [
            'tahun' => 2026, 'baseline_gj_ton' => 8, 'target_gj_ton' => 10,
        ])->assertSessionHasErrors('target_gj_ton');

        $this->assertSame(0, EnergyBaseline::count());
    }

    public function test_baseline_tahun_sama_diperbarui_bukan_digandakan(): void
    {
        $this->masuk();

        foreach ([9.0, 8.5] as $target) {
            $this->post(route('energi.baseline.simpan'), [
                'tahun' => 2026, 'baseline_gj_ton' => 10, 'target_gj_ton' => $target,
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, EnergyBaseline::count());
        $this->assertEqualsWithDelta(8.5, EnergyBaseline::first()->target_gj_ton, 0.0001);
    }

    /* ---------- rekonsiliasi ---------- */

    public function test_terpakai_menurut_stok_dan_selisihnya(): void
    {
        $r = EnergyFuelRecon::create([
            'tanggal' => '2026-01-05',
            'stok_awal_liter' => 5000, 'disalurkan_liter' => 10000, 'stok_akhir_liter' => 4000,
        ]);

        $this->assertEqualsWithDelta(11000.0, $r->terpakaiMenurutStok(), 0.0001);

        // Tercatat 10.450 dari 11.000 yang seharusnya terpakai — selisih 5%.
        $this->assertEqualsWithDelta(5.0, $r->selisihPersen(10450), 0.0001);
    }

    /* ---------- peluang penghematan ---------- */

    public function test_hanya_peluang_berjalan_yang_dihitung_sebagai_penghematan(): void
    {
        $this->masuk();

        EnergyOpportunity::create(['judul' => 'Usulan saja', 'status' => 'usulan', 'hemat_liter' => 1000]);
        EnergyOpportunity::create(['judul' => 'Sudah jalan', 'status' => 'berjalan', 'hemat_liter' => 500]);
        EnergyOpportunity::create(['judul' => 'Sudah kelar', 'status' => 'selesai', 'hemat_kwh' => 2000]);

        $this->get(route('energi.kpi'))
            ->assertOk()
            ->assertSee('Penghematan yang Sudah Berjalan');

        // 500 L + 2000 kWh, bukan 1500 L: usulan bukan penghematan.
        $terwujud = EnergyOpportunity::whereIn('status', ['berjalan', 'selesai'])->get();
        $this->assertSame(2, $terwujud->count());
        $this->assertEqualsWithDelta(
            Energi::literKeGj(500) + Energi::kwhKeGj(2000),
            $terwujud->sum(fn ($o) => $o->gj()),
            0.0001
        );
    }

    public function test_peluang_dicatat_diubah_dan_dihapus(): void
    {
        $this->masuk();

        $this->post(route('energi.hemat.simpan'), [
            'judul' => 'Batasi idle dump truck', 'status' => 'usulan', 'hemat_liter' => 800,
        ])->assertSessionHasNoErrors();

        $o = EnergyOpportunity::first();
        $this->assertNotNull($o);
        $this->assertSame(0.0, $o->hemat_kwh);   // kosong menjadi nol, bukan null

        $this->put(route('energi.hemat.ubah', $o), ['status' => 'berjalan']);
        $this->assertTrue($o->fresh()->terwujud());

        $this->delete(route('energi.hemat.hapus', $o));
        $this->assertSame(0, EnergyOpportunity::count());
    }

    public function test_status_peluang_di_luar_daftar_ditolak(): void
    {
        $this->masuk();

        $this->post(route('energi.hemat.simpan'), ['judul' => 'Coba', 'status' => 'entah'])
            ->assertSessionHasErrors('status');
    }

    /* ---------- data induk ---------- */

    public function test_unit_terdaftar_dan_hapusnya_ikut_membawa_catatan(): void
    {
        $this->masuk();

        $this->post(route('energi.master.simpan'), [
            'kode' => 'HD785-01', 'nama' => 'Dump Truck Komatsu HD785-7', 'kategori' => 'hauling',
        ])->assertSessionHasNoErrors();

        $u = EnergyEquipment::firstWhere('kode', 'HD785-01');
        $this->hari('2026-01-05', $u, 400, 10, 500);

        $this->delete(route('energi.master.hapus', $u));

        $this->assertSame(0, EnergyEquipment::count());
        $this->assertSame(0, EnergyFuelLog::count());   // cascade, bukan catatan yatim
    }

    public function test_kode_unit_tidak_boleh_kembar(): void
    {
        $this->masuk();
        $this->unit(['kode' => 'EX-01']);

        $this->post(route('energi.master.simpan'), [
            'kode' => 'EX-01', 'nama' => 'Excavator lain', 'kategori' => 'excavator',
        ])->assertSessionHasErrors('kode');
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_energi_terbuka_dengan_data(): void
    {
        $this->masuk();

        $truk = $this->unit(['kode' => 'HD785-01', 'kategori' => 'hauling']);
        $exca = $this->unit(['kode' => 'PC2000-01', 'nama' => 'Excavator Komatsu', 'kategori' => 'excavator']);

        $this->hari('2026-01-05', $truk, 400, 10, 500);
        EnergyFuelLog::create(['equipment_id' => $exca->id, 'tanggal' => '2026-01-05', 'liter' => 300, 'hm' => 10, 'bcm' => 900]);

        EnergyPowerLog::create([
            'tanggal' => '2026-01-05', 'area' => 'workshop', 'sumber' => 'pln',
            'kwh' => 1200, 'puncak_kw' => 80, 'jam_operasi' => 20,
        ]);
        EnergyPowerLog::create([
            'tanggal' => '2026-01-05', 'area' => 'camp', 'sumber' => 'genset',
            'kwh' => 700, 'liter_genset' => 200, 'jam_operasi' => 12, 'puncak_kw' => 70,
        ]);

        EnergyBaseline::create(['tahun' => 2026, 'baseline_gj_ton' => 0.1, 'target_gj_ton' => 0.08]);
        EnergyFuelRecon::create([
            'tanggal' => '2026-01-05', 'stok_awal_liter' => 5000,
            'disalurkan_liter' => 1000, 'stok_akhir_liter' => 4100,
        ]);

        foreach (['index','konsumsi','fuel','listrik','equipment','kpi','baseline','hemat','karbon','kalkulator','master','laporan'] as $aksi) {
            $this->get(route('energi.'.$aksi, $this->rentang()))
                ->assertOk()
                ->assertDontSee('NaN');
        }

        $this->get(route('energi.equipment.show', [$truk] + $this->rentang()))
            ->assertOk()
            ->assertSee('HD785-01');
    }

    public function test_halaman_terbuka_meski_belum_ada_data_sama_sekali(): void
    {
        $this->masuk();

        foreach (['index','konsumsi','fuel','listrik','equipment','kpi','baseline','hemat','karbon','kalkulator','master','laporan'] as $aksi) {
            $this->get(route('energi.'.$aksi))->assertOk();
        }
    }

    public function test_rentang_terbalik_dibetulkan_bukan_ditolak(): void
    {
        $this->masuk();
        $unit = $this->unit();
        $this->hari('2026-01-15', $unit, 400, 10, 500);

        $benar    = $this->get(route('energi.konsumsi', $this->rentang()))->assertOk();
        $terbalik = $this->get(route('energi.konsumsi', ['dari' => '2026-01-31', 'sampai' => '2026-01-01']))->assertOk();

        // Rentang terbalik dibetulkan diam-diam, jadi halamannya sama persis
        // dengan rentang yang benar — bukan halaman kosong tanpa penjelasan.
        $this->assertSame($benar->getContent(), $terbalik->getContent());

        // Dan halaman itu memang berisi data, bukan sama-sama kosong.
        $benar->assertSee(number_format(\App\Support\Energi::literKeGj(400), 2));
    }

    public function test_solar_genset_ikut_terhitung_sebagai_solar(): void
    {
        $this->masuk();
        $unit = $this->unit();
        $this->hari('2026-01-05', $unit, 400, 10, 500);

        EnergyPowerLog::create([
            'tanggal' => '2026-01-05', 'area' => 'camp', 'sumber' => 'genset',
            'kwh' => 700, 'liter_genset' => 200, 'jam_operasi' => 12,
        ]);

        // 400 L alat + 200 L genset dibakar di lokasi yang sama, dari tangki
        // yang sama — totalnya 600 L, bukan 400.
        $this->get(route('energi.fuel', $this->rentang()))
            ->assertOk()
            ->assertSee('600');
    }

    public function test_peringkat_membandingkan_unit_terhadap_kelompoknya_sendiri(): void
    {
        $this->masuk();

        // Excavator haus tetapi normal bagi kelompoknya; truk hemat secara
        // mutlak tetapi boros bagi kelompoknya.
        $ex1 = $this->unit(['kode' => 'PC-01', 'kategori' => 'excavator']);
        $ex2 = $this->unit(['kode' => 'PC-02', 'kategori' => 'excavator']);
        $hd1 = $this->unit(['kode' => 'HD-01', 'kategori' => 'hauling']);
        $hd2 = $this->unit(['kode' => 'HD-02', 'kategori' => 'hauling']);

        foreach ([[$ex1, 400], [$ex2, 400], [$hd1, 200], [$hd2, 100]] as [$u, $liter]) {
            EnergyFuelLog::create([
                'equipment_id' => $u->id, 'tanggal' => '2026-01-05', 'liter' => $liter, 'hm' => 10,
            ]);
        }

        // Acuan excavator 40 L/HM, acuan hauling 15 L/HM. PC-01 pada 40
        // efisien; HD-01 pada 20 justru boros meski liternya separuh.
        $status = $this->statusPeringkat();

        $this->assertSame('efisien', $status['PC-01'], 'Excavator paling haus tetap efisien bagi kelompoknya.');
        $this->assertSame('boros',   $status['HD-01'], 'Truk berada 1,33 kali di atas acuan kelompoknya.');
        $this->assertSame('efisien', $status['HD-02']);
    }

    /** Status tiap unit menurut halaman peringkat, dibaca dari kode unitnya. */
    private function statusPeringkat(): array
    {
        $peringkat = (new \ReflectionMethod(\App\Http\Controllers\EnergyController::class, 'peringkatUnit'))
            ->invoke(app(\App\Http\Controllers\EnergyController::class),
                     \Illuminate\Support\Carbon::parse('2026-01-01'),
                     \Illuminate\Support\Carbon::parse('2026-01-31'),
                     50);

        return collect($peringkat)->mapWithKeys(
            fn ($b) => [$b['unit']->kode => $b['status']['kode']]
        )->all();
    }

    public function test_laporan_menyebut_nomor_halaman_pada_tiap_lembar(): void
    {
        $this->masuk();

        $laporan = $this->get(route('energi.laporan'))->assertOk();

        foreach (['1 dari 3', '2 dari 3', '3 dari 3'] as $halaman) {
            $laporan->assertSee($halaman);
        }

        $laporan->assertSee('LAPORAN KINERJA ENERGI DAN EMISI KARBON');
    }

    public function test_tamu_tidak_dapat_membuka_halaman_energi(): void
    {
        $this->get(route('energi.index'))->assertRedirect(route('login'));
        $this->post(route('energi.hemat.simpan'), ['judul' => 'X', 'status' => 'usulan'])
            ->assertRedirect(route('login'));
    }
}
