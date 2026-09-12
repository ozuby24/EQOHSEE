<?php

namespace App\Support\Hr;

use Illuminate\Support\Facades\DB;

/**
 * Acuan pajak dan jaminan sosial, dipasang sebagai data.
 *
 * TABEL TARIF EFEKTIF DITANDAI BELUM TERVERIFIKASI SAAT DIPASANG, dan
 * itu bukan kehati-hatian berlebihan melainkan pengakuan yang jujur.
 * Lampiran PMK 168/2023 memuat seratus dua puluhan baris batas
 * penghasilan; satu batas saja yang meleset sudah cukup untuk memotong
 * pajak seseorang dengan tarif yang keliru selama sebelas bulan, dan
 * yang menanggung dendanya perusahaan.
 *
 * Angka di bawah disusun dari acuan yang beredar umum dan BELUM
 * dicocokkan baris demi baris dengan naskah peraturannya. Selama
 * penandanya belum dicentang seseorang yang memegang naskah itu,
 * periode gaji tidak dapat dikunci — perhitungannya boleh dilihat,
 * yang ditahan adalah saat angkanya berhenti menjadi pratinjau dan
 * mulai menjadi dasar pembayaran.
 *
 * Menandainya "sudah benar" sejak awal akan membuat ketidakpastian itu
 * hilang dari pandangan tanpa pernah hilang dari datanya.
 */
final class MasterPajak
{
    /**
     * Acuan yang dipasang, beserta sumbernya.
     *
     * @var list<array{0:string,1:string,2:string,3:?string}>
     */
    public const ACUAN = [
        ['ter', 'Tarif Efektif Rata-rata bulanan', 'PMK 168/2023 lampiran — kategori A, B, dan C',
         'Disusun dari acuan yang beredar umum. Cocokkan baris demi baris dengan lampiran PMK sebelum periode gaji pertama dikunci.'],

        ['ptkp', 'Penghasilan Tidak Kena Pajak', 'PMK 101/2016',
         'Rp54.000.000 untuk wajib pajak, tambah Rp4.500.000 bila kawin, tambah Rp4.500.000 per tanggungan (maks 3).'],

        ['pasal17', 'Tarif progresif Pasal 17', 'UU 7/2021 (HPP) pasal 17 ayat (1) huruf a',
         'Dipakai pada rekonsiliasi Desember, bukan pada bulan Januari sampai November.'],

        ['bpjs', 'Iuran BPJS Kesehatan dan Ketenagakerjaan',
         'Perpres 63/2022 jo. 59/2024; PP 6/2025 dan ketentuan BPJS Ketenagakerjaan 2026',
         'Plafon JP Rp11.086.300 berlaku sejak Maret 2026. JKK 1,74% untuk risiko sangat tinggi (pertambangan).'],
    ];

    /**
     * Tarif efektif bulanan kategori A — PTKP TK/0, TK/1, dan K/0.
     *
     * Pasangan: batas atas penghasilan bruto bulanan, tarif persen.
     * Batas atas null berarti lapis terakhir yang tidak berbatas.
     *
     * @var list<array{0:?int,1:float}>
     */
    public const TER_A = [
        [5_400_000, 0.00],    [5_650_000, 0.25],    [5_950_000, 0.50],    [6_300_000, 0.75],
        [6_750_000, 1.00],    [7_500_000, 1.25],    [8_550_000, 1.50],    [9_650_000, 1.75],
        [10_050_000, 2.00],   [10_350_000, 2.25],   [10_700_000, 2.50],   [11_050_000, 3.00],
        [11_600_000, 3.50],   [12_500_000, 4.00],   [13_750_000, 5.00],   [15_100_000, 6.00],
        [16_950_000, 7.00],   [19_750_000, 8.00],   [24_150_000, 9.00],   [26_450_000, 10.00],
        [28_000_000, 11.00],  [30_050_000, 12.00],  [32_400_000, 13.00],  [35_400_000, 14.00],
        [39_100_000, 15.00],  [43_850_000, 16.00],  [47_800_000, 17.00],  [51_400_000, 18.00],
        [56_300_000, 19.00],  [62_200_000, 20.00],  [68_600_000, 21.00],  [77_500_000, 22.00],
        [89_000_000, 23.00],  [103_000_000, 24.00], [125_000_000, 25.00], [157_000_000, 26.00],
        [206_000_000, 27.00], [337_000_000, 28.00], [454_000_000, 29.00], [550_000_000, 30.00],
        [695_000_000, 31.00], [910_000_000, 32.00], [1_400_000_000, 33.00], [null, 34.00],
    ];

    /**
     * Tarif efektif bulanan kategori B — PTKP TK/2, TK/3, K/1, dan K/2.
     *
     * @var list<array{0:?int,1:float}>
     */
    public const TER_B = [
        [6_200_000, 0.00],    [6_500_000, 0.25],    [6_850_000, 0.50],    [7_300_000, 0.75],
        [9_200_000, 1.00],    [10_750_000, 1.50],   [11_250_000, 2.00],   [11_600_000, 2.50],
        [12_600_000, 3.00],   [13_600_000, 4.00],   [14_950_000, 5.00],   [16_400_000, 6.00],
        [18_450_000, 7.00],   [21_850_000, 8.00],   [26_000_000, 9.00],   [27_700_000, 10.00],
        [29_350_000, 11.00],  [31_450_000, 12.00],  [33_950_000, 13.00],  [37_100_000, 14.00],
        [41_100_000, 15.00],  [45_800_000, 16.00],  [49_500_000, 17.00],  [53_800_000, 18.00],
        [58_500_000, 19.00],  [64_000_000, 20.00],  [71_000_000, 21.00],  [80_000_000, 22.00],
        [93_000_000, 23.00],  [109_000_000, 24.00], [129_000_000, 25.00], [163_000_000, 26.00],
        [211_000_000, 27.00], [374_000_000, 28.00], [459_000_000, 29.00], [555_000_000, 30.00],
        [704_000_000, 31.00], [957_000_000, 32.00], [1_405_000_000, 33.00], [null, 34.00],
    ];

    /**
     * Tarif efektif bulanan kategori C — PTKP K/3.
     *
     * @var list<array{0:?int,1:float}>
     */
    public const TER_C = [
        [6_600_000, 0.00],    [6_950_000, 0.25],    [7_350_000, 0.50],    [7_800_000, 0.75],
        [8_850_000, 1.00],    [9_800_000, 1.25],    [10_950_000, 1.50],   [11_200_000, 1.75],
        [12_050_000, 2.00],   [12_950_000, 3.00],   [14_150_000, 4.00],   [15_550_000, 5.00],
        [17_050_000, 6.00],   [19_500_000, 7.00],   [22_700_000, 8.00],   [26_600_000, 9.00],
        [28_100_000, 10.00],  [30_100_000, 11.00],  [32_600_000, 12.00],  [35_400_000, 13.00],
        [38_900_000, 14.00],  [43_000_000, 15.00],  [47_400_000, 16.00],  [51_200_000, 17.00],
        [55_800_000, 18.00],  [60_400_000, 19.00],  [66_700_000, 20.00],  [74_500_000, 21.00],
        [83_200_000, 22.00],  [95_600_000, 23.00],  [110_000_000, 24.00], [134_000_000, 25.00],
        [169_000_000, 26.00], [221_000_000, 27.00], [390_000_000, 28.00], [463_000_000, 29.00],
        [561_000_000, 30.00], [709_000_000, 31.00], [965_000_000, 32.00], [1_419_000_000, 33.00],
        [null, 34.00],
    ];

    /** @return array<string,int> nama tabel => jumlah baris sesudahnya */
    public static function pasang(): array
    {
        $saat = now();

        foreach (self::ACUAN as [$kunci, $nama, $sumber, $catatan]) {
            $ada = DB::table('pay_acuan')->where('kunci', $kunci)->first();

            if ($ada) {
                /* PENANDA VERIFIKASI TIDAK PERNAH DISENTUH PEMASANGAN
                   ULANG. Diperbarui, tiap penerapan aplikasi akan
                   diam-diam mencabut pemeriksaan yang sudah dikerjakan
                   orang — atau lebih buruk, mencentangnya kembali. */
                DB::table('pay_acuan')->where('id', $ada->id)->update([
                    'nama' => $nama, 'sumber' => $sumber, 'catatan' => $catatan,
                    'updated_at' => $saat,
                ]);

                continue;
            }

            DB::table('pay_acuan')->insert([
                'kunci' => $kunci, 'nama' => $nama, 'sumber' => $sumber, 'catatan' => $catatan,
                'terverifikasi' => false,
                'created_at' => $saat, 'updated_at' => $saat,
            ]);
        }

        /* Tabel tarif ditulis ulang seluruhnya, bukan disisipkan satu
           per satu: batasnya bergeser sebagai satu kesatuan tiap kali
           peraturannya berubah, dan menyisipkan yang baru di samping
           yang lama menghasilkan dua lapis yang saling tumpang tindih
           pada penghasilan yang sama. */
        DB::table('pay_ter')->delete();

        foreach (['A' => self::TER_A, 'B' => self::TER_B, 'C' => self::TER_C] as $kategori => $tabel) {
            $bawah = 0;

            foreach ($tabel as [$atas, $tarif]) {
                DB::table('pay_ter')->insert([
                    'kategori'    => $kategori,
                    'batas_bawah' => $bawah,
                    'batas_atas'  => $atas,
                    'tarif'       => $tarif,
                    'created_at'  => $saat,
                    'updated_at'  => $saat,
                ]);

                $bawah = $atas ?? $bawah;
            }
        }

        return [
            'pay_acuan' => (int) DB::table('pay_acuan')->count(),
            'pay_ter'   => (int) DB::table('pay_ter')->count(),
        ];
    }
}
