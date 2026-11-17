<?php

use App\Http\Controllers\Api\AbsensiIngestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoint mesin
|--------------------------------------------------------------------------
|
| SATU-SATUNYA permukaan yang dipanggil tanpa sesi pengguna, dan
| sengaja dibiarkan sesempit ini. Yang memanggilnya adalah alat pindai
| di pos jaga — push SDK ZKTeco dan Hikvision — yang tidak punya orang
| di dalamnya untuk masuk mewakilinya.
|
| Berdiri di berkas rutenya sendiri, bukan diselipkan ke routes/web.php,
| karena perbedaannya bukan kosmetik: grup web memasang sesi, CSRF, dan
| middleware Inertia, dan ketiganya tidak berarti apa-apa bagi alat
| yang mengirim JSON. Diselipkan ke sana, alat harus dikecualikan dari
| CSRF satu per satu — dan pengecualian semacam itu melebar diam-diam.
|
| Pembatasan kecepatan dipasang TEGAS di sini. Pembuktian tokennya
| memakai bcrypt, yang memang lambat dengan sengaja; tanpa batas
| kecepatan, permukaan ini menjadi cara termurah untuk menghabiskan CPU
| server dari luar.
*/

Route::middleware('throttle:120,1')->prefix('v1')->name('api.v1.')->group(function () {
    Route::post('absensi', [AbsensiIngestController::class, 'store'])->name('absensi.kirim');
});
