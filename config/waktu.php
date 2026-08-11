<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zona waktu tampilan
    |--------------------------------------------------------------------------
    |
    | Penyimpanan tetap UTC (lihat config/app.php). Yang diatur di sini hanya
    | zona yang dipakai saat menampilkan waktu kepada pengguna dan saat
    | menentukan sapaan pada dashboard.
    |
    | Asia/Jakarta (WIB) · Asia/Makassar (WITA) · Asia/Jayapura (WIT)
    |
    */

    'zona' => env('WAKTU_ZONA', 'Asia/Makassar'),

];
