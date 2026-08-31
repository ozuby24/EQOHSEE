<?php

use Eqohsee\SmkpAudit\Http\Controllers\SmkpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute modul Audit SMKP
|--------------------------------------------------------------------------
| Didaftarkan otomatis oleh SmkpServiceProvider dengan awalan, nama, dan
| middleware dari config('smkp.rute'). Aplikasi yang ingin menyusun rutenya
| sendiri cukup menyetel config('smkp.rute.daftar') menjadi false lalu
| menyalin berkas ini ke routes/ miliknya.
|
| Urutan di bawah bukan selera: rute beruas tetap ('buat', 'acuan',
| 'lanjut/...') harus berada SEBELUM '{smkp}', jika tidak pengikat model akan
| mencoba mencari audit bernomor "buat" dan menjawab 404.
*/

Route::get('/',     [SmkpController::class, 'index'])->name('index');
Route::get('buat',  [SmkpController::class, 'create'])->name('create');
Route::post('/',    [SmkpController::class, 'store'])->name('store');
Route::get('acuan', [SmkpController::class, 'acuan'])->name('acuan');

// Pintasan menu samping: tanpa parameter, disalurkan ke audit yang berjalan.
foreach ([
    'tahap1'  => 'tahap-1',
    'rencana' => 'rencana',
    'rapat'   => 'rapat',
    'temuan'  => 'temuan',
    'berita'  => 'berita-acara',
    'rencana-cetak' => 'laporan-rencana',
    'laporan' => 'laporan-audit',
] as $bagian => $ruas) {
    Route::get("lanjut/{$ruas}", [SmkpController::class, 'lanjut'])
        ->defaults('bagian', $bagian)
        ->name('ke.'.$bagian);
}

Route::get('{smkp}',      [SmkpController::class, 'show'])->name('show');
Route::get('{smkp}/ubah', [SmkpController::class, 'edit'])->name('edit');
Route::put('{smkp}',      [SmkpController::class, 'update'])->name('update');

Route::delete('{smkp}', [SmkpController::class, 'destroy'])
    ->middleware(config('smkp.rute.middleware_hapus', []))
    ->name('destroy');

Route::get('{smkp}/laporan', [SmkpController::class, 'laporan'])->name('laporan');
Route::post('{smkp}/tahap',  [SmkpController::class, 'ubahTahap'])->name('tahap');

// Tahap I — permulaan audit, peninjauan dokumen, persiapan lapangan
Route::get('{smkp}/tahap-1',      [SmkpController::class, 'tahap1'])->name('tahap1');
Route::post('{smkp}/tahap-1',     [SmkpController::class, 'simpanTahap1'])->name('tahap1.simpan');
Route::get('{smkp}/berita-acara', [SmkpController::class, 'beritaAcara'])->name('berita-acara');

// Rencana Audit — sembilan komponen wajib, plus laporannya
Route::get('{smkp}/rencana',       [SmkpController::class, 'rencana'])->name('rencana');
Route::post('{smkp}/rencana',      [SmkpController::class, 'simpanRencana'])->name('rencana.simpan');
Route::get('{smkp}/rencana/cetak', [SmkpController::class, 'rencanaCetak'])->name('rencana.cetak');

// Tahap II — rapat pembukaan & penutupan
Route::get('{smkp}/rapat',                [SmkpController::class, 'rapat'])->name('rapat');
Route::post('{smkp}/rapat',               [SmkpController::class, 'simpanHadir'])->name('rapat.simpan');
Route::delete('{smkp}/rapat/{hadir}',     [SmkpController::class, 'hapusHadir'])->name('rapat.hapus');
Route::get('{smkp}/daftar-hadir/{rapat}', [SmkpController::class, 'daftarHadir'])->name('hadir.cetak');

// Temuan / tindakan perbaikan
Route::get('{smkp}/temuan',             [SmkpController::class, 'temuan'])->name('temuan');
Route::post('{smkp}/temuan/angkat',     [SmkpController::class, 'angkatTemuan'])->name('temuan.angkat');
Route::put('{smkp}/temuan/{temuan}',    [SmkpController::class, 'simpanTemuan'])->name('temuan.simpan');
Route::delete('{smkp}/temuan/{temuan}', [SmkpController::class, 'hapusTemuan'])->name('temuan.hapus');

// Formulir penilaian per elemen — ditaruh terakhir agar tidak menyerobot rute di atas
Route::get('{smkp}/elemen/{elemen}',  [SmkpController::class, 'nilai'])->name('nilai');
Route::post('{smkp}/elemen/{elemen}', [SmkpController::class, 'simpanNilai'])->name('nilai.simpan');
