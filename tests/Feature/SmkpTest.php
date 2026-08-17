<?php

namespace Tests\Feature;

use App\Support\Smkp;
use Tests\TestCase;

/**
 * Mesin hitung audit SMKP Minerba.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019. Nilai pembanding pada
 * beberapa uji diambil dari dokumen audit nyata (PT CAM 2025) agar hasil
 * hitungnya dapat diperiksa terhadap sesuatu yang benar-benar terjadi, bukan
 * hanya terhadap rumus yang sama yang sedang diuji.
 */
class SmkpTest extends TestCase
{
    /** Seluruh butir terisi nilai penuh. */
    private function penuh(): array
    {
        $h = [];
        foreach (Smkp::butir() as $b) $h[$b['kode']] = ['v' => $b['maks']];
        return $h;
    }

    private function elemen(string $kode): array
    {
        foreach (Smkp::elemen() as $e) if ($e['kode'] === $kode) return $e;
        $this->fail("Elemen {$kode} tidak ada.");
    }

    /* ---------- struktur acuan ---------- */

    public function test_struktur_sesuai_lampiran_ii_kepdirjen(): void
    {
        $el = Smkp::elemen();
        $this->assertCount(7, $el, 'Kepdirjen 185.K/2019 menetapkan 7 elemen.');
        $this->assertSame(51, array_sum(array_map(fn($e) => count($e['sub']), $el)));
        $this->assertSame(100, array_sum(array_column($el, 'bobot')), 'Bobot elemen harus genap 100%.');
    }

    public function test_bobot_tiap_elemen_sesuai_acuan(): void
    {
        $harap = ['I'=>10,'II'=>15,'III'=>17,'IV'=>35,'V'=>15,'VI'=>3,'VII'=>5];
        foreach (Smkp::elemen() as $e) {
            $this->assertSame($harap[$e['kode']], $e['bobot'], "Bobot elemen {$e['kode']} meleset.");
        }
    }

    public function test_tiap_sub_elemen_menyebut_halaman_acuannya(): void
    {
        // Rujukan halaman inilah yang membuat tiap angka dapat ditelusuri
        // kembali ke Kepdirjen; tanpa itu angkanya tidak dapat diperiksa.
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $this->assertNotEmpty($s['ref'] ?? null, "Sub-elemen {$s['kode']} tanpa rujukan halaman.");
            }
        }
    }

    public function test_nilai_maks_diturunkan_bukan_disimpan_ganda(): void
    {
        // Pada sumber aslinya nilai induk sempat berbeda dari jumlah rinciannya
        // (V.5 tertulis 8 padahal rinciannya 20). Menurunkannya menutup celah itu.
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                if (empty($s['subsub'])) continue;

                $this->assertArrayNotHasKey('maks', $s,
                    "Sub-elemen {$s['kode']} punya rincian, nilai maksnya tidak boleh disimpan terpisah.");
                $this->assertSame(
                    array_sum(array_column($s['subsub'], 'maks')),
                    Smkp::maksSub($s),
                    "Nilai maks {$s['kode']} harus sama dengan jumlah rinciannya."
                );
            }
        }
    }

    /* ---------- perhitungan ---------- */

    public function test_seluruh_butir_penuh_menghasilkan_nilai_sempurna(): void
    {
        $r = Smkp::rekap($this->penuh());
        $this->assertSame(100.0, $r['skor']);
        $this->assertSame('Baik', $r['tingkat']['label']);
        $this->assertSame(Smkp::totalNilai(), (int) $r['nilai']);
    }

    public function test_seluruh_butir_nol_menghasilkan_nilai_nol(): void
    {
        $h = [];
        foreach (Smkp::butir() as $b) $h[$b['kode']] = ['v' => 0];

        $r = Smkp::rekap($h);
        $this->assertSame(0.0, $r['skor']);
        $this->assertSame('Perlu Perhatian Serius', $r['tingkat']['label']);
    }

    public function test_belum_dinilai_dihitung_nol_bukan_dilewati(): void
    {
        // Kalau butir kosong dilewati, audit yang baru berjalan 10% akan
        // tampak bernilai tinggi — itu menyesatkan pembacanya.
        $r = Smkp::rekap([]);
        $this->assertSame(0.0, $r['skor']);
        $this->assertSame(0, $r['dinilai']);
        $this->assertGreaterThan(0, $r['berlaku']);
    }

    public function test_elemen_i_cocok_dengan_dokumen_audit_pt_cam(): void
    {
        // Formulir Kriteria Audit SMKP PT CAM 2025: I.1=4 I.2=4 I.3=3 I.4=3 I.5=4
        // dari maksimum 19 — dokumen mencatat capaian 18.
        $h = ['I.1'=>['v'=>4],'I.2'=>['v'=>4],'I.3'=>['v'=>3],'I.4'=>['v'=>3],'I.5'=>['v'=>4]];
        $r = Smkp::rekapElemen($this->elemen('I'), $h);

        $this->assertSame(18.0, $r['nilai']);
        $this->assertSame(19,   $r['maks']);
    }

    public function test_nilai_di_luar_rentang_tidak_mengangkat_capaian(): void
    {
        $h = ['I.1'=>['v'=>999],'I.2'=>['v'=>-5]];
        $r = Smkp::rekapSub($this->elemen('I')['sub'][0], $h);

        $this->assertSame(4.0, $r['nilai'], 'Nilai di atas maks dipotong ke maks.');
        $this->assertLessThanOrEqual(1.0, $r['capaian']);
    }

    public function test_sub_elemen_berrincian_dinilai_lewat_rinciannya(): void
    {
        // II.2 Manajemen Risiko: 5 rincian berjumlah 15.
        $h = ['II.2.1'=>['v'=>4],'II.2.2'=>['v'=>3],'II.2.3'=>['v'=>2],
              'II.2.4'=>['v'=>3],'II.2.5'=>['v'=>3]];
        $sub = collect($this->elemen('II')['sub'])->firstWhere('kode','II.2');

        $r = Smkp::rekapSub($sub, $h);
        $this->assertSame(15,   $r['maks']);
        $this->assertSame(15.0, $r['nilai']);
        $this->assertSame(5,    $r['dinilai']);
    }

    /* ---------- N/A ---------- */

    public function test_butir_na_dikeluarkan_dari_pembagi(): void
    {
        $h = ['I.1'=>['v'=>4],'I.2'=>['v'=>4],'I.3'=>['v'=>3],'I.4'=>['v'=>4],'I.5'=>['v'=>'N/A']];
        $r = Smkp::rekapElemen($this->elemen('I'), $h);

        $this->assertSame(15, $r['maks'], 'I.5 bernilai 4 dan harus keluar dari maksimum 19.');
        $this->assertSame(1.0, $r['capaian'], 'Butir N/A tidak boleh menghukum capaian.');
    }

    public function test_tambang_terbuka_menandai_butir_bawah_tanah_sebagai_na(): void
    {
        // III.2.2 (Kepala Tambang Bawah Tanah) dan III.2.3 (Kepala Kapal Keruk)
        // tidak berlaku bagi tambang terbuka.
        $h = ['III.2.1'=>['v'=>4],'III.2.2'=>['v'=>'N/A'],'III.2.3'=>['v'=>'N/A']];
        $sub = collect($this->elemen('III')['sub'])->firstWhere('kode','III.2');

        $r = Smkp::rekapSub($sub, $h);
        $this->assertSame(4, $r['maks']);
        $this->assertSame(1, $r['berlaku']);
        $this->assertSame('Kesesuaian', $r['kategori']['label']);
    }

    public function test_elemen_yang_seluruhnya_na_tidak_menggerus_nilai_akhir(): void
    {
        // Perusahaan tanpa kegiatan peledakan: seluruh IV.5 ditandai N/A.
        $h = $this->penuh();
        foreach (['IV.5.1','IV.5.2','IV.5.3','IV.5.4'] as $k) $h[$k] = ['v' => 'N/A'];

        $r = Smkp::rekap($h);
        $this->assertSame(100.0, $r['skor'], 'Capaian penuh tetap 100 walau sebagian butir tidak berlaku.');
        $this->assertLessThan(Smkp::totalNilai(), $r['maks']);
    }

    /* ---------- kategori & tingkat ---------- */

    public function test_kategori_diturunkan_dari_nilai(): void
    {
        /* Formulir kriteria: "KATEGORI TEMUAN (Berdasarkan Nilai)".
           Ambang mayor 50%, sama dengan lampiran dan sistem rujukan.
           Angka itu dikunci di sini supaya perubahannya tidak pernah
           lolos diam-diam — mengubahnya mengubah jumlah temuan mayor
           yang dilaporkan ke inspektur tambang. */
        $mayorMin = (float) collect(Smkp::kategori())->firstWhere('kode', 'minor')['min'];
        $this->assertSame(50.0, $mayorMin, 'Ambang mayor berubah tanpa disengaja.');

        $this->assertSame('Kesesuaian',            Smkp::kategoriDari(1.00)['label']);
        $this->assertSame('Ketidaksesuaian Minor', Smkp::kategoriDari(0.99)['label']);
        $this->assertSame('Ketidaksesuaian Minor', Smkp::kategoriDari(0.50)['label']);
        $this->assertSame('Ketidaksesuaian Minor', Smkp::kategoriDari($mayorMin / 100)['label']);
        $this->assertSame('Ketidaksesuaian Mayor', Smkp::kategoriDari(($mayorMin - 1) / 100)['label']);
        $this->assertSame('Ketidaksesuaian Mayor', Smkp::kategoriDari(0.00)['label']);
    }

    public function test_tingkat_penerapan_sesuai_ambang(): void
    {
        $this->assertSame('Baik',                   Smkp::tingkatDari(85)['label']);
        $this->assertSame('Perlu Perbaikan',        Smkp::tingkatDari(84)['label']);
        $this->assertSame('Perlu Perbaikan',        Smkp::tingkatDari(60)['label']);
        $this->assertSame('Perlu Perhatian Serius', Smkp::tingkatDari(59)['label']);
    }

    /* ---------- temuan ---------- */

    public function test_temuan_hanya_muncul_untuk_yang_sudah_dinilai_dan_belum_sesuai(): void
    {
        $h = ['I.1'=>['v'=>1], 'I.2'=>['v'=>2], 'I.3'=>['v'=>3]];   // I.4, I.5 belum dinilai
        $t = Smkp::temuan($h);

        $kode = array_column($t, 'kode');
        $this->assertContains('I.1', $kode, 'Capaian 25% adalah Mayor.');
        $this->assertContains('I.2', $kode, 'Capaian 50% adalah Minor.');
        $this->assertNotContains('I.3', $kode, 'Capaian penuh bukan temuan.');
        $this->assertNotContains('I.4', $kode, 'Yang belum dinilai belum jadi temuan.');
    }

    public function test_temuan_terurut_menurut_urutan_kriteria(): void
    {
        $h = ['I.1'=>['v'=>2], 'I.2'=>['v'=>0]];   // Minor lalu Mayor

        /* Urutan berkas, bukan urutan berat. Nomor NC diturunkan dari
           urutan ini dan disebut dalam rapat penutupan; menomori menurut
           berat memindahkan nomor setiap kali satu nilai berubah, sehingga
           "temuan nomor 3" pada risalah menunjuk temuan lain minggu depan. */
        $this->assertSame(['I.1', 'I.2'], array_column(Smkp::temuan($h), 'kode'));
    }

    public function test_mayor_sub_elemen_berincian_melekat_pada_induknya(): void
    {
        // III.2 punya tiga rincian bermaksimum 4. Agregat 1/12 = 8% → mayor.
        $t = Smkp::temuan(['III.2.1'=>['v'=>1], 'III.2.2'=>['v'=>0], 'III.2.3'=>['v'=>0]]);

        /* Satu kegagalan sub-elemen dinyatakan sekali, pada sub-elemennya.
           Memecahnya jadi tiga mayor per rincian melipatgandakan kegagalan
           yang sama dan membuat rekapitulasi terbaca lebih buruk daripada
           keadaannya. */
        $this->assertSame([['III.2', 'mayor']], array_map(
            fn ($x) => [$x['kode'], $x['jenis']], $t));
    }

    public function test_rincian_yang_tertinggal_jadi_minor_sendiri_bila_induknya_berjalan(): void
    {
        // Agregat 10/12 = 83% → induknya berjalan; dua rincian belum penuh.
        $t = Smkp::temuan(['III.2.1'=>['v'=>4], 'III.2.2'=>['v'=>3], 'III.2.3'=>['v'=>3]]);

        /* Masing-masing perlu tindakan perbaikannya sendiri, jadi
           masing-masing jadi temuan sendiri. */
        $this->assertSame([['III.2.2', 'minor'], ['III.2.3', 'minor']], array_map(
            fn ($x) => [$x['kode'], $x['jenis']], $t));

        $this->assertSame('III.2 Penunjukan KTT/Kepala Tambang', $t[0]['induk'],
            'Rincian harus menyebut sub-elemen induknya di lembar rekapitulasi.');
    }

    public function test_rincian_bernilai_nol_tetap_minor_bila_agregat_induknya_sehat(): void
    {
        // Agregat 8/12 = 67% → induknya berjalan meski satu rincian nol.
        $t = Smkp::temuan(['III.2.1'=>['v'=>4], 'III.2.2'=>['v'=>4], 'III.2.3'=>['v'=>0]]);

        /* Mayor adalah pernyataan tentang SUB-ELEMEN, bukan tentang satu
           butir. Butir nol di dalam sub-elemen yang berjalan tetap minor. */
        $this->assertSame([['III.2.3', 'minor']], array_map(
            fn ($x) => [$x['kode'], $x['jenis']], $t));

        /* Labelnya harus ikut jenisnya. Dihitung ulang dari capaian butir
           — yang di sini nol — barisnya akan berbunyi "minor" pada kolom
           jenis dan "Ketidaksesuaian Mayor" pada kolom label. */
        $this->assertSame('Ketidaksesuaian Minor', $t[0]['label']);
    }

    public function test_penomoran_membawa_urutan_berjalan_dan_kode_per_jenis(): void
    {
        $h = ['I.1'=>['v'=>0], 'I.2'=>['v'=>0], 'I.4'=>['v'=>2]];
        $t = Smkp::beriNomor(Smkp::temuan($h), 'CAM');

        /* NC-xx satu urutan berjalan — itu yang disebut dalam rapat.
           {kode perusahaan}-MAY/MIN-xx urutan terpisah per jenis — itu yang
           dipakai dalam surat-menyurat antar-perusahaan, tempat "NC-01"
           saja tidak cukup menunjuk temuan siapa. */
        $this->assertSame(['NC-01','NC-02','NC-03'], array_column($t, 'nomor'));
        $this->assertSame(['CAM-MAY-01','CAM-MAY-02','CAM-MIN-01'], array_column($t, 'kode_nc'));
    }

    public function test_kode_nc_jatuh_ke_awalan_bawaan_bila_perusahaan_tak_berkode(): void
    {
        $t = Smkp::beriNomor(Smkp::temuan(['I.1'=>['v'=>0]]), '');

        $this->assertSame('NC-MAY-01', $t[0]['kode_nc']);
    }

    public function test_hitung_temuan_per_jenis(): void
    {
        $h = ['I.1'=>['v'=>0], 'I.2'=>['v'=>2], 'I.3'=>['v'=>3]];
        $this->assertSame(['mayor'=>1,'minor'=>1], Smkp::hitungTemuan($h));
    }

    public function test_urutan_jenis_sql_berlaku_lintas_basis_data(): void
    {
        // CASE WHEN adalah SQL baku; FIELD() hanya ada di MySQL.
        $sql = Smkp::urutJenisSql();
        $this->assertStringContainsString('CASE', $sql);
        $this->assertStringNotContainsString('FIELD(', $sql);
    }
}
