<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kuki sesi kembar dibersihkan.
 *
 * Mengubah SESSION_DOMAIN (misalnya dari kosong menjadi eqohsee.id) tidak
 * menghapus kuki sesi yang sudah ada di peramban. Peramban kini menyimpan
 * DUA kuki bernama sama — satu khusus host (lama), satu untuk seluruh
 * domain (baru) — dan mengirim keduanya, yang lama lebih dulu. PHP hanya
 * membaca yang PERTAMA, padahal aplikasi hanya memperbarui yang baru.
 *
 * Akibatnya berlangsung diam-diam sampai sesi lama itu kedaluwarsa: masuk
 * berhasil tetapi permintaan berikutnya membaca sesi lama (kembali ke
 * halaman masuk), dan masuk dengan Google berakhir InvalidStateException
 * karena state ditulis ke sesi yang tidak dibaca saat kembali.
 *
 * Bila kepala Cookie memuat nama kuki sesi lebih dari sekali, kembaran
 * yang TIDAK ditulis aplikasi dihapus: bila SESSION_DOMAIN diisi, kuki
 * khusus host; bila kosong, kuki untuk domain induk.
 */
class RapikanKukiSesi
{
    public function handle(Request $request, Closure $next): Response
    {
        $jawab = $next($request);

        $nama = (string) config('session.cookie');
        $mentah = '; '.(string) $request->headers->get('cookie', '');

        if ($nama === '' || substr_count($mentah, '; '.$nama.'=') < 2) {
            return $jawab;
        }

        $domain = config('session.domain');
        $kembaran = $domain
            ? null                                                   // khusus host
            : (string) preg_replace('/^www\./', '', $request->getHost()); // domain induk

        $jawab->headers->setCookie(new Cookie(
            $nama, '', 1, (string) config('session.path', '/'), $kembaran,
            (bool) config('session.secure'), true, false, config('session.same_site'),
        ));

        return $jawab;
    }
}
