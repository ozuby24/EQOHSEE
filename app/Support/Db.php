<?php

namespace App\Support;

use Illuminate\Support\Facades\DB as DBFacade;

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
        return DBFacade::connection()->getDriverName();
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

    /**
     * Urutkan menurut DAFTAR NILAI, bukan menurut abjad.
     *
     * Sebagian kolom pada aplikasi ini berisi tingkatan yang urutannya
     * bukan urutan hurufnya: risiko Tinggi > Sedang > Rendah, status
     * Open sebelum Closed. Diurutkan `ORDER BY risiko`, yang teratas
     * menjadi "Rendah" — dan lembar register yang seharusnya menaruh
     * temuan paling berbahaya di baris pertama justru menaruhnya di
     * baris terakhir.
     *
     * Nilainya DIKUTIP lewat PDO, bukan ditempel ke dalam tali teks.
     * Seluruh pemanggil yang ada memberi tetapan yang ditulis di kode,
     * tetapi pemanggil berikutnya akan menulis nilainya dari permintaan
     * — dan ekspresi ORDER BY tetap sebuah tempat suntikan SQL meski ia
     * tidak pernah memuat isian pengguna hari ini.
     *
     * @param list<string> $urutan nilai dari yang paling dulu
     */
    public static function urutanNilai(string $kolom, array $urutan): string
    {
        $pdo = DBFacade::connection()->getPdo();

        $kasus = '';

        foreach (array_values($urutan) as $i => $nilai) {
            $kasus .= ' WHEN '.$pdo->quote((string) $nilai)." THEN {$i}";
        }

        /* Nilai yang TIDAK terdaftar jatuh ke belakang, bukan ke depan.
           Status baru yang ditambahkan orang kemudian tidak boleh
           diam-diam naik ke puncak register hanya karena ia belum
           disebut di sini. */
        return "CASE {$kolom}{$kasus} ELSE ".count($urutan).' END';
    }
}
