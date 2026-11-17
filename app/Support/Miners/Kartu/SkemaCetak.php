<?php

namespace App\Support\Miners\Kartu;

use App\Models\Miners\TataCetak;
use App\Models\User;

/**
 * Tata letak lembar cetak kartu — SkemaCetak Safe Track.
 *
 * Skema: bolak-balik (2 halaman, printer duplex — sisi belakang dicerminkan
 * per baris supaya jatuh di balik kartunya), lipat (depan dan belakang
 * berdampingan, cetak satu sisi lalu dilipat), atau depan saja. Kertas
 * A4/Letter/F4, orientasi, sela antarkartu, sela lipat, margin, dan garis
 * potong. Safe Track menyimpannya sebagai pengaturan global; di sini per
 * perusahaan (`mnr_tata_cetak`), boleh lebih dari satu preset bernama.
 */
final class SkemaCetak
{
    public const KARTU_LEBAR = 54.0;
    public const KARTU_TINGGI = 86.0;

    public const KERTAS = ['a4' => [210.0, 297.0], 'letter' => [215.9, 279.4], 'f4' => [215.0, 330.0]];

    public const SKEMA = [
        'bolak-balik' => 'Bolak-balik (2 halaman, printer duplex)',
        'lipat'       => 'Lipat (depan-belakang berdampingan, cetak 1 sisi)',
        'depan'       => 'Sisi depan saja',
    ];

    /** @return array<string,mixed> */
    public static function bawaan(): array
    {
        return ['skema' => 'lipat', 'kertas' => 'a4', 'orientasi' => 'landscape', 'sela' => 8.0, 'sela_lipat' => 4.0,
            'margin' => 10.0, 'garis_potong' => true];
    }

    /**
     * Pengaturan dibersihkan: nilai asing kembali ke bawaan, angka dijepit.
     *
     * @param  array<string,mixed>  $s
     * @return array<string,mixed>
     */
    public static function rapikan(array $s): array
    {
        $b = self::bawaan();

        $jepit = fn (string $k, float $min, float $maks) => isset($s[$k]) && is_numeric($s[$k])
            ? max($min, min($maks, round((float) $s[$k], 1))) : $b[$k];

        return [
            'skema'        => isset(self::SKEMA[$s['skema'] ?? '']) ? $s['skema'] : $b['skema'],
            'kertas'       => isset(self::KERTAS[strtolower((string) ($s['kertas'] ?? ''))]) ? strtolower($s['kertas']) : $b['kertas'],
            'orientasi'    => ($s['orientasi'] ?? '') === 'portrait' ? 'portrait' : 'landscape',
            'sela'         => $jepit('sela', 0, 25),
            'sela_lipat'   => $jepit('sela_lipat', 0, 12),
            'margin'       => $jepit('margin', 3, 30),
            'garis_potong' => array_key_exists('garis_potong', $s) ? filter_var($s['garis_potong'], FILTER_VALIDATE_BOOLEAN) : $b['garis_potong'],
        ];
    }

    /**
     * Ukuran petak dan jumlah kolom/baris per lembar.
     *
     * @param  array<string,mixed>  $s
     * @return array<string,float|int>
     */
    public static function petak(array $s): array
    {
        [$l, $t] = self::KERTAS[$s['kertas']];
        [$kl, $kt] = $s['orientasi'] === 'landscape' ? [$t, $l] : [$l, $t];

        $pl = $s['skema'] === 'lipat' ? self::KARTU_LEBAR * 2 + $s['sela_lipat'] : self::KARTU_LEBAR;
        $pt = self::KARTU_TINGGI;

        $muat = fn (float $ruang, float $petak) => $ruang < $petak ? 0 : (int) floor(($ruang + $s['sela']) / ($petak + $s['sela']) + 1e-9);

        $kolom = max(1, $muat($kl - 2 * $s['margin'], $pl));
        $baris = max(1, $muat($kt - 2 * $s['margin'], $pt));

        return [
            'kolom' => $kolom, 'baris' => $baris, 'per_lembar' => $kolom * $baris,
            'petak_l' => $pl, 'petak_t' => $pt, 'kertas_l' => $kl, 'kertas_t' => $kt,
            'kiri' => max(0, round(($kl - ($kolom * $pl + ($kolom - 1) * $s['sela'])) / 2, 2)),
            'atas' => max(0, round(($kt - ($baris * $pt + ($baris - 1) * $s['sela'])) / 2, 2)),
        ];
    }

    /** Preset yang terakhir dipakai perusahaan pengguna ini, atau bawaan. */
    public static function tersimpan(User $u): array
    {
        $t = TataCetak::query()->orderByDesc('updated_at')->orderByDesc('id')->first();

        return self::rapikan($t?->pengaturan ?? []);
    }

    /** @param array<string,mixed> $s */
    public static function simpan(User $u, string $nama, array $s): TataCetak
    {
        $s = self::rapikan($s);

        $t = TataCetak::query()->firstOrNew(['nama' => $nama]);
        $t->fill(['pengaturan' => $s, 'user_id' => $u->id]);
        if (! $t->exists && ! $t->company_id) $t->company_id = $u->company_id;
        $t->updated_at = now();
        $t->save();

        return $t;
    }
}
