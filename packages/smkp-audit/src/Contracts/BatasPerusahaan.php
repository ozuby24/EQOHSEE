<?php

namespace Eqohsee\SmkpAudit\Contracts;

/**
 * Penyaring data per perusahaan.
 *
 * Modul ini tidak tahu bagaimana aplikasi induk menyimpan penempatan
 * perusahaan seorang pengguna — kolom pada tabel users, tabel pivot, klaim
 * pada token, atau tidak ada sama sekali. Yang perlu ia ketahui hanya dua
 * jawaban di bawah, dan keduanya ditanyakan lewat wadah layanan sehingga
 * aplikasi induk dapat menggantinya tanpa menyunting satu baris pun modul.
 */
interface BatasPerusahaan
{
    /**
     * Perusahaan yang sedang aktif bagi pengguna saat ini.
     *
     * null berarti tidak ada batas yang dapat disimpulkan: perintah konsol,
     * antrean, penyemai, atau aplikasi satu perusahaan.
     */
    public function idAktif(): int|string|null;

    /**
     * Benar bila pengguna saat ini boleh melihat seluruh perusahaan.
     */
    public function lintasPerusahaan(): bool;
}
