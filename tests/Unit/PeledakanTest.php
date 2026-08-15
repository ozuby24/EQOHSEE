<?php

namespace Tests\Unit;

use App\Support\Peledakan;
use PHPUnit\Framework\TestCase;

class PeledakanTest extends TestCase
{
    /* ---------- pemakaian bahan peledak ---------- */

    public function test_powder_factor_membagi_bahan_peledak_dengan_volume(): void
    {
        $this->assertSame(0.25, Peledakan::powderFactor(500, 2000));
    }

    public function test_volume_nol_tidak_dipaksa_menjadi_angka(): void
    {
        $this->assertNull(Peledakan::powderFactor(500, 0));
    }

    public function test_volume_per_lubang_hasil_kali_ketiga_ukurannya(): void
    {
        $this->assertSame(240.0, Peledakan::volumePerLubang(4, 5, 12));
    }

    /* ---------- jarak skala ---------- */

    public function test_jarak_skala_membagi_dengan_akar_isi_per_tundaan(): void
    {
        // 300 m dengan 100 kg per tundaan = 300/10 = 30
        $this->assertSame(30.0, Peledakan::jarakSkala(300, 100));
    }

    public function test_memakai_isi_per_tundaan_bukan_isi_seluruh_peledakan(): void
    {
        // Inilah gunanya penundaan: satu peledakan 1600 kg yang dipecah
        // menjadi 16 tundaan @100 kg punya jarak skala empat kali lebih
        // besar — dan getaran yang jauh lebih kecil — daripada bila
        // seluruhnya meledak bersamaan.
        $terpecah  = Peledakan::jarakSkala(300, 100);
        $bersamaan = Peledakan::jarakSkala(300, 1600);

        $this->assertSame(4.0, round($terpecah / $bersamaan, 4));
    }

    /* ---------- getaran ---------- */

    public function test_getaran_melemah_terhadap_jarak(): void
    {
        $dekat = Peledakan::ppvPerkiraan(100, 100);
        $jauh  = Peledakan::ppvPerkiraan(400, 100);

        $this->assertGreaterThan($jauh, $dekat);
    }

    public function test_getaran_menguat_dengan_isi_per_tundaan_yang_lebih_besar(): void
    {
        $kecil = Peledakan::ppvPerkiraan(300, 50);
        $besar = Peledakan::ppvPerkiraan(300, 400);

        $this->assertGreaterThan($kecil, $besar);
    }

    public function test_getaran_mengikuti_rumus_penskalaan_akar(): void
    {
        // SD = 300/√100 = 30 ; PPV = 1140 · 30^(−1,6)
        $harap = 1140 * 30 ** (-1.6);

        $this->assertEqualsWithDelta($harap, Peledakan::ppvPerkiraan(300, 100), 0.01);
    }

    /* ---------- isi maksimum: hitungan yang dipakai sebelum meledakkan ---------- */

    public function test_isi_maksimum_menghasilkan_getaran_tepat_pada_ambangnya(): void
    {
        // Pemeriksaan bolak-balik: isi yang dikembalikan, bila dimasukkan
        // kembali ke rumus getaran, harus mendarat pas di ambangnya.
        $w = Peledakan::isiMaksPerTunda(300, 5.0);
        $this->assertNotNull($w);

        $this->assertEqualsWithDelta(5.0, Peledakan::ppvPerkiraan(300, $w), 0.02);
    }

    public function test_ambang_lebih_ketat_menurunkan_isi_maksimum(): void
    {
        $longgar = Peledakan::isiMaksPerTunda(300, 12.5);
        $ketat   = Peledakan::isiMaksPerTunda(300, 3.0);

        $this->assertGreaterThan($ketat, $longgar);
    }

    public function test_rumah_yang_lebih_jauh_boleh_menerima_isi_lebih_besar(): void
    {
        $dekat = Peledakan::isiMaksPerTunda(150, 5.0);
        $jauh  = Peledakan::isiMaksPerTunda(600, 5.0);

        $this->assertGreaterThan($dekat, $jauh);
    }

    public function test_isi_maksimum_memakai_tetapan_situs_bila_diberikan(): void
    {
        $umum  = Peledakan::isiMaksPerTunda(300, 5.0);
        $situs = Peledakan::isiMaksPerTunda(300, 5.0, k: 500.0, beta: 1.6);

        // Batuan yang menjalarkan getaran lebih lemah (K kecil)
        // memperbolehkan isi yang lebih besar pada ambang yang sama.
        $this->assertGreaterThan($umum, $situs);
    }

    /* ---------- kalibrasi ---------- */

    public function test_kalibrasi_menemukan_kembali_tetapan_yang_dipakai_membuat_datanya(): void
    {
        // Disusun mundur dari jawabannya: data dibangkitkan dengan
        // K=850 dan β=1,75, lalu kalibrasinya harus menemukan keduanya.
        $ukur = [];
        foreach ([[120, 40], [200, 60], [320, 80], [450, 120], [600, 150], [800, 200]] as [$d, $w]) {
            $sd = $d / sqrt($w);
            $ukur[] = ['jarak_m' => $d, 'isi_kg' => $w, 'ppv' => 850 * $sd ** (-1.75)];
        }

        $k = Peledakan::kalibrasi($ukur);

        $this->assertTrue($k['dapatDipakai'], $k['alasan']);
        $this->assertEqualsWithDelta(850.0, $k['k'], 5.0);
        $this->assertEqualsWithDelta(1.75, $k['beta'], 0.02);
        $this->assertGreaterThan(0.99, $k['r2']);
    }

    public function test_pengukuran_sedikit_tidak_menggantikan_tetapan_umum(): void
    {
        $k = Peledakan::kalibrasi([
            ['jarak_m' => 200, 'isi_kg' => 60, 'ppv' => 8],
            ['jarak_m' => 400, 'isi_kg' => 60, 'ppv' => 3],
        ]);

        $this->assertFalse($k['dapatDipakai']);
        $this->assertNull($k['k']);
    }

    public function test_getaran_yang_menguat_terhadap_jarak_ditolak(): void
    {
        // Biasanya salah catat jarak atau isi per tundaan. Tetapan
        // seperti ini, bila dipakai menghitung isi maksimum, justru
        // menghasilkan angka yang berbahaya.
        $ukur = [];
        foreach ([[100, 50], [200, 50], [300, 50], [400, 50], [500, 50], [600, 50]] as $i => [$d, $w]) {
            $ukur[] = ['jarak_m' => $d, 'isi_kg' => $w, 'ppv' => 2 + $i * 3];
        }

        $k = Peledakan::kalibrasi($ukur);

        $this->assertFalse($k['dapatDipakai']);
        $this->assertNull($k['beta']);
        $this->assertStringContainsString('tidak melemah', $k['alasan']);
    }

    /* ---------- lemparan batu ---------- */

    public function test_radius_lemparan_membesar_dengan_diameter_lubang(): void
    {
        $kecil = Peledakan::radiusLemparan(76);   // 3 inci
        $besar = Peledakan::radiusLemparan(200);  // ±7,9 inci

        $this->assertGreaterThan($kecil, $besar);
    }

    public function test_radius_lemparan_mengikuti_lundborg(): void
    {
        // d = 152,4 mm = 6 inci → 260 · 6^(2/3) ≈ 858 m
        $this->assertEqualsWithDelta(858.0, Peledakan::radiusLemparan(152.4), 2.0);
    }

    public function test_diameter_tidak_masuk_akal_tidak_menghasilkan_radius(): void
    {
        $this->assertNull(Peledakan::radiusLemparan(0));
    }

    /* ---------- fragmentasi ---------- */

    public function test_powder_factor_lebih_tinggi_menghasilkan_fragmen_lebih_halus(): void
    {
        // Volume tetap, isi dinaikkan → fragmen mengecil.
        $sedikit = Peledakan::x50(240, 60);
        $banyak  = Peledakan::x50(240, 120);

        $this->assertLessThan($sedikit, $banyak);
    }

    public function test_batuan_lebih_keras_menghasilkan_fragmen_lebih_kasar(): void
    {
        $sedang = Peledakan::x50(240, 80, faktorBatuan: 7);
        $keras  = Peledakan::x50(240, 80, faktorBatuan: 13);

        $this->assertGreaterThan($sedang, $keras);
    }

    public function test_bahan_peledak_lebih_kuat_menghasilkan_fragmen_lebih_halus(): void
    {
        $anfo  = Peledakan::x50(240, 80, kekuatanRelatif: 100);
        $emulsi = Peledakan::x50(240, 80, kekuatanRelatif: 130);

        $this->assertLessThan($anfo, $emulsi);
    }

    /* ---------- geometri ---------- */

    public function test_stemming_terlalu_pendek_ditandai(): void
    {
        $t = Peledakan::periksaGeometri(burdenM: 4, spasiM: 5, stemmingM: 2,
            diameterMm: 150, subdrillM: 1, tinggiJenjangM: 10);

        $hal = array_column($t, 'hal');
        $this->assertContains('Stemming terhadap burden', $hal);
    }

    public function test_stemming_yang_cukup_tidak_ditandai(): void
    {
        $t = Peledakan::periksaGeometri(burdenM: 4, spasiM: 5, stemmingM: 3.2,
            diameterMm: 150, subdrillM: 1, tinggiJenjangM: 10);

        $this->assertNotContains('Stemming terhadap burden', array_column($t, 'hal'));
    }

    public function test_burden_terlalu_tipis_dan_terlalu_tebal_dibedakan(): void
    {
        $tipis = Peledakan::periksaGeometri(2, 3, 2, 150, 0.6, 10);
        $tebal = Peledakan::periksaGeometri(9, 10, 7, 150, 2, 20);

        $cari = fn ($t) => collect($t)->firstWhere('hal', 'Burden terhadap diameter')['akibat'] ?? '';

        $this->assertStringContainsString('mendatar', $cari($tipis));
        $this->assertStringContainsString('bongkah besar', $cari($tebal));
    }

    public function test_rancangan_yang_wajar_tidak_menghasilkan_temuan(): void
    {
        // Burden 4 m pada lubang 150 mm = 26,7×d; spasi 5 m = 1,25×B;
        // stemming 3,2 m = 0,8×B; subdrill 1,2 m = 0,3×B; jenjang 10 m.
        $t = Peledakan::periksaGeometri(4, 5, 3.2, 150, 1.2, 10);

        $this->assertSame([], $t, 'Temuan tak terduga: '.json_encode(array_column($t, 'hal')));
    }
}
