<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\MasukPihakKetigaController;
use App\Support\MasukPihakKetiga;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\DuaFaktorTantanganController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifikasiKodeController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    /* Batas laju pada pintu masuk — lihat AppServiceProvider::batasLaju
       untuk angka dan alasannya. Dipasang di rute, bukan di controller:
       rute yang lupa memakainya terlihat di berkas ini, sedangkan
       controller yang lupa memanggilnya tidak terlihat di mana pun. */
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:daftar');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:masuk');

    /* Masuk dengan akun Google / Microsoft. 404 bila penyedianya belum
       dipasang di .env (MasukPihakKetigaController). */
    Route::get('masuk/{penyedia}', [MasukPihakKetigaController::class, 'alihkan'])
        ->whereIn('penyedia', MasukPihakKetiga::PENYEDIA)
        ->middleware('throttle:masuk-sso')
        ->name('masuk.sso');

    Route::get('masuk/{penyedia}/kembali', [MasukPihakKetigaController::class, 'kembali'])
        ->whereIn('penyedia', MasukPihakKetiga::PENYEDIA)
        ->middleware('throttle:masuk-sso')
        ->name('masuk.sso.kembali');

    /* Daftar dengan akun Google / Microsoft. Kembalinya lewat alamat
       yang sama dengan masuk (satu-satunya yang didaftarkan di konsol
       penyedia); akunnya baru dibuat sesudah formulir Daftar dikirim. */
    Route::get('daftar/{penyedia}', [MasukPihakKetigaController::class, 'daftar'])
        ->whereIn('penyedia', MasukPihakKetiga::PENYEDIA)
        ->middleware('throttle:masuk-sso')
        ->name('daftar.sso');

    Route::post('daftar/sso/batal', [MasukPihakKetigaController::class, 'batalDaftar'])
        ->name('daftar.sso.batal');

    /* Alamat lazim panduan Laravel Socialite: /auth/google dan
       /auth/google/callback. Banyak yang mendaftarkan alamat INI di konsol
       Google; keduanya dilayani alur yang sama supaya pemasangan tidak
       berakhir redirect_uri_mismatch. Alamat kembali yang dikirim tetap
       satu — services.{penyedia}.redirect (GOOGLE_REDIRECT_URI) — dan
       `php artisan sso:periksa` menyebutkan yang harus didaftarkan.

       Ditulis harfiah per penyedia: rute harfiah yang sama yang ditulis
       tangan di routes/web.php (dimuat lebih dulu) tertimpa oleh ini. */
    foreach (MasukPihakKetiga::PENYEDIA as $penyedia) {
        Route::get("auth/{$penyedia}", [MasukPihakKetigaController::class, 'alihkan'])
            ->defaults('penyedia', $penyedia)
            ->middleware('throttle:masuk-sso');

        Route::get("auth/{$penyedia}/callback", [MasukPihakKetigaController::class, 'kembali'])
            ->defaults('penyedia', $penyedia)
            ->middleware('throttle:masuk-sso');
    }

    /* Halaman kode dua faktor.
       Tetap di dalam grup 'guest': yang membukanya BELUM masuk — ia
       hanya membawa setengah-masuk di sesinya. Dipasang di luar grup,
       ia akan terjangkau oleh orang yang sudah masuk dan tertutup bagi
       satu-satunya orang yang membutuhkannya. */
    Route::get('dua-faktor', [DuaFaktorTantanganController::class, 'tampil'])
        ->name('dua-faktor.tantangan');

    Route::post('dua-faktor', [DuaFaktorTantanganController::class, 'kirim'])
        ->middleware('throttle:dua-faktor');

    Route::post('dua-faktor/batal', [DuaFaktorTantanganController::class, 'batal'])
        ->name('dua-faktor.batal');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:lupa-sandi')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:sandi')
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

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:sandi');

    Route::put('password', [PasswordController::class, 'update'])
        ->middleware('throttle:sandi')
        ->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
