<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pembungkus SQL yang berbeda antar mesin basis data.
 *
 * Dipakai supaya modul Hazard, Inspeksi, dan Evaluasi Temuan bisa berjalan
 * di SQLite maupun PostgreSQL tanpa cabang kode di controller.
 */
class Db
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    public static function pgsql(): bool  { return self::driver() === 'pgsql'; }
    public static function sqlite(): bool { return self::driver() === 'sqlite'; }

    /** Ekspresi "YYYY-MM" dari sebuah kolom tanggal. */
    public static function ym(string $kolom): string
    {
        return match (self::driver()) {
            'pgsql'  => "to_char({$kolom}, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT({$kolom}, '%Y-%m')",
            'sqlsrv' => "FORMAT({$kolom}, 'yyyy-MM')",
            default  => "strftime('%Y-%m', {$kolom})",   // sqlite
        };
    }

    /** Ekspresi "YYYY" dari sebuah kolom tanggal. */
    public static function tahun(string $kolom): string
    {
        return match (self::driver()) {
            'pgsql'  => "to_char({$kolom}, 'YYYY')",
            'mysql', 'mariadb' => "DATE_FORMAT({$kolom}, '%Y')",
            'sqlsrv' => "FORMAT({$kolom}, 'yyyy')",
            default  => "strftime('%Y', {$kolom})",
        };
    }

    /** Pencarian tanpa peduli besar-kecil huruf. */
    public static function like(): string
    {
        return self::pgsql() ? 'ilike' : 'like';
    }
}
