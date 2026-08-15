<?php

namespace App\Support;

/**
 * Alamat berkas statis dengan penanda versi.
 *
 * Berkas yang namanya tidak pernah berubah akan dipegang peramban jauh
 * lebih lama daripada yang dikira siapa pun — favicon adalah kasus
 * terburuknya: ia disimpan di luar cache halaman biasa, tidak ikut
 * terhapus oleh muat-ulang paksa, dan pada sebagian peramban baru
 * berganti setelah seluruh tab ditutup.
 *
 * Menambahkan waktu ubah berkas ke alamatnya membuat berkas yang berubah
 * punya alamat baru, sehingga peramban terpaksa mengambilnya lagi.
 * Yang tidak berubah tetap memakai alamat lama dan tetap tersimpan.
 *
 * Vite sudah melakukan hal setara untuk CSS dan JS lewat nama ber-hash;
 * ini melengkapi berkas yang berada di luar jangkauannya.
 */
final class Aset
{
    /** @var array<string,string> */
    private static array $ingat = [];

    public static function v(string $path): string
    {
        return self::$ingat[$path] ??= self::susun($path);
    }

    private static function susun(string $path): string
    {
        $url  = asset($path);
        $file = public_path($path);

        // Berkas yang belum ada — mis. pada pemasangan baru sebelum aset
        // dibangun — tidak boleh menjatuhkan halaman hanya karena penanda.
        if (!is_file($file)) return $url;

        return $url.'?v='.filemtime($file);
    }
}
