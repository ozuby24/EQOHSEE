<?php

namespace App\Support\Hr;

use App\Models\Miners\Blok;

/**
 * Jarak sebuah titik ke area kerjanya.
 *
 * DIHITUNG SAAT PERISTIWANYA DITERIMA, lalu disimpan. Titik geofence
 * dapat digeser kemudian — pos jaga dipindah, areanya diperluas — dan
 * menghitung ulang saat dibaca akan mengubah keputusan yang sudah
 * diambil atas data lama. Absen yang kemarin sah menjadi tidak sah
 * karena seseorang menggeser pin di peta.
 *
 * Memakai haversine, bukan Euclid. Pada jarak ratusan meter selisihnya
 * memang kecil — tetapi site tambang membentang puluhan kilometer, dan
 * di situ Euclid meleset ratusan meter ke arah yang tidak menentu.
 */
final class Geofence
{
    /** Jari-jari bumi rata-rata, meter. */
    private const JARI_BUMI = 6_371_000;

    /**
     * Jarak dua titik dalam meter.
     *
     * @return int|null null bila salah satu titiknya tidak lengkap
     */
    public static function jarak(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?int
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) return null;

        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return (int) round(self::JARI_BUMI * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Periksa sebuah titik terhadap area kerja.
     *
     * AREA TANPA JARI-JARI TIDAK MENOLAK APA PUN, dan itu keputusan
     * yang sah: kantor pusat memang tidak dipagari, dan memperlakukan
     * jari-jari kosong sebagai nol akan menolak setiap absen dari sana.
     *
     * @return array{jarak:?int,dalam:?bool}
     */
    public static function periksa(?Blok $blok, ?float $lat, ?float $lng): array
    {
        if (! $blok || $blok->lat === null || $blok->lng === null) {
            return ['jarak' => null, 'dalam' => null];
        }

        $jarak = self::jarak((float) $blok->lat, (float) $blok->lng, $lat, $lng);

        if ($jarak === null) return ['jarak' => null, 'dalam' => null];

        $radius = $blok->radius_m;

        return [
            'jarak' => $jarak,
            'dalam' => $radius === null ? null : $jarak <= (int) $radius,
        ];
    }
}
