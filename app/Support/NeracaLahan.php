<?php

namespace App\Support;

/**
 * Neraca lahan dan kemajuan reklamasi.
 *
 * Dua hal di sini mudah salah, dan keduanya salah tanpa menimbulkan galat.
 *
 * PERTAMA: tahapan reklamasi bertingkat, bukan terpisah. Lahan yang sudah
 * direvegetasi pasti sudah ditata dan sudah ditebari tanah pucuk lebih
 * dulu. Menjumlahkan luas ditata + topsoil + revegetasi menghitung petak
 * yang sama sampai tiga kali, dan angkanya melampaui luas bukaannya
 * sendiri — laporan yang menyatakan reklamasi 240% dari lahan terganggu.
 * Karena itu tiap tahapan disimpan sebagai luas kumulatif yang sudah
 * mencapai tahapan itu, dan luas "selesai" diambil dari tahapan
 * terjauhnya saja, bukan dari penjumlahan.
 *
 * KEDUA: lahan terganggu yang masih ditambang bukan tunggakan reklamasi.
 * Menghitungnya sebagai tunggakan membuat tambang yang sedang berproduksi
 * selalu tampak lalai, dan angka yang selalu merah berhenti dibaca.
 * Jam tunggakan baru berjalan sejak penambangan di petak itu berhenti.
 */
final class NeracaLahan
{
    /**
     * Ambang bawaan tingkat tumbuh revegetasi, persen.
     *
     * Mengikuti angka yang lazim dipakai pada penilaian keberhasilan
     * reklamasi. Disimpan sebagai bawaan yang dapat ditimpa, bukan
     * ketetapan: aturan pelaksanaannya berganti dari waktu ke waktu, dan
     * mengunci angkanya di dalam kode membuat modul ini kedaluwarsa
     * bersama satu versi regulasi.
     */
    public const TUMBUH_MINIMUM_PERSEN = 80.0;

    /** Batas bawaan lamanya lahan boleh menganggur sebelum direklamasi. */
    public const TUNGGAKAN_WAJAR_HARI = 365;

    /**
     * Susun neraca dari sekumpulan petak.
     *
     * Tiap petak berbentuk:
     *   luas_ha         luas terganggu
     *   tahap           tahapan terjauh yang dicapai
     *   menganggur_hari null bila masih ditambang
     *
     * @param  list<array{luas_ha:float,tahap:string,menganggur_hari:?int}> $petak
     * @return array<string,mixed>
     */
    public static function susun(array $petak, int $tunggakanWajarHari = self::TUNGGAKAN_WAJAR_HARI): array
    {
        $terganggu = 0.0;
        $selesai   = 0.0;
        $berjalan  = 0.0;   // sudah mulai direklamasi, belum selesai
        $menunggu  = 0.0;   // berhenti ditambang, belum disentuh
        $aktif     = 0.0;   // masih ditambang
        $telat     = 0.0;
        $umurTerlama = 0;

        foreach ($petak as $p) {
            $luas = (float) $p['luas_ha'];
            $terganggu += $luas;

            $tahap = $p['tahap'];
            $nganggur = $p['menganggur_hari'];

            if (Reklamasi::selesai($tahap)) {
                $selesai += $luas;
                continue;
            }

            if ($nganggur === null) {
                // Masih ditambang: terganggu, tetapi belum jatuh tempo.
                $aktif += $luas;
                continue;
            }

            if (Reklamasi::sedangBerjalan($tahap)) {
                $berjalan += $luas;
            } else {
                $menunggu += $luas;
            }

            if ($nganggur > $tunggakanWajarHari) {
                $telat += $luas;
                $umurTerlama = max($umurTerlama, $nganggur);
            }
        }

        return [
            'terganggu'    => round($terganggu, 2),
            'selesai'      => round($selesai, 2),
            'berjalan'     => round($berjalan, 2),
            'menunggu'     => round($menunggu, 2),
            'aktif'        => round($aktif, 2),
            'tunggakan'    => round($berjalan + $menunggu, 2),
            'telat'        => round($telat, 2),
            'umurTerlama'  => $umurTerlama,
            // Persentase dihitung terhadap lahan yang memang sudah waktunya
            // direklamasi, bukan terhadap seluruh lahan terganggu. Memakai
            // seluruhnya membuat tambang yang baru membuka pit tampak
            // tertinggal jauh padahal belum ada yang jatuh tempo.
            'wajibReklamasi' => round($terganggu - $aktif, 2),
            'persenSelesai'  => self::persen($selesai, $terganggu - $aktif),
        ];
    }

    /**
     * Nisbah reklamasi pada satu periode: luas diselesaikan berbanding
     * luas dibuka.
     *
     * Di bawah satu berarti tunggakan bertambah meski reklamasi berjalan.
     * Angka inilah yang menunjukkan arah, bukan luas reklamasi saja —
     * reklamasi 50 ha terdengar besar sampai diketahui bukaannya 200 ha.
     */
    public static function nisbah(float $diselesaikanHa, float $dibukaHa): ?float
    {
        if ($dibukaHa <= 0) return null;

        return round($diselesaikanHa / $dibukaHa, 3);
    }

    /**
     * Perkiraan tahun sampai tunggakan habis pada laju sekarang.
     *
     * Null berarti tidak akan habis: lajunya nol, atau bukaannya
     * bertambah tidak lebih lambat daripada reklamasinya.
     */
    public static function tahunMenutupTunggakan(float $tunggakanHa, float $lajuBersihHaPerTahun): ?float
    {
        if ($tunggakanHa <= 0) return 0.0;
        if ($lajuBersihHaPerTahun <= 0) return null;

        return round($tunggakanHa / $lajuBersihHaPerTahun, 2);
    }

    /**
     * Kecukupan jaminan reklamasi.
     *
     * Membandingkan yang sudah ditempatkan dengan kebutuhan menurut luas
     * yang belum direklamasi dan biaya satuannya. Yang dikembalikan
     * selisihnya, bukan sekadar cukup atau tidak: besarnya kekurangan
     * itulah yang perlu dianggarkan.
     */
    public static function jaminan(float $ditempatkan, float $luasBelumHa, float $biayaPerHa): array
    {
        $butuh = round($luasBelumHa * $biayaPerHa, 2);
        $selisih = round($ditempatkan - $butuh, 2);

        return [
            'butuh'      => $butuh,
            'ditempatkan'=> round($ditempatkan, 2),
            'selisih'    => $selisih,
            'cukup'      => $selisih >= 0,
            'persen'     => self::persen($ditempatkan, $butuh),
        ];
    }

    private static function persen(float $bagian, float $dari): ?float
    {
        if ($dari <= 0) return null;

        return round($bagian / $dari * 100, 2);
    }
}
