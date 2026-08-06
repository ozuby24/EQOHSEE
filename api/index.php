<?php

/*
 | Titik masuk EQOHSEE untuk Vercel (serverless).
 |
 | Di Vercel seluruh sistem berkas bersifat HANYA-BACA kecuali /tmp.
 | Laravel butuh direktori yang bisa ditulis untuk kompilasi Blade dan
 | cache, jadi semuanya diarahkan ke /tmp SEBELUM framework di-boot.
 |
 | Untuk menjalankan aplikasi secara lokal (Termux), berkas ini tidak
 | dipakai sama sekali — `php artisan serve` tetap memakai public/index.php.
 */

$storage = '/tmp/storage';

foreach ([
    $storage.'/framework/views',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/framework/testing',
    $storage.'/app/public',
    $storage.'/logs',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

/*
 | VIEW_COMPILED_PATH dibaca oleh config view bawaan Laravel.
 | LARAVEL_STORAGE_PATH memindahkan seluruh storage_path() ke /tmp.
 | Keduanya di-set lewat putenv + superglobal agar terbaca oleh
 | helper Env Laravel apa pun urutan boot-nya.
 */
$env = [
    'VIEW_COMPILED_PATH'   => $storage.'/framework/views',
    'LARAVEL_STORAGE_PATH' => $storage,
];

foreach ($env as $kunci => $nilai) {
    putenv("$kunci=$nilai");
    $_ENV[$kunci] = $nilai;
    $_SERVER[$kunci] = $nilai;
}

/*
 | public/index.php memakai __DIR__ untuk menemukan vendor/ dan
 | bootstrap/app.php, sehingga tetap benar walau dipanggil dari sini.
 */
require __DIR__.'/../public/index.php';
