<?php

return [

    /*
     * Perkiraan cuaca otomatis dari koordinat situs.
     *
     * Dimatikan di sini akan mengembalikan lencana cuaca kepada satu
     * sumber saja: curah hujan yang dicatat sendiri di modul Penirisan.
     * Yang tercatat sendiri selalu menang; baris ini hanya mengatur
     * cadangannya.
     */
    'aktif' => env('CUACA_OTOMATIS', true),

    /*
     * Open-Meteo: tanpa kunci API, gratis, dan tidak menuntut
     * pendaftaran — sehingga pemasangan baru tidak punya langkah
     * tambahan sebelum lencananya hidup.
     */
    'url'          => env('CUACA_URL', 'https://api.open-meteo.com/v1/forecast'),
    'url_geocode'  => env('CUACA_URL_GEOCODE', 'https://geocoding-api.open-meteo.com/v1/search'),

    /*
     * Batas waktu yang PENDEK, dan itu disengaja.
     *
     * Lencana ini digambar pada halaman awal tiap modul, jadi ia berada
     * di jalur permintaan yang ditunggu orang. Jaringan site tambang
     * kerap lambat atau putus; batas yang longgar membuat seluruh
     * halaman ikut menunggunya. Lebih baik lencananya tidak muncul
     * daripada halamannya tidak muncul.
     */
    'jeda_detik'   => env('CUACA_JEDA', 4),

    /* Umur singgahan. Hujan tidak berubah tiap menit, dan tiap
       pengambilan adalah satu permintaan ke luar dari server yang
       melayani banyak orang sekaligus. */
    'simpan_menit'         => env('CUACA_SIMPAN', 30),

    /* Koordinat hasil penerjemahan nama disimpan jauh lebih lama:
       nama tempat tidak berpindah. */
    'simpan_koordinat_jam' => env('CUACA_SIMPAN_KOORDINAT', 720),
];
