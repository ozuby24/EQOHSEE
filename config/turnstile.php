<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Verifikasi Cloudflare Turnstile pada pintu tamu
    |--------------------------------------------------------------------------
    |
    | Berlaku pada tiga halaman sekaligus — masuk, daftar, dan lupa sandi
    | — yang menyala dan mati bersama dari satu pasang kunci ini. Pintu
    | keempat, penyetelan ulang sandi lewat tautan, sengaja dibiarkan:
    | tautannya sudah membuktikan penerimanya memegang kotak surat yang
    | dituju.
    |
    | MATI selama kuncinya belum diisi, dan itu disengaja.
    |
    | Fitur ini berdiri tepat di jalan masuk. Dibuat wajib sementara
    | kuncinya belum terpasang di server, akibatnya bukan peringatan
    | melainkan SELURUH ORANG tidak dapat masuk — termasuk administrator
    | yang seharusnya memperbaikinya. Maka selama salah satu kunci masih
    | kosong, ketiga halaman itu bekerja persis seperti sebelum fitur ini
    | ada.
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

    /*
    |--------------------------------------------------------------------------
    | Bila sesudah kuncinya dipasang tidak ada yang bisa masuk
    |--------------------------------------------------------------------------
    |
    | Ditulis di sini, dan bukan di halaman masuk, karena yang perlu
    | membacanya bukan orang yang sedang mencoba masuk melainkan orang
    | yang harus memulihkannya — dan orang itu justru yang ikut terkunci
    | di luar.
    |
    | 'saat_gagal' di atas TIDAK menolong dalam keadaan ini, dan penting
    | untuk melihat kenapa. Ia mengurus satu arah saja: SERVER ini yang
    | tidak dapat menghubungi Cloudflare. Arah yang satu lagi tidak
    | pernah melewati server ini sama sekali — PERAMBAN pemakainya yang
    | tidak dapat mengambil challenges.cloudflare.com, karena jaringan
    | site tambang menyaring domain luar, karena pemblokir iklan, atau
    | karena domain itu diblokir di negara tempat ia berada.
    |
    | Rantainya lalu berjalan sendiri sampai habis: skripnya tidak
    | sampai, widget-nya tidak digambar, tokennya tidak pernah terbit,
    | dan server menolak setiap kiriman tanpa token — persis seperti yang
    | seharusnya ia lakukan terhadap skrip penebak sandi. Permintaan
    | tanpa token dari peramban yang terhalang memang tidak dapat
    | dibedakan dari permintaan tanpa token yang dikirim penyerang, jadi
    | tidak ada jalan pintas yang aman di sisi server. Halaman masuk
    | menyebutkan sebabnya kepada yang membacanya, tetapi menyebutkan
    | sebab bukan memulihkan.
    |
    | Pemulihannya satu langkah, dan tidak menyentuh basis data:
    |
    |   1. Kosongkan TURNSTILE_SITE_KEY pada .env di server.
    |   2. php artisan config:clear   (deploy.sh sudah melakukannya)
    |
    | Verifikasinya mati seketika — aktif() menuntut KEDUA kuncinya — dan
    | ketiga pintu kembali seperti sebelum fitur ini ada. Tidak ada yang
    | hilang, dan kuncinya dapat dipasang lagi kapan saja sesudah
    | jaringannya dibereskan.
    |
    */

];
