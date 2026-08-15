<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PilarController;

/*
 | Halaman Enam Pilar — kerangka kerja EQOHSEE.
 | Ditambahkan otomatis oleh paket eqohsee-pilar.
 */
Route::middleware('auth')->group(function () {
    Route::get('/pilar', [PilarController::class, 'index'])->name('pilar');
});
