<?php

namespace App\Support;

/**
 * Masuk dengan akun Google / Microsoft: penyedia mana yang benar-benar
 * dapat dipakai di pemasangan ini.
 *
 * Penyedia dianggap ada hanya bila client id DAN client secret-nya terisi
 * di .env. Halaman masuk menggambar tombolnya dari daftar ini, jadi tidak
 * pernah ada tombol yang mengarah ke alur yang belum dipasang.
 */
final class MasukPihakKetiga
{
    public const PENYEDIA = ['google', 'microsoft'];

    /** @return array{google: bool, microsoft: bool} */
    public static function tersedia(): array
    {
        $ada = [];
        foreach (self::PENYEDIA as $p) {
            $ada[$p] = self::aktif($p);
        }

        return $ada;
    }

    public static function aktif(string $penyedia): bool
    {
        if (!in_array($penyedia, self::PENYEDIA, true)) {
            return false;
        }

        return trim((string) config("services.{$penyedia}.client_id")) !== ''
            && trim((string) config("services.{$penyedia}.client_secret")) !== '';
    }

    public static function nama(string $penyedia): string
    {
        return $penyedia === 'microsoft' ? 'Microsoft' : 'Google';
    }
}
