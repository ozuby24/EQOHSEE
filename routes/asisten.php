<?php

/*
 * Asisten AI audit SMKP — pengguna yang masuk (prefix /asisten, nama rute
 * asisten.*). Didaftarkan dari routes/web.php di dalam grup auth+verified.
 *
 * Seluruhnya POST, JSON masuk/keluar, hanya auditor & administrator, dan
 * dibatasi per pengguna per jam (RateLimiter "asisten" di AppServiceProvider).
 * Batas perusahaan ditegakkan global scope model lewat route model binding.
 * Sambungan ke penyedia AI memakai pengaturan Pusat Kendali (App\Support\Ai).
 */

use App\Http\Controllers\{AsistenController, RencanaImporController};
use Illuminate\Support\Facades\Route;

Route::middleware(['can:audit', 'throttle:asisten'])->group(function () {
    Route::post('smkp/{smkp}/butir',            [AsistenController::class, 'butir'])->name('smkp.butir');
    Route::post('smkp/{smkp}/temuan/{temuan}',  [AsistenController::class, 'temuan'])->name('smkp.temuan');
    // Analisis berkas bukti satu butir (PDF/gambar/dokumen Office yang diunggah).
    Route::post('smkp/{smkp}/bukti',            [AsistenController::class, 'bukti'])->name('smkp.bukti');
    // Ekstraksi metode & sampel dari dokumen Rencana Audit lama (DOCX/PDF).
    Route::post('smkp/{smkp}/rencana',          [RencanaImporController::class, 'ekstrak'])->name('smkp.rencana');
});
