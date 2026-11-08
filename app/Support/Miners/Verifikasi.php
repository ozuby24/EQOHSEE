<?php

namespace App\Support\Miners;

use Illuminate\Support\Facades\Route;

/**
 * Kode verifikasi bertanda tangan — VerifikasiKartu Safe Track.
 *
 * Bentuknya `<JENIS><id>.<10 heksa HMAC-SHA256(APP_KEY)>`, mis.
 * `ID42.3fa9c0d1b2`. Jenis mengikuti Safe Track: MP Mine Permit,
 * SP SIMPER, ID peserta induksi; MC ditambahkan bagi sertifikat MCU.
 * Huruf yang berbeda untuk tabel yang berbeda — satu kode tidak boleh
 * dapat menunjuk orang lain.
 *
 * Halaman verifikasi publik `/v/{kode}` dibangun pada paket kartu. Selama
 * rutenya belum terdaftar, alamat() memulangkan null dan QR memuat teks
 * keterangannya sendiri — lebih baik daripada QR yang menuju halaman
 * galat. Begitu rutenya ada, QR yang sama berpindah ke sana tanpa
 * mengubah sertifikat mana pun.
 */
final class Verifikasi
{
    public const PANJANG_TANDA = 10;

    public const JENIS = ['MP', 'SP', 'ID', 'MC'];

    public static function kode(string $jenis, int $id): string
    {
        $jenis = strtoupper($jenis);

        return $jenis.$id.'.'.self::tanda($jenis, $id);
    }

    /**
     * Alamat verifikasi publik bagi sebuah kode, atau null bila halaman
     * verifikasinya belum terpasang.
     */
    public static function alamat(string $kode): ?string
    {
        return Route::has('verifikasi.kartu') ? route('verifikasi.kartu', $kode) : null;
    }

    /**
     * Uraikan kode; null bila bentuk atau tanda tangannya tidak sah.
     *
     * @return array{jenis:string,id:int}|null
     */
    public static function baca(string $kode): ?array
    {
        if (! preg_match('/^([A-Z]{2})(\d+)\.([0-9a-f]+)$/', trim($kode), $c)) return null;

        [, $jenis, $id, $tanda] = $c;

        if (! in_array($jenis, self::JENIS, true)) return null;

        /* hash_equals, bukan ===: perbandingan biasa berhenti pada huruf
           pertama yang berbeda, dan selisih waktunya cukup untuk menebak
           tanda tangan huruf demi huruf. */
        if (! hash_equals(self::tanda($jenis, (int) $id), $tanda)) return null;

        return ['jenis' => $jenis, 'id' => (int) $id];
    }

    private static function tanda(string $jenis, int $id): string
    {
        return substr(hash_hmac('sha256', $jenis.':'.$id, (string) config('app.key')), 0, self::PANJANG_TANDA);
    }
}
