<?php

namespace Eqohsee\SmkpAudit\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Batas perusahaan bagi baris yang pemiliknya ada pada INDUKNYA.
 *
 * Temuan dan daftar hadir tidak punya company_id sendiri, dan memang tidak
 * perlu: kepemilikannya tidak pernah berbeda dari auditnya, dan menyalin
 * kolomnya ke bawah hanya melahirkan dua sumber kebenaran yang cepat atau
 * lambat berselisih.
 *
 * Masalahnya bukan di daftar — daftar selalu digambar lewat induknya. Yang
 * berbahaya adalah RUTE: `PUT /smkp/{smkp}/temuan/{temuan}` mengikat anaknya
 * langsung. Penjagaannya dipasang sebagai global scope pada anaknya,
 * menyaring lewat `whereHas` ke induknya; batas perusahaannya sendiri tidak
 * ditulis ulang di sini karena scope induk ikut berlaku di dalam subkueri.
 *
 * Pemakaian:
 *
 *     class SmkpFinding extends Model
 *     {
 *         use BerindukPerusahaan;
 *
 *         protected static string $indukPerusahaan = 'audit';
 *     }
 */
trait BerindukPerusahaan
{
    public static function bootBerindukPerusahaan(): void
    {
        static::addGlobalScope('smkp-induk', function (Builder $q) {
            $relasi = static::$indukPerusahaan
                ?? throw new \LogicException(
                    static::class.' memakai BerindukPerusahaan tanpa menyebut $indukPerusahaan.'
                );

            /* Satu syarat saja, dan sengaja tanpa kelonggaran untuk baris
               yatim. `whereHas OR doesntHave` pernah dicoba supaya anak tanpa
               induk tetap terlihat; itu membuat penjaganya berhenti menjaga:
               doesntHave() ikut memakai scope induknya, sehingga bagi induk
               milik perusahaan lain ia menjawab "tidak punya induk" — dan
               gabungannya bernilai benar untuk SETIAP baris. Kelonggarannya
               juga tidak diperlukan: kunci induk kedua tabel ini NOT NULL,
               jadi anak yatim tidak dapat ada. */
            $q->whereHas($relasi);
        });
    }
}
