<?php

namespace App\Services\Asisten;

/**
 * Perapian jawaban model sebelum dikirim ke peramban.
 *
 * Skema JSON menjamin bentuk, bukan isi: panjang teks, rentang angka, dan
 * jumlah butir daftar tetap dijepit di sini (structured outputs tidak
 * mendukung minLength/maximum/maxItems), sehingga yang diterapkan ke
 * formulir selalu muat di kolom basis datanya.
 */
final class Rapi
{
    /** Teks satu baris/paragraf yang dipangkas dan dibatasi panjangnya. */
    public static function teks(mixed $v, int $maks = 2000): string
    {
        if (!is_scalar($v)) return '';
        $t = trim(preg_replace('/[ \t]+/u', ' ', str_replace("\r", '', (string) $v)) ?? '');

        return mb_strlen($t) > $maks ? rtrim(mb_substr($t, 0, $maks - 1)).'…' : $t;
    }

    /** @return list<string> daftar teks tidak kosong, paling banyak $jumlah butir */
    public static function daftar(mixed $v, int $jumlah = 8, int $maks = 500): array
    {
        $out = [];
        foreach (is_array($v) ? $v : [] as $x) {
            $t = self::teks($x, $maks);
            if ($t !== '') $out[] = $t;
            if (count($out) >= $jumlah) break;
        }

        return $out;
    }

    /** Salah satu pilihan yang sah; selain itu nilai bawaan. */
    public static function pilihan(mixed $v, array $sah, string $bawaan): string
    {
        $v = is_string($v) ? mb_strtolower(trim($v)) : '';

        return in_array($v, $sah, true) ? $v : $bawaan;
    }

    public static function bulat(mixed $v, int $min, int $maks, int $bawaan): int
    {
        if (!is_numeric($v)) return $bawaan;

        return max($min, min($maks, (int) round((float) $v)));
    }

    /** Gabungkan baris tidak kosong, lalu jepit ke panjang kolom tujuan. */
    public static function gabung(array $baris, int $maks): string
    {
        $t = implode("\n", array_values(array_filter($baris, fn ($b) => trim((string) $b) !== '')));

        return mb_strlen($t) > $maks ? rtrim(mb_substr($t, 0, $maks - 1)).'…' : $t;
    }
}
