<?php

namespace App\Support\Miners\Kartu;

/**
 * Warna akses kartu — WarnaAkses Safe Track (SOP 004-SPM-006 butir 7–8):
 * putih = Mine Permit tanpa SIMPER, hijau = sarana (LV), merah = A2B /
 * unit mining, biru = unit support. Warna yang DINYATAKAN pada permit
 * (`kode_warna`) menang; tanpa itu diambil yang TERTINGGI dari unit
 * SIMPER-nya (merah > biru > hijau > putih), dikenali dari nama, kategori,
 * lalu kode unit.
 */
final class WarnaAkses
{
    public const URUTAN = ['merah', 'biru', 'hijau', 'putih'];

    public const LABEL = [
        'putih' => 'TIDAK BISA MENGENDARAI UNIT',
        'hijau' => 'SARANA (LIGHT VEHICLE)',
        'merah' => 'A2B / UNIT MINING',
        'biru'  => 'UNIT SUPPORT',
    ];

    public const HEX = ['putih' => '#FFFFFF', 'hijau' => '#1D7A28', 'merah' => '#E8232A', 'biru' => '#1F2FBE'];

    public const CAKUPAN_AREA = ['FULL' => 'FULL ACCESS', 'RESTRICTED' => 'RESTRICTED AREA', 'UNRESTRICTED' => 'UNRESTRICTED AREA'];

    public const SINGKATAN = [
        'DT' => 'merah', 'HDT' => 'merah', 'ADT' => 'merah', 'EX' => 'merah', 'PC' => 'merah', 'DZ' => 'merah',
        'GD' => 'merah', 'GR' => 'merah', 'WL' => 'merah', 'CP' => 'merah',
        'WT' => 'biru', 'FT' => 'biru', 'LT' => 'biru', 'FL' => 'biru', 'CR' => 'biru', 'MT' => 'biru', 'ELF' => 'biru', 'BUS' => 'biru',
        'LV' => 'hijau',
    ];

    /** Urutan penting: frasa panjang sebelum kata pendek yang dikandungnya (WHEEL LOADER sebelum LOADER). */
    public const KATA = [
        'DUMP TRUCK' => 'merah', 'DUMPTRUCK' => 'merah', 'TRONTON' => 'merah', 'WHEEL LOADER' => 'merah', 'WHELL LOADER' => 'merah',
        'WHEELLOADER' => 'merah', 'EXCAVATOR' => 'merah', 'EXCA' => 'merah', 'BULLDOZER' => 'merah', 'DOZER' => 'merah',
        'GRADER' => 'merah', 'COMPACTOR' => 'merah', 'BREAKER' => 'merah', 'DRILL' => 'merah', 'SCRAPER' => 'merah', 'LOADER' => 'merah',
        'WATER TRUCK' => 'biru', 'WATER TANK' => 'biru', 'WATERTRUCK' => 'biru', 'FUEL' => 'biru', 'LUBE' => 'biru', 'FORKLIF' => 'biru',
        'MANITOU' => 'biru', 'CRANE' => 'biru', 'MANLIFT' => 'biru', 'MAN LIFT' => 'biru', 'MAN HAUL' => 'biru', 'MANHAUL' => 'biru',
        'TRACTOR' => 'biru', 'TOWER LAMP' => 'biru', 'GENSET' => 'biru', 'TRAILER' => 'biru', 'LOWBOY' => 'biru', 'AMBULANCE' => 'biru',
        'FIRE TRUCK' => 'biru',
        'LIGHT VEHICLE' => 'hijau', 'LIGHTVEHICLE' => 'hijau', 'SARANA' => 'hijau', 'PICK UP' => 'hijau', 'PICKUP' => 'hijau',
    ];

    public static function golongkan(?string $nama, ?string $kode = null): ?string
    {
        $nama = self::rapikan($nama);

        if ($nama !== '') {
            foreach (self::KATA as $kata => $warna) {
                if (str_contains($nama, $kata)) return $warna;
            }
            foreach (explode(' ', $nama) as $kata) {
                if (isset(self::SINGKATAN[$kata])) return self::SINGKATAN[$kata];
            }
        }

        $kode = self::rapikan($kode);

        return self::SINGKATAN[$kode] ?? null;
    }

    /** @param list<array{nama?:string,kode?:string,kategori?:string}> $unit */
    public static function untukKartu(array $unit, bool $punyaSimper = true): string
    {
        if (! $punyaSimper) return 'putih';

        $ketemu = [];
        foreach ($unit as $u) {
            $w = self::golongkan($u['nama'] ?? '') ?? self::golongkan($u['kategori'] ?? '') ?? self::golongkan('', $u['kode'] ?? null);
            if ($w) $ketemu[$w] = true;
        }

        foreach (self::URUTAN as $w) {
            if (isset($ketemu[$w])) return $w;
        }

        return 'putih';
    }

    public static function sah(?string $w): bool
    {
        return is_string($w) && isset(self::LABEL[$w]);
    }

    public static function label(?string $w): string
    {
        return self::LABEL[$w] ?? self::LABEL['putih'];
    }

    public static function hex(?string $w): string
    {
        return self::HEX[$w] ?? self::HEX['putih'];
    }

    private static function rapikan(?string $t): string
    {
        $t = (string) preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(trim((string) $t)));

        return trim((string) preg_replace('/\s+/', ' ', $t));
    }
}
