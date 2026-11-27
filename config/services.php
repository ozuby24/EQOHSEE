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

    /* Masuk dengan akun Google / Microsoft (Laravel Socialite).
       Tombolnya hanya tampil di halaman masuk bila client id DAN secret
       terisi (App\Support\MasukPihakKetiga). Alamat kembali yang
       didaftarkan di konsol penyedia: https://<domain>/masuk/google/kembali
       dan https://<domain>/masuk/microsoft/kembali. */
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
