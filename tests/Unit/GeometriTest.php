<?php

namespace Tests\Unit;

use App\Support\Geometri;
use PHPUnit\Framework\TestCase;

/**
 * Ukuran dari GeoJSON.
 *
 * Yang diuji bukan sekadar "menghasilkan angka" melainkan menghasilkan
 * angka yang benar. Luas yang keliru tidak menimbulkan galat apa pun —
 * ia hanya muncul sebagai hektare yang tetap terlihat masuk akal, lalu
 * ikut ke laporan reklamasi.
 */
class GeometriTest extends TestCase
{
    private const R = 6_371_008.8;

    private function poligon(array $cincin): string
    {
        return json_encode(['type' => 'Polygon', 'coordinates' => [$cincin]]);
    }

    /** Kotak lon/lat sederhana, arah berlawanan jarum jam. */
    private function kotak(float $lon, float $lat, float $dLon, float $dLat): string
    {
        return $this->poligon([
            [$lon, $lat], [$lon + $dLon, $lat],
            [$lon + $dLon, $lat + $dLat], [$lon, $lat + $dLat], [$lon, $lat],
        ]);
    }

    /* ---------- ketepatan luas ---------- */

    public function test_kotak_satu_derajat_di_khatulistiwa_cocok_dengan_rumus_analitis(): void
    {
        // Luas pita bola: R² · Δbujur · (sin lat2 − sin lat1)
        $harap = self::R ** 2 * deg2rad(1) * (sin(deg2rad(1)) - sin(0));

        $ukur = Geometri::ukur($this->kotak(0, 0, 1, 1));

        $this->assertEqualsWithDelta($harap, $ukur['luas_m2'], $harap * 0.0001);
    }

    public function test_kotak_yang_sama_menyusut_mendekati_kutub(): void
    {
        // Satu derajat bujur menyempit mengikuti kosinus lintang. Inilah
        // yang salah bila luas dihitung dengan rumus tali sepatu di atas
        // derajat: di 60° hasilnya kira-kira dua kali lipat semestinya.
        $khatulistiwa = Geometri::ukur($this->kotak(0, 0, 1, 1))['luas_m2'];
        $enamPuluh    = Geometri::ukur($this->kotak(0, 60, 1, 1))['luas_m2'];

        $this->assertEqualsWithDelta(0.5, $enamPuluh / $khatulistiwa, 0.01);
    }

    public function test_arah_putaran_tidak_mengubah_luas(): void
    {
        $lawanJarumJam = $this->kotak(110, -2, 0.01, 0.01);
        $searahJarumJam = $this->poligon([
            [110, -2], [110, -1.99], [110.01, -1.99], [110.01, -2], [110, -2],
        ]);

        $this->assertEqualsWithDelta(
            Geometri::ukur($lawanJarumJam)['luas_m2'],
            Geometri::ukur($searahJarumJam)['luas_m2'],
            1.0
        );
    }

    public function test_lubang_dikurangkan_dari_cincin_luar(): void
    {
        $luar = [[110, -2], [110.01, -2], [110.01, -1.99], [110, -1.99], [110, -2]];
        $dalam = [[110.002, -1.998], [110.004, -1.998], [110.004, -1.996], [110.002, -1.996], [110.002, -1.998]];

        $tanpa = Geometri::ukur(json_encode(['type' => 'Polygon', 'coordinates' => [$luar]]));
        $dengan = Geometri::ukur(json_encode(['type' => 'Polygon', 'coordinates' => [$luar, $dalam]]));

        $this->assertLessThan($tanpa['luas_m2'], $dengan['luas_m2'],
            'Kolam pengendap di tengah timbunan bukan area terganggu.');
    }

    /* ---------- keliling dan panjang ---------- */

    public function test_panjang_jalan_angkut_diukur_sepanjang_busur(): void
    {
        // Satu derajat lintang ≈ 111,195 km pada bola berjari-jari IUGG.
        $garis = json_encode(['type' => 'LineString', 'coordinates' => [[110, 0], [110, 1]]]);

        $this->assertEqualsWithDelta(
            self::R * deg2rad(1),
            Geometri::ukur($garis)['keliling_m'],
            50.0
        );
    }

    public function test_poligon_kelilingnya_tertutup(): void
    {
        // Kotak 0,01° × 0,01° di khatulistiwa: empat sisi hampir sama.
        $sisi = self::R * deg2rad(0.01);

        $this->assertEqualsWithDelta(
            4 * $sisi,
            Geometri::ukur($this->kotak(0, 0, 0.01, 0.01))['keliling_m'],
            5.0
        );
    }

    /* ---------- bentuk masukan ---------- */

    public function test_feature_collection_dijumlahkan(): void
    {
        $satu = Geometri::ukur($this->kotak(110, -2, 0.01, 0.01))['luas_m2'];

        $dua = Geometri::ukur(json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                ['type' => 'Feature', 'properties' => [], 'geometry' => json_decode($this->kotak(110, -2, 0.01, 0.01), true)],
                ['type' => 'Feature', 'properties' => [], 'geometry' => json_decode($this->kotak(111, -2, 0.01, 0.01), true)],
            ],
        ]));

        $this->assertEqualsWithDelta($satu * 2, $dua['luas_m2'], 1.0);
        $this->assertSame(2, $dua['fitur']);
    }

    public function test_multipolygon_dijumlahkan(): void
    {
        $a = [[110, -2], [110.01, -2], [110.01, -1.99], [110, -1.99], [110, -2]];
        $b = [[111, -2], [111.01, -2], [111.01, -1.99], [111, -1.99], [111, -2]];

        $ukur = Geometri::ukur(json_encode(['type' => 'MultiPolygon', 'coordinates' => [[$a], [$b]]]));

        $this->assertGreaterThan(0, $ukur['luas_m2']);
        $this->assertEqualsWithDelta(
            Geometri::ukur(json_encode(['type' => 'Polygon', 'coordinates' => [$a]]))['luas_m2'] * 2,
            $ukur['luas_m2'], 1.0
        );
    }

    public function test_titik_penanda_tidak_menggagalkan_seluruh_berkas(): void
    {
        // Berkas survei kerap memuat titik anotasi di samping poligonnya;
        // menolak seluruh berkas karena satu titik membuat layernya
        // mustahil disimpan.
        $ukur = Geometri::ukur(json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                ['type' => 'Feature', 'geometry' => json_decode($this->kotak(110, -2, 0.01, 0.01), true)],
                ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [110.005, -1.995]]],
            ],
        ]));

        $this->assertGreaterThan(0, $ukur['luas_m2']);
        $this->assertSame(2, $ukur['fitur']);
    }

    public function test_json_rusak_menghasilkan_nol_bukan_galat(): void
    {
        $ukur = Geometri::ukur('{bukan json');

        $this->assertSame(0.0, $ukur['luas_m2']);
        $this->assertNull($ukur['titik']);
    }

    /* ---------- titik tengah dan kotak batas ---------- */

    public function test_titik_tengah_dan_kotak_batas(): void
    {
        $ukur = Geometri::ukur($this->kotak(110, -2, 0.02, 0.02));

        $this->assertEqualsWithDelta(110.008, $ukur['titik'][0], 0.005);
        $this->assertEqualsWithDelta(-1.992, $ukur['titik'][1], 0.005);
        $this->assertSame([110.0, -2.0, 110.02, -1.98], $ukur['kotak']);
    }

    public function test_hektare(): void
    {
        $this->assertSame(1.0, Geometri::hektare(10_000));
        $this->assertSame(12.3456, Geometri::hektare(123_456));
    }
}
