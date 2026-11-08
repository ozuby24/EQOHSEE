<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Perkiraan cuaca dihangatkan di latar, bukan oleh permintaan halaman.
 *
 * Lencana cuaca digambar pada halaman awal tiap modul — jalur yang
 * ditunggu orang. Dibiarkan terisi sendiri saat pertama kali kosong,
 * yang menanggung tunggu jaringannya adalah pembuka halaman pertama,
 * dan di site tambang tunggu itu terasa.
 *
 * Setengah jam, sepadan dengan umur singgahannya. withoutOverlapping
 * menjaga agar jaringan yang lambat tidak menumpuk beberapa jalannya
 * sekaligus.
 */
Schedule::command('cuaca:segarkan')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Masa berlaku Miners — `masa-berlaku:sync` 06:55 dan
 * `app:update-mcu-status` 07:20 Safe Track, kini satu perintah.
 *
 * Pagi WAKTU TAMBANG, bukan UTC: kartu yang habis kemarin harus sudah
 * terbaca expired sebelum regu pagi tiba di gerbang.
 */
Schedule::command('miners:sinkron')
    ->dailyAt('06:55')
    ->timezone(\App\Support\Waktu::zona())
    ->withoutOverlapping();
