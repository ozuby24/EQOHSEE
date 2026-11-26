<?php

/*
|--------------------------------------------------------------------------
| MERANTI — peta satelit
|--------------------------------------------------------------------------
|
| Peta Spasial MERANTI menggambar poligon di atas citra satelit dan medan
| 3D, seperti Google Earth. Ubinnya diambil peramban langsung dari
| penyedianya, jadi host di bawah ini SEKALIGUS menjadi kelonggaran CSP
| (img-src dan connect-src) di App\Http\Middleware\TajukKeamanan —
| mengganti penyedia cukup di sini, tanpa menyentuh CSP.
|
| Bawaannya citra Esri World Imagery dan medan Terrain Tiles di AWS Open
| Data (format Terrarium). Untuk pemakaian berat atau komersial, daftarkan
| kunci penyedia (ArcGIS Location Platform, MapTiler, dsb.) dan ganti
| alamatnya lewat .env. Jaringan site yang tertutup tetap dapat memakai
| peta skema, yang digambar tanpa internet.
|
*/

return [
    'peta' => [
        'aktif' => (bool) env('MERANTI_PETA_SATELIT_AKTIF', true),

        // Citra satelit — templat XYZ {z}/{x}/{y} (Esri memakai urutan {z}/{y}/{x}).
        'satelit' => env('MERANTI_PETA_SATELIT', 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'),
        'atribusi' => env('MERANTI_PETA_ATRIBUSI', 'Citra © Esri, Maxar, Earthstar Geographics, dan komunitas pengguna GIS'),
        'zoom_maks' => (int) env('MERANTI_PETA_ZOOM_MAKS', 19),

        // Nama tempat dan batas wilayah di atas citra; kosongkan untuk mematikan.
        'label' => env('MERANTI_PETA_LABEL', 'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}'),

        // Medan 3D (raster-dem, encoding terrarium); kosongkan untuk mematikan 3D.
        'medan' => env('MERANTI_PETA_MEDAN', 'https://elevation-tiles-prod.s3.amazonaws.com/terrarium/{z}/{x}/{y}.png'),
        'atribusi_medan' => env('MERANTI_PETA_ATRIBUSI_MEDAN', 'Medan: Terrain Tiles, AWS Open Data'),
    ],
];
