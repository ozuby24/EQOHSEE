<?php

namespace Tests\Unit;

use App\Support\NeracaAir;
use PHPUnit\Framework\TestCase;

/**
 * Neraca air kolam tambang.
 *
 * Perubahan satuannya sekali salah akan meleset sepuluh kali lipat
 * tanpa menimbulkan galat apa pun, jadi yang diuji lebih dulu adalah
 * persis titik itu.
 */
class NeracaAirTest extends TestCase
{
    /** Kolam 10.000 m³, terisi 4.000, tangkapan 20 ha, koefisien 0,8. */
    private function kolam(float $volume = 4000): NeracaAir
    {
        return new NeracaAir(10_000, $volume, 20, 0.8);
    }

    /* ---------- perubahan satuan ---------- */

    public function test_satu_milimeter_di_atas_satu_hektare_adalah_sepuluh_meter_kubik(): void
    {
        $satuHa = new NeracaAir(1_000_000, 0, 1, 1.0);

        $this->assertSame(10.0, $satuHa->limpasan(1),
            'Kekeliruan sepuluh kali lipat di sini menghasilkan jam yang tetap terlihat masuk akal.');
    }

    public function test_limpasan_memperhitungkan_koefisien(): void
    {
        // 50 mm × 20 ha × 10 × 0,8 = 8.000 m³
        $this->assertSame(8000.0, $this->kolam()->limpasan(50));
    }

    public function test_koefisien_hutan_pada_bukaan_tambang_membuat_perkiraan_terlalu_optimis(): void
    {
        $tambang = (new NeracaAir(10_000, 0, 20, 0.8))->limpasan(50);
        $hutan   = (new NeracaAir(10_000, 0, 20, 0.3))->limpasan(50);

        $this->assertGreaterThan($hutan * 2, $tambang,
            'Tanah tambang yang padat dan terbuka mengalirkan jauh lebih banyak.');
    }

    /* ---------- ruang dan daya tampung ---------- */

    public function test_hujan_yang_masih_tertampung_dapat_dibandingkan_dengan_ramalan_cuaca(): void
    {
        // Ruang kosong 6.000 m³ ÷ (20 ha × 10 × 0,8) = 37,5 mm
        $this->assertSame(37.5, $this->kolam()->hujanTertampungMm());
    }

    public function test_kolam_penuh_tidak_dapat_menampung_hujan_apa_pun(): void
    {
        $penuh = $this->kolam(10_000);

        $this->assertSame(0.0, $penuh->ruangKosong());
        $this->assertSame(0.0, $penuh->hujanTertampungMm());
        $this->assertTrue($penuh->akanLimpah(1));
    }

    public function test_terisi_tidak_pernah_melebihi_seratus_persen(): void
    {
        // Data lapangan kerap melaporkan volume di atas kapasitas
        // nominal ketika kolam sedang meluap.
        $this->assertSame(100.0, $this->kolam(15_000)->terisiPersen());
        $this->assertSame(0.0, $this->kolam(15_000)->ruangKosong());
    }

    /* ---------- waktu ---------- */

    public function test_jam_sampai_limpah(): void
    {
        // Ruang 6.000 m³, masuk 500, keluar 200 → bersih 300 → 20 jam
        $this->assertSame(20.0, $this->kolam()->jamSampaiLimpah(500, 200));
    }

    public function test_pompa_yang_mengimbangi_berarti_tidak_akan_melimpah(): void
    {
        $this->assertNull($this->kolam()->jamSampaiLimpah(300, 300),
            'Nol berarti melimpah sekarang juga — kebalikan dari maksudnya.');
        $this->assertNull($this->kolam()->jamSampaiLimpah(200, 500));
    }

    public function test_jam_sampai_kosong(): void
    {
        // Volume 4.000, keluar 500, masuk 100 → bersih 400 → 10 jam
        $this->assertSame(10.0, $this->kolam()->jamSampaiKosong(100, 500));
    }

    public function test_pompa_yang_tidak_mengejar_berarti_tidak_akan_kosong(): void
    {
        $this->assertNull($this->kolam()->jamSampaiKosong(500, 200));
        $this->assertNull($this->kolam()->jamSampaiKosong(300, 300));
    }

    /* ---------- kebutuhan pompa ---------- */

    public function test_kebutuhan_pompa_mengurangi_ruang_yang_masih_tersedia(): void
    {
        // Limpasan 8.000 m³, ruang kosong 6.000 → hanya 2.000 wajib
        // dipompa; dalam 4 jam berarti 500 m³/jam.
        $this->assertSame(500.0, $this->kolam()->pompaDibutuhkan(50, 4));
    }

    public function test_hujan_yang_seluruhnya_tertampung_tidak_menuntut_pompa(): void
    {
        // 20 mm → 3.200 m³, masih di bawah ruang kosong 6.000 m³.
        $this->assertSame(0.0, $this->kolam()->pompaDibutuhkan(20, 4));
    }

    public function test_jangka_waktu_nol_tidak_membagi_dengan_nol(): void
    {
        $this->assertSame(0.0, $this->kolam()->pompaDibutuhkan(50, 0));
    }

    /* ---------- perkiraan luapan ---------- */

    public function test_akan_limpah_dihitung_tanpa_pemompaan(): void
    {
        // Pompa dapat mati justru ketika hujan paling deras.
        $this->assertTrue($this->kolam()->akanLimpah(50));
        $this->assertFalse($this->kolam()->akanLimpah(30));
    }

    public function test_kolam_tanpa_daerah_tangkapan_tidak_membagi_dengan_nol(): void
    {
        $tanpa = new NeracaAir(10_000, 4_000, 0, 0.8);

        $this->assertSame(0.0, $tanpa->limpasan(100));
        $this->assertSame(0.0, $tanpa->hujanTertampungMm());
        $this->assertFalse($tanpa->akanLimpah(100));
    }
}
