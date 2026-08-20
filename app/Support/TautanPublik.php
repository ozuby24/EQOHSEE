<?php

namespace App\Support;

use App\Models\Company;
use App\Models\TpkkpAssessment;
use Illuminate\Support\Str;

/**
 * Tautan bertoken untuk halaman PTPKKP yang diisi tanpa login.
 *
 * Satu token per perusahaan, dipakai bersama oleh kuesioner persepsi
 * dan pengujian (kuis). Sengaja SATU, bukan satu per halaman: yang
 * membagikannya di lapangan adalah orang, lewat WhatsApp dan kertas
 * tempel di ruang ganti, dan dua tautan berbeda untuk satu perusahaan
 * berakhir dengan separuh pekerja mengisi yang keliru. Mengganti token
 * pun karena itu mencabut keduanya sekaligus — yang memang dimaksud
 * saat seseorang menekan "reset tautan".
 *
 * Tokennya menumpang pada `profil['tokens']` milik penilaian periode
 * berjalan. Itu bukan tempat yang ideal, tetapi ia sudah dipakai sejak
 * kuesioner ada; memindahkannya sekarang akan mematikan setiap tautan
 * yang sudah tersebar.
 */
final class TautanPublik
{
    /** Penilaian periode berjalan — tempat token menumpang. */
    private static function penilaian(): TpkkpAssessment
    {
        return TpkkpAssessment::forYear((int) now()->year);
    }

    public static function token(Company $c): string
    {
        $t = self::penilaian()->profil['tokens'][$c->id] ?? null;

        return $t ?: self::setToken($c, Str::random(24));
    }

    public static function setToken(Company $c, string $token): string
    {
        $a = self::penilaian();
        $p = (array) $a->profil;
        $p['tokens'] ??= [];
        $p['tokens'][$c->id] = $token;
        $a->update(['profil' => $p]);

        return $token;
    }

    /** Perusahaan pemilik token, atau null bila tak dikenal. */
    public static function perusahaan(string $token): ?Company
    {
        foreach (TpkkpAssessment::all() as $a) {
            foreach ((array) ($a->profil['tokens'] ?? []) as $cid => $t) {
                if ($t === $token && ($c = Company::find($cid))) return $c;
            }
        }

        return null;
    }

    /** Sama, tetapi 404 bila tokennya sudah tidak berlaku. */
    public static function wajib(string $token): Company
    {
        return self::perusahaan($token)
            ?? abort(404, 'Tautan tidak valid atau sudah diganti.');
    }
}
