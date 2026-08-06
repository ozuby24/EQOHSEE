<?php

namespace App\Support;

/**
 * TPKKP — replika mesin hitung workbook "Penilaian Kinerja Keselamatan Pertambangan 2026".
 *
 * Sumber kebenaran: resources/data/tpkkp/instrumen.json (diekstrak dari instrument.js).
 *
 * Rumus (identik dengan engine.js sumber):
 *   skor metode  = rerata seluruh entitas terisi, atau nilai langsung bila metode tanpa entitas
 *   nilai item   = Σ skor metode          · maks item = 5 × jumlah metode
 *   capaian item = nilai / maks           · metode kosong dihitung NOL
 *   nilai param  = Σ nilai item / Σ maks item × bobot
 *   nilai total  = Σ nilai indikator      · maks 1,000
 *
 * Ambang tunggal untuk SEMUA tingkat (item, parameter, indikator, total):
 *   < 0,5 Dasar · < 0,7 Reaktif · < 0,8 Terencana · < 0,9 Proaktif · ≤ 1 Resilient
 *
 * Bentuk $scores:
 *   $scores[metode][kodeItem] = ['v' => 1..5|null, 'e' => [entitas => 1..5], 'ket' => string]
 */
class Tpkkp
{
    public const LV    = ['Dasar', 'Reaktif', 'Terencana', 'Proaktif', 'Resilient'];
    public const LVHEX = ['#E5484D', '#F5760A', '#C7DE30', '#1EE699', '#47CEFF'];

    private static ?array $ref    = null;
    private static ?array $rubrik = null;
    private static ?array $target = null;

    /* ================= referensi ================= */

    public static function ref(): array
    {
        return self::$ref ??= json_decode(
            file_get_contents(resource_path('data/tpkkp/instrumen.json')), true
        );
    }

    /** Rubrik dimuat malas — 374 KB, hanya saat benar-benar dipakai. */
    public static function rubrik(): array
    {
        return self::$rubrik ??= json_decode(
            file_get_contents(resource_path('data/tpkkp/rubrik.json')), true
        );
    }

    /** Target sampel/dokumen per item per metode. */
    public static function target(): array
    {
        return self::$target ??= json_decode(
            file_get_contents(resource_path('data/tpkkp/target.json')), true
        );
    }

    public static function meta(): array         { return self::ref()['meta'] ?? []; }
    public static function methods(): array      { return self::ref()['methods']; }
    public static function indicators(): array   { return self::ref()['indicators']; }
    public static function paramTargets(): array { return self::ref()['paramTargets'] ?? []; }
    public static function totalTarget(): float  { return (float) (self::ref()['totalTarget'] ?? 0); }
    public static function programSeed(): array  { return self::ref()['programSeed'] ?? []; }
    public static function schedule(): array     { return self::ref()['schedule'] ?? []; }
    public static function samplingRef(): array  { return self::ref()['sampling'] ?? []; }

    /** Semua item, diratakan, membawa kode parameter & indikator. */
    public static function allItems(): array
    {
        $out = [];
        foreach (self::indicators() as $ind) {
            foreach ($ind['params'] as $par) {
                foreach ($par['items'] as $it) {
                    $out[] = $it + [
                        'param'     => $par['code'],
                        'paramName' => $par['name'],
                        'indicator' => $ind['code'],
                    ];
                }
            }
        }
        return $out;
    }

    public static function totalItems(): int { return count(self::allItems()); }

    public static function itemByCode(string $code): ?array
    {
        foreach (self::allItems() as $it) if ($it['code'] === $code) return $it;
        return null;
    }

    public static function paramByCode(string $code): ?array
    {
        foreach (self::indicators() as $ind)
            foreach ($ind['params'] as $par) if ($par['code'] === $code) return $par;
        return null;
    }

    /** Rubrik khusus metode, jatuh balik ke rubrik acuan (REF). */
    public static function rubrikFor(string $method, string $code): ?array
    {
        $R = self::rubrik();
        return $R[$method . '|' . $code] ?? $R['REF|' . $code] ?? null;
    }

    /* ================= kategori ================= */

    /** @return string|null label kategori dari rasio 0..1 */
    public static function category(?float $x): ?string
    {
        if ($x === null || is_nan($x)) return null;
        foreach (self::ref()['thresholds'] as $t) {
            if ($x < $t['lt']) return $t['label'];
        }
        return 'Resilient';
    }

    /** Level 1..5 dari label; 0 bila belum ada nilai. */
    public static function level(?string $label): int
    {
        $i = array_search($label, self::LV, true);
        return $i === false ? 0 : $i + 1;
    }

    public static function levelHex(int $n): string
    {
        return self::LVHEX[$n - 1] ?? '#C4C7CD';
    }

    /* ================= hitungan ================= */

    /**
     * Skor satu metode untuk satu item.
     * $onlyEntity diisi → hanya nilai entitas itu (isolasi rekap per perusahaan).
     */
    public static function methodScore(array $scores, string $method, string $code, ?string $onlyEntity = null): array
    {
        $rec = $scores[$method][$code] ?? null;
        if (!$rec) return ['val' => null, 'filled' => false, 'nEnt' => 0];

        $e = $rec['e'] ?? [];

        if ($onlyEntity !== null) {
            $v = $e[$onlyEntity] ?? null;
            return (is_numeric($v) && $v >= 1)
                ? ['val' => (float) $v, 'filled' => true, 'nEnt' => 1]
                : ['val' => null, 'filled' => false, 'nEnt' => 0];
        }

        $vals = [];
        foreach ($e as $v) if (is_numeric($v) && $v >= 1) $vals[] = (float) $v;
        if ($vals) {
            return ['val' => array_sum($vals) / count($vals), 'filled' => true, 'nEnt' => count($vals)];
        }

        $v = $rec['v'] ?? null;
        return (is_numeric($v) && $v >= 1)
            ? ['val' => (float) $v, 'filled' => true, 'nEnt' => 0]
            : ['val' => null, 'filled' => false, 'nEnt' => 0];
    }

    public static function itemCalc(array $scores, array $item, ?string $onlyEntity = null): array
    {
        $perMethod = []; $sum = 0.0; $filled = 0;
        $M = self::methods();

        foreach ($item['methods'] as $m) {
            $ents   = $M[$m]['entities'] ?? [];
            $useEnt = ($onlyEntity !== null && in_array($onlyEntity, $ents, true)) ? $onlyEntity : null;
            $s = self::methodScore($scores, $m, $item['code'], $useEnt);
            $perMethod[$m] = $s['val'];
            if ($s['filled']) { $sum += $s['val']; $filled++; }
        }

        $total = count($item['methods']);
        $max   = $total * 5;
        $achv  = $filled ? $sum / $max : null;   // metode kosong dihitung nol

        return [
            'code'      => $item['code'],
            'name'      => $item['name'],
            'perMethod' => $perMethod,
            'nilai'     => $filled ? $sum : null,
            'max'       => $max,
            'achv'      => $achv,
            'category'  => self::category($achv),
            'filled'    => $filled,
            'total'     => $total,
            'complete'  => $filled === $total,
        ];
    }

    public static function paramCalc(array $scores, array $par): array
    {
        $sum = 0.0; $max = 0; $filledCells = 0; $totalCells = 0; $any = false; $items = [];

        foreach ($par['items'] as $it) {
            $c = self::itemCalc($scores, $it);
            $max         += $c['max'];
            $totalCells  += $c['total'];
            $filledCells += $c['filled'];
            if ($c['nilai'] !== null) { $sum += $c['nilai']; $any = true; }
            $items[] = $c;
        }

        $ratio = $any && $max > 0 ? $sum / $max : null;

        return [
            'code'        => $par['code'],
            'name'        => $par['name'],
            'weight'      => $par['weight'],
            'items'       => $items,
            'nilai'       => $any ? $sum : null,
            'max'         => $max,
            'ratio'       => $ratio,
            'score'       => $ratio !== null ? $ratio * $par['weight'] : null,
            'target'      => self::paramTargets()[$par['code']] ?? null,
            'category'    => self::category($ratio),
            'filledCells' => $filledCells,
            'totalCells'  => $totalCells,
        ];
    }

    public static function indicatorCalc(array $scores, array $ind): array
    {
        $params = [];
        foreach ($ind['params'] as $p) $params[] = self::paramCalc($scores, $p);

        $weight = 0.0; $score = null; $target = 0.0; $filledCells = 0; $totalCells = 0;
        foreach ($params as $p) {
            $weight      += $p['weight'];
            $target      += $p['target'] ?? 0;
            $filledCells += $p['filledCells'];
            $totalCells  += $p['totalCells'];
            if ($p['score'] !== null) $score = ($score ?? 0) + $p['score'];
        }
        $ratio = ($score !== null && $weight > 0) ? $score / $weight : null;

        return [
            'code'        => $ind['code'],
            'name'        => $ind['name'],
            'params'      => $params,
            'weight'      => $weight,
            'score'       => $score,
            'target'      => $target,
            'ratio'       => $ratio,
            'category'    => self::category($ratio),
            'filledCells' => $filledCells,
            'totalCells'  => $totalCells,
        ];
    }

    public static function totalCalc(array $scores): array
    {
        $inds = []; $score = null; $filledCells = 0; $totalCells = 0;
        foreach (self::indicators() as $i) {
            $c = self::indicatorCalc($scores, $i);
            $inds[]       = $c;
            $filledCells += $c['filledCells'];
            $totalCells  += $c['totalCells'];
            if ($c['score'] !== null) $score = ($score ?? 0) + $c['score'];
        }

        return [
            'indicators'   => $inds,
            'score'        => $score,
            'target'       => self::totalTarget(),
            'category'     => self::category($score),
            'level'        => self::level(self::category($score)),
            'filledCells'  => $filledCells,
            'totalCells'   => $totalCells,
            'completeness' => $totalCells ? $filledCells / $totalCells : 0.0,
        ];
    }

    /** Rekap per metode — seperti bagian bawah sheet Result. */
    public static function methodTotals(array $scores): array
    {
        $acc = [];
        foreach (self::methods() as $k => $m) $acc[$k] = ['sum' => 0.0, 'max' => 0, 'filled' => 0, 'items' => 0];

        foreach (self::allItems() as $it) {
            foreach ($it['methods'] as $m) {
                $acc[$m]['max'] += 5;
                $acc[$m]['items']++;
                $s = self::methodScore($scores, $m, $it['code']);
                if ($s['filled']) { $acc[$m]['sum'] += $s['val']; $acc[$m]['filled']++; }
            }
        }

        $out = [];
        foreach ($acc as $k => $v) {
            $ratio = $v['filled'] ? $v['sum'] / $v['max'] : null;
            $out[] = $v + [
                'key'      => $k,
                'name'     => self::methods()[$k]['name'],
                'refMax'   => self::methods()[$k]['refMax'] ?? null,
                'ratio'    => $ratio,
                'category' => self::category($ratio),
            ];
        }
        return $out;
    }

    /** Item bercapaian terendah — bahan program improvement. */
    public static function gaps(array $scores, ?int $limit = null): array
    {
        $rows = [];
        foreach (self::allItems() as $it) {
            $c = self::itemCalc($scores, $it);
            if ($c['filled'] > 0) $rows[] = $c + ['param' => $it['param'], 'paramName' => $it['paramName']];
        }
        usort($rows, fn ($a, $b) => ($a['achv'] ?? 2) <=> ($b['achv'] ?? 2));
        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    /** Sebaran kategori item — hanya item yang lengkap semua metodenya. */
    public static function distribution(array $scores): array
    {
        $d = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'none' => 0];
        foreach (self::allItems() as $it) {
            $c = self::itemCalc($scores, $it);
            if (!$c['complete'] || $c['achv'] === null) { $d['none']++; continue; }
            $d[self::level($c['category'])]++;
        }
        return $d;
    }

    /* ============ rekap per perusahaan (isolasi entitas) ============ */

    /** Metode yang entitasnya berupa perusahaan (mengikuti roster TD). */
    public static function perCompanyMethods(): array
    {
        $roster = self::methods()['TD']['entities'] ?? [];
        $out = [];
        foreach (self::methods() as $k => $m) {
            $ents = $m['entities'] ?? [];
            if ($ents && array_intersect($ents, $roster)) $out[] = $k;
        }
        return $out;
    }

    /** Rerata 1..5 satu item untuk satu perusahaan, lintas metode yang diizinkan. */
    public static function companyItemScore(array $scores, string $company, array $item, ?array $methods = null): array
    {
        $sum = 0.0; $n = 0;
        foreach ($item['methods'] as $m) {
            if ($methods !== null && !in_array($m, $methods, true)) continue;
            $s = self::methodScore($scores, $m, $item['code'], $company);
            if ($s['filled']) { $sum += $s['val']; $n++; }
        }
        return ['code' => $item['code'], 'name' => $item['name'], 'avg' => $n ? $sum / $n : null, 'n' => $n];
    }

    /** Rincian satu perusahaan: indikator → parameter → item (rerata 1..5). */
    public static function companyBreakdown(array $scores, string $company, ?array $methods = null): array
    {
        $out = [];
        foreach (self::indicators() as $ind) {
            $params = []; $wsum = 0.0; $wn = 0;
            foreach ($ind['params'] as $par) {
                $sum = 0.0; $n = 0; $items = [];
                foreach ($par['items'] as $it) {
                    $r = self::companyItemScore($scores, $company, $it, $methods);
                    if ($r['avg'] !== null) { $sum += $r['avg']; $n++; }
                    $items[] = $r;
                }
                $params[] = [
                    'code' => $par['code'], 'name' => $par['name'],
                    'avg'  => $n ? $sum / $n : null, 'count' => $n, 'items' => $items,
                ];
                if ($n) { $wsum += $sum; $wn += $n; }
            }
            $out[] = [
                'code' => $ind['code'], 'name' => $ind['name'],
                'avg'  => $wn ? $wsum / $wn : null, 'params' => $params,
            ];
        }
        return $out;
    }

    /** Item terlemah satu perusahaan. */
    public static function companyGaps(array $scores, string $company, ?array $methods = null, ?int $limit = null): array
    {
        $rows = [];
        foreach (self::allItems() as $it) {
            $r = self::companyItemScore($scores, $company, $it, $methods);
            if ($r['n'] > 0) $rows[] = $r + ['param' => $it['param']];
        }
        usort($rows, fn ($a, $b) => ($a['avg'] ?? 6) <=> ($b['avg'] ?? 6));
        return $limit ? array_slice($rows, 0, $limit) : $rows;
    }

    /* ================= sampling ================= */

    /** Rumus Slovin: n = N / (1 + N·e²), dibulatkan ke atas. */
    public static function slovin(int $N, float $e): int
    {
        return $N > 0 ? (int) ceil($N / (1 + $N * $e * $e)) : 0;
    }

    /** Alokasi proporsional per strata. */
    public static function strataAlloc(array $strata, float $e): array
    {
        $N = 0;
        foreach ($strata as $s) $N += (int) ($s['N'] ?? 0);
        $n = self::slovin($N, $e);

        $rows = []; $tot = 0;
        foreach ($strata as $s) {
            $Nh = (int) ($s['N'] ?? 0);
            $nh = $N > 0 ? (int) round($Nh * $n / $N) : 0;
            $tot += $nh;
            $rows[] = ['j' => $s['j'] ?? '', 'N' => $Nh, 'nh' => $nh];
        }
        return ['N' => $N, 'n' => $n, 'rows' => $rows, 'total' => $tot];
    }
}
