<?php

namespace Eqohsee\SmkpAudit\Models\Concerns;

use Eqohsee\SmkpAudit\Contracts\BatasPerusahaan;
use Illuminate\Database\Eloquent\Builder;

/**
 * Batas data per perusahaan bagi tabel yang punya kolom perusahaannya sendiri.
 *
 * Dipasang sebagai global scope, bukan sebagai baris `where` di tiap
 * controller. Batas yang ditulis ulang di tiap tempat pemakaian akan
 * tertinggal cepat atau lambat — pada ekspor, pada hitungan statistik, pada
 * halaman baru yang ditulis enam bulan kemudian — dan yang tertinggal tidak
 * menimbulkan galat, hanya data perusahaan lain yang diam-diam ikut terbaca.
 * Sebagai global scope, yang perlu ditulis tegas justru kebalikannya:
 * melepas batas (`withoutGlobalScope`) terlihat saat ditinjau.
 *
 * Baris tanpa perusahaan (company_id NULL) terlihat oleh semua orang. Itu
 * bukan kelonggaran melainkan arti kolomnya: baris yang belum dimiliki
 * perusahaan mana pun bukan milik pihak lain yang harus disembunyikan.
 * Menyaringnya sebagai `company_id = <milik saya>` saja membuat seluruh
 * modul tampak KOSONG bagi pengguna yang sudah ditempatkan di sebuah
 * perusahaan selama data lamanya masih NULL — kegagalan yang diam.
 */
trait MilikPerusahaan
{
    public static function bootMilikPerusahaan(): void
    {
        static::addGlobalScope('smkp-perusahaan', function (Builder $q) {
            if (!static::pakaiPerusahaan()) return;

            $batas = app(BatasPerusahaan::class);

            if ($batas->lintasPerusahaan()) return;

            $kolom = $q->getModel()->getTable().'.company_id';
            $milik = $batas->idAktif();

            $q->where(function (Builder $b) use ($kolom, $milik) {
                $b->whereNull($kolom);

                if ($milik !== null) $b->orWhere($kolom, $milik);
            });
        });

        /* Baris baru mewarisi perusahaan pembuatnya. Tanpa ini, batas per
           perusahaan hanya setengah bekerja: data baru terus lahir tanpa
           pemilik dan tetap terlihat oleh semua orang. Nilai yang sudah
           ditentukan tidak pernah ditimpa — administrator yang sengaja
           membuatkan data untuk perusahaan lain tetap berlaku. */
        static::creating(function ($model) {
            if (!static::pakaiPerusahaan()) return;

            if (!is_null($model->company_id)) return;

            $model->company_id = app(BatasPerusahaan::class)->idAktif();
        });
    }

    /**
     * Batas perusahaan hanya berlaku bila aplikasi induk memang punya
     * perusahaan. Pada aplikasi satu perusahaan, kolomnya tetap ada tetapi
     * tidak pernah diisi maupun disaring — dan scope yang tetap menyaring di
     * situ akan menyembunyikan baris yang justru satu-satunya yang ada.
     */
    public static function pakaiPerusahaan(): bool
    {
        return (bool) config('smkp.model.perusahaan');
    }
}
