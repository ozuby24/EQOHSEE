<?php

use Illuminate\Support\Facades\Route;

/*
 | Halaman Enam Pilar — kerangka kerja EQOHSEE.
 | Ditambahkan otomatis oleh paket eqohsee-pilar.
 */
Route::middleware('auth')->group(function () {
    Route::view('/pilar', 'pilar')->name('pilar');
});
