<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verifikasi Cloudflare Turnstile pada halaman masuk
    |--------------------------------------------------------------------------
    |
    | MATI selama kuncinya belum diisi, dan itu disengaja.
    |
    | Fitur ini berdiri tepat di jalan masuk. Dibuat wajib sementara
    | kuncinya belum terpasang di server, akibatnya bukan peringatan
    | melainkan SELURUH ORANG tidak dapat masuk — termasuk administrator
    | yang seharusnya memperbaikinya. Maka selama salah satu kunci masih
    | kosong, halaman masuk bekerja persis seperti sebelum fitur ini ada.
    |
    | Kuncinya diambil di dash.cloudflare.com → Turnstile → Add site.
    | Situsnya TIDAK perlu diproksikan lewat Cloudflare untuk memakainya.
    |
    */

    'situs'   => env('TURNSTILE_SITE_KEY'),
    'rahasia' => env('TURNSTILE_SECRET_KEY'),

    'url' => env('TURNSTILE_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),

    /*
    | Asal skrip widget-nya. Disebut di config, bukan ditulis langsung di
    | CSP dan di Vue: dua tempat yang harus sama persis, dan yang tidak
    | sama tidak menimbulkan galat — hanya kotak verifikasi yang diam-diam
    | tidak pernah muncul, lalu masuk yang selalu ditolak.
    */
    'asal' => env('TURNSTILE_ASAL', 'https://challenges.cloudflare.com'),

    'jeda_detik' => (int) env('TURNSTILE_JEDA', 5),

    /*
    |--------------------------------------------------------------------------
    | Bila Cloudflare tidak dapat dihubungi
    |--------------------------------------------------------------------------
    |
    | 'lolos' — permintaannya diteruskan. Ini BAWAANNYA.
    | 'tolak' — permintaannya ditolak.
    |
    | Pilihan bawaannya sengaja bukan yang terdengar paling aman, dan
    | alasannya perlu dibaca sebelum diubah.
    |
    | 'tolak' mengubah gangguan di pihak Cloudflare — atau jaringan site
    | tambang yang memang kerap putus — menjadi situs yang tidak dapat
    | dimasuki siapa pun. Pada aplikasi ini yang tertahan di luar adalah
    | orang yang hendak melaporkan bahaya di lapangan, dan laporan bahaya
    | yang tertunda punya harga yang jauh lebih mahal daripada satu sesi
    | penebak sandi yang lolos.
    |
    | Yang hilang saat 'lolos' pun tidak sebesar yang terdengar: Turnstile
    | di sini menahan pengisian sandi secara otomatis, dan pembatas laju
    | masuk tetap berlaku penuh — sepuluh percobaan per menit per akun,
    | dua puluh per menit per alamat. Jadi yang tersisa bukan pintu
    | terbuka melainkan pintu yang tetap berpalang, hanya tanpa
    | penjaganya.
    |
    | Pasang 'tolak' bila situs ini memang berada di balik Cloudflare dan
    | penebakan sandi adalah ancaman yang lebih Anda khawatirkan daripada
    | orang yang tidak dapat masuk.
    |
    */
    'saat_gagal' => env('TURNSTILE_SAAT_GAGAL', 'lolos'),

];
