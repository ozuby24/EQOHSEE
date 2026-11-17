<?php

namespace App\Support\Hr;

/**
 * Perhitungan upah lembur menurut PP 35/2021.
 *
 * SELURUH ANGKANYA PASAL, bukan kebijakan. Yang menyengketakan upah
 * lembur menyebut pasalnya, dan jawaban sistem harus dapat disandingkan
 * dengannya baris demi baris — karena itu tiap tetapan di bawah
 * menyebut pasalnya sendiri, dan rincian jam-kali-faktor ikut
 * dipulangkan, bukan hanya jumlahnya.
 *
 * SATU HAL DI SINI BERBEDA DARI PRD, DAN PERATURANNYA YANG DIPAKAI.
 * PRD menyebut faktor 4x untuk "jam ke-10 sampai ke-12" pada pola lima
 * hari kerja. Pasal 31 ayat (2) huruf b menyebut jam ke-10 dan ke-11 —
 * dua jam, bukan tiga. Yang diterapkan di sini pasalnya; kelebihan jam
 * di luar tabel tetap dihitung pada faktor tertinggi, dan itu pilihan
 * yang menguntungkan pekerja sekaligus tidak berpura-pura bahwa
 * jamnya tidak terjadi.
 */
final class UpahLembur
{
    /**
     * Pembagi upah sebulan menjadi upah sejam — PP 35/2021 pasal 32.
     *
     * Angka 173 itu sendiri berasal dari 40 jam seminggu dikalikan 52
     * minggu dibagi 12 bulan, dibulatkan. Ditulis sebagai 1/173 dan
     * bukan dihitung dari 40 jam, supaya ia tidak ikut bergeser ketika
     * jam kerja sepekan diatur berbeda.
     */
    public const PEMBAGI_JAM = 173;

    /** Batas lembur hari kerja — pasal 29 ayat (2). */
    public const MAKS_JAM_HARI    = 4;
    public const MAKS_JAM_MINGGU  = 18;

    /**
     * Faktor lembur hari kerja — pasal 31 ayat (1).
     *
     * @var list<array{0:float,1:float}> jam pada tingkat ini, faktornya
     */
    public const FAKTOR_HARI_KERJA = [
        [1, 1.5],   // jam pertama
        [0, 2.0],   // jam kedua dan seterusnya — 0 berarti tak terbatas
    ];

    /**
     * Faktor lembur hari libur pada pola ENAM hari kerja —
     * pasal 31 ayat (2) huruf a.
     */
    public const FAKTOR_LIBUR_6 = [
        [7, 2.0],   // jam ke-1 s.d. ke-7
        [1, 3.0],   // jam ke-8
        [0, 4.0],   // jam ke-9 dan seterusnya
    ];

    /**
     * Faktor lembur hari libur pada pola LIMA hari kerja —
     * pasal 31 ayat (2) huruf b.
     */
    public const FAKTOR_LIBUR_5 = [
        [8, 2.0],   // jam ke-1 s.d. ke-8
        [1, 3.0],   // jam ke-9
        [0, 4.0],   // jam ke-10 dan seterusnya
    ];

    /**
     * Ambang dasar 75% — pasal 32 ayat (3).
     *
     * Bukan "ada tunjangan tidak tetap maka 75%", yang keliru dan
     * merugikan pekerja. Pasalnya berbunyi: bila upah terdiri dari
     * pokok, tunjangan tetap, DAN tunjangan tidak tetap, sedangkan
     * pokok ditambah tunjangan tetap KURANG DARI 75% keseluruhan upah,
     * barulah dasarnya 75% dari keseluruhan.
     *
     * Disederhanakan menjadi "ada tunjangan tidak tetap → 75%", seorang
     * yang pokoknya sudah 90% dari upahnya dihitung atas 75% saja —
     * dan ia kehilangan seperenam upah lemburnya tiap bulan, tanpa
     * satu galat pun.
     */
    public const AMBANG_DASAR = 0.75;

    /**
     * Upah sebulan yang menjadi dasar, beserta persennya.
     *
     * @return array{upah:float,persen:int}
     */
    public static function dasar(float $pokok, float $tetap, float $tidakTetap): array
    {
        $seluruh = $pokok + $tetap + $tidakTetap;

        if ($seluruh <= 0) return ['upah' => 0.0, 'persen' => 100];

        /* Tanpa tunjangan tidak tetap, pasal 32 ayat (2) berlaku apa
           adanya: dasarnya 100% dari pokok ditambah tunjangan tetap. */
        if ($tidakTetap <= 0) return ['upah' => $pokok + $tetap, 'persen' => 100];

        $tetapPenuh = $pokok + $tetap;

        if ($tetapPenuh >= self::AMBANG_DASAR * $seluruh) {
            return ['upah' => $tetapPenuh, 'persen' => 100];
        }

        return ['upah' => self::AMBANG_DASAR * $seluruh, 'persen' => 75];
    }

    /** Upah sejam — pasal 32 ayat (1). */
    public static function upahSejam(float $upahSebulan): float
    {
        return $upahSebulan / self::PEMBAGI_JAM;
    }

    /**
     * Hitung upah lembur satu hari.
     *
     * @param  string  $jenisHari      'kerja' atau 'libur'
     * @param  int     $hariSeminggu   5 atau 6, hanya berarti pada hari libur
     * @return array{jam:float,upah_sejam:float,nilai:float,dasar_persen:int,upah_sebulan:float,rincian:list<array<string,mixed>>,melebihi:?string}
     */
    public static function hitung(
        float $jam,
        float $pokok,
        float $tetap,
        float $tidakTetap,
        string $jenisHari = 'kerja',
        int $hariSeminggu = 6,
    ): array {
        $jam = max(0.0, round($jam, 2));

        $dasar  = self::dasar($pokok, $tetap, $tidakTetap);
        $sejam  = self::upahSejam($dasar['upah']);
        $tabel  = self::tabel($jenisHari, $hariSeminggu);

        $rincian = [];
        $nilai   = 0.0;
        $sisa    = $jam;

        foreach ($tabel as [$batas, $faktor]) {
            if ($sisa <= 0) break;

            /* Batas nol berarti tingkat terakhir: seluruh sisanya
               masuk ke sini. Diperlakukan sebagai nol jam, jam yang
               melampaui tabel hilang dari perhitungan tanpa jejak —
               dan pekerja yang lembur dua belas jam dibayar sepuluh. */
            $ambil = $batas === 0 ? $sisa : min($sisa, (float) $batas);

            $bagian = $ambil * $faktor * $sejam;

            $rincian[] = [
                'jam'    => round($ambil, 2),
                'faktor' => $faktor,
                'nilai'  => round($bagian, 2),
            ];

            $nilai += $bagian;
            $sisa  -= $ambil;
        }

        return [
            'jam'          => $jam,
            'upah_sebulan' => round($dasar['upah'], 2),
            'dasar_persen' => $dasar['persen'],
            'upah_sejam'   => round($sejam, 2),
            'rincian'      => $rincian,
            'nilai'        => round($nilai, 2),
            'melebihi'     => self::melebihi($jam, $jenisHari),
        ];
    }

    /**
     * Batas yang dilampaui, bila ada.
     *
     * MELAMPAUI BATAS TIDAK MEMBATALKAN UPAHNYA. Jamnya sudah
     * dikerjakan, dan menolak membayarnya berarti menghukum pekerja
     * atas perintah yang bukan keputusannya. Yang ditandai adalah
     * pelanggarannya — supaya yang menyetujui melihatnya sebelum
     * menekan tombol, dan supaya ia terbaca pada laporan pengawasan.
     *
     * BATAS INI TIDAK BERLAKU PADA HARI LIBUR — pasal 29 ayat (2)
     * menyebutnya tegas: "tidak termasuk waktu kerja lembur pada waktu
     * istirahat mingguan dan/atau hari libur resmi". Diberlakukan pula,
     * seluruh lembur hari Minggu ditandai melanggar.
     */
    public static function melebihi(float $jam, string $jenisHari): ?string
    {
        if ($jenisHari === 'libur') return null;

        return $jam > self::MAKS_JAM_HARI
            ? 'Melebihi '.self::MAKS_JAM_HARI.' jam sehari — PP 35/2021 pasal 29 ayat (2).'
            : null;
    }

    /**
     * Jam lembur sepekan yang melampaui batas, bila ada.
     *
     * Dihitung terpisah dari harian sebab batasnya memang terpisah —
     * dan sebab ia hanya dapat dilihat dari rangkaian, bukan dari satu
     * baris. Lembur hari libur TIDAK ikut dijumlahkan, sesuai pasal 29
     * ayat (2).
     *
     * @param  list<array{jam:float,jenis_hari:string}>  $baris
     */
    public static function melebihiMinggu(array $baris): ?string
    {
        $jam = 0.0;

        foreach ($baris as $b) {
            if (($b['jenis_hari'] ?? 'kerja') === 'libur') continue;

            $jam += (float) ($b['jam'] ?? 0);
        }

        return $jam > self::MAKS_JAM_MINGGU
            ? 'Melebihi '.self::MAKS_JAM_MINGGU.' jam seminggu ('.round($jam, 2).' jam)'
                .' — PP 35/2021 pasal 29 ayat (2).'
            : null;
    }

    /** @return list<array{0:int,1:float}> */
    private static function tabel(string $jenisHari, int $hariSeminggu): array
    {
        if ($jenisHari !== 'libur') return self::FAKTOR_HARI_KERJA;

        return $hariSeminggu === 5 ? self::FAKTOR_LIBUR_5 : self::FAKTOR_LIBUR_6;
    }
}
