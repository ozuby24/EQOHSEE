<?php

/*
 * Asisten AI audit SMKP — saran penilaian butir, analisis akar masalah &
 * RTP temuan, analisis berkas bukti, dan ekstraksi Rencana Audit.
 *
 * Penyedia, kunci API, dan model TIDAK diatur di sini: asisten audit
 * menumpang sambungan AI aplikasi (App\Support\Ai, Pusat Kendali → Asisten
 * AI). Tanpa kunci untuk penyedia aktif, asisten nonaktif dan seluruh
 * endpoint /asisten/* menjawab 503.
 */

return [
    /* Sakelar utama khusus asisten audit. Asisten benar-benar aktif bila
       sakelar ini menyala DAN sambungan AI aplikasi punya kunci. */
    'aktif' => filter_var(env('ASISTEN_AKTIF', true), FILTER_VALIDATE_BOOL),

    /* Batas token keluaran per jawaban (penalaran + JSON). */
    'maks_token' => (int) env('ASISTEN_MAKS_TOKEN', 16000),

    /* Batas permintaan per pengguna per jam (RateLimiter "asisten"). */
    'batas_per_jam' => (int) env('ASISTEN_BATAS_PER_JAM', 30),

    /* Tenggat satu percobaan dalam detik. Seluruh permintaan, termasuk
       pengulangan, tetap dibatasi App\Support\Ai di bawah 120 detik Nginx. */
    'timeout' => (int) env('ASISTEN_TIMEOUT', 100),

    /* Pengulangan otomatis untuk 429/5xx dan putus sambungan. */
    'ulang' => (int) env('ASISTEN_ULANG', 2),
];
