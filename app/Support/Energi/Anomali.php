<?php

namespace App\Support\Energi;

use App\Models\{EnergyEquipment, EnergyFuelLog};
use Illuminate\Support\Carbon;

/**
 * Deteksi anomali konsumsi (FSD §6.10).
 *
 * Per unit: liter per jam operasi harian dibandingkan median 30 hari
 * unit itu sendiri memakai z robust (MAD). Median dipakai, bukan rerata,
 * karena satu hari salah ketik 4.000 L menarik rerata cukup jauh untuk
 * menyembunyikan dirinya sendiri — median tidak tergeser olehnya.
 *
 * Ditambah aturan yang tidak membutuhkan statistik: jam operasi lebih
 * dari 24 jam sehari, dan liter tercatat tanpa jam operasi.
 */
final class Anomali
{
    public const Z = 3.0;

    /** @return list<array{tanggal:string,unit:string,unit_id:int,jenis:string,nilai:?float,acuan:?float,pesan:string}> */
    public static function bbm(Carbon $sampai, int $hari = 30, ?int $companyId = null): array
    {
        $dari = $sampai->copy()->subDays($hari - 1)->startOfDay();

        $log = EnergyFuelLog::query()
            ->whereBetween('tanggal', Inventaris::sela($dari, $sampai))
            ->when($companyId, fn ($q, $c) => $q->whereHas('equipment', fn ($e) => $e->where('company_id', $c)))
            ->get(['id', 'equipment_id', 'tanggal', 'liter', 'hm']);

        if ($log->isEmpty()) return [];

        $alat = EnergyEquipment::withoutGlobalScopes()->whereIn('id', $log->pluck('equipment_id')->unique())->pluck('kode', 'id');
        $out = [];

        foreach ($log->groupBy('equipment_id') as $id => $baris) {
            $kode = $alat[$id] ?? ('#'.$id);

            foreach ($baris as $b) {
                if ($b->hm > 24) {
                    $out[] = self::temuan($b, $kode, 'hm', $b->hm, 24.0, "Jam operasi {$b->hm} jam dalam sehari melebihi 24 jam.");
                }
                if ($b->liter > 0 && $b->hm <= 0) {
                    $out[] = self::temuan($b, $kode, 'tanpa_hm', $b->liter, null, 'Solar tercatat tanpa jam operasi.');
                }
            }

            $rasio = $baris->filter(fn ($b) => $b->hm > 0)->map(fn ($b) => $b->liter / $b->hm)->values()->all();
            if (count($rasio) < 7) continue;

            $med = self::median($rasio);
            $mad = self::median(array_map(fn ($v) => abs($v - $med), $rasio));
            if ($mad <= 0) continue;

            foreach ($baris as $b) {
                if ($b->hm <= 0) continue;
                $z = 0.6745 * (($b->liter / $b->hm) - $med) / $mad;
                if (abs($z) > self::Z) {
                    $out[] = self::temuan($b, $kode, 'lonjakan', $b->liter / $b->hm, $med,
                        sprintf('L/HM %.1f jauh dari median 30 hari %.1f (z robust %.1f).', $b->liter / $b->hm, $med, $z));
                }
            }
        }

        usort($out, fn ($a, $b) => strcmp($b['tanggal'], $a['tanggal']));

        return $out;
    }

    private static function temuan($b, string $kode, string $jenis, ?float $nilai, ?float $acuan, string $pesan): array
    {
        return ['tanggal' => $b->tanggal->toDateString(), 'unit' => $kode, 'unit_id' => (int) $b->equipment_id,
                'log_id' => (int) $b->id, 'jenis' => $jenis, 'nilai' => $nilai, 'acuan' => $acuan, 'pesan' => $pesan];
    }

    public static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        if ($n === 0) return 0.0;

        return $n % 2 ? (float) $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }
}
