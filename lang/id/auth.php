<?php

/*
 * Kalimat autentikasi dalam bahasa Indonesia.
 *
 * Seluruh antarmuka berbahasa Indonesia; pesan "These credentials do not
 * match our records." di bawah formulir masuk yang berbahasa Indonesia
 * terbaca sebagai aplikasi yang belum selesai. Kuncinya sama persis
 * dengan bawaan Laravel supaya tidak ada pesan yang jatuh ke bahasa
 * Inggris tanpa sengaja.
 */

return [
    'failed'   => 'Email atau kata sandi tidak cocok dengan catatan kami.',
    'password' => 'Kata sandi yang dimasukkan salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
];
