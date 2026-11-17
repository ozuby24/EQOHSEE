<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pengenal kunjungan dari aplikasi Android EQOHSEE.
 *
 * Aplikasi Android membungkus platform ini dan mengirim user agent
 * "EQOHSEE-Android/…". Kunjungan dari sana dibuka di mode lapangan —
 * layar ringkas untuk muka tambang — alih-alih dasbor meja kantor.
 * Peramban biasa, termasuk peramban ponsel, tetap mendarat di dasbor;
 * mode lapangan tetap dapat dibuka dari menu akun.
 */
final class Lapangan
{
    public static function dariAplikasi(?Request $r = null): bool
    {
        $ua = (string) ($r ?? request())->userAgent();

        return str_contains($ua, 'EQOHSEE-Android');
    }

    /** Tujuan sesudah masuk. */
    public static function berandaUntuk(?Request $r = null): string
    {
        return self::dariAplikasi($r)
            ? route('lapangan.beranda', absolute: false)
            : route('dashboard', absolute: false);
    }
}
