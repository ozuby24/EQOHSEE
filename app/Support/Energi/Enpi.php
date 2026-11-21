<?php

namespace App\Support\Energi;

/**
 * Energy Baseline & EnPI ternormalisasi (ISO 50006, FSD §6.7, §8.5).
 *
 * Model regresi linear berganda OLS: energi = b₀ + Σ bᵢ·xᵢ, dengan x
 * variabel relevan (ton, BCM, jarak angkut, stripping ratio). Model
 * diterima bila R² ≥ 0,75 dan CV(RMSE) ≤ 20% (ASHRAE Guideline 14, bawaan
 * yang dapat diubah); bila tidak, EnPI rasio sederhana (GJ/ton) yang
 * dianjurkan — model yang tidak menjelaskan datanya hanya memberi
 * "penghematan" yang sebenarnya derau.
 */
final class Enpi
{
    public const R2_MIN = 0.75;
    public const CVRMSE_MAKS = 20.0;

    /**
     * @param list<float> $y nilai variabel terikat per periode
     * @param list<array<string,float>> $x variabel bebas per periode, berkunci nama variabel
     * @return array{sah:bool,n:int,variabel:list<string>,intercept:?float,koefisien:array<string,float>,
     *               r2:?float,cv_rmse:?float,p:array<string,?float>,diterima:bool,alasan:?string}
     */
    public static function regresi(array $y, array $x): array
    {
        $n = count($y);
        $var = $n ? array_keys($x[0]) : [];
        $k = count($var);

        $gagal = fn (string $alasan) => ['sah' => false, 'n' => $n, 'variabel' => $var, 'intercept' => null,
            'koefisien' => [], 'r2' => null, 'cv_rmse' => null, 'p' => [], 'diterima' => false, 'alasan' => $alasan];

        if ($n < $k + 2) return $gagal("Titik data ({$n}) terlalu sedikit untuk {$k} variabel bebas — minimal ".($k + 2).'.');

        // Matriks rancangan dengan kolom konstanta.
        $X = [];
        foreach ($x as $baris) $X[] = array_merge([1.0], array_map('floatval', array_values($baris)));

        $XtX = self::kali(self::transpos($X), $X);
        $inv = self::invers($XtX);
        if ($inv === null) return $gagal('Variabel bebas saling bergantung linear (kolinear) — kurangi variabelnya.');

        $Xty = self::kali(self::transpos($X), array_map(fn ($v) => [(float) $v], $y));
        $b = array_map(fn ($r) => $r[0], self::kali($inv, $Xty));

        $rata = array_sum($y) / $n;
        $ssr = $sst = 0.0;
        foreach ($X as $i => $baris) {
            $pred = 0.0;
            foreach ($baris as $j => $v) $pred += $b[$j] * $v;
            $ssr += ($y[$i] - $pred) ** 2;
            $sst += ($y[$i] - $rata) ** 2;
        }

        $dof = $n - $k - 1;
        $r2 = $sst > 0 ? 1 - $ssr / $sst : null;
        $rmse = sqrt($ssr / max(1, $dof));
        $cv = $rata != 0.0 ? $rmse / $rata * 100 : null;

        $s2 = $ssr / max(1, $dof);
        $p = [];
        foreach ($var as $j => $nama) {
            $se = sqrt(max(0.0, $s2 * $inv[$j + 1][$j + 1]));
            $p[$nama] = $se > 0 ? self::pDuaSisi($b[$j + 1] / $se, $dof) : null;
        }

        $koef = [];
        foreach ($var as $j => $nama) $koef[$nama] = $b[$j + 1];

        $diterima = $r2 !== null && $cv !== null && $r2 >= self::R2_MIN && $cv <= self::CVRMSE_MAKS;

        return [
            'sah' => true, 'n' => $n, 'variabel' => $var, 'intercept' => $b[0], 'koefisien' => $koef,
            'r2' => $r2, 'cv_rmse' => $cv, 'p' => $p, 'diterima' => $diterima,
            'alasan' => $diterima ? null : 'R² atau CV(RMSE) di luar ambang — gunakan EnPI rasio sederhana.',
        ];
    }

    /** Prediksi EnB untuk nilai variabel satu periode. */
    public static function prediksi(array $model, array $x): ?float
    {
        if (empty($model['sah'])) return null;

        $v = (float) $model['intercept'];
        foreach ($model['koefisien'] as $nama => $b) $v += $b * (float) ($x[$nama] ?? 0);

        return $v;
    }

    /* ── aljabar kecil ── */

    private static function transpos(array $m): array
    {
        $t = [];
        foreach ($m as $i => $baris) {
            foreach ($baris as $j => $v) $t[$j][$i] = $v;
        }

        return $t;
    }

    private static function kali(array $a, array $b): array
    {
        $out = [];
        $bt = self::transpos($b);
        foreach ($a as $i => $baris) {
            foreach ($bt as $j => $kol) {
                $s = 0.0;
                foreach ($baris as $t => $v) $s += $v * $kol[$t];
                $out[$i][$j] = $s;
            }
        }

        return $out;
    }

    /** Invers Gauss-Jordan dengan pivot parsial; null bila singular. */
    private static function invers(array $m): ?array
    {
        $n = count($m);
        $a = [];
        foreach ($m as $i => $baris) {
            $a[$i] = array_merge($baris, array_map(fn ($j) => $i === $j ? 1.0 : 0.0, range(0, $n - 1)));
        }

        for ($c = 0; $c < $n; $c++) {
            $p = $c;
            for ($r = $c + 1; $r < $n; $r++) if (abs($a[$r][$c]) > abs($a[$p][$c])) $p = $r;
            if (abs($a[$p][$c]) < 1e-12) return null;
            [$a[$c], $a[$p]] = [$a[$p], $a[$c]];

            $pv = $a[$c][$c];
            foreach ($a[$c] as $j => $v) $a[$c][$j] = $v / $pv;

            for ($r = 0; $r < $n; $r++) {
                if ($r === $c) continue;
                $f = $a[$r][$c];
                if ($f == 0.0) continue;
                foreach ($a[$r] as $j => $v) $a[$r][$j] = $v - $f * $a[$c][$j];
            }
        }

        return array_map(fn ($baris) => array_slice($baris, $n), $a);
    }

    /** p-value dua sisi uji-t lewat fungsi beta tak lengkap teregulasi. */
    private static function pDuaSisi(float $t, int $dof): float
    {
        $x = $dof / ($dof + $t * $t);

        return min(1.0, max(0.0, self::betaInc($dof / 2, 0.5, $x)));
    }

    private static function betaInc(float $a, float $b, float $x): float
    {
        if ($x <= 0) return 0.0;
        if ($x >= 1) return 1.0;

        $ln = self::lnGamma($a + $b) - self::lnGamma($a) - self::lnGamma($b) + $a * log($x) + $b * log(1 - $x);
        $depan = exp($ln);

        return $x < ($a + 1) / ($a + $b + 2)
            ? $depan * self::pecahan($a, $b, $x) / $a
            : 1 - $depan * self::pecahan($b, $a, 1 - $x) / $b;
    }

    private static function pecahan(float $a, float $b, float $x): float
    {
        $qab = $a + $b; $qap = $a + 1; $qam = $a - 1;
        $c = 1.0; $d = 1 - $qab * $x / $qap;
        $d = abs($d) < 1e-30 ? 1e-30 : $d; $d = 1 / $d; $h = $d;

        for ($m = 1; $m <= 200; $m++) {
            $m2 = 2 * $m;
            $aa = $m * ($b - $m) * $x / (($qam + $m2) * ($a + $m2));
            $d = 1 + $aa * $d; $d = abs($d) < 1e-30 ? 1e-30 : $d;
            $c = 1 + $aa / $c; $c = abs($c) < 1e-30 ? 1e-30 : $c;
            $d = 1 / $d; $h *= $d * $c;

            $aa = -($a + $m) * ($qab + $m) * $x / (($a + $m2) * ($qap + $m2));
            $d = 1 + $aa * $d; $d = abs($d) < 1e-30 ? 1e-30 : $d;
            $c = 1 + $aa / $c; $c = abs($c) < 1e-30 ? 1e-30 : $c;
            $d = 1 / $d; $del = $d * $c; $h *= $del;
            if (abs($del - 1) < 3e-12) break;
        }

        return $h;
    }

    private static function lnGamma(float $z): float
    {
        $g = [76.18009172947146, -86.50532032941677, 24.01409824083091, -1.231739572450155, 0.1208650973866179e-2, -0.5395239384953e-5];
        $x = $z; $y = $z; $tmp = $x + 5.5; $tmp -= ($x + 0.5) * log($tmp);
        $ser = 1.000000000190015;
        foreach ($g as $c) $ser += $c / ++$y;

        return -$tmp + log(2.5066282746310005 * $ser / $x);
    }
}
