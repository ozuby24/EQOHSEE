<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Waktu tampilan untuk pengguna.
 *
 * Penyimpanan tetap UTC — `config('app.timezone')` sengaja tidak diubah.
 * Mengubahnya berarti seluruh baris yang sudah tersimpan terbaca bergeser
 * beberapa jam: tanggal audit, jam kejadian bahaya, dan waktu terbit
 * dokumen ikut melenceng tanpa ada yang menyunting apa pun. Yang diubah
 * hanyalah cara menampilkannya.
 *
 * Zona tampilan diatur lewat WAKTU_ZONA. Bawaannya Asia/Makassar (WITA),
 * zona lokasi tambang yang memakai sistem ini.
 */
final class Waktu
{
    public static function zona(): string
    {
        return (string) config('waktu.zona', 'Asia/Makassar');
    }

    /** Waktu sekarang menurut zona tampilan. */
    public static function kini(): Carbon
    {
        return Carbon::now(self::zona());
    }

    /** Sebuah waktu dipindah ke zona tampilan; null tetap null. */
    public static function lokal($waktu): ?Carbon
    {
        if ($waktu === null || $waktu === '') return null;

        return Carbon::parse($waktu)->setTimezone(self::zona());
    }

    /**
     * Sapaan menurut jam setempat.
     *
     * Sempat dihitung dari jam server yang berjalan UTC, sehingga pukul
     * 18.00 WITA disapa "Selamat pagi" — salah delapan jam, dan justru
     * bagian halaman yang paling pertama dibaca orang.
     */
    public static function sapaan(?Carbon $saat = null): string
    {
        $jam = (int) ($saat ?? self::kini())->format('G');

        return match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 19 => 'Selamat sore',
            default   => 'Selamat malam',
        };
    }

    /** Singkatan zona waktu Indonesia: WIB, WITA, atau WIT. */
    public static function singkatan(): string
    {
        return match (self::zona()) {
            'Asia/Jakarta'  => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default         => self::kini()->format('T'),
        };
    }
}
