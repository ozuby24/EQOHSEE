<?php

namespace App\Models\Concerns;

/**
 * Mengisi company_id dari pengguna yang membuat barisnya.
 *
 * Batas data per perusahaan hanya bekerja bila barisnya memang bertuan.
 * Tanpa ini, Gudang tidak pernah menyebut company_id sama sekali,
 * sementara Energi, Dokumen, dan Inspeksi mengambilnya dari isian yang
 * boleh dikosongkan — sehingga data baru terus lahir tanpa pemilik dan
 * tetap terlihat oleh semua perusahaan. Pemisahan yang diminta tidak
 * pernah benar-benar terjadi, dan tidak ada satu pun galat yang
 * menandainya.
 *
 * Nilai yang sudah ditentukan tidak pernah ditimpa: administrator yang
 * sengaja membuatkan data untuk perusahaan lain, atau sengaja
 * membiarkannya menjadi milik bersama lewat isian formulir, tetap
 * berlaku. Yang diisi hanyalah yang dibiarkan kosong.
 *
 * Tanpa pengguna — penyemai, migrasi, antrean — tidak diisi apa pun,
 * sebab tidak ada perusahaan yang dapat disimpulkan dari situ.
 */
trait BerpemilikPerusahaan
{
    public static function bootBerpemilikPerusahaan(): void
    {
        static::creating(function ($model) {
            if (!is_null($model->company_id)) return;

            $u = auth()->user();

            if ($u && $u->company_id) $model->company_id = $u->company_id;
        });
    }
}
