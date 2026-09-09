<?php

namespace Tests\Feature;

use App\Models\{EnergyEquipment, EnergyFuelLog, EnergyProduction, User};
use App\Support\Engineering as E;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mining Engineering Hub.
 *
 * Yang diuti terutama aritmetikanya: seluruh halaman menurunkan angkanya
 * dari besaran mentah yang sama, jadi satu salah bagi akan menyebar ke
 * sembilan halaman sekaligus.
 */
class EngineeringTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private const HALAMAN = ['index', 'monitor', 'energy', 'fleet', 'equipment',
                             'maintenance', 'hse', 'kpi', 'tools', 'regulations'];

    /* ---------- ketersediaan ---------- */

    public function test_ketersediaan_diturunkan_dari_jam_bukan_diketik(): void
    {
        // Satu unit yang angkanya mudah diperiksa dengan kepala sendiri.
        $u = ['kerja' => 600, 'standby' => 200, 'rusak' => 200, 'liter' => 12000];

        $this->assertEqualsWithDelta(80.0, E::pa($u), 0.001);          // (600+200)/1000
        $this->assertEqualsWithDelta(75.0, E::ma($u), 0.001);          // 600/(600+200)
        $this->assertEqualsWithDelta(75.0, E::ua($u), 0.001);          // 600/(600+200)
        $this->assertEqualsWithDelta(60.0, E::utilisasi($u), 0.001);   // 600/1000
        $this->assertEqualsWithDelta(20.0, E::fuelRate($u), 0.001);    // 12000/600
    }

    public function test_ringkasan_armada_konsisten_dengan_unitnya(): void
    {
        $a = E::ringkasArmada();
        $unit = E::armada();

        $this->assertSame(count($unit), $a['jumlah']);
        $this->assertEqualsWithDelta(
            array_sum(array_map(fn ($u) => E::jamTerjadwal($u), $unit)),
            $a['terjadwal'], 0.001
        );
        // Jam terjadwal harus persis jumlah ketiga komponennya.
        $this->assertEqualsWithDelta($a['kerja'] + $a['standby'] + $a['rusak'], $a['terjadwal'], 0.001);
    }

    public function test_pembagi_nol_menghasilkan_nol_bukan_tak_hingga(): void
    {
        $this->assertSame(0.0, E::bagi(500.0, 0.0));
        $this->assertSame(0.0, E::fuelRate(['kerja' => 0, 'liter' => 900]));
        $this->assertSame(0.0, E::ma(['kerja' => 0, 'rusak' => 0]));
    }

    /* ---------- status keborosan ---------- */

    public function test_keborosan_dinilai_terhadap_acuan_kelasnya_sendiri(): void
    {
        // Excavator berat pada 62 L/jam tetap efisien bila acuannya 60.
        $this->assertSame('efisien', E::statusBoros(62, 60)['kode']);

        // Unit kecil pada 26 L/jam justru boros bila acuannya 20.
        $this->assertSame('boros', E::statusBoros(26, 20)['kode']);

        $this->assertSame('pantau', E::statusBoros(22, 20)['kode']);
        $this->assertSame('belum',  E::statusBoros(20, 0)['kode']);
    }

    /* ---------- energi ---------- */

    public function test_seluruh_sumber_disamakan_ke_gigajoule(): void
    {
        $e = E::ringkasEnergi();

        $harap = 0.0;
        foreach (E::energi() as $x) {
            $harap += $x['liter'] * E::GJ_PER_LITER + $x['kwh'] * E::GJ_PER_KWH;
        }
        $this->assertEqualsWithDelta($harap, $e['gj'], 0.0001);

        // Intensitas adalah total gigajoule dibagi total produksi — bukan
        // rata-rata intensitas harian, yang memberi bobot sama pada hari
        // berproduksi besar dan kecil.
        $this->assertEqualsWithDelta($e['gj'] / $e['ton'], $e['intensitas'], 1e-9);
    }

    public function test_penurunan_positif_berarti_membaik(): void
    {
        $e = E::ringkasEnergi();
        $harap = (E::BASELINE_GJ_TON - $e['intensitas']) / E::BASELINE_GJ_TON * 100;
        $this->assertEqualsWithDelta($harap, $e['penurunan'], 0.0001);
    }

    /* ---------- keselamatan ---------- */

    public function test_frekuensi_kecelakaan_dihitung_dari_kejadian_dan_jam_kerja(): void
    {
        $h = E::ringkasHse();
        $j = E::HSE['jam_kerja'];
        $p = E::HSE['pengali'];

        $this->assertEqualsWithDelta(E::HSE['recordable'] / $j * $p, $h['trifr'], 1e-9);
        $this->assertEqualsWithDelta(E::HSE['lost_time'] / $j * $p, $h['ltifr'], 1e-9);

        // LTIFR tidak mungkin melebihi TRIFR: setiap lost time injury
        // juga sebuah recordable injury.
        $this->assertLessThanOrEqual($h['trifr'], $h['ltifr']);
    }

    public function test_nilai_smkp_ditimbang_bobot_bukan_dirata_rata_biasa(): void
    {
        $h = E::ringkasHse();

        $bobot = array_sum(array_column(E::smkp(), 'bobot'));
        $this->assertSame(100, $bobot, 'Bobot tujuh elemen SMKP harus berjumlah 100%.');

        $tertimbang = 0.0;
        foreach (E::smkp() as $el) $tertimbang += $el['capaian'] * $el['bobot'];
        $this->assertEqualsWithDelta($tertimbang / $bobot, $h['nilaiSmkp'], 1e-9);

        // Rata-rata biasa berbeda dari yang tertimbang — kalau sama, bobotnya
        // tidak berpengaruh dan ada yang salah.
        $biasa = array_sum(array_column(E::smkp(), 'capaian')) / count(E::smkp());
        $this->assertNotEqualsWithDelta($biasa, $h['nilaiSmkp'], 0.01);
    }

    /* ---------- pajanan bising ---------- */

    public function test_dosis_bising_pada_nab_tepat_seratus_persen(): void
    {
        // Delapan jam pada 85 dBA adalah definisi batas harian.
        $b = E::bising([['db' => 85, 'jam' => 8]]);
        $this->assertEqualsWithDelta(100.0, $b['dosis'], 0.001);
        $this->assertEqualsWithDelta(85.0, $b['twa'], 0.001);
        $this->assertFalse($b['lewat']);
    }

    public function test_laju_pertukaran_tiga_desibel_memparuh_durasi_izin(): void
    {
        // Naik 3 dB memparuh durasi yang diizinkan, jadi 4 jam pada 88 dBA
        // memberi dosis yang sama dengan 8 jam pada 85 dBA.
        foreach ([[88, 4], [91, 2], [94, 1]] as [$db, $jam]) {
            $b = E::bising([['db' => $db, 'jam' => $jam]]);
            $this->assertEqualsWithDelta(100.0, $b['dosis'], 0.001,
                "{$jam} jam pada {$db} dBA seharusnya tepat satu dosis penuh.");
            $this->assertEqualsWithDelta(85.0, $b['twa'], 0.001);
        }
    }

    public function test_pajanan_berlebih_ditandai_melewati_nab(): void
    {
        $b = E::bising([['db' => 91, 'jam' => 8]]);

        $this->assertEqualsWithDelta(400.0, $b['dosis'], 0.001);
        $this->assertEqualsWithDelta(91.0, $b['twa'], 0.001);
        $this->assertTrue($b['lewat']);
    }

    public function test_dosis_beberapa_baris_dijumlahkan_bukan_dirata_rata(): void
    {
        // Satu jam pada 100 dBA jauh lebih berat daripada delapan jam pada
        // 86 dBA; perataan tingkat biasa akan menyembunyikan itu.
        $b = E::bising([['db' => 100, 'jam' => 1], ['db' => 80, 'jam' => 7]]);

        $rata = (100 * 1 + 80 * 7) / 8;                 // 82,5 dBA — tampak aman
        $this->assertLessThan(85, $rata);
        $this->assertGreaterThan(100, $b['dosis'], 'Dosis gabungan seharusnya melewati batas.');
        $this->assertGreaterThan(85, $b['twa']);
    }

    public function test_pajanan_tanpa_durasi_tidak_menghasilkan_dosis(): void
    {
        $b = E::bising([['db' => 95, 'jam' => 0]]);
        $this->assertSame(0.0, $b['dosis']);
        $this->assertNull($b['twa']);
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_terbuka(): void
    {
        $this->masuk();

        foreach (self::HALAMAN as $h) {
            $this->tanpaNaN($this->get(route('meh.'.$h))->assertOk());
        }
    }

    public function test_tamu_tidak_dapat_membuka_halaman(): void
    {
        foreach (self::HALAMAN as $h) {
            $this->get(route('meh.'.$h))->assertRedirect(route('login'));
        }
    }

    public function legacy_alamat_lama_situs_statis_tetap_sampai(): void
    {
        // Tautan '/mining-engineering-hub' sudah beredar sejak situs ini
        // berupa berkas statis; alamatnya sengaja dipertahankan.
        $this->masuk();
        $this->get('/mining-engineering-hub')->assertOk()->assertSee('Mining Engineering Hub');
    }

    public function legacy_penyaring_armada_mempersempit_daftar(): void
    {
        $this->masuk();

        $semua = count(E::armada());
        $operating = count(array_filter(E::armada(), fn ($u) => $u['status'] === 'Operating'));
        $this->assertGreaterThan(0, $operating);
        $this->assertLessThan($semua, $operating);

        $this->get(route('meh.fleet', ['status' => 'Breakdown']))
            ->assertOk()
            ->assertSee('DT-005')          // satu-satunya unit Breakdown
            ->assertDontSee('EX-002');     // Operating, seharusnya tersaring
    }

    public function legacy_penyaring_regulasi_bekerja(): void
    {
        $this->masuk();

        $this->get(route('meh.regulations', ['kategori' => 'Energi']))
            ->assertOk()
            ->assertSee('SNI ISO 50001:2018')
            ->assertDontSee('SNI ISO 9001:2015');
    }

    public function legacy_halaman_membawa_rumus_indikatornya(): void
    {
        $this->masuk();

        // Angka tanpa rumus tidak dapat diperiksa siapa pun.
        $this->get(route('meh.kpi'))
            ->assertOk()
            ->assertSee('Jam kerja ÷ (Jam kerja + Jam rusak) × 100')
            ->assertSee('Overburden dipindahkan ÷ Batu bara terangkut');
    }

    public function test_halaman_lama_membawa_component_inertia(): void
    {
        $this->masuk();
        $props = $this->get('/mining-engineering-hub')->assertOk()->viewData('page')['props'];
        $this->assertSame('index', $props['mode']);
    }

    public function test_penyaring_armada_mempersempit_props_inertia(): void
    {
        $this->masuk();
        $props = $this->get(route('meh.fleet', ['status' => 'Breakdown']))->assertOk()->viewData('page')['props'];
        $kode = array_column($props['unit'], 'kode');

        $this->assertContains('DT-005', $kode);
        $this->assertNotContains('EX-002', $kode);
    }

    public function test_penyaring_regulasi_bekerja_pada_props_inertia(): void
    {
        $this->masuk();
        $props = $this->get(route('meh.regulations', ['kategori' => 'Energi']))->assertOk()->viewData('page')['props'];
        $judul = array_column($props['daftar'], 'judul');

        $this->assertContains('SNI ISO 50001:2018', $judul);
        $this->assertNotContains('SNI ISO 9001:2015', $judul);
    }

    public function test_halaman_kpi_membawa_rumus_indikator(): void
    {
        $this->masuk();
        $props = $this->get(route('meh.kpi'))->assertOk()->viewData('page')['props'];
        $this->assertContains('Jam kerja ÷ (Jam kerja + Jam rusak) × 100', $props['rumus']);
        $this->assertContains('Overburden dipindahkan ÷ Batu bara terangkut', $props['rumus']);
    }

    public function test_control_tower_membaca_data_operasional_dan_mendeteksi_gap(): void
    {
        $this->masuk();
        $unit = EnergyEquipment::create(['kode' => 'DT-MON-01', 'nama' => 'Dump Truck Monitor', 'kategori' => 'hauling']);
        EnergyFuelLog::create(['equipment_id' => $unit->id, 'tanggal' => '2026-01-05', 'hm' => 10, 'liter' => 300, 'idle_jam' => 3, 'ton' => 500]);
        EnergyProduction::create(['tanggal' => '2026-01-05', 'ton' => 500, 'bcm' => 1000]);

        $props = $this->get(route('meh.monitor', ['dari' => '2026-01-01', 'sampai' => '2026-01-31']))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(500.0, $props['monitor']['ton']);
        $this->assertSame(1, count($props['unit']));
        $this->assertNotEmpty($props['trenMonitor']);
    }

    public function test_peringatan_acuan_tercantum_pada_props_inertia(): void
    {
        $this->masuk();

        $props = $this->get(route('meh.tools'))->assertOk()->viewData('page')['props'];
        $this->assertContains('Engineering reference only.', $props['catatan']);
        $this->assertContains('Permenaker No. 5 Tahun 2018', $props['catatan']);
    }
}
