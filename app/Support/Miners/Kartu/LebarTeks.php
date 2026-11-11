<?php

namespace App\Support\Miners\Kartu;

/**
 * Perkiraan lebar teks pada kartu — LebarTeks Safe Track.
 *
 * Kartu dicetak lewat peramban, tetapi ukuran huruf nama dan kode unit
 * tetap DIHITUNG di server: halaman cetak massal memuat ratusan kartu,
 * dan mengukur tiap baris dengan skrip di peramban membuat pratinjaunya
 * berkedip. Tabel lebar (per 1000 em) diambil dari metrik huruf sans
 * DejaVu/Helvetica-mirip yang dipakai Safe Track; huruf di luar ASCII
 * dianggap selebar satu em supaya perkiraannya selalu lebih aman.
 */
final class LebarTeks
{
    private const LEBAR = [
        318, 401, 460, 838, 636, 950, 780, 275, 390, 390, 500, 838, 318, 361, 318, 337,
        636, 636, 636, 636, 636, 636, 636, 636, 636, 636, 337, 337, 838, 838, 838, 531,
        1000, 684, 686, 698, 770, 632, 575, 775, 752, 295, 295, 656, 557, 863, 748, 787,
        603, 787, 695, 635, 611, 732, 684, 989, 685, 611, 685, 390, 337, 390, 838, 500,
        500, 613, 635, 550, 635, 615, 352, 635, 634, 278, 278, 579, 278, 974, 634, 612,
        635, 635, 411, 521, 392, 634, 592, 818, 592, 592, 525, 636, 337, 636, 838,
    ];

    private const LEBAR_TEBAL = [
        348, 456, 521, 838, 696, 1002, 872, 306, 457, 457, 523, 838, 380, 415, 380, 365,
        696, 696, 696, 696, 696, 696, 696, 696, 696, 696, 400, 400, 838, 838, 838, 580,
        1000, 774, 762, 734, 830, 683, 683, 821, 837, 372, 372, 775, 637, 995, 837, 850,
        733, 850, 770, 720, 682, 812, 774, 1103, 771, 724, 725, 457, 365, 457, 838, 500,
        500, 675, 716, 593, 716, 678, 435, 716, 712, 343, 343, 665, 343, 1042, 712, 687,
        716, 716, 493, 595, 478, 712, 652, 924, 645, 652, 582, 712, 365, 712, 838,
    ];

    /** Lebar teks dalam pt pada ukuran huruf tertentu. */
    public static function lebar(string $teks, float $ukuranPt, bool $tebal = false): float
    {
        $tabel  = $tebal ? self::LEBAR_TEBAL : self::LEBAR;
        $jumlah = 0;

        foreach (mb_str_split($teks) as $h) {
            $k = mb_ord($h);
            $jumlah += ($k >= 32 && $k <= 126) ? $tabel[$k - 32] : 1000;
        }

        return $jumlah * $ukuranPt / 1000;
    }

    /** Ukuran huruf terbesar (≤ baku, ≥ minimum) yang membuat teks muat. */
    public static function ukuranMuat(string $teks, float $lebarPt, float $baku, float $minimum, bool $tebal = false, float $spasiPt = 0): float
    {
        $spasi = $spasiPt * mb_strlen($teks);
        $lebar = self::lebar($teks, $baku, $tebal);

        if ($lebar <= 0 || $lebar + $spasi <= $lebarPt) return $baku;

        $pas = floor($baku * max(0, $lebarPt - $spasi) / $lebar * 20) / 20;

        return max($minimum, $pas);
    }
}
