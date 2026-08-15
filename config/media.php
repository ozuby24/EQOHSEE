<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Letak berkas media halaman depan
    |--------------------------------------------------------------------------
    |
    | Relatif terhadap public/. Dapat dialihkan saat pengujian agar perilaku
    | halaman ketika berkasnya belum ada tetap dapat diuji, tanpa perlu
    | memindahkan foto dan video yang sungguhan.
    |
    */

    'akar' => env('MEDIA_AKAR', 'media'),

];
