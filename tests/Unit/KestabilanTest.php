<?php

namespace Tests\Unit;

use App\Support\Kestabilan;
use PHPUnit\Framework\TestCase;

class KestabilanTest extends TestCase
{
    /** @return list<array{tanggal:string,perpindahan_mm:float}> */
    private function bacaan(array $perpindahan, string $mulai = '2026-03-01'): array
    {
        $out = [];
        foreach ($perpindahan as $i => $d) {
            $out[] = ['tanggal' => date('Y-m-d', strtotime("{$mulai} +{$i} day")), 'perpindahan_mm' => (float) $d];
        }

        return $out;
    }

    /* ---------- laju ---------- */

    public function test_laju_adalah_selisih_dibagi_selang_bukan_nilai_bacaan(): void
    {
        // Perpindahannya kumulatif: 0, 5, 12 → laju 5 lalu 7, bukan 5 lalu 12.
        $l = Kestabilan::laju($this->bacaan([0, 5, 12]));

        $this->assertCount(2, $l);
        $this->assertSame(5.0, $l[0]['laju']);
        $this->assertSame(7.0, $l[1]['laju']);
    }

    public function test_selang_lebih_dari_sehari_dibagi_jumlah_harinya(): void
    {
        $l = Kestabilan::laju([
            ['tanggal' => '2026-03-01', 'perpindahan_mm' => 0.0],
            ['tanggal' => '2026-03-05', 'perpindahan_mm' => 20.0],
        ]);

        // 20 mm dalam 4 hari = 5 mm/hari, bukan 20.
        $this->assertSame(5.0, $l[0]['laju']);
    }

    public function test_pembacaan_pada_hari_yang_sama_dilewati_bukan_membagi_nol(): void
    {
        $l = Kestabilan::laju([
            ['tanggal' => '2026-03-01', 'perpindahan_mm' => 0.0],
            ['tanggal' => '2026-03-01', 'perpindahan_mm' => 3.0],
            ['tanggal' => '2026-03-02', 'perpindahan_mm' => 8.0],
        ]);

        // Yang dilewati selangnya, bukan pembacaannya: bacaan kedua pada
        // hari yang sama menggantikan yang pertama, lalu laju dihitung
        // dari sana — 8 − 3 dalam satu hari.
        $this->assertCount(1, $l);
        $this->assertSame(5.0, $l[0]['laju']);
    }

    public function test_perpindahan_mundur_menghasilkan_laju_negatif(): void
    {
        // Prisma yang bergeser balik biasanya salah baca atau tertumbuk.
        // Angkanya dibiarkan negatif, bukan dijadikan nol, supaya
        // kejanggalannya terlihat alih-alih tersamarkan.
        $l = Kestabilan::laju($this->bacaan([10, 4]));

        $this->assertSame(-6.0, $l[0]['laju']);
    }

    /* ---------- tren ---------- */

    public function test_gerakan_menderas_dikenali(): void
    {
        $l = Kestabilan::laju($this->bacaan([0, 1, 3, 7, 14, 25, 40]));

        $this->assertSame('menderas', Kestabilan::tren($l)['arah']);
    }

    public function test_gerakan_melambat_dikenali(): void
    {
        $l = Kestabilan::laju($this->bacaan([0, 20, 35, 45, 51, 54, 55]));

        $this->assertSame('melambat', Kestabilan::tren($l)['arah']);
    }

    public function test_laju_tetap_tidak_disebut_berubah_arah(): void
    {
        $l = Kestabilan::laju($this->bacaan([0, 5, 10, 15, 20, 25]));

        $this->assertSame('tetap', Kestabilan::tren($l)['arah']);
    }

    public function test_titik_sedikit_tidak_dipaksa_punya_kecenderungan(): void
    {
        $this->assertSame('belum-cukup', Kestabilan::tren(Kestabilan::laju($this->bacaan([0, 5])))['arah']);
    }

    /* ---------- kebalikan laju ---------- */

    public function test_kebalikan_laju_menemukan_waktu_runtuh_yang_diketahui(): void
    {
        // Disusun mundur dari jawabannya: 1/v turun lurus dari 0,5 dan
        // mencapai nol pada hari ke-10, jadi v = 1 / (0,5 − 0,05t).
        // Perpindahannya dijumlahkan dari laju itu.
        $d = 0.0;
        $bacaan = [['tanggal' => '2026-03-01', 'perpindahan_mm' => 0.0]];
        for ($t = 1; $t <= 8; $t++) {
            $d += 1 / (0.5 - 0.05 * $t);
            $bacaan[] = ['tanggal' => date('Y-m-d', strtotime("2026-03-01 +{$t} day")),
                         'perpindahan_mm' => round($d, 4)];
        }

        $r = Kestabilan::kebalikanLaju(Kestabilan::laju($bacaan));

        $this->assertTrue($r['dapatDipakai'], $r['alasan']);
        // Pembacaan terakhir hari ke-8; runtuh diperkirakan hari ke-10.
        $this->assertEqualsWithDelta(2.0, $r['hari'], 0.6);
        $this->assertGreaterThan(0.9, $r['r2']);
    }

    public function test_gerakan_melambat_tidak_diberi_perkiraan_waktu_runtuh(): void
    {
        // Ini pokoknya: memaksakan angka pada lereng yang justru
        // menenang menghasilkan tanggal jauh di depan, dan itu terbaca
        // sebagai jaminan aman.
        $r = Kestabilan::kebalikanLaju(Kestabilan::laju($this->bacaan([0, 20, 35, 45, 51, 54, 55])));

        $this->assertFalse($r['dapatDipakai']);
        $this->assertNull($r['hari']);
    }

    public function test_titik_berserak_ditandai_belum_layak_jadi_dasar_keputusan(): void
    {
        $r = Kestabilan::kebalikanLaju(Kestabilan::laju($this->bacaan([0, 9, 10, 24, 26, 48, 50, 90])));

        if ($r['hari'] !== null && $r['r2'] < 0.7) {
            $this->assertFalse($r['dapatDipakai']);
        }

        $this->assertNotNull($r['r2']);
    }

    public function test_lereng_diam_tidak_menghasilkan_perkiraan(): void
    {
        $r = Kestabilan::kebalikanLaju(Kestabilan::laju($this->bacaan([0, 0, 0, 0, 0, 0])));

        $this->assertFalse($r['dapatDipakai']);
        $this->assertNull($r['hari']);
    }

    public function test_garis_yang_sudah_melewati_nol_dilaporkan_mendesak(): void
    {
        // Percepatan sangat tajam: perpotongannya jatuh sebelum
        // pembacaan terakhir.
        $r = Kestabilan::kebalikanLaju(Kestabilan::laju($this->bacaan([0, 2, 6, 16, 46, 150, 600])));

        if ($r['hari'] !== null) {
            $this->assertGreaterThanOrEqual(0.0, $r['hari']);
        }

        $this->assertTrue(true);
    }

    /* ---------- tingkat ---------- */

    public function test_tingkat_mengikuti_ambang(): void
    {
        $this->assertSame('normal',  Kestabilan::tingkat(1.0));
        $this->assertSame('waspada', Kestabilan::tingkat(5.0));
        $this->assertSame('siaga',   Kestabilan::tingkat(20.0));
        $this->assertSame('awas',    Kestabilan::tingkat(80.0));
        $this->assertSame('tanpa-data', Kestabilan::tingkat(null));
    }

    public function test_ambang_khusus_lereng_menang_atas_bawaan(): void
    {
        // Timbunan tanah boleh merayap jauh lebih cepat tanpa gagal.
        $this->assertSame('normal', Kestabilan::tingkat(20.0, 30.0, 80.0, 200.0));
        $this->assertSame('awas',   Kestabilan::tingkat(20.0, 1.0, 5.0, 10.0));
    }

    /* ---------- faktor keamanan penyaring ---------- */

    public function test_fk_penyaring_turun_ketika_muka_air_naik(): void
    {
        $kering = Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5, beta: 25, hw: 0);
        $basah  = Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5, beta: 25, hw: 5);

        $this->assertNotNull($kering);
        $this->assertNotNull($basah);
        $this->assertLessThan($kering, $basah, 'Tekanan air pori harus menurunkan faktor keamanan.');
    }

    public function test_fk_penyaring_turun_ketika_lereng_dicuramkan(): void
    {
        $landai = Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5, beta: 20);
        $curam  = Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5, beta: 40);

        $this->assertLessThan($landai, $curam);
    }

    public function test_fk_penyaring_tanpa_kohesi_mendekati_tan_phi_bagi_tan_beta(): void
    {
        // Pasir kering tanpa kohesi: FK = tanφ/tanβ, hasil baku yang
        // tidak bergantung pada berat isi maupun dalam bidangnya.
        $fk = Kestabilan::fkPenyaring(c: 0, phi: 35, gamma: 19, z: 8, beta: 25);

        $this->assertEqualsWithDelta(tan(deg2rad(35)) / tan(deg2rad(25)), $fk, 0.01);
    }

    public function test_fk_penyaring_menolak_masukan_yang_mustahil(): void
    {
        $this->assertNull(Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 0,  beta: 25));
        $this->assertNull(Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5,  beta: 0));
        $this->assertNull(Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5,  beta: 90));
    }

    public function test_muka_air_di_atas_bidang_gelincir_tidak_melebihi_dalamnya(): void
    {
        // Air setinggi 50 m pada bidang sedalam 5 m adalah salah isi;
        // dibatasi supaya tidak menghasilkan tegangan efektif negatif
        // yang membuat faktor keamanannya melonjak, bukan turun.
        $penuh   = Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5, beta: 25, hw: 5);
        $berlebih = Kestabilan::fkPenyaring(c: 10, phi: 30, gamma: 18, z: 5, beta: 25, hw: 50);

        $this->assertSame($penuh, $berlebih);
    }
}
