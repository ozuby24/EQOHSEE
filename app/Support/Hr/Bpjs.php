<?php

namespace App\Support\Hr;

/**
 * Iuran BPJS Kesehatan dan Ketenagakerjaan.
 *
 * PLAFONNYA BERBEDA-BEDA PER PROGRAM, dan itu yang paling sering
 * keliru. BPJS Kesehatan berplafon Rp12.000.000; Jaminan Pensiun
 * berplafon Rp11.086.300 sejak Maret 2026; JHT, JKK, dan JKM TIDAK
 * berplafon sama sekali. Dipukul satu plafon untuk semuanya, iuran
 * JHT seorang pengawas berupah lima belas juta dipotong seolah
 * upahnya dua belas juta — dan saldo hari tuanya berkurang tiap bulan
 * selama bertahun-tahun tanpa seorang pun menyadarinya.
 *
 * JKK 1,74% ADALAH TARIF RISIKO SANGAT TINGGI, dan pertambangan
 * memang berada di sana. Dipasang pada tarif risiko rendah 0,24%,
 * selisihnya satu setengah persen dari seluruh upah site — dan
 * kekurangan bayar itu baru ketahuan saat BPJS memeriksa.
 *
 * ACUAN:
 *   · Perpres 63/2022 jo. 59/2024 — BPJS Kesehatan 5% (4% pemberi
 *     kerja + 1% pekerja), plafon Rp12.000.000, batas bawah UMP/UMK.
 *   · PP 6/2025 dan ketentuan BPJS Ketenagakerjaan 2026 — JHT 5,7%
 *     (3,7% + 2%); JP 3% (2% + 1%) plafon Rp11.086.300 sejak Maret
 *     2026; JKK 0,24%–1,74% menurut kelompok risiko; JKM 0,3%.
 */
final class Bpjs
{
    /* ═══════════════════ BPJS Kesehatan ═══════════════════ */

    public const KESEHATAN_PERUSAHAAN = 4.0;
    public const KESEHATAN_KARYAWAN   = 1.0;
    public const KESEHATAN_PLAFON     = 12_000_000;

    /* ═══════════════════ Ketenagakerjaan ═══════════════════ */

    public const JHT_PERUSAHAAN = 3.7;
    public const JHT_KARYAWAN   = 2.0;

    public const JP_PERUSAHAAN = 2.0;
    public const JP_KARYAWAN   = 1.0;

    /** Plafon upah Jaminan Pensiun, berlaku sejak Maret 2026. */
    public const JP_PLAFON = 11_086_300;

    /**
     * Tarif JKK menurut kelompok risiko.
     *
     * Pertambangan berada pada "sangat tinggi". Ditanggung PERUSAHAAN
     * seluruhnya — pekerja tidak dipotong sepeser pun untuk ini.
     */
    public const JKK_TARIF = [
        'sangat_rendah' => 0.24,
        'rendah'        => 0.54,
        'sedang'        => 0.89,
        'tinggi'        => 1.27,
        'sangat_tinggi' => 1.74,
    ];

    public const JKK_BAWAAN = 'sangat_tinggi';

    /** Jaminan Kematian, ditanggung perusahaan seluruhnya. */
    public const JKM = 0.30;

    /**
     * Hitung seluruh iuran atas sebuah upah sebulan.
     *
     * UPAH YANG DIPAKAI adalah upah tetap — pokok ditambah tunjangan
     * tetap — bukan penghasilan bruto yang memuat lembur. Dihitung
     * dari bruto, iuran seseorang naik turun tiap bulan mengikuti
     * lemburnya, dan saldo jaminan hari tuanya menjadi angka yang
     * tidak dapat dijelaskan kepada siapa pun.
     *
     * @param  float   $upah      upah tetap sebulan
     * @param  float   $upahMin   batas bawah (UMP/UMK) bagi BPJS Kesehatan
     * @param  string  $risiko    kelompok risiko JKK
     * @return array{perusahaan:float,karyawan:float,rincian:array<string,array{dasar:float,tarif:float,perusahaan:float,karyawan:float}>}
     */
    public static function hitung(float $upah, float $upahMin = 0, string $risiko = self::JKK_BAWAAN): array
    {
        $upah = max(0.0, $upah);

        /* BPJS Kesehatan punya batas BAWAH pula: upah di bawah UMP
           tetap diiur atas UMP. Diabaikan, pekerja harian yang upahnya
           di bawah UMP diiur terlalu kecil dan kepesertaannya
           bermasalah justru saat ia perlu berobat. */
        $dasarKes = min(max($upah, $upahMin), (float) self::KESEHATAN_PLAFON);

        $dasarJp  = min($upah, (float) self::JP_PLAFON);

        /* JHT, JKK, dan JKM memakai upah apa adanya — tidak berplafon. */
        $dasarJht = $upah;

        $jkk = self::JKK_TARIF[$risiko] ?? self::JKK_TARIF[self::JKK_BAWAAN];

        $rincian = [
            'kesehatan' => self::baris($dasarKes, self::KESEHATAN_PERUSAHAAN, self::KESEHATAN_KARYAWAN),
            'jht'       => self::baris($dasarJht, self::JHT_PERUSAHAAN, self::JHT_KARYAWAN),
            'jp'        => self::baris($dasarJp,  self::JP_PERUSAHAAN,  self::JP_KARYAWAN),
            'jkk'       => self::baris($dasarJht, $jkk, 0),
            'jkm'       => self::baris($dasarJht, self::JKM, 0),
        ];

        return [
            'perusahaan' => round(array_sum(array_column($rincian, 'perusahaan')), 2),
            'karyawan'   => round(array_sum(array_column($rincian, 'karyawan')), 2),
            'rincian'    => $rincian,
        ];
    }

    /**
     * Iuran yang MENAMBAH penghasilan bruto pajak.
     *
     * Premi JKK dan JKM yang dibayar pemberi kerja adalah premi
     * asuransi bagi pekerja, dan karena itu objek PPh 21. Iuran JHT
     * dan JP yang dibayar pemberi kerja TIDAK — keduanya baru
     * dikenakan pajak saat manfaatnya dibayarkan. BPJS Kesehatan yang
     * dibayar pemberi kerja diperlakukan sama dengan premi asuransi
     * kesehatan, jadi ikut menambah bruto.
     *
     * Disamaratakan, penghasilan bruto pajak seseorang meleset
     * beberapa ratus ribu tiap bulan — ke arah yang salah, dan tidak
     * ada satu galat pun yang menandainya.
     */
    public static function menambahBruto(array $rincian): float
    {
        $n = 0.0;

        foreach (['kesehatan', 'jkk', 'jkm'] as $k) {
            $n += (float) ($rincian[$k]['perusahaan'] ?? 0);
        }

        return round($n, 2);
    }

    /** @return array{dasar:float,tarif:float,perusahaan:float,karyawan:float} */
    private static function baris(float $dasar, float $tarifPerusahaan, float $tarifKaryawan): array
    {
        return [
            'dasar'      => round($dasar, 2),
            'tarif'      => $tarifPerusahaan + $tarifKaryawan,
            'perusahaan' => round($dasar * $tarifPerusahaan / 100, 2),
            'karyawan'   => round($dasar * $tarifKaryawan / 100, 2),
        ];
    }
}
