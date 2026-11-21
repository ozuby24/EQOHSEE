<?php

namespace App\Support\Energi;

use Illuminate\Support\Carbon;

/**
 * Tinjauan energi: Pareto pemakai energi dan Significant Energy Use (ISO 50001 6.3).
 *
 * Pemakai dikelompokkan per kategori alat dan per area listrik. Yang masuk
 * 80% pertama energi kumulatif diusulkan sebagai SEU; penetapannya tetap
 * keputusan Manajer Energi (ditandai pada unit alat, `energy_equipment.seu`,
 * atau dicantumkan pada ruang lingkup audit).
 */
final class Seu
{
    /** @return list<array{kunci:string,label:string,gj:float,persen:float,kumulatif:float,usul_seu:bool}> */
    public static function pareto(Carbon|string $dari, Carbon|string $sampai, array $saring = []): array
    {
        $baris = Inventaris::hitung($dari, $sampai, $saring)['baris'];

        $g = [];
        foreach ($baris as $b) {
            if ($b['scope'] === 3 || !$b['gj']) continue;

            $kunci = match ($b['sumber']['jenis']) {
                'alat'    => 'kategori:'.$b['sumber']['kategori'],
                'listrik', 'genset' => 'area:'.$b['sumber']['kategori'],
                default   => 'energi:'.$b['kode'],
            };
            $label = match ($b['sumber']['jenis']) {
                'alat'    => 'Alat — '.$b['sumber']['kelompok'],
                'listrik', 'genset' => 'Area — '.$b['sumber']['kelompok'],
                default   => $b['sumber']['kelompok'],
            };

            $g[$kunci] ??= ['kunci' => $kunci, 'label' => $label, 'gj' => 0.0];
            $g[$kunci]['gj'] += $b['gj'];
        }

        usort($g, fn ($a, $b) => $b['gj'] <=> $a['gj']);
        $total = array_sum(array_column($g, 'gj'));
        $kum = 0.0;

        foreach ($g as &$x) {
            $x['persen'] = $total > 0 ? $x['gj'] / $total * 100 : 0.0;
            $sebelum = $kum;
            $kum += $x['persen'];
            $x['kumulatif'] = $kum;
            // Pemakai yang MULAI di bawah 80% kumulatif ikut diusulkan.
            $x['usul_seu'] = $sebelum < 80.0;
        }
        unset($x);

        return array_values($g);
    }
}
