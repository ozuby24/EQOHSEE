<?php

namespace App\Support;

/**
 * Warna tetap untuk tiap kategori kursus.
 *
 * Warnanya diikatkan pada nama kategori, bukan pada urutan tampil. Memilih
 * warna dari indeks perulangan membuat "Wajib" berwarna jingga di satu
 * halaman dan biru di halaman lain hanya karena daftarnya terurut berbeda —
 * dan warna yang berpindah-pindah tidak dapat dipakai untuk mengenali apa
 * pun, yang justru satu-satunya gunanya.
 *
 * Kategori di luar daftar tetap mendapat warna yang tetap lewat crc32 nama­nya,
 * sehingga kategori baru tidak perlu didaftarkan lebih dulu supaya konsisten.
 */
final class Kategori
{
    /** Nada yang tersedia; sama dengan kelas .t-* pada lapisan visual. */
    public const NADA = ['toska', 'biru', 'kuning', 'hijau', 'ungu', 'merah'];

    /**
     * Kata kunci nama kategori → nada.
     *
     * Kunci terpanjang yang cocok menang, jadi urutan penulisan di sini tidak
     * memengaruhi hasil.
     */
    private const PETA = [
        'wajib'         => 'kuning',   // yang harus diikuti — paling menonjol
        'operasional'   => 'biru',
        'keselamatan'   => 'toska',
        'kesehatan'     => 'hijau',
        'lingkungan'    => 'hijau',
        'higiene'       => 'toska',
        'hygiene'       => 'toska',
        'darurat'       => 'merah',
        'tanggap'       => 'merah',
        'risiko'        => 'merah',
        'kepemimpinan'  => 'ungu',
        'manajemen'     => 'ungu',
        'sertifikasi'   => 'ungu',
        'penyegaran'    => 'biru',
        'induksi'       => 'kuning',
        'teknis'        => 'biru',
        'energi'        => 'kuning',
        'konservasi'    => 'hijau',
        'mutu'          => 'ungu',
        'kualitas'      => 'ungu',
    ];

    public static function nada(?string $kategori): string
    {
        $k = mb_strtolower(trim((string) $kategori));

        if ($k === '') return 'toska';

        $nada = null;
        $panjang = 0;
        foreach (self::PETA as $cari => $n) {
            if (mb_strlen($cari) > $panjang && str_contains($k, $cari)) {
                $nada = $n;
                $panjang = mb_strlen($cari);
            }
        }

        return $nada ?? self::NADA[crc32($k) % count(self::NADA)];
    }
}
