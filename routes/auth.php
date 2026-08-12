<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifikasiKodeController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    /* Verifikasi memakai kode enam angka, bukan tautan bertanda tangan.
       Tautan hanya bekerja bila surelnya dibuka di peramban yang sama
       dengan tempat mendaftar; surel kerja sering dibuka di ponsel lain
       atau di peramban dalaman sebuah aplikasi, dan sesi di sana kosong.

       Namanya tetap verification.notice karena middleware 'verified'
       bawaan Laravel mengalihkan ke nama itu. */
    Route::get('verify-email', [VerifikasiKodeController::class, 'tampil'])
        ->name('verification.notice');

    Route::post('verify-email', [VerifikasiKodeController::class, 'periksa'])
        ->middleware('throttle:10,1')
        ->name('verification.periksa');

    Route::post('email/verification-notification', [VerifikasiKodeController::class, 'kirimUlang'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
