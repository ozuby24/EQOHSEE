<?php

namespace App\Support;

/**
 * Penghasil barcode Code 128-B dalam bentuk SVG — tanpa pustaka luar,
 * sehingga tetap jalan tanpa koneksi internet.
 */
class Barcode
{
    /** Lebar bar/spasi untuk tiap nilai Code128 (3 bar + 3 spasi). */
    private const P = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];

    /** Hasilkan SVG barcode Code128-B. */
    public static function svg(string $teks, int $tinggi = 46, float $unit = 1.5): string
    {
        $teks = preg_replace('/[^\x20-\x7E]/', '', $teks);
        if ($teks === '') return '';

        $kode = [104];                                   // Start B
        foreach (str_split($teks) as $ch) $kode[] = ord($ch) - 32;

        $cek = 104;
        foreach (array_slice($kode, 1) as $i => $v) $cek += ($i + 1) * $v;
        $kode[] = $cek % 103;                            // checksum
        $kode[] = 106;                                   // Stop

        $x = 0; $bars = '';
        foreach ($kode as $v) {
            $pola = self::P[$v] ?? self::P[0];
            foreach (str_split($pola) as $i => $w) {
                $lebar = (int) $w * $unit;
                if ($i % 2 === 0) {                      // indeks genap = bar hitam
                    $bars .= '<rect x="'.round($x,2).'" y="0" width="'.round($lebar,2).'" height="'.$tinggi.'"/>';
                }
                $x += $lebar;
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.round($x,2).' '.$tinggi.'" '
             . 'width="100%" height="'.$tinggi.'" preserveAspectRatio="none" fill="#1b1817" '
             . 'shape-rendering="crispEdges">'.$bars.'</svg>';
    }
}
