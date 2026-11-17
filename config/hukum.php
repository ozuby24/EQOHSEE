<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alamat surel pada halaman hukum
    |--------------------------------------------------------------------------
    |
    | Dipakai halaman kebijakan privasi sebagai alamat yang dituju permintaan
    | penghapusan data dan laporan dugaan kebocoran.
    |
    | Google Play menolak kebijakan privasi yang tidak memuat cara menghubungi
    | penerbitnya, dan alamat yang ditulis di sini akan dibaca orang luar —
    | jadi ia harus alamat yang memang dibaca seseorang, bukan alamat
    | no-reply.
    |
    | Disetel lewat HUKUM_SUREL pada .env supaya dapat berbeda antara server
    | uji dan server sungguhan tanpa menyunting berkas apa pun.
    |
    */

    'surel' => env('HUKUM_SUREL', 'privasi@eqohsee.id'),

];
