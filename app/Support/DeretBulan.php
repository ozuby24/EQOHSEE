<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Deret bulan berurutan — dan penjagaan terhadap luapan tanggal.
 *
 * ── CACAT YANG DICEGAHNYA ──
 *
 * `now()->subMonths($i)` terlihat benar dan tidak benar pada tiga hari
 * terakhir tiap bulan. Carbon mengurangi bulan dengan mempertahankan
 * TANGGALNYA, lalu meluap ketika tanggal itu tidak ada di bulan tujuan:
 *
 *     31 Agustus − 6 bulan  →  31 Februari  →  meluap ke 3 Maret
 *     31 Agustus − 5 bulan  →  31 Maret
 *
 * Keduanya jatuh di bulan yang sama. Pada deret dua belas bulan yang
 * disusun dari tanggal 31, yang tersisa hanya TUJUH bulan berbeda —
 * lima bulan hilang tanpa satu pun galat.
 *
 * Akibatnya berbeda-beda menurut pemakaiannya, dan semuanya sunyi:
 *
 *   Deret berkunci ('Y-m' => angka) kehilangan barisnya diam-diam,
 *   sehingga grafik tren memendek dari dua belas batang menjadi tujuh.
 *   Sumbu yang memendek mengikuti tanggal membuat dua kunjungan pada
 *   halaman yang sama tampak seperti rentang waktu yang berbeda.
 *
 *   Deret berlarik menghitung bulan yang sama dua kali dan melewatkan
 *   bulan lain sepenuhnya — grafiknya tetap dua belas batang, tetapi
 *   dua di antaranya berlabel sama dan satu bulan tidak pernah muncul.
 *   Yang kedua ini lebih berbahaya: ia tidak terlihat salah.
 *
 * Sebabnya hanya muncul tanggal 29–31, jadi ia lewat dari perhatian
 * selama dua puluh delapan hari lalu kambuh tanpa ada yang mengubah
 * apa pun.
 *
 * ── PENAWARNYA ──
 *
 * Berangkat dari AWAL BULAN sebelum menambah atau mengurangi. Tanggal 1
 * ada di setiap bulan, jadi tidak ada yang dapat meluap.
 */
final class DeretBulan
{
    /**
     * `$jumlah` bulan ke belakang sampai bulan ini, urut lama ke baru.
     *
     * @return list<Carbon> masing-masing pada tanggal 1
     */
    public static function mundur(Carbon $kini, int $jumlah): array
    {
        $awal = $kini->copy()->startOfMonth();

        $out = [];
        for ($i = $jumlah - 1; $i >= 0; $i--) $out[] = $awal->copy()->subMonths($i);

        return $out;
    }

    /**
     * `$jumlah` bulan ke depan mulai bulan ini, urut baru ke lama.
     *
     * @return list<Carbon> masing-masing pada tanggal 1
     */
    public static function maju(Carbon $kini, int $jumlah): array
    {
        $awal = $kini->copy()->startOfMonth();

        $out = [];
        for ($i = 0; $i < $jumlah; $i++) $out[] = $awal->copy()->addMonths($i);

        return $out;
    }

    /**
     * Kunci 'Y-m' dari deret mundur — bentuk yang paling sering dipakai.
     *
     * @return list<string>
     */
    public static function kunciMundur(Carbon $kini, int $jumlah): array
    {
        return array_map(fn (Carbon $b) => $b->format('Y-m'), self::mundur($kini, $jumlah));
    }
}
