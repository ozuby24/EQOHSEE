<?php

namespace Tests\Unit;

use App\Support\Angkutan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AngkutanTest extends TestCase
{
    /* ═══════════ match factor ═══════════ */

    #[Test]
    public function match_factor_satu_ketika_kedatangan_truk_pas(): void
    {
        // 5 truk, memuat 4 menit, 1 excavator, edar 20 menit:
        // (5 × 4) / (1 × 20) = 1,0
        $this->assertSame(1.0, Angkutan::matchFactor(5, 4.0, 1, 20.0));
    }

    #[Test]
    public function match_factor_di_atas_satu_ketika_truk_berlebih(): void
    {
        $mf = Angkutan::matchFactor(7, 4.0, 1, 20.0);

        $this->assertSame(1.4, $mf);
        $this->assertSame('lebih-truk', Angkutan::bacaMatchFactor($mf)['kelas']);
    }

    #[Test]
    public function match_factor_di_bawah_satu_ketika_truk_kurang(): void
    {
        $mf = Angkutan::matchFactor(3, 4.0, 1, 20.0);

        $this->assertSame(0.6, $mf);
        $this->assertSame('kurang-truk', Angkutan::bacaMatchFactor($mf)['kelas']);
    }

    #[Test]
    public function match_factor_memperhitungkan_jumlah_alat_muat(): void
    {
        // Dua excavator melayani armada yang sama: bebannya terbagi.
        $satu = Angkutan::matchFactor(10, 4.0, 1, 20.0);
        $dua  = Angkutan::matchFactor(10, 4.0, 2, 20.0);

        $this->assertSame(2.0, $satu);
        $this->assertSame(1.0, $dua);
    }

    #[Test]
    public function match_factor_null_ketika_masukan_tidak_masuk_akal(): void
    {
        $this->assertNull(Angkutan::matchFactor(0, 4.0, 1, 20.0));
        $this->assertNull(Angkutan::matchFactor(5, 0.0, 1, 20.0));
        $this->assertNull(Angkutan::matchFactor(5, 4.0, 0, 20.0));
        $this->assertNull(Angkutan::matchFactor(5, 4.0, 1, 0.0));
    }

    #[Test]
    public function pembacaan_match_factor_null_tidak_menebak(): void
    {
        $this->assertSame('tak-diketahui', Angkutan::bacaMatchFactor(null)['kelas']);
    }

    #[Test]
    public function batas_seimbang_tidak_terlalu_ketat(): void
    {
        $this->assertSame('seimbang', Angkutan::bacaMatchFactor(0.9)['kelas']);
        $this->assertSame('seimbang', Angkutan::bacaMatchFactor(1.1)['kelas']);
        $this->assertSame('seimbang', Angkutan::bacaMatchFactor(Angkutan::MF_BAWAH)['kelas']);
        $this->assertSame('seimbang', Angkutan::bacaMatchFactor(Angkutan::MF_ATAS)['kelas']);
    }

    /* ═══════════ waktu edar ═══════════ */

    #[Test]
    public function waktu_edar_produktif_tidak_menghitung_antre(): void
    {
        // Inilah pemisahan yang menentukan: antre adalah akibat dari
        // ketidakseimbangan, dan memasukkannya ke penyebut match factor
        // membuat armada yang kelebihan truk terbaca seimbang.
        $this->assertSame(20.0, Angkutan::waktuEdar(4.0, 8.0, 2.0, 6.0));
        $this->assertSame(28.0, Angkutan::waktuEdarNyata(4.0, 8.0, 2.0, 6.0, 8.0));
    }

    #[Test]
    public function antre_yang_besar_tidak_menurunkan_match_factor(): void
    {
        $edar = Angkutan::waktuEdar(4.0, 8.0, 2.0, 6.0);
        $nyata = Angkutan::waktuEdarNyata(4.0, 8.0, 2.0, 6.0, 12.0);

        $benar = Angkutan::matchFactor(8, 4.0, 1, $edar);
        $keliru = Angkutan::matchFactor(8, 4.0, 1, $nyata);

        $this->assertSame('lebih-truk', Angkutan::bacaMatchFactor($benar)['kelas']);
        $this->assertSame('seimbang', Angkutan::bacaMatchFactor($keliru)['kelas']);
    }

    #[Test]
    public function waktu_edar_null_ketika_seluruh_komponen_kosong(): void
    {
        $this->assertNull(Angkutan::waktuEdar(0, 0, 0, 0));
        $this->assertNull(Angkutan::waktuEdar(null, null, null, null));
        $this->assertNull(Angkutan::waktuEdarNyata(null, null, null, null, 5.0));
    }

    #[Test]
    public function porsi_antre_dihitung_terhadap_waktu_edar_nyata(): void
    {
        // 8 menit antre dari 28 menit total = 28,57%
        $this->assertSame(28.57, Angkutan::porsiAntre(4.0, 8.0, 2.0, 6.0, 8.0));
        $this->assertSame(0.0, Angkutan::porsiAntre(4.0, 8.0, 2.0, 6.0, 0.0));
    }

    #[Test]
    public function rincian_edar_berjumlah_seratus_persen(): void
    {
        $rincian = Angkutan::rincianEdar(4.0, 8.0, 2.0, 6.0, 8.0);

        $this->assertCount(5, $rincian);
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($rincian, 'persen')), 0.05);
    }

    #[Test]
    public function rincian_edar_melewati_komponen_kosong(): void
    {
        $rincian = Angkutan::rincianEdar(4.0, 8.0, 2.0, 6.0, 0.0);

        $this->assertCount(4, $rincian);
        $this->assertNotContains('Antre', array_column($rincian, 'nama'));
    }

    /* ═══════════ produktivitas ═══════════ */

    #[Test]
    public function ritase_dibulatkan_ke_bawah(): void
    {
        // 8 jam = 480 menit; edar 28 menit → 17,14 rit, dan rit ke-18
        // tidak selesai sebelum shift berakhir sehingga tidak menghasilkan
        // tonase apa pun.
        $this->assertSame(17, Angkutan::ritaseTeoritis(8.0, 28.0));
    }

    #[Test]
    public function ritase_null_ketika_jam_atau_edar_kosong(): void
    {
        $this->assertNull(Angkutan::ritaseTeoritis(0.0, 28.0));
        $this->assertNull(Angkutan::ritaseTeoritis(8.0, null));
        $this->assertNull(Angkutan::ritaseTeoritis(8.0, 0.0));
    }

    #[Test]
    public function produktivitas_truk_per_jam(): void
    {
        // 91 ton per 28 menit = 195 ton/jam
        $this->assertSame(195.0, Angkutan::produktivitasTruk(91.0, 28.0));
    }

    #[Test]
    public function kecepatan_rata_menghitung_pulang_pergi(): void
    {
        // 3,4 km sekali jalan; 8 menit isi + 6 menit kosong = 14 menit
        // untuk 6,8 km → 29,14 km/jam
        $this->assertSame(29.14, Angkutan::kecepatanRata(3.4, 8.0, 6.0));
    }

    #[Test]
    public function kecepatan_rata_null_tanpa_jarak_atau_waktu(): void
    {
        $this->assertNull(Angkutan::kecepatanRata(0.0, 8.0, 6.0));
        $this->assertNull(Angkutan::kecepatanRata(3.4, 0.0, 0.0));
    }

    #[Test]
    public function utilisasi_jam_terjadwal(): void
    {
        $this->assertSame(87.5, Angkutan::utilisasi(7.0, 1.0));
        $this->assertNull(Angkutan::utilisasi(0.0, 0.0));
    }

    /* ═══════════ kepatuhan muatan ═══════════ */

    #[Test]
    public function muatan_dalam_batas_dinyatakan_patuh(): void
    {
        $muatan = array_fill(0, 24, 88.0);   // 96,7% dari 91 ton
        $h = Angkutan::kepatuhanMuatan($muatan, 91.0);

        $this->assertTrue($h['patuh']);
        $this->assertTrue($h['cukupData']);
        $this->assertSame(0, $h['lebih110']);
        $this->assertSame(0, $h['lebih120']);
    }

    #[Test]
    public function satu_muatan_di_atas_120_persen_langsung_melanggar(): void
    {
        // Batas mutlak menilai satu muatan, bukan sebarannya, sehingga ia
        // berlaku sejak penimbangan pertama — tanpa menunggu jumlah.
        $h = Angkutan::kepatuhanMuatan([115.0], 91.0);

        $this->assertFalse($h['patuh']);
        $this->assertFalse($h['lulusPuncak']);
        $this->assertSame(1, $h['lebih120']);
        $this->assertFalse($h['cukupData']);
        $this->assertStringContainsString('120%', $h['alasan']);
    }

    #[Test]
    public function rata_rata_rapi_tidak_menutupi_satu_muatan_berlebih(): void
    {
        // Justru inilah alasan batas 120% bersifat mutlak: rata-ratanya
        // 90,4 ton — masih di bawah kapasitas nominal, dan porsi di atas
        // 110% pun hanya 3,3% — namun satu truk turun membawa 130 ton.
        $muatan = array_merge(array_fill(0, 29, 89.0), [130.0]);
        $h = Angkutan::kepatuhanMuatan($muatan, 91.0);

        $this->assertLessThanOrEqual(100.0, $h['rataPersen']);
        $this->assertTrue($h['lulusRata']);
        $this->assertTrue($h['lulusPorsi']);
        $this->assertFalse($h['patuh']);
        $this->assertSame(1, $h['lebih120']);
    }

    #[Test]
    public function porsi_di_atas_110_persen_melanggar_kaidah_kedua(): void
    {
        // 5 dari 25 = 20% berada di atas 110%, batasnya 10%.
        $muatan = array_merge(array_fill(0, 20, 80.0), array_fill(0, 5, 103.0));
        $h = Angkutan::kepatuhanMuatan($muatan, 91.0);

        $this->assertSame(5, $h['lebih110']);
        $this->assertSame(20.0, $h['porsi110']);
        $this->assertFalse($h['lulusPorsi']);
        $this->assertFalse($h['patuh']);
        $this->assertSame(0, $h['lebih120']);
    }

    #[Test]
    public function kaidah_porsi_menunggu_penimbangan_yang_cukup(): void
    {
        // 1 dari 3 = 33%, dan angka itu tidak berarti apa-apa.
        $h = Angkutan::kepatuhanMuatan([80.0, 85.0, 103.0], 91.0);

        $this->assertFalse($h['cukupData']);
        $this->assertNull($h['lulusPorsi']);
        $this->assertNull($h['lulusRata']);
        $this->assertNull($h['patuh']);
        $this->assertTrue($h['lulusPuncak']);
        $this->assertStringContainsString('penimbangan', $h['alasan']);
    }

    #[Test]
    public function rata_rata_di_atas_nominal_melanggar_kaidah_pertama(): void
    {
        $muatan = array_fill(0, 25, 96.0);   // 105,5% dari 91, tetapi belum 110%
        $h = Angkutan::kepatuhanMuatan($muatan, 91.0);

        $this->assertSame(0, $h['lebih110']);
        $this->assertFalse($h['lulusRata']);
        $this->assertFalse($h['patuh']);
    }

    #[Test]
    public function tanpa_penimbangan_kepatuhan_tidak_ditebak(): void
    {
        $h = Angkutan::kepatuhanMuatan([], 91.0);

        $this->assertSame(0, $h['n']);
        $this->assertNull($h['patuh']);
        $this->assertNull($h['rata']);
    }

    #[Test]
    public function nominal_nol_tidak_menghasilkan_pembagian_nol(): void
    {
        $h = Angkutan::kepatuhanMuatan([80.0, 90.0], 0.0);

        $this->assertNull($h['patuh']);
        $this->assertNull($h['rataPersen']);
    }

    #[Test]
    public function timbangan_nol_dan_negatif_diabaikan(): void
    {
        $h = Angkutan::kepatuhanMuatan([88.0, 0.0, -5.0, 90.0], 91.0);

        $this->assertSame(2, $h['n']);
        $this->assertSame(89.0, $h['rata']);
    }

    #[Test]
    public function persen_muatan_terhadap_nominal(): void
    {
        $this->assertSame(100.0, Angkutan::persenMuatan(91.0, 91.0));
        $this->assertSame(120.9, Angkutan::persenMuatan(110.0, 91.0));
        $this->assertNull(Angkutan::persenMuatan(91.0, 0.0));
    }

    /* ═══════════ kehilangan ═══════════ */

    #[Test]
    public function tonase_hilang_karena_antre(): void
    {
        // 160 menit antre seluruh armada; edar produktif 20 menit →
        // 8 rit yang seharusnya masih sempat, × 91 ton = 728 ton.
        $this->assertSame(728.0, Angkutan::tonaseHilangAntre(160.0, 20.0, 91.0));
    }

    #[Test]
    public function tanpa_antre_tidak_ada_tonase_hilang(): void
    {
        $this->assertNull(Angkutan::tonaseHilangAntre(0.0, 20.0, 91.0));
        $this->assertNull(Angkutan::tonaseHilangAntre(160.0, null, 91.0));
        $this->assertNull(Angkutan::tonaseHilangAntre(160.0, 20.0, 0.0));
    }
}
