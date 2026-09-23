<?php

namespace Tests\Unit;

use App\Support\Frop\Penilaian as P;
use PHPUnit\Framework\TestCase;

/**
 * Aturan penilaian observasi operator loader (FROP).
 *
 * Tiap kesalahan rumus berkas kerja asalnya yang dikoreksi punya ujinya
 * sendiri di sini, dengan contoh yang di Excel memberi jawaban salah.
 * Tanpa uji itu, "penyederhanaan" berikutnya paling mungkin
 * mengembalikan persis rumus yang dulu salah — karena rumus itulah yang
 * tertulis di berkas yang dijadikan acuan.
 */
class FropPenilaianTest extends TestCase
{
    private function sesi(array $x = []): array
    {
        return array_merge([
            'id' => 1, 'tanggal' => '2026-07-01', 'unit' => 'Ex 700', 'operator' => 'Operator A',
            'level' => 'average', 'plan_ct' => null,
            'spotting' => 8, 'digging' => 10, 'swl' => 7, 'dump' => 3, 'swe' => 5,
            'loading_detik' => 80, 'n_passing' => '5', 'bucket_heap' => true,
            'target_pty' => 600, 'aktual_pty' => 570, 'temuan' => null, 'status_ca' => 'Open',
        ], $x);
    }

    public function test_aktual_ct_menjumlah_empat_komponen_tanpa_spotting(): void
    {
        $this->assertSame(25.0, P::aktualCt($this->sesi(['spotting' => 99])));
    }

    public function test_aktual_ct_kosong_bila_satu_komponen_kosong(): void
    {
        /* Excel hanya memeriksa kolom Digging; SWE yang kosong dihitung
           nol dan CT tampak lebih cepat. */
        $this->assertNull(P::aktualCt($this->sesi(['swe' => null])));
        $this->assertNull(P::onTarget($this->sesi(['swe' => null])));
    }

    public function test_plan_mengikuti_level_atau_isian_sesi(): void
    {
        $this->assertSame(24.0, P::plan($this->sesi(['level' => 'easy'])));
        $this->assertSame(28.0, P::plan($this->sesi(['level' => 'average'])));
        $this->assertSame(31.0, P::plan($this->sesi(['level' => 'severe'])));
        $this->assertSame(22.0, P::plan($this->sesi(['level' => 'severe', 'plan_ct' => 22])));
    }

    public function test_on_target_bila_ct_sama_dengan_plan(): void
    {
        $this->assertTrue(P::onTarget($this->sesi(['level' => 'easy', 'digging' => 9, 'swl' => 7, 'dump' => 3, 'swe' => 5])));
        $this->assertFalse(P::onTarget($this->sesi(['level' => 'easy', 'digging' => 9.1, 'swl' => 7, 'dump' => 3, 'swe' => 5])));
    }

    /** Koreksi 1: rekomendasi dibandingkan dengan plan material, bukan 22 detik. */
    public function test_rekomendasi_memakai_plan_material_bukan_22_detik(): void
    {
        // CT 30 pada material severe (plan 31): ON TARGET. Excel: 30 > 22 → coaching digging.
        $s = $this->sesi(['level' => 'severe', 'digging' => 14, 'swl' => 8, 'dump' => 3, 'swe' => 5]);

        $this->assertTrue(P::onTarget($s));
        $this->assertSame('pertahankan', P::rekomendasi($s)['kode']);
    }

    public function test_urutan_rekomendasi_coaching(): void
    {
        $over = ['level' => 'easy', 'swl' => 7, 'dump' => 3, 'swe' => 5];

        $this->assertSame('digging', P::rekomendasi($this->sesi($over + ['digging' => 11, 'spotting' => 20]))['kode']);
        $this->assertSame('spotting', P::rekomendasi($this->sesi($over + ['digging' => 10, 'spotting' => 11]))['kode']);
        $this->assertSame('metode', P::rekomendasi($this->sesi($over + ['digging' => 10, 'spotting' => 10]))['kode']);
    }

    /** Koreksi 6: sesi tanpa PTY bukan "produksi tercapai". */
    public function test_kesimpulan_tanpa_pty_tidak_disebut_tercapai(): void
    {
        $this->assertSame('pty_kosong', P::kesimpulan($this->sesi(['aktual_pty' => null]))['kode']);
        $this->assertSame('tercapai', P::kesimpulan($this->sesi(['aktual_pty' => 540]))['kode']); // tepat 90%
    }

    public function test_urutan_kesimpulan_saat_pty_rendah(): void
    {
        $rendah = ['aktual_pty' => 400];

        $this->assertSame('ct_over', P::kesimpulan($this->sesi(['digging' => 20] + $rendah))['kode']);
        $this->assertSame('loading_pass_heap', P::kesimpulan($this->sesi(
            ['loading_detik' => 91, 'n_passing' => '6', 'bucket_heap' => false] + $rendah))['kode']);
        $this->assertSame('loading', P::kesimpulan($this->sesi(['loading_detik' => 91, 'n_passing' => '6'] + $rendah))['kode']);
        $this->assertSame('passing', P::kesimpulan($this->sesi(['n_passing' => '6', 'bucket_heap' => false] + $rendah))['kode']);
        $this->assertSame('heap', P::kesimpulan($this->sesi(['bucket_heap' => false] + $rendah))['kode']);
        $this->assertSame('faktor_lain', P::kesimpulan($this->sesi($rendah))['kode']);
        // Tepat 1:30 belum melebihi standar.
        $this->assertSame('faktor_lain', P::kesimpulan($this->sesi(['loading_detik' => 90] + $rendah))['kode']);
    }

    /** Excel membandingkan teks '5' > 5 dan menjawab benar. */
    public function test_n_passing_teks_dan_rentang(): void
    {
        $this->assertFalse(P::passingLewat($this->sesi(['n_passing' => '5'])));
        $this->assertTrue(P::passingLewat($this->sesi(['n_passing' => '5-6'])));
        $this->assertSame(6.0, P::angkaPassing('5 – 6'));
        $this->assertNull(P::angkaPassing(''));
    }

    public function test_komponen_dibagi_menurut_rasio_plan(): void
    {
        $k = collect(P::komponen($this->sesi(['level' => 'severe'])))->keyBy('kunci');

        $this->assertSame(12.7, $k['digging']['plan']);   // 31 × 9/22
        $this->assertSame(8.5, $k['swl']['plan']);        // 31 × 6/22
        $this->assertSame(4.2, $k['dump']['plan']);       // 31 × 3/22
        $this->assertSame(5.6, $k['swe']['plan']);        // 31 × 4/22
    }

    /** Koreksi 8: status sesi dari CT sesi itu sendiri. */
    public function test_status_sesi(): void
    {
        $this->assertSame('excellent', P::statusSesi($this->sesi())['kode']);
        $this->assertSame('good', P::statusSesi($this->sesi(['aktual_pty' => 480]))['kode']);
        $this->assertSame('ct_ok_pty_low', P::statusSesi($this->sesi(['aktual_pty' => 300]))['kode']);
        $this->assertSame('good_pty', P::statusSesi($this->sesi(['digging' => 20]))['kode']);
        $this->assertSame('critical', P::statusSesi($this->sesi(['digging' => 20, 'aktual_pty' => 300]))['kode']);
    }

    /** Koreksi 3: tren = sesi terakhir − sesi pertama, dapat memburuk. */
    public function test_tren_operator_menurut_urutan_waktu(): void
    {
        $naik = P::rekapOperator([$this->sesi(['digging' => 8]), $this->sesi(['digging' => 12])]);
        $this->assertSame(4.0, $naik['tren']);
        $this->assertSame('menurun', $naik['arah']);

        $turun = P::rekapOperator([$this->sesi(['digging' => 12]), $this->sesi(['digging' => 8])]);
        $this->assertSame('membaik', $turun['arah']);

        $this->assertNull(P::rekapOperator([$this->sesi()])['tren']);
    }

    public function test_performer_selaras_dengan_catatan_coaching(): void
    {
        $this->assertSame('top', P::performer(0.9, 0.85)['kode']);
        $this->assertSame('average', P::performer(1.0, 0.76)['kode']);   // CT konsisten, PTY 76%
        $this->assertSame('coaching', P::performer(1.0, 0.5)['kode']);   // "Perlu Coaching – PTY rendah"
        $this->assertSame('coaching', P::performer(0.4, 0.95)['kode']);
        $this->assertSame('average', P::performer(0.6, null)['kode']);

        $r = P::rekapOperator([$this->sesi(['aktual_pty' => 300])]);
        $this->assertSame('coaching', $r['performer']['kode']);
        $this->assertStringStartsWith('Perlu Coaching – PTY rendah', $r['catatan']);
    }

    public function test_butir_dan_kategori_temuan(): void
    {
        $this->assertSame(['Track melintang bench', 'sudut passing 90°', 'Front undulating', 'MF 0.9'],
            P::butirTemuan('Track melintang bench, sudut passing 90°. Front undulating; MF 0.9'));
        $this->assertSame([], P::butirTemuan(' - '));

        $this->assertSame('unit', P::kategoriButir('kuku bucket tumpul'));
        $this->assertSame('jalan', P::kategoriButir('jalan disposal undulating'));
        $this->assertSame('front', P::kategoriButir('front rump up menyebabkan hauler selip'));
        $this->assertSame('hauler', P::kategoriButir('MF 0.9'));
        $this->assertSame('hauler', P::kategoriButir('Exc menggantung'));
        $this->assertSame('material', P::kategoriButir('material keras'));
        $this->assertSame('metode', P::kategoriButir('Track sejajar bench'));
        $this->assertSame('lain', P::kategoriButir('operator baru'));
    }

    /** Koreksi 7: berulang hanya bila kategori sama muncul lagi di unit yang sama. */
    public function test_berulang_per_unit(): void
    {
        $u = P::berulang([
            ['id' => 1, 'unit' => 'EX 750', 'temuan' => 'front undulating'],
            ['id' => 2, 'unit' => 'Ex 699', 'temuan' => 'front undulating'],
            ['id' => 3, 'unit' => 'Ex750', 'temuan' => 'boulder; material keras'],
            ['id' => 4, 'unit' => 'EX699/PC1250', 'temuan' => 'jalan licin'],
        ]);

        $this->assertSame([], $u[1]);
        $this->assertSame([], $u[2]);        // unit lain
        $this->assertSame(['front'], $u[3]); // "EX 750" = "Ex750"
        $this->assertSame([], $u[4]);
    }

    public function test_pekan_bulan(): void
    {
        $p = P::pekan(2026, 2);
        $this->assertSame(['2026-02-01', '2026-02-07'], [$p[0][0]->toDateString(), $p[0][1]->toDateString()]);
        $this->assertSame(['2026-02-22', '2026-02-28'], [$p[3][0]->toDateString(), $p[3][1]->toDateString()]);
        $this->assertSame('2026-07-31', P::pekan(2026, 7)[3][1]->toDateString());
    }

    /** Koreksi 4: persentase dibagi sesi yang benar-benar dinilai. */
    public function test_kpi_membagi_dengan_sesi_bernilai(): void
    {
        $sesi = [
            $this->sesi(['id' => 1, 'tanggal' => '2026-07-02']),
            $this->sesi(['id' => 2, 'tanggal' => '2026-07-09', 'digging' => 20, 'temuan' => 'front undulating', 'status_ca' => 'Closed']),
            $this->sesi(['id' => 3, 'tanggal' => '2026-07-23', 'swe' => null, 'temuan' => 'front undulating']),
        ];

        $k = P::kpi($sesi, 2026, 7);

        $this->assertSame(3, $k['total']['sesi']);
        $this->assertSame(0.5, $k['total']['on_target']);   // 1 dari 2 yang punya CT
        $this->assertSame(0.5, $k['total']['ca_closed']);   // 1 dari 2 bertemuan
        $this->assertSame(0.5, $k['total']['berulang']);    // front pada Ex 700 kembali
        $this->assertSame([1, 1, 0, 1], array_column($k['nilai'], 'sesi'));
        $this->assertSame(40, $k['target_sesi_bulan']);
    }

    public function test_kpi_berulang_memakai_riwayat_sebelum_bulannya(): void
    {
        $juni = $this->sesi(['id' => 1, 'tanggal' => '2026-06-30', 'temuan' => 'boulder']);
        $juli = $this->sesi(['id' => 2, 'tanggal' => '2026-07-01', 'temuan' => 'boulder']);

        $this->assertSame(0.0, P::kpi([$juli], 2026, 7)['total']['berulang']);
        $this->assertSame(1.0, P::kpi([$juli], 2026, 7, P::berulang([$juni, $juli]))['total']['berulang']);
    }
}
