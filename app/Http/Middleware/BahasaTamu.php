<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bahasa pesan server di halaman tamu mengikuti pilihan bahasanya.
 *
 * Pil bahasa di halaman masuk menyimpan `eq_bahasa` (id/en) di kuki —
 * tanpa enkripsi, karena peramban sendiri yang menulisnya
 * (bootstrap/app.php: encryptCookies except). Pada alur masuk, daftar,
 * lupa sandi, atur ulang sandi, dua faktor, verifikasi surel, dan
 * konfirmasi sandi, pesan validasi dan pesan galat ikut bahasa itu.
 * Aplikasi di balik login tetap berbahasa Indonesia, jadi aturan ini
 * sengaja hanya berlaku pada jalur-jalur tamu tersebut.
 */
class BahasaTamu
{
    public const KUKI = 'eq_bahasa';

    public const JALUR = [
        'login', 'register', 'forgot-password', 'reset-password', 'reset-password/*',
        'dua-faktor', 'dua-faktor/*', 'verify-email', 'email/verification-notification',
        'confirm-password', 'masuk/*',
    ];

    /**
     * Bahasa bawaan aplikasi, dicatat sebelum middleware ini pernah
     * mengubahnya. Tidak dibaca ulang dari config('app.locale') tiap kali:
     * setLocale() ikut menimpa nilai config itu.
     */
    private static ?string $bawaan = null;

    public function handle(Request $request, Closure $next): Response
    {
        self::$bawaan ??= (string) config('app.locale');

        /* Ditetapkan pada SETIAP permintaan, bukan hanya saat Inggris:
           pada aplikasi yang melayani beberapa permintaan berturut-turut
           (uji, pekerja yang hidup lama) bahasa yang sekali disetel 'en'
           terbawa ke permintaan berikutnya — termasuk ke aplikasi di balik
           login. */
        $inggris = $request->cookie(self::KUKI) === 'en' && $request->is(...self::JALUR);

        app()->setLocale($inggris ? 'en' : self::$bawaan);

        return $next($request);
    }
}
