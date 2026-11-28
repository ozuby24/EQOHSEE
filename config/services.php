<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /* Masuk & daftar dengan akun Google / Microsoft (Laravel Socialite).
       Tombolnya hanya tampil bila client id DAN secret terisi
       (App\Support\MasukPihakKetiga). Alamat kembali yang dikirim — dan
       harus terdaftar PERSIS di konsol penyedia — dilengkapi dari APP_URL:
       bawaan https://<domain>/masuk/{google|microsoft}/kembali; aplikasi
       juga melayani https://<domain>/auth/{google|microsoft}/callback.
       Periksa dengan: php artisan eqohsee:periksa-sso */
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/masuk/google/kembali'),
    ],

    'microsoft' => [
        'client_id'     => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect'      => env('MICROSOFT_REDIRECT_URI', '/masuk/microsoft/kembali'),
        // 'organizations' = akun kerja/sekolah penyewa mana pun; isi GUID
        // penyewa untuk membatasi ke satu organisasi.
        'tenant'        => env('MICROSOFT_TENANT_ID', 'organizations'),
    ],

];
