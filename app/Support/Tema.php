<?php

namespace App\Support;

use App\Models\User;

/**
 * Tema tampilan: terang/gelap, dan warna yang diwarisi dari logo perusahaan.
 *
 * Warna bawaan EQOHSEE dipakai selama perusahaan belum mengunggah logo,
 * atau ketika logonya tidak punya warna khas (logo hitam-putih, atau SVG
 * yang tidak dapat dibaca GD). Perusahaan tanpa logo tidak boleh membuat
 * aplikasinya tampil tanpa warna sama sekali.
 */
final class Tema
{
    public const BAWAAN_TERANG = '#F57C00';
    public const BAWAAN_GELAP  = '#0B1117';

    /** 'terang', 'gelap', atau null bila mengikuti setelan perangkat. */
    public static function pilihan(?User $u): ?string
    {
        $t = $u?->tema;

        return in_array($t, ['terang', 'gelap'], true) ? $t : null;
    }

    public static function aksen(?User $u): string
    {
        return self::hex($u?->company?->theme_color) ?? self::BAWAAN_TERANG;
    }

    public static function dasar(?User $u): string
    {
        return self::hex($u?->company?->theme_dark) ?? self::BAWAAN_GELAP;
    }

    /**
     * Variabel CSS yang ditanam pada elemen akar.
     *
     * Ditulis sebagai custom property, bukan sebagai kelas: warnanya
     * berbeda tiap perusahaan dan tidak dapat diketahui saat berkas gaya
     * dibangun.
     */
    public static function gaya(?User $u): string
    {
        $aksen = self::aksen($u);
        $dasar = self::dasar($u);

        return sprintf(
            '--eq-aksen:%s;--eq-aksen-lembut:%s;--eq-aksen-tipis:%s;--eq-dasar:%s;',
            $aksen,
            self::campur($aksen, '#FFFFFF', 0.35),
            self::rgba($aksen, 0.12),
            $dasar
        );
    }

    /** Hex 6 digit yang sah, atau null. */
    public static function hex(?string $w): ?string
    {
        $w = trim((string) $w);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $w) ? strtoupper($w) : null;
    }

    /** Campuran dua warna; $bagian 0 = warna pertama, 1 = warna kedua. */
    public static function campur(string $a, string $b, float $bagian): string
    {
        [$ar, $ag, $ab] = self::pecah($a);
        [$br, $bg, $bb] = self::pecah($b);

        return sprintf('#%02X%02X%02X',
            (int) round($ar + ($br - $ar) * $bagian),
            (int) round($ag + ($bg - $ag) * $bagian),
            (int) round($ab + ($bb - $ab) * $bagian));
    }

    public static function rgba(string $hex, float $alfa): string
    {
        [$r, $g, $b] = self::pecah($hex);

        return sprintf('rgba(%d,%d,%d,%.2f)', $r, $g, $b, $alfa);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function pecah(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [
            (int) hexdec(substr($h, 0, 2)),
            (int) hexdec(substr($h, 2, 2)),
            (int) hexdec(substr($h, 4, 2)),
        ];
    }
}
