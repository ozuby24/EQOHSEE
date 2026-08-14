<?php

namespace Tests\Unit;

use App\Support\Ramalan;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Ramalan capaian akhir periode.
 *
 * Waktu "kini" selalu disuntikkan, tidak pernah dibaca dari jam sistem,
 * supaya tesnya tidak berubah artinya ketika dijalankan pada tanggal
 * yang berbeda — kegagalan yang muncul sebulan sekali dan hilang lagi
 * sebelum sempat ditelusuri.
 */
class RamalanTest extends TestCase
{
    private function ramalan(float $aktual, float $target, string $kini): Ramalan
    {
        return new Ramalan(
            $aktual, $target,
            Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'),
            Carbon::parse($kini),
        );
    }

    public function test_hari_dihitung_inklusif(): void
    {
        $this->assertSame(31, $this->ramalan(0, 0, '2026-08-10')->hariTotal());
        $this->assertSame(10, $this->ramalan(0, 0, '2026-08-10')->hariBerjalan());
        $this->assertSame(21, $this->ramalan(0, 0, '2026-08-10')->hariTersisa());
    }

    public function test_laju_memakai_hari_kalender_bukan_hari_berdata(): void
    {
        // 1.000 ton pada hari ke-10 → 100 ton per hari, sekalipun
        // laporannya hanya masuk pada tiga hari.
        $r = $this->ramalan(1000, 3100, '2026-08-10');

        $this->assertSame(100.0, $r->laju());
        $this->assertSame(3100.0, $r->proyeksi());
        $this->assertSame('aman', $r->status());
    }

    public function test_capaian_sama_dinilai_berbeda_menurut_tanggalnya(): void
    {
        // 600 dari target 1.000 — sehat pada tanggal 10, meleset pada 25.
        $awal  = $this->ramalan(600, 1000, '2026-08-10');
        $akhir = $this->ramalan(600, 1000, '2026-08-25');

        $this->assertSame('aman', $awal->status());
        $this->assertSame('meleset', $akhir->status());
    }

    public function test_periode_yang_sudah_lampau_meramal_tepat_pada_aktual(): void
    {
        $r = $this->ramalan(2000, 3000, '2026-09-15');

        $this->assertSame(31, $r->hariBerjalan());
        $this->assertSame(0, $r->hariTersisa());
        $this->assertSame(2000.0, $r->proyeksi(), 'Tidak ada lagi yang perlu diramalkan.');
        $this->assertSame(1000.0, $r->kekurangan());
        $this->assertSame(0.0, $r->lajuDibutuhkan(), 'Tidak ada hari tersisa untuk mengejar.');
    }

    public function test_periode_yang_belum_mulai(): void
    {
        $r = $this->ramalan(0, 1000, '2026-07-20');

        $this->assertSame(0, $r->hariBerjalan());
        $this->assertSame(0.0, $r->laju());
        $this->assertSame('belum-mulai', $r->status());
    }

    public function test_pengali_menyatakan_seberapa_keras_laju_harus_naik(): void
    {
        // Hari ke-10, baru 500 dari 2.000. Sisa 21 hari perlu 1.500 →
        // 71,43/hari, sementara laju sekarang 50/hari.
        $r = $this->ramalan(500, 2000, '2026-08-10');

        $this->assertEqualsWithDelta(71.43, $r->lajuDibutuhkan(), 0.01);
        $this->assertEqualsWithDelta(1.43, $r->pengaliDibutuhkan(), 0.01);
    }

    public function test_tanpa_target_tidak_dinilai(): void
    {
        $r = $this->ramalan(500, 0, '2026-08-10');

        $this->assertSame('tanpa-target', $r->status());
        $this->assertSame(0.0, $r->proyeksiPersen());
        $this->assertSame(0.0, $r->kekurangan());
    }

    public function test_target_terlampaui_tidak_menuntut_laju_tambahan(): void
    {
        $r = $this->ramalan(1500, 1000, '2026-08-10');

        $this->assertSame('aman', $r->status());
        $this->assertSame(0.0, $r->kekurangan());
        $this->assertSame(0.0, $r->lajuDibutuhkan());
    }
}
