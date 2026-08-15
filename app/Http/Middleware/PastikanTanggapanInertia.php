<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyelamatkan kunjungan Inertia yang mendarat di halaman Blade.
 *
 * Inertia TIDAK mundur sendiri ke navigasi peramban ketika tanggapannya
 * bukan Inertia. Yang terjadi justru: HTML utuh itu ditampilkan mentah di
 * dalam bingkai galat, sehingga halaman tujuan tampak sebagai jendela
 * rusak di atas halaman yang baru ditinggalkan. Kegagalannya selalu
 * terlihat sebagai tampilan berantakan, bukan sebagai galat yang jelas —
 * dan karena itu selalu dilaporkan sebagai "tampilannya error", bukan
 * sebagai sebabnya.
 *
 * Aplikasi ini setengah Blade dan setengah Inertia, jadi keadaan itu
 * bukan kasus langka melainkan konsekuensi biasa: setiap pengalihan dari
 * halaman Inertia menuju halaman Blade menghasilkannya. Sudah dua kali
 * ditambal satu per satu — pada tombol keluar dan pada verifikasi email —
 * dan penambalan satu per satu hanya menunggu kemunculan berikutnya.
 *
 * Di sini keadaan itu ditangkap di satu tempat: bila sebuah kunjungan
 * Inertia dijawab HTML biasa, jawabannya diubah menjadi perintah agar
 * peramban memuat alamat itu secara penuh — persis yang seharusnya
 * terjadi.
 *
 * Ini jaring pengaman, bukan pengganti App\Support\RuteInertia. Menandai
 * tautan dengan benar sejak awal tetap lebih baik: yang ditangkap di sini
 * membayar satu perjalanan bolak-balik tambahan sebelum halamannya
 * tergambar.
 */
class PastikanTanggapanInertia
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Hanya kunjungan Inertia yang dapat salah paham terhadap HTML.
        if (!$request->header('X-Inertia')) return $response;

        // Sudah Inertia — tidak ada yang perlu diselamatkan.
        if ($response->headers->has('X-Inertia')) return $response;

        /* Pengalihan dibiarkan: klien Inertia mengikutinya sendiri, dan
           yang menentukan nasibnya adalah tanggapan di ujung rantai —
           yang juga melewati middleware ini. */
        if ($response->isRedirection()) return $response;

        /* Galat punya penanganannya sendiri di sisi klien (modal galat
           yang memang disengaja), dan 409 dari Inertia::location tidak
           boleh ikut dibungkus ulang. */
        if (!$response->isSuccessful()) return $response;

        if (!str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        return Inertia::location($request->fullUrl());
    }
}
