<?php

namespace App\Support\Hr;

use Illuminate\Support\Facades\DB;

/**
 * PPh 21 — tarif efektif bulanan dan rekonsiliasi tahunan.
 *
 * DUA CARA HITUNG DALAM SATU TAHUN, dan itu bukan kerumitan yang
 * dibuat-buat: PP 58/2023 memang memisahkannya. Januari sampai
 * November dipotong dengan tarif efektif rata-rata atas penghasilan
 * bruto bulan itu saja — sederhana, tanpa menyetahunkan apa pun.
 * Desember baru menghitung pajak setahun penuh dengan tarif progresif
 * Pasal 17, lalu mengurangi seluruh yang sudah dipotong sebelas bulan
 * sebelumnya.
 *
 * Dipakai TER pula pada Desember, pajak setahun tidak pernah
 * direkonsiliasi — dan selisihnya, kurang atau lebih, tidak pernah
 * diperhitungkan kepada siapa pun.
 *
 * KATEGORI TER DITENTUKAN STATUS PTKP, bukan besar penghasilan.
 * Ditebak dari penghasilannya, seorang lajang berpenghasilan besar
 * dipotong dengan tabel kepala keluarga beranak tiga.
 */
final class Pajak
{
    /** PTKP setahun — PMK 101/2016. */
    public const PTKP_DIRI       = 54_000_000;
    public const PTKP_KAWIN      = 4_500_000;
    public const PTKP_TANGGUNGAN = 4_500_000;
    public const MAKS_TANGGUNGAN = 3;

    /**
     * Biaya jabatan — 5% penghasilan bruto, dibatasi.
     *
     * Batasnya Rp500.000 sebulan dan Rp6.000.000 setahun. Tanpa
     * batas, seorang berpenghasilan besar mengurangi penghasilan kena
     * pajaknya jauh melebihi yang diperbolehkan.
     */
    public const BIAYA_JABATAN_PERSEN  = 5.0;
    public const BIAYA_JABATAN_SEBULAN = 500_000;
    public const BIAYA_JABATAN_SETAHUN = 6_000_000;

    /**
     * Tarif progresif Pasal 17 — UU 7/2021 (HPP).
     *
     * @var list<array{0:?int,1:float}> batas atas lapisan, tarif persen
     */
    public const PASAL_17 = [
        [60_000_000, 5.0],
        [250_000_000, 15.0],
        [500_000_000, 25.0],
        [5_000_000_000, 30.0],
        [null, 35.0],
    ];

    /**
     * Kategori TER menurut status PTKP — PMK 168/2023.
     *
     * @var array<string,string>
     */
    public const KATEGORI = [
        'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
        'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
        'K/3'  => 'C',
    ];

    /** Seluruh status PTKP yang dikenal, siap menjadi daftar pilih. */
    public const STATUS = [
        'TK/0' => 'TK/0 — tidak kawin, tanpa tanggungan',
        'TK/1' => 'TK/1 — tidak kawin, 1 tanggungan',
        'TK/2' => 'TK/2 — tidak kawin, 2 tanggungan',
        'TK/3' => 'TK/3 — tidak kawin, 3 tanggungan',
        'K/0'  => 'K/0 — kawin, tanpa tanggungan',
        'K/1'  => 'K/1 — kawin, 1 tanggungan',
        'K/2'  => 'K/2 — kawin, 2 tanggungan',
        'K/3'  => 'K/3 — kawin, 3 tanggungan',
    ];

    /** Bulan yang memakai rekonsiliasi progresif, bukan TER. */
    public const BULAN_REKONSILIASI = 12;

    /* ═══════════════════ PTKP ═══════════════════ */

    public static function ptkp(?string $status): int
    {
        [$kawin, $tanggungan] = self::urai($status);

        return self::PTKP_DIRI
            + ($kawin ? self::PTKP_KAWIN : 0)
            + $tanggungan * self::PTKP_TANGGUNGAN;
    }

    public static function kategori(?string $status): string
    {
        /* Dinormalkan lebih dahulu, bukan dicari apa adanya. "K/4"
           bukan status yang sah, tetapi ia jelas menyebut seorang yang
           KAWIN — dicari apa adanya, ia tidak ditemukan dan jatuh ke
           kategori A, yaitu tabel bagi seorang lajang. Orang itu
           dipotong terlalu besar tiap bulan atas kesalahan ketik satu
           angka. */
        /* Cadangan 'A' TIDAK PERNAH TERCAPAI selama normalkan()
           hanya memulangkan delapan status baku yang seluruhnya ada
           pada KATEGORI — dan uji mutasi memang membuktikannya: mengubah
           cadangan ini menjadi 'C' tidak menggagalkan satu uji pun.
           Dibiarkan berdiri sebagai penjagaan bila daftar statusnya
           kelak bertambah tanpa KATEGORI ikut diperbarui; disebutkan
           di sini supaya tidak ada yang menyangka baris ini teruji. */
        return self::KATEGORI[self::normalkan($status)] ?? 'A';
    }

    /**
     * Status PTKP yang sudah dibakukan.
     *
     * TANGGUNGAN DIJEPIT DI TIGA, bukan dibuang seluruh statusnya.
     * Pasal 7 UU PPh membatasi tanggungan paling banyak tiga orang —
     * "K/4" karena itu berarti K/3, bukan TK/0. Ditolak seluruhnya,
     * satu angka yang salah ketik mengubah seorang kepala keluarga
     * beranak empat menjadi lajang tanpa tanggungan, dan pajaknya
     * dipotong jauh lebih besar daripada seharusnya.
     */
    public static function normalkan(?string $status): string
    {
        [$kawin, $tanggungan] = self::urai($status);

        return ($kawin ? 'K' : 'TK').'/'.$tanggungan;
    }

    /* ═══════════════════ TER bulanan ═══════════════════ */

    /**
     * Tarif efektif untuk sebuah penghasilan bruto bulanan.
     *
     * @return array{kategori:string,tarif:float,pajak:float}
     */
    public static function ter(float $bruto, ?string $status): array
    {
        $kategori = self::kategori($status);
        $bruto    = max(0.0, $bruto);

        /* Lapisan yang batas atasnya null adalah yang terakhir, dan ia
           harus menang atas apa pun. Diurut menurut batas atas tanpa
           menjaga null di belakang, penghasilan sebesar apa pun jatuh
           ke lapisan nol persen. */
        $baris = DB::table('pay_ter')
            ->where('kategori', $kategori)
            ->where('batas_bawah', '<', max($bruto, 0.01))
            ->orderByDesc('batas_bawah')
            ->first();

        $tarif = (float) ($baris->tarif ?? 0);

        /* Lapisan pertama berbatas bawah nol dan tidak terjaring
           perbandingan "<" bila brutonya nol. Itu justru benar: bruto
           nol tidak berpajak. */
        if ($bruto <= 0) $tarif = 0.0;

        return [
            'kategori' => $kategori,
            'tarif'    => $tarif,
            'pajak'    => round($bruto * $tarif / 100, 0),
        ];
    }

    /* ═══════════════════ rekonsiliasi Desember ═══════════════════ */

    /**
     * Pajak setahun dengan tarif progresif, dikurangi yang sudah
     * dipotong.
     *
     * @param  float  $brutoSetahun   penghasilan bruto Januari–Desember
     * @param  float  $iuranSetahun   iuran JHT dan JP yang dibayar PEKERJA
     * @param  float  $sudahDipotong  PPh 21 yang sudah dipotong Jan–Nov
     * @return array{bruto:float,biaya_jabatan:float,iuran:float,neto:float,ptkp:int,pkp:float,pajak_setahun:float,sudah:float,pajak:float,lapis:list<array<string,mixed>>}
     */
    public static function tahunan(float $brutoSetahun, float $iuranSetahun, ?string $status, float $sudahDipotong): array
    {
        $bruto = max(0.0, $brutoSetahun);

        $biaya = min($bruto * self::BIAYA_JABATAN_PERSEN / 100, (float) self::BIAYA_JABATAN_SETAHUN);

        /* Iuran JHT dan JP yang dibayar PEKERJA mengurangi penghasilan
           neto; yang dibayar pemberi kerja tidak. Disamakan, penghasilan
           kena pajaknya terlalu kecil dan pajaknya kurang setor. */
        $neto = max(0.0, $bruto - $biaya - max(0.0, $iuranSetahun));

        $ptkp = self::ptkp($status);

        /* PKP dibulatkan ke bawah ke ribuan penuh — ketentuan umum
           penghitungan PPh 21. */
        $pkp = max(0.0, floor(($neto - $ptkp) / 1000) * 1000);

        [$pajak, $lapis] = self::progresif($pkp);

        return [
            'bruto'         => round($bruto, 2),
            'biaya_jabatan' => round($biaya, 2),
            'iuran'         => round(max(0.0, $iuranSetahun), 2),
            'neto'          => round($neto, 2),
            'ptkp'          => $ptkp,
            'pkp'           => $pkp,
            'pajak_setahun' => round($pajak, 0),
            'sudah'         => round($sudahDipotong, 0),

            /* Boleh NEGATIF, dan itu memang maksudnya: lebih potong
               dikembalikan kepada pekerja pada slip Desember. Dijepit
               di nol, kelebihan potong sebelas bulan hilang begitu
               saja — dan pekerjanya tidak pernah tahu. */
            'pajak'         => round($pajak - $sudahDipotong, 0),

            'lapis'         => $lapis,
        ];
    }

    /**
     * Pajak progresif atas penghasilan kena pajak.
     *
     * @return array{0:float,1:list<array<string,mixed>>}
     */
    public static function progresif(float $pkp): array
    {
        $sisa  = max(0.0, $pkp);
        $bawah = 0.0;
        $pajak = 0.0;
        $lapis = [];

        foreach (self::PASAL_17 as [$atas, $tarif]) {
            if ($sisa <= 0) break;

            $lebar = $atas === null ? $sisa : min($sisa, (float) $atas - $bawah);

            if ($lebar <= 0) continue;

            $bagian = $lebar * $tarif / 100;

            $lapis[] = ['dasar' => round($lebar, 2), 'tarif' => $tarif, 'pajak' => round($bagian, 0)];

            $pajak += $bagian;
            $sisa  -= $lebar;
            $bawah  = (float) ($atas ?? $bawah);
        }

        return [$pajak, $lapis];
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /** @return array{0:bool,1:int} kawin, jumlah tanggungan */
    private static function urai(?string $status): array
    {
        if (! $status || ! preg_match('#^(TK|K)/(\d+)$#', strtoupper(trim($status)), $c)) {
            return [false, 0];
        }

        return [$c[1] === 'K', min((int) $c[2], self::MAKS_TANGGUNGAN)];
    }
}
