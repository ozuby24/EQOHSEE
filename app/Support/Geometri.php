<?php

namespace App\Support;

use JsonException;

/**
 * Ukuran dari GeoJSON: luas, keliling, titik tengah, dan kotak batas.
 *
 * Sebelum ini sebuah layer hanyalah gumpalan JSON yang disimpan dan
 * ditampilkan kembali apa adanya. Tidak ada yang dapat menjawab berapa
 * hektare pit-nya, berapa yang sudah direklamasi, atau berapa panjang
 * jalan angkutnya — padahal ketiganya adalah pertanyaan yang membuat
 * peta tambang berguna, dan ketiganya sudah terkandung di dalam
 * koordinat yang tersimpan.
 *
 * Luas dihitung dengan rumus kelebihan bola (spherical excess), bukan
 * dengan rumus tali sepatu di atas derajat lintang-bujur. Rumus tali
 * sepatu memperlakukan derajat sebagai satuan panjang yang sama ke
 * segala arah; di lintang nol kekeliruannya kecil, tetapi satu derajat
 * bujur menyempit mengikuti kosinus lintang — pada 60° ia tinggal
 * setengah. Kekeliruannya tidak menimbulkan galat, hanya angka hektare
 * yang salah dan tetap terlihat masuk akal.
 *
 * Jari-jari yang dipakai adalah jari-jari rata-rata bumi menurut IUGG.
 * Untuk luas bukaan tambang, selisihnya terhadap perhitungan elipsoid
 * berada jauh di bawah ketelitian survei yang menghasilkan koordinatnya.
 */
final class Geometri
{
    /** Jari-jari rata-rata bumi (IUGG), meter. */
    private const R = 6_371_008.8;

    /**
     * Mengukur sebuah GeoJSON.
     *
     * Menerima FeatureCollection, Feature, atau geometri telanjang.
     * Bentuk yang tidak dikenali menghasilkan ukuran nol, bukan galat:
     * berkas survei sering memuat titik penanda dan anotasi di samping
     * poligonnya, dan menolak seluruh berkas karena satu titik akan
     * membuat layernya mustahil disimpan.
     *
     * @return array{luas_m2:float,keliling_m:float,titik:?array{0:float,1:float},kotak:?array{0:float,1:float,2:float,3:float},fitur:int}
     */
    public static function ukur(string $geojson): array
    {
        try {
            $data = json_decode($geojson, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::kosong();
        }

        if (!is_array($data)) return self::kosong();

        $geometri = self::kumpulkan($data);

        if ($geometri === []) return self::kosong();

        $luas = 0.0;
        $keliling = 0.0;
        $titik = [];
        $kotak = null;

        foreach ($geometri as $g) {
            $luas += self::luasGeometri($g);
            $keliling += self::kelilingGeometri($g);

            foreach (self::titikDari($g) as $p) {
                $titik[] = $p;
                $kotak = $kotak === null
                    ? [$p[0], $p[1], $p[0], $p[1]]
                    : [min($kotak[0], $p[0]), min($kotak[1], $p[1]), max($kotak[2], $p[0]), max($kotak[3], $p[1])];
            }
        }

        return [
            'luas_m2'    => round($luas, 2),
            'keliling_m' => round($keliling, 2),
            'titik'      => self::rataRata($titik),
            'kotak'      => $kotak,
            'fitur'      => count($geometri),
        ];
    }

    public static function hektare(float $m2): float
    {
        return round($m2 / 10_000, 4);
    }

    /* ---------- penguraian ---------- */

    /** @return list<array> daftar geometri telanjang */
    private static function kumpulkan(array $n): array
    {
        $jenis = $n['type'] ?? null;

        if ($jenis === 'FeatureCollection') {
            $out = [];
            foreach (($n['features'] ?? []) as $f) {
                if (is_array($f)) $out = array_merge($out, self::kumpulkan($f));
            }

            return $out;
        }

        if ($jenis === 'Feature') {
            return is_array($n['geometry'] ?? null) ? self::kumpulkan($n['geometry']) : [];
        }

        if ($jenis === 'GeometryCollection') {
            $out = [];
            foreach (($n['geometries'] ?? []) as $g) {
                if (is_array($g)) $out = array_merge($out, self::kumpulkan($g));
            }

            return $out;
        }

        return isset($n['coordinates']) && is_array($n['coordinates']) ? [$n] : [];
    }

    /* ---------- luas ---------- */

    private static function luasGeometri(array $g): float
    {
        $c = $g['coordinates'];

        return match ($g['type'] ?? '') {
            'Polygon'      => self::luasPoligon($c),
            'MultiPolygon' => array_sum(array_map(
                fn ($p) => is_array($p) ? self::luasPoligon($p) : 0.0,
                $c
            )),
            default        => 0.0,
        };
    }

    /**
     * Luas sebuah poligon: cincin luar dikurangi seluruh cincin dalam.
     *
     * Cincin dalam adalah lubang — kolam pengendap di tengah area
     * timbunan, atau bukaan yang belum ditambang di dalam batas pit.
     * Mengabaikannya melaporkan luas yang lebih besar daripada yang
     * sebenarnya terganggu.
     */
    private static function luasPoligon(array $cincin): float
    {
        if ($cincin === []) return 0.0;

        $luar = self::luasCincin($cincin[0] ?? []);
        $dalam = 0.0;

        foreach (array_slice($cincin, 1) as $lubang) {
            if (is_array($lubang)) $dalam += self::luasCincin($lubang);
        }

        return max(0.0, $luar - $dalam);
    }

    /**
     * Kelebihan bola atas sebuah cincin tertutup.
     *
     * Rumus Chamberlain & Duquette: menjumlahkan sumbangan tiap ruas
     * terhadap luas yang dilingkupinya pada permukaan bola. Nilainya
     * dimutlakkan supaya arah putaran cincin — searah atau berlawanan
     * jarum jam — tidak mengubah hasilnya; berkas survei tidak selalu
     * mematuhi kaidah arah putaran GeoJSON.
     */
    private static function luasCincin(array $titik): float
    {
        $n = count($titik);
        if ($n < 3) return 0.0;

        $jumlah = 0.0;

        for ($i = 0; $i < $n; $i++) {
            [$lon1, $lat1] = self::pasangan($titik[$i]);
            [$lon2, $lat2] = self::pasangan($titik[($i + 1) % $n]);

            $jumlah += deg2rad($lon2 - $lon1)
                * (2 + sin(deg2rad($lat1)) + sin(deg2rad($lat2)));
        }

        return abs($jumlah * self::R * self::R / 2.0);
    }

    /* ---------- keliling dan panjang ---------- */

    private static function kelilingGeometri(array $g): float
    {
        $c = $g['coordinates'];

        return match ($g['type'] ?? '') {
            'LineString'      => self::panjangGaris($c),
            'MultiLineString' => array_sum(array_map(
                fn ($l) => is_array($l) ? self::panjangGaris($l) : 0.0, $c)),
            'Polygon'         => array_sum(array_map(
                fn ($r) => is_array($r) ? self::panjangGaris($r, true) : 0.0, $c)),
            'MultiPolygon'    => array_sum(array_map(
                fn ($p) => is_array($p) ? array_sum(array_map(
                    fn ($r) => is_array($r) ? self::panjangGaris($r, true) : 0.0, $p)) : 0.0, $c)),
            default           => 0.0,
        };
    }

    /** Panjang sepanjang busur besar; menutup cincin bila diminta. */
    private static function panjangGaris(array $titik, bool $tutup = false): float
    {
        $n = count($titik);
        if ($n < 2) return 0.0;

        $total = 0.0;
        $batas = $tutup ? $n : $n - 1;

        for ($i = 0; $i < $batas; $i++) {
            $total += self::haversine(
                self::pasangan($titik[$i]),
                self::pasangan($titik[($i + 1) % $n])
            );
        }

        return $total;
    }

    private static function haversine(array $a, array $b): float
    {
        [$lon1, $lat1] = $a;
        [$lon2, $lat2] = $b;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * self::R * asin(min(1.0, sqrt($h)));
    }

    /* ---------- titik ---------- */

    /** @return list<array{0:float,1:float}> */
    private static function titikDari(array $g): array
    {
        return self::ratakan($g['coordinates'] ?? []);
    }

    /** @return list<array{0:float,1:float}> */
    private static function ratakan(mixed $c): array
    {
        if (!is_array($c) || $c === []) return [];

        // Pasangan angka adalah sebuah titik; selebihnya sarang.
        if (is_numeric($c[0] ?? null)) {
            return [self::pasangan($c)];
        }

        $out = [];
        foreach ($c as $anak) $out = array_merge($out, self::ratakan($anak));

        return $out;
    }

    /** @return array{0:float,1:float} */
    private static function pasangan(mixed $t): array
    {
        return is_array($t)
            ? [(float) ($t[0] ?? 0), (float) ($t[1] ?? 0)]
            : [0.0, 0.0];
    }

    /** @param list<array{0:float,1:float}> $titik */
    private static function rataRata(array $titik): ?array
    {
        $n = count($titik);
        if ($n === 0) return null;

        $lon = array_sum(array_column($titik, 0)) / $n;
        $lat = array_sum(array_column($titik, 1)) / $n;

        return [round($lon, 7), round($lat, 7)];
    }

    private static function kosong(): array
    {
        return ['luas_m2' => 0.0, 'keliling_m' => 0.0, 'titik' => null, 'kotak' => null, 'fitur' => 0];
    }
}
