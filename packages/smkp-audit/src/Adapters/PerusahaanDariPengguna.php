<?php

namespace Eqohsee\SmkpAudit\Adapters;

use Eqohsee\SmkpAudit\Contracts\BatasPerusahaan;

/**
 * Batas perusahaan yang dibaca dari pengguna yang sedang masuk.
 *
 * Cocok untuk aplikasi yang menyimpan penempatan perusahaan sebagai kolom
 * pada model penggunanya (bawaan: `company_id`), dan menandai administrator
 * lewat sebuah metode (bawaan: `isAdmin()`).
 *
 * Keduanya dibaca dengan hati-hati: aplikasi yang tidak punya kolom atau
 * metode itu tidak menghasilkan galat, hanya tidak ada batas yang dapat
 * disimpulkan — persis keadaan aplikasi satu perusahaan.
 */
class PerusahaanDariPengguna implements BatasPerusahaan
{
    public function idAktif(): int|string|null
    {
        $u = auth()->user();
        if (!$u) return null;

        $atribut = (string) config('smkp.perusahaan.atribut', 'company_id');

        return $u->{$atribut} ?? null;
    }

    public function lintasPerusahaan(): bool
    {
        $u = auth()->user();

        // Tanpa pengguna — konsol, antrean, penyemai — tidak disaring.
        // Menyaring di situ membuat pekerjaan terjadwal diam-diam memproses
        // sebagian data saja: kegagalan yang jauh lebih sulit dilacak
        // daripada kebocoran yang sedang dicegah.
        if (!$u) return true;

        $metode = config('smkp.perusahaan.admin');

        return $metode && method_exists($u, $metode) ? (bool) $u->{$metode}() : false;
    }
}
