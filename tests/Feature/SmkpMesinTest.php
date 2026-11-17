<?php

namespace Tests\Feature;

use App\Support\Smkp;
use Tests\TestCase;

/**
 * Mesin hitung audit SMKP Minerba.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019. Nilai pembanding pada
 * beberapa uji diambil dari dokumen audit nyata (auditi nyata) agar hasil
 * hitungnya dapat diperiksa terhadap sesuatu yang benar-benar terjadi, bukan
 * hanya terhadap rumus yang sama yang sedang diuji.
 */
class SmkpMesinTest extends TestCase
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

    public function test_elemen_i_cocok_dengan_dokumen_audit_nyata(): void
    {
        // Formulir Kriteria Audit SMKP auditi nyata: I.1=4 I.2=4 I.3=3 I.4=3 I.5=4
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
        $h = ['III.2.1'=>['v'=>2],'III.2.2'=>['v'=>'N/A'],'III.2.3'=>['v'=>'N/A']];
        $sub = collect($this->elemen('III')['sub'])->firstWhere('kode','III.2');

        $r = Smkp::rekapSub($sub, $h);
        $this->assertSame(2, $r['maks']);
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

    public function test_butir_na_mengurangi_nilai_maksimal_bukan_bobot_elemen(): void
    {
        // Elemen I (maks 19, bobot 10%): I.5 (maks 4) N/A, I.1 bernilai 2 dari 4.
        $h = $this->penuh();
        $h['I.1'] = ['v' => 2];
        $h['I.5'] = ['v' => 'N/A'];

        $r = Smkp::rekap($h);
        $e = $r['elemen']['I'];

        $this->assertSame(15, $e['maks'], 'Total Nilai Elemen Maksimal tanpa butir N/A.');
        $this->assertSame(19, $e['maksPenuh']);
        $this->assertSame(10, $e['bobot'], 'Persentase Nilai Maksimal tetap bobot Kepdirjen.');
        $this->assertEqualsWithDelta(13 / 15 * 10, $e['skor'], 1e-9);
        $this->assertSame(100, $r['bobotTerpakai']);
        $this->assertSame(round(90 + 13 / 15 * 10, 2), $r['skor'], 'Total Persentase = Σ Persentase Nilai Elemen.');
    }

    public function test_persentase_mengikuti_keterangan_formulir_kepdirjen(): void
    {
        // Kepdirjen hal. 608: Persentase Nilai Elemen = (Total Nilai Elemen :
        // Total Nilai Elemen Maksimal) × Persentase Nilai Maksimal; Total
        // Persentase = Σ Persentase Nilai Elemen.
        $h = $this->penuh();
        $h['IV.1.1'] = ['v' => 1];
        $h['II.2.1'] = ['v' => 0];
        $h['III.2.2'] = ['v' => 'N/A'];

        $r = Smkp::rekap($h);
        $harap = 0.0;
        foreach (Smkp::elemen() as $e) {
            $re = $r['elemen'][$e['kode']];
            $harap += $re['nilai'] / $re['maks'] * $e['bobot'];
        }
        $this->assertSame(round($harap, 2), $r['skor']);
    }

    /* ---------- kategori & tingkat ---------- */

    public function test_kategori_diturunkan_dari_nilai(): void
    {
        // Formulir kriteria: "KATEGORI TEMUAN (Berdasarkan Nilai)".
        $this->assertSame('Kesesuaian',            Smkp::kategoriDari(1.00)['label']);
        $this->assertSame('Ketidaksesuaian Minor', Smkp::kategoriDari(0.99)['label']);
        $this->assertSame('Ketidaksesuaian Minor', Smkp::kategoriDari(0.50)['label']);
        $this->assertSame('Ketidaksesuaian Mayor', Smkp::kategoriDari(0.49)['label']);
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

    /**
     * Bug nyata dari auditi: sub-elemen berincian (mis. III.12 beranak
     * III.12.1..III.12.5) sempat dilaporkan sebagai SATU temuan gabungan
     * berkode sub-elemen induknya, bukan satu temuan per rincian yang
     * benar-benar tidak sesuai.
     */
    /** Nilai seluruh rincian III.12: penuh kecuali yang diberi. */
    private function rinciIII12(array $ubah): array
    {
        $h = [];
        foreach (Smkp::elemen() as $e) foreach ($e['sub'] as $s) if ($s['kode'] === 'III.12') {
            foreach (Smkp::butirSub($s) as $b) $h[$b['kode']] = ['v' => $ubah[$b['kode']] ?? $b['maks']];
        }
        $this->assertCount(5, $h);
        return $h;
    }

    public function test_rincian_tidak_pernah_mayor_bila_sub_elemen_minor(): void
    {
        // Satu rincian bernilai 0 (capaiannya < 50%), tetapi sub-elemen III.12 tetap Minor:
        // temuannya Minor pada rincian itu, bukan Mayor.
        $t = Smkp::temuan($this->rinciIII12(['III.12.2' => 0, 'III.12.4' => 2]));
        $jenis = array_column($t, 'jenis', 'kode');

        $this->assertSame(['III.12.2' => 'minor', 'III.12.4' => 'minor'], array_intersect_key($jenis, ['III.12.2' => 1, 'III.12.4' => 1]));
        $this->assertArrayNotHasKey('III.12', $jenis, 'Sub-elemen Minor tidak dihitung sebagai temuan tersendiri.');
        $this->assertArrayNotHasKey('III.12.1', $jenis, 'Rincian dengan capaian penuh bukan temuan.');
        $this->assertSame(['mayor' => 0, 'minor' => 2], Smkp::hitungTemuan($this->rinciIII12(['III.12.2' => 0, 'III.12.4' => 2])));
    }

    public function test_sub_elemen_mayor_satu_temuan_tanpa_rincian(): void
    {
        $nol = array_fill_keys(['III.12.1', 'III.12.2', 'III.12.3', 'III.12.4', 'III.12.5'], 0);
        $h = $this->rinciIII12($nol);
        $h['III.12.3']['ket'] = 'Laporan tidak tepat waktu';
        $t = Smkp::temuan($h);

        $this->assertSame(['III.12' => 'mayor'], array_column($t, 'jenis', 'kode'), 'Cukup satu Mayor di sub-elemen, rincian tidak ditambahkan.');
        $this->assertSame('III.12.3: Laporan tidak tepat waktu', $t[0]['ket']);
        $this->assertSame(['mayor' => 1, 'minor' => 0], Smkp::hitungTemuan($h));
    }

    public function test_sub_elemen_berincian_menunggu_seluruh_rincian_dinilai(): void
    {
        // Mayor atau Minor baru pasti setelah seluruh rincian dinilai.
        $h = ['III.12.1' => ['v' => 0], 'III.12.2' => ['v' => 0]];
        $this->assertSame([], array_values(array_filter(Smkp::temuan($h), fn ($t) => str_starts_with($t['kode'], 'III.12'))));
    }

    public function test_temuan_terurut_dari_yang_terberat(): void
    {
        $h = ['I.1'=>['v'=>2], 'I.2'=>['v'=>0]];   // Minor lalu Mayor
        $t = Smkp::temuan($h);

        $this->assertSame('mayor', $t[0]['jenis'], 'Mayor harus di atas Minor.');
    }

    public function test_penomoran_berjalan_terpisah_per_jenis(): void
    {
        // Dua urutan terpisah — Mayor lalu Minor — bukan satu urutan gabungan;
        // format (kode perusahaan)-(kategori)-(nomor)-(tahun).
        $h = ['I.4'=>['v'=>0], 'I.1'=>['v'=>0], 'I.2'=>['v'=>2]];
        $t = Smkp::beriNomor(Smkp::temuan($h), 2025, 'AIC');

        $this->assertSame(['I.1' => 'AIC-MYR-01-2025', 'I.4' => 'AIC-MYR-02-2025', 'I.2' => 'AIC-MNR-01-2025'], array_column($t, 'nomor', 'kode'));
        $this->assertSame(['NJA-MYR-01-2026', 'MNR-03-2026'], [Smkp::nomorTemuan('mayor', 1, 2026, 'nja'), Smkp::nomorTemuan('minor', 3, 2026)]);
        // Nomor ketikan lama ikut terbaca agar penomoran melanjutkannya.
        $this->assertSame([7, 0, 3, 34, 12], [Smkp::urutanNomor('KEPS-MYR-07-2026', 'mayor'), Smkp::urutanNomor('KEPS-MYR-07-2026', 'minor'),
            Smkp::urutanNomor('MNR-03-2026', 'minor'), Smkp::urutanNomor('34-MYR-2025', 'mayor'), Smkp::urutanNomor('NJA/12/MNR/2026', 'minor')]);
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
