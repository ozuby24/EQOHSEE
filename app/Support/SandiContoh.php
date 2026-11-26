<?php

namespace App\Support;

/**
 * Sandi seluruh akun contoh, dan larangan memakainya di server sungguhan.
 *
 * ── KENAPA ADA LARANGANNYA ──
 *
 * `demo:pasang` dan pemuat data contoh membuat akun bersandi seragam
 * supaya alurnya dapat dicoba tanpa mengatur sandi satu per satu —
 * termasuk satu akun ADMINISTRATOR (demo@contoh.test) yang menjangkau
 * seluruh perusahaan dan seluruh tombol Pusat Kendali. Sandinya tertulis
 * di kode sumber, dan kode sumber ini terbuka untuk umum. Di server yang
 * dapat dibuka dari internet, itu bukan lagi akun contoh melainkan pintu
 * administrator yang kuncinya diumumkan.
 *
 * Ditolak di PINTU MASUK, bukan dengan mengubah akunnya diam-diam. Akun,
 * peran, dan datanya tetap utuh; yang tidak lagi berlaku hanyalah sandi
 * yang sudah diketahui semua orang. Begitu administrator mengatur sandi
 * baru di Kelola Pengguna, akun yang sama langsung dapat dipakai lagi.
 *
 * Penolakannya tidak bergantung pada akunnya: sandi ini ditolak untuk
 * SETIAP surel, terdaftar atau tidak. Memeriksa akunnya lebih dulu akan
 * memberi tahu penebak surel mana yang ada.
 *
 * Hanya `local` dan `testing` yang dikecualikan — lingkungan yang memang
 * tidak dapat dibuka orang lain. Lingkungan kosong (APP_ENV tidak diisi),
 * `staging`, dan `production` semuanya dianggap dapat dibuka dari luar.
 */
final class SandiContoh
{
    /** Tertulis di kode sumber yang terbuka. Jangan dipakai sebagai rahasia. */
    public const BAWAAN = 'rahasia123';

    public const PESAN = 'Sandi ini adalah sandi akun contoh yang tercantum di kode sumber, '
        .'sehingga tidak dapat dipakai masuk di server ini. Minta administrator mengatur '
        .'sandi baru untuk akun Anda di Kelola Pengguna.';

    /** Apakah sandi contoh boleh dipakai masuk di lingkungan ini. */
    public static function bolehDipakai(): bool
    {
        return app()->environment(['local', 'testing']);
    }

    /** Apakah sandi yang diketik adalah sandi contoh. Dibandingkan waktu-tetap. */
    public static function adalah(?string $sandi): bool
    {
        return $sandi !== null && hash_equals(self::BAWAAN, $sandi);
    }
}
