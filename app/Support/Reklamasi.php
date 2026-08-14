<?php

namespace App\Support;

/**
 * Tahapan reklamasi, sebagai urutan.
 *
 * Dipisahkan menjadi kelasnya sendiri karena sifat berurutan itulah yang
 * mudah hilang. Ketika tahapan hanya berupa daftar string di dalam model,
 * tidak ada yang mencegah petak melompat dari "belum" langsung ke
 * "selesai", dan tidak ada tempat tunggal untuk menjawab "apakah tahapan
 * ini sudah melewati penebaran tanah pucuk".
 *
 * Urutannya mengikuti pelaksanaan reklamasi di lapangan: penataan lahan
 * lebih dulu, tanah pucuk menyusul, revegetasi setelahnya, lalu
 * pemeliharaan sampai tanamannya benar-benar hidup. Melewati satu tahapan
 * bukan percepatan — tanah pucuk yang ditebar di atas lahan yang belum
 * ditata akan tergerus pada hujan pertama.
 */
final class Reklamasi
{
    /** Urut dari yang paling awal. Kuncinya disimpan, bukan urutannya. */
    public const TAHAP = [
        'belum'        => 'Belum direklamasi',
        'penataan'     => 'Penataan lahan',
        'topsoil'      => 'Penebaran tanah pucuk',
        'revegetasi'   => 'Revegetasi',
        'pemeliharaan' => 'Pemeliharaan',
        'selesai'      => 'Selesai dinilai',
        'dilepas'      => 'Dilepas',
    ];

    /** Tahapan yang berarti pekerjaan reklamasinya sudah tuntas. */
    public const TUNTAS = ['selesai', 'dilepas'];

    public static function urutan(string $tahap): int
    {
        return array_search($tahap, array_keys(self::TAHAP), true) ?: 0;
    }

    public static function selesai(string $tahap): bool
    {
        return in_array($tahap, self::TUNTAS, true);
    }

    /** Sudah dimulai tetapi belum tuntas. */
    public static function sedangBerjalan(string $tahap): bool
    {
        return $tahap !== 'belum' && !self::selesai($tahap);
    }

    /** Sudah mencapai tahapan tertentu, atau melewatinya. */
    public static function sudahMencapai(string $tahap, string $patokan): bool
    {
        return self::urutan($tahap) >= self::urutan($patokan);
    }

    /**
     * Tahapan berikutnya yang wajar; null bila sudah di ujung.
     *
     * Dipakai tampilan untuk menawarkan langkah selanjutnya alih-alih
     * menyodorkan seluruh daftar, yang membuat lompatan tahapan tampak
     * sama wajarnya dengan urutan yang benar.
     */
    public static function berikutnya(string $tahap): ?string
    {
        $kunci = array_keys(self::TAHAP);
        $i = array_search($tahap, $kunci, true);

        return ($i === false || $i >= count($kunci) - 1) ? null : $kunci[$i + 1];
    }

    /** Boleh berpindah ke tahapan ini? Mundur diizinkan, melompat maju tidak. */
    public static function bolehKe(string $dari, string $ke): bool
    {
        if (!isset(self::TAHAP[$ke])) return false;

        // Mundur selalu boleh: revegetasi yang gagal memang harus
        // dikembalikan ke tahapan sebelumnya, dan menolaknya memaksa
        // orang mencatat keberhasilan yang tidak terjadi.
        return self::urutan($ke) <= self::urutan($dari) + 1;
    }
}
