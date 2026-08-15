<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Batas perusahaan bagi baris yang pemiliknya ada pada INDUKNYA.
 *
 * Sebagian besar tabel anak tidak punya company_id sendiri, dan memang
 * tidak perlu: satu instrumen milik lerengnya, satu pompa milik
 * kolamnya, satu temuan milik auditnya. Kepemilikannya tidak pernah
 * berbeda dari induknya, dan menyalin kolomnya ke bawah hanya melahirkan
 * dua sumber kebenaran yang cepat atau lambat berselisih.
 *
 * Masalahnya bukan di daftar — daftar selalu digambar lewat induknya,
 * jadi induknya sudah menyaring. Masalahnya di ROUTE: `PUT
 * /geoteknik/instrumen/{instrumen}` mengikat anaknya langsung, dan
 * pengikatan itu tidak pernah menyentuh induknya sama sekali. Pengguna
 * perusahaan mana pun karena itu dapat mengubah status instrumen dan
 * pompa milik perusahaan lain — dua alat yang statusnya dipakai orang
 * untuk memutuskan apakah suatu tempat aman dimasuki.
 *
 * Penjagaannya dipasang sebagai global scope pada anaknya, menyaring
 * lewat `whereHas` ke induknya. Batas perusahaannya sendiri TIDAK
 * ditulis ulang di sini: induknya sudah memakai MilikPerusahaan, dan
 * scope itu ikut berlaku di dalam subkueri whereHas. Satu tempat yang
 * mengatur artinya, dan anak-anaknya mewarisi apa pun yang berlaku di
 * sana — termasuk kelonggaran bagi administrator dan bagi baris yang
 * belum bertuan.
 *
 * Pemakaian:
 *
 *     class GeoInstrumen extends Model
 *     {
 *         use BerindukPerusahaan;
 *
 *         protected static string $indukPerusahaan = 'lereng';
 *     }
 */
trait BerindukPerusahaan
{
    public static function bootBerindukPerusahaan(): void
    {
        static::addGlobalScope('induk-perusahaan', function (Builder $q) {
            /* Tanpa pengguna — perintah konsol, antrean, penyemai,
               migrasi — tidak disaring, sama seperti MilikPerusahaan.
               Menyaring di situ membuat pekerjaan terjadwal diam-diam
               memproses sebagian data saja. */
            $u = auth()->user();
            if (!$u || $u->isAdmin()) return;

            $relasi = static::$indukPerusahaan
                ?? throw new \LogicException(
                    static::class.' memakai BerindukPerusahaan tanpa menyebut $indukPerusahaan.'
                );

            /* Satu syarat saja, dan sengaja tanpa kelonggaran untuk
               baris yatim.

               Percobaan pertama menulisnya sebagai `whereHas OR
               doesntHave` supaya anak tanpa induk tetap terlihat. Itu
               membuat penjaganya berhenti menjaga sama sekali:
               doesntHave() ikut memakai scope induknya, sehingga bagi
               induk milik perusahaan lain ia menjawab "tidak punya
               induk" — benar menurut pandangan pengguna itu, dan justru
               karena itu gabungannya bernilai benar untuk SETIAP baris.
               Scope-nya terpasang, kuerinya bertambah panjang, dan tidak
               satu baris pun tersaring.

               Kelonggarannya juga tidak diperlukan: seluruh tabel yang
               memakai trait ini punya kunci induk NOT NULL, sehingga
               anak yatim tidak dapat ada. Yang menjaga pernyataan itu
               tetap benar adalah uji, bukan ingatan — lihat
               BatasIndukTest::test_kunci_induk_tidak_boleh_null. */
            $q->whereHas($relasi);
        });
    }
}
