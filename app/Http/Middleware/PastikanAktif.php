<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan langsung dikeluarkan pada permintaan berikutnya —
 * termasuk sesi yang sudah terbuka dan cookie "Ingat saya" (porting
 * Aurorasys). Status aktif tidak hanya diperiksa saat masuk.
 *
 * Sebelumnya kotak "Akun aktif" di Admin → Kelola Pengguna hanya dihitung
 * di Pusat Kendali: akun nonaktif tetap dapat masuk dan bekerja. Batas akun
 * auditi audit SMKP ("nonaktifkan salah satunya lebih dulu") bergantung
 * pada nonaktif yang sungguh menutup akses.
 */
class PastikanAktif
{
    public const PESAN = 'Akun Anda dinonaktifkan. Hubungi administrator.';

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        // null (model baru dalam memori tanpa kolom ini) = aktif: bawaan kolomnya true.
        if ($u && $u->active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Antrean mode lapangan (fetch JSON) menerima galat berstatus,
            // bukan pengalihan ke halaman masuk.
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json(['message' => self::PESAN], 401);
            }

            // Ditampilkan bilah kilat GuestLayout di halaman masuk.
            return redirect()->route('login')->with('galat', self::PESAN);
        }

        return $next($request);
    }
}
