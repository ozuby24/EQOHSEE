<?php

namespace Tests\Unit;

use App\Support\Biaya;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BiayaTest extends TestCase
{
    /* ═══════════ biaya satuan ═══════════ */

    #[Test]
    public function biaya_per_satuan(): void
    {
        $this->assertSame(12500.0, Biaya::perSatuan(1_250_000_000, 100_000));
    }

    #[Test]
    public function volume_nol_tidak_menjadi_biaya_satuan_nol(): void
    {
        // Nol akan terbaca "sangat murah" — kebalikan dari maknanya.
        $this->assertNull(Biaya::perSatuan(1_250_000_000, 0));
        $this->assertNull(Biaya::perSatuan(1_250_000_000, null));
    }

    /* ═══════════ pemecahan selisih anggaran ═══════════ */

    #[Test]
    public function selisih_terpecah_tepat_menjadi_volume_dan_tarif(): void
    {
        // Anggaran 1 M untuk 100.000 bcm → 10.000/bcm.
        // Nyata 1,26 M untuk 120.000 bcm → 10.500/bcm.
        $v = Biaya::varians(1_000_000_000, 100_000, 1_260_000_000, 120_000);

        $this->assertTrue($v['dapatDipecah']);
        $this->assertSame(1_200_000_000.0, $v['anggaranLuwes']);
        $this->assertSame(200_000_000.0, $v['volume']);
        $this->assertSame(60_000_000.0, $v['tarif']);
        $this->assertSame(260_000_000.0, $v['total']);

        // Syarat, bukan kebetulan: bagian yang tidak menutup adalah
        // bagian yang akan dipakai berdebat.
        $this->assertEqualsWithDelta($v['total'], $v['volume'] + $v['tarif'], 0.01);
    }

    #[Test]
    public function volume_lebih_besar_tanpa_pemborosan_hanya_melahirkan_selisih_volume(): void
    {
        // Biaya satuan persis sama; yang berubah hanya banyaknya material.
        $v = Biaya::varians(1_000_000_000, 100_000, 1_300_000_000, 130_000);

        $this->assertSame(300_000_000.0, $v['volume']);
        $this->assertSame(0.0, $v['tarif']);
        $this->assertSame($v['unitAnggaran'], $v['unitNyata']);
    }

    #[Test]
    public function pemborosan_pada_volume_yang_sama_hanya_melahirkan_selisih_tarif(): void
    {
        $v = Biaya::varians(1_000_000_000, 100_000, 1_150_000_000, 100_000);

        $this->assertSame(0.0, $v['volume']);
        $this->assertSame(150_000_000.0, $v['tarif']);
    }

    #[Test]
    public function volume_turun_menghasilkan_selisih_volume_negatif(): void
    {
        $v = Biaya::varians(1_000_000_000, 100_000, 850_000_000, 80_000);

        $this->assertSame(-200_000_000.0, $v['volume']);
        $this->assertSame(50_000_000.0, $v['tarif']);   // tetap boros per satuan
        $this->assertSame(-150_000_000.0, $v['total']);
    }

    #[Test]
    public function tanpa_volume_selisih_tidak_dipecah_dan_tidak_ditebak(): void
    {
        $v = Biaya::varians(1_000_000_000, null, 1_200_000_000, 120_000);

        $this->assertFalse($v['dapatDipecah']);
        $this->assertNull($v['volume']);
        $this->assertNull($v['tarif']);
        $this->assertSame(200_000_000.0, $v['total']);
    }

    #[Test]
    public function volume_nyata_nol_tidak_membagi_nol(): void
    {
        $v = Biaya::varians(1_000_000_000, 100_000, 400_000_000, 0);

        $this->assertFalse($v['dapatDipecah']);
        $this->assertNull($v['unitNyata']);
        $this->assertSame(-600_000_000.0, $v['total']);
    }

    /* ═══════════ harga terhadap pemakaian ═══════════ */

    #[Test]
    public function selisih_harga_dan_pemakaian_menutup_totalnya(): void
    {
        // Solar: rencana 14.500/L × 1.000.000 L; nyata 15.200/L × 1.050.000 L.
        $v = Biaya::variansHargaPakai(14_500, 1_000_000, 15_200, 1_050_000);

        $this->assertTrue($v['dapatDipecah']);
        $this->assertSame(735_000_000.0, $v['harga']);   // (15.200−14.500) × 1.050.000
        $this->assertSame(725_000_000.0, $v['pakai']);   // (1.050.000−1.000.000) × 14.500
        $this->assertSame(1_460_000_000.0, $v['total']);
        $this->assertEqualsWithDelta(
            15_200 * 1_050_000 - 14_500 * 1_000_000, $v['total'], 0.01
        );
    }

    #[Test]
    public function kenaikan_harga_murni_tidak_menyentuh_bagian_pemakaian(): void
    {
        // Yang dapat dikendalikan dari pit adalah pemakaiannya, dan di
        // sini pemakaiannya tidak bergerak sama sekali.
        $v = Biaya::variansHargaPakai(14_500, 1_000_000, 16_000, 1_000_000);

        $this->assertSame(0.0, $v['pakai']);
        $this->assertSame(1_500_000_000.0, $v['harga']);
    }

    #[Test]
    public function pemborosan_murni_tidak_menyentuh_bagian_harga(): void
    {
        $v = Biaya::variansHargaPakai(14_500, 1_000_000, 14_500, 1_120_000);

        $this->assertSame(0.0, $v['harga']);
        $this->assertSame(1_740_000_000.0, $v['pakai']);
    }

    #[Test]
    public function tanpa_harga_rencana_selisih_tidak_dipisahkan(): void
    {
        $v = Biaya::variansHargaPakai(null, 1_000_000, 15_200, 1_050_000);

        $this->assertFalse($v['dapatDipecah']);
        $this->assertNull($v['harga']);
        $this->assertNull($v['pakai']);
    }

    /* ═══════════ serapan ═══════════ */

    #[Test]
    public function serapan_anggaran(): void
    {
        $this->assertSame(62.5, Biaya::serapan(625_000_000, 1_000_000_000));
        $this->assertNull(Biaya::serapan(625_000_000, 0));
        $this->assertNull(Biaya::serapan(625_000_000, null));
    }

    #[Test]
    public function serapan_dibandingkan_dengan_produksi_bukan_dengan_kalender(): void
    {
        // 60% anggaran pada produksi 45% — uangnya berjalan lebih cepat
        // daripada materialnya, meski bulannya baru keenam.
        $this->assertSame('mendahului', Biaya::bacaSerapan(60.0, 45.0)['kelas']);
    }

    #[Test]
    public function serapan_sepadan_tidak_dipersoalkan(): void
    {
        $this->assertSame('sepadan', Biaya::bacaSerapan(60.0, 55.0)['kelas']);
        $this->assertSame('sepadan', Biaya::bacaSerapan(50.0, 58.0)['kelas']);
    }

    #[Test]
    public function serapan_yang_jauh_tertinggal_juga_ditanyakan(): void
    {
        // Hemat yang tidak dapat dijelaskan biasanya berarti pencatatan
        // yang tertinggal, bukan penghematan.
        $this->assertSame('tertinggal', Biaya::bacaSerapan(30.0, 62.0)['kelas']);
    }

    #[Test]
    public function serapan_tidak_ditebak_ketika_datanya_belum_ada(): void
    {
        $this->assertSame('tak-diketahui', Biaya::bacaSerapan(null, 45.0)['kelas']);
        $this->assertSame('tak-diketahui', Biaya::bacaSerapan(60.0, null)['kelas']);
    }

    /* ═══════════ proyeksi ═══════════ */

    #[Test]
    public function proyeksi_memakai_laju_bulan_yang_sudah_lengkap(): void
    {
        // 600 juta dalam 6 bulan → 1,2 M setahun.
        $this->assertSame(1_200_000_000.0, Biaya::proyeksiTahunan(600_000_000, 6));
    }

    #[Test]
    public function proyeksi_menolak_jumlah_bulan_yang_mustahil(): void
    {
        $this->assertNull(Biaya::proyeksiTahunan(600_000_000, 0));
        $this->assertNull(Biaya::proyeksiTahunan(600_000_000, 13));
    }

    /* ═══════════ nisbah kupas ═══════════ */

    #[Test]
    public function nisbah_kupas_dan_selisihnya(): void
    {
        $this->assertSame(8.5, Biaya::nisbahKupas(850_000, 100_000));
        $this->assertNull(Biaya::nisbahKupas(850_000, 0));

        $this->assertSame(25.0, Biaya::selisihNisbah(8.0, 10.0));
        $this->assertNull(Biaya::selisihNisbah(0, 10.0));
    }

    #[Test]
    public function nisbah_yang_bergerak_jauh_menandai_perbandingan_yang_tidak_setara(): void
    {
        // Biaya per ton turun dengan sendirinya ketika nisbah turun,
        // tanpa satu pun perbaikan di lapangan.
        $selisih = Biaya::selisihNisbah(10.0, 8.0);

        $this->assertSame(-20.0, $selisih);
        $this->assertGreaterThan(Biaya::AMBANG_SELISIH_NISBAH_PERSEN, abs($selisih));
    }

    /* ═══════════ ambang tarif ═══════════ */

    #[Test]
    public function selisih_tarif_diukur_relatif_terhadap_anggarannya(): void
    {
        // Lima puluh juta pada akun 10 M adalah derau; pada akun 200 juta
        // adalah kesalahan pencatatan.
        $this->assertFalse(Biaya::tarifMenonjol(50_000_000, 10_000_000_000));
        $this->assertTrue(Biaya::tarifMenonjol(50_000_000, 200_000_000));
    }

    #[Test]
    public function selisih_tarif_yang_menghemat_pun_ditandai(): void
    {
        // Hemat sebesar itu pada satu akun juga perlu penjelasan.
        $this->assertTrue(Biaya::tarifMenonjol(-40_000_000, 200_000_000));
    }

    #[Test]
    public function tanpa_anggaran_tidak_ada_yang_menonjol(): void
    {
        $this->assertFalse(Biaya::tarifMenonjol(50_000_000, 0));
        $this->assertFalse(Biaya::tarifMenonjol(null, 200_000_000));
    }
}
