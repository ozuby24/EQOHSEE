<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asisten AI
    |--------------------------------------------------------------------------
    |
    | Dipakai halaman Bantuan untuk menjawab pertanyaan umum tentang EQOHSEE.
    | Tanpa kunci, asistennya mati dan seluruh pertanyaan langsung diteruskan
    | ke admin — bukan dijawab dengan tebakan.
    |
    | Google Gemini dipilih karena punya kuota gratis; OpenAI tidak menyediakan
    | tingkat gratis untuk API-nya. Kunci diambil dari Google AI Studio.
    |
    */

    'ai' => [
        'kunci' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'alamat' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),

        // Jawaban pendek: ini kotak bantuan, bukan ruang esai.
        'maks_token' => (int) env('GEMINI_MAKS_TOKEN', 700),

        // Berapa pesan terakhir yang ikut dikirim sebagai konteks. Riwayat
        // panjang membuat tiap pertanyaan makin mahal tanpa menambah manfaat.
        'riwayat' => (int) env('GEMINI_RIWAYAT', 12),

        'jeda' => (int) env('GEMINI_JEDA', 30),
    ],

];
