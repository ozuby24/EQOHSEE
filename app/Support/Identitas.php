<?php

namespace App\Support;

/**
 * Menyatukan identitas pelapor yang tertulis berbeda-beda.
 *
 * Laporan menyimpan identitas orang sebagai teks — nama, NRP, jabatan —
 * di samping user_id. Teks itu diketik di lapangan, dan satu orang yang
 * sama muncul sebagai "Budi Santoso", "budi santoso", dan "Budi  Santoso"
 * pada tiga laporan berbeda.
 *
 * Dikelompokkan mentah, ketiganya terhitung sebagai TIGA orang. Karena
 * target KPI dijumlahkan per orang, targetnya ikut menjadi tiga kali
 * lipat sementara laporannya tetap tiga — dan capaian orang itu, beserta
 * capaian golongannya, ambruk menjadi sepertiga. Tidak ada galat yang
 * muncul; yang terlihat hanya angka yang tampak buruk.
 *
 * Urutan kunci mengikuti seberapa dapat dipercaya sumbernya: user_id
 * ditetapkan sistem, NRP diketik sekali dan jarang berubah, nama paling
 * mudah bervariasi.
 */
final class Identitas
{
    /** Kunci pengelompokan satu orang di seluruh laporan. */
    public static function kunci(?int $userId, ?string $nrp, ?string $nama): string
    {
        if ($userId) return 'u'.$userId;

        if ($bersih = self::normalNrp($nrp)) return 'n'.$bersih;

        return 'x'.self::normalNama($nama);
    }

    /** NRP tanpa pemisah dan tanpa beda huruf besar-kecil. */
    public static function normalNrp(?string $nrp): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim((string) $nrp)));
    }

    /** Nama tanpa beda huruf besar-kecil dan tanpa spasi berlebih. */
    public static function normalNama(?string $nama): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $nama)));
    }
}
