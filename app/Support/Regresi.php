<?php

namespace App\Support;

/**
 * Regresi lurus kuadrat terkecil.
 *
 * Dipakai dua modul untuk hal yang tampak jauh berbeda tetapi
 * berbentuk sama: menarik garis melalui titik yang berserak, lalu
 * menyatakan seberapa layak garis itu mewakili titiknya.
 *
 *  - Kestabilan lereng menariknya pada kebalikan laju terhadap waktu,
 *    dan memakai perpotongannya dengan nol sebagai perkiraan runtuh.
 *  - Peledakan menariknya pada logaritma getaran terhadap logaritma
 *    jarak skala, dan memakai kemiringannya sebagai tetapan situs.
 */
final class Regresi
{
    /**
     * @param  list<array{x:float,y:float}> $titik
     * @return array{m:float,b:float,r2:float,n:int}
     */
    public static function lurus(array $titik): array
    {
        $n = count($titik);
        if ($n < 2) return ['m' => 0.0, 'b' => $n ? $titik[0]['y'] : 0.0, 'r2' => 0.0, 'n' => $n];

        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($titik as $t) {
            $sx  += $t['x'];
            $sy  += $t['y'];
            $sxy += $t['x'] * $t['y'];
            $sxx += $t['x'] ** 2;
        }

        $pembagi = $n * $sxx - $sx ** 2;

        // Seluruh x sama: garisnya tegak, dan kemiringannya tak hingga.
        // Mengembalikan nol lebih jujur daripada membagi bilangan sangat
        // kecil dan menghasilkan kemiringan raksasa yang tampak berarti.
        if (abs($pembagi) < 1e-12) return ['m' => 0.0, 'b' => $sy / $n, 'r2' => 0.0, 'n' => $n];

        $m = ($n * $sxy - $sx * $sy) / $pembagi;
        $b = ($sy - $m * $sx) / $n;

        $rerata = $sy / $n;
        $ssTot = $ssRes = 0.0;
        foreach ($titik as $t) {
            $ssTot += ($t['y'] - $rerata) ** 2;
            $ssRes += ($t['y'] - ($m * $t['x'] + $b)) ** 2;
        }

        // Seluruh y sama: garisnya sempurna, tetapi tidak menjelaskan
        // ragam apa pun. R² = 1 di sini menyesatkan, jadi dinyatakan 0.
        $r2 = $ssTot > 1e-12 ? 1 - $ssRes / $ssTot : 0.0;

        return ['m' => $m, 'b' => $b, 'r2' => max(0.0, min(1.0, $r2)), 'n' => $n];
    }
}
