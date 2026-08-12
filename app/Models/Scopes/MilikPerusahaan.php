<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Batas data per perusahaan.
 *
 * Dipasang sebagai global scope, bukan sebagai baris `where` di tiap
 * controller. Batas yang ditulis ulang di tiap tempat pemakaian akan
 * tertinggal cepat atau lambat — pada ekspor, pada hitungan statistik,
 * pada halaman baru yang ditulis enam bulan kemudian — dan yang
 * tertinggal tidak menimbulkan galat, hanya data perusahaan lain yang
 * diam-diam ikut terbaca. Sebagai global scope, yang perlu diingat
 * justru kebalikannya: melepas batas harus ditulis dengan tegas
 * (`withoutGlobalScope`), sehingga terlihat saat ditinjau.
 *
 * Administrator EQOHSEE menjangkau seluruh perusahaan; penyaringan
 * `?perusahaan=` yang sudah ada di beberapa modul tetap bekerja karena
 * scope ini tidak ikut campur bagi admin.
 *
 * Pengguna tanpa perusahaan hanya menjangkau baris yang juga tanpa
 * perusahaan. Laravel menerjemahkan `where(kolom, null)` menjadi
 * `is null`, jadi satu baris ini sudah menutup kedua keadaan.
 */
class MilikPerusahaan implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $u = auth()->user();

        /* Tanpa pengguna: perintah konsol, antrean, penyemai, migrasi.
           Menyaring di situ akan membuat pekerjaan terjadwal diam-diam
           memproses sebagian data saja — kegagalan yang jauh lebih sulit
           dilacak daripada kebocoran yang sedang dicegah di sini. */
        if (!$u || $u->isAdmin()) return;

        $builder->where($model->getTable().'.company_id', $u->company_id);
    }
}
