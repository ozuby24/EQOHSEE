<?php

namespace App\Support;

/**
 * Enam pilar EQOHSEE — Energy, Quality, Occupational Health,
 * Safety, Environment, Engineering.
 *
 * Dipakai untuk memberi warna & identitas per-pilar pada dashboard,
 * badge modul, tab, dan ikon. Warna selaras dengan token Tailwind
 * (pillar-energy, pillar-quality, dst).
 */
class Pillars
{
    /** @return array<string,array{nama:string,warna:string,ikon:string,ket:string}> */
    public static function all(): array
    {
        return [
            'energy'      => ['nama' => 'Energy',              'warna' => '#2E6BE6', 'ikon' => 'bolt',    'ket' => 'Optimasi energi berkelanjutan'],
            'quality'     => ['nama' => 'Quality',             'warna' => '#17A2DC', 'ikon' => 'droplet', 'ket' => 'Mutu di setiap pekerjaan'],
            'occhealth'   => ['nama' => 'Occupational Health', 'warna' => '#F0921E', 'ikon' => 'health',  'ket' => 'Lindungi kesehatan kerja'],
            'safety'      => ['nama' => 'Safety',              'warna' => '#0FA08F', 'ikon' => 'shield',  'ket' => 'Zero compromise, zero tolerance'],
            'environment' => ['nama' => 'Environment',         'warna' => '#4FA82E', 'ikon' => 'leaf',    'ket' => 'Jaga alam untuk masa depan'],
            'engineering' => ['nama' => 'Engineering',         'warna' => '#1093B8', 'ikon' => 'gear',    'ket' => 'Solusi andal & efisien'],
        ];
    }

    /** Ambil satu pilar. */
    public static function get(string $slug): ?array
    {
        return static::all()[$slug] ?? null;
    }

    /** Warna hex untuk sebuah pilar (fallback: brand ink). */
    public static function color(string $slug): string
    {
        return static::all()[$slug]['warna'] ?? '#171B21';
    }

    /**
     * Petakan nama modul (dari App\Support\Modules) ke slug pilar.
     * SESUAIKAN aturan di bawah dengan penamaan modul kamu.
     */
    public static function forModule(string $modul): string
    {
        return match (true) {
            str_contains($modul, 'Learning')
                || str_contains($modul, 'Maturity')
                || str_contains($modul, 'SMKP')                 => 'safety',
            str_contains($modul, 'Hazard')                      => 'occhealth',
            str_contains($modul, 'SIGAP')
                || str_contains($modul, 'Gudang')               => 'environment',
            str_contains($modul, 'ISO')                         => 'quality',
            str_contains($modul, 'KO')
                || str_contains($modul, 'Keselamatan Operasi')  => 'engineering',
            default                                             => 'engineering',
        };
    }
}
