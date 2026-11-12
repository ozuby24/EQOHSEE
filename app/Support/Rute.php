<?php

namespace App\Support;

/**
 * Pola parameter rute.
 *
 * `whereNumber()` menerima angka sepanjang apa pun. Di SQLite dan MySQL
 * `/smkp/99999999999999999999999` hanya tidak menemukan baris — 404 —
 * tetapi PostgreSQL menolak membandingkan kolom bigint dengan angka di
 * luar jangkauannya (SQLSTATE 22003), dan teks seperti `/smkp/abc` pada
 * parameter tanpa pola (22P02): keduanya menjadi galat 500. Delapan belas
 * digit selalu muat dalam bigint.
 */
class Rute
{
    public const ID = '[0-9]{1,18}';

    /** @return array<string,string> pola ID untuk setiap parameter bernama ini */
    public static function id(string ...$nama): array
    {
        return array_fill_keys($nama, self::ID);
    }
}
