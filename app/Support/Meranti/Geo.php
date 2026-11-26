<?php

namespace App\Support\Meranti;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Peta Spasial MERANTI — unggah KML / KMZ / GeoJSON menjadi satu baris
 * per geometri, seperti saveGeoUpload() di Code.gs.
 *
 * MultiPolygon dan GeometryCollection dipecah menjadi geometri tunggal,
 * luas poligon dihitung bila berkasnya tidak menyebutnya (shoelace di
 * bidang ekuirektangular — cukup untuk skala blok tanam), dan properti
 * KML/GeoJSON dipetakan ke kolom nama, kategori, petak, minggu, bulan,
 * tahun, dan luas.
 *
 * KML dibaca tanpa entitas eksternal dan tanpa jaringan (LIBXML_NONET):
 * berkas peta datang dari luar, dan XML yang boleh memuat berkas atau
 * alamat lain adalah celah, bukan fitur.
 */
final class Geo
{
    public const BATAS_FITUR = 5000;

    /**
     * Ukuran terbesar doc.kml di dalam KMZ, SESUDAH dibuka.
     *
     * KMZ adalah arsip zip: beberapa kilobita di unggahan dapat mengembang
     * menjadi gigabita di memori (bom zip), dan isinya dibaca utuh ke
     * memori sebelum diurai. Batas unggahannya tidak menolong — yang
     * dibatasi di sana ukuran SEBELUM dibuka.
     */
    public const MAKS_KML = 50 * 1024 * 1024;

    /**
     * @return list<array{props: array<string, mixed>, geometry: array<string, mixed>}>
     */
    public static function bacaBerkas(string $jalur, string $nama): array
    {
        $kecil = mb_strtolower($nama);

        if (str_ends_with($kecil, '.kmz')) {
            return self::dariKml(self::kmlDariKmz($jalur));
        }
        if (preg_match('/\.(kml|xml)$/', $kecil)) {
            return self::dariKml((string) file_get_contents($jalur));
        }
        if (preg_match('/\.(geojson|json)$/', $kecil)) {
            $gj = json_decode((string) file_get_contents($jalur), true);
            if (!is_array($gj)) throw new RuntimeException('Berkas GeoJSON tidak dapat dibaca.');

            return self::dariGeojson($gj);
        }

        throw new RuntimeException('Format tidak dikenali. Gunakan .kml, .kmz, atau .geojson.');
    }

    private static function kmlDariKmz(string $jalur): string
    {
        $zip = new ZipArchive();
        if ($zip->open($jalur) !== true) throw new RuntimeException('KMZ tidak dapat dibuka.');

        $pilih = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = mb_strtolower((string) $zip->getNameIndex($i));
            if (str_ends_with($n, '.kml')) {
                $pilih = $i;
                if (str_contains($n, 'doc.kml')) break;
            }
        }
        if ($pilih === null) {
            $zip->close();
            throw new RuntimeException('KMZ tidak berisi berkas .kml.');
        }

        $ukuran = (int) ($zip->statIndex($pilih)['size'] ?? 0);
        if ($ukuran > self::MAKS_KML) {
            $zip->close();
            throw new RuntimeException('Berkas .kml di dalam KMZ terlalu besar (lebih dari '
                .(self::MAKS_KML / 1024 / 1024).' MB sesudah dibuka).');
        }

        $isi = (string) $zip->getFromIndex($pilih);
        $zip->close();

        return $isi;
    }

    /** @return list<array{props: array<string, mixed>, geometry: array<string, mixed>}> */
    public static function dariKml(string $kml): array
    {
        /* KML tidak pernah memerlukan DOCTYPE. Ditolak sebelum diurai,
           karena DOCTYPE-lah tempat entitas dideklarasikan — dan dengan
           LIBXML_PARSEHUGE (perlu untuk deretan koordinat yang panjang)
           batas pengembangan entitas libxml ikut dilonggarkan: sepuluh
           baris entitas bertingkat cukup untuk menghabiskan memori
           peladen ("billion laughs"). */
        if (preg_match('/<!DOCTYPE/i', $kml)) {
            throw new RuntimeException('KML tidak boleh memuat DOCTYPE.');
        }

        $dom = new DOMDocument();
        $lama = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($kml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($lama);
        if (!$ok) throw new RuntimeException('KML tidak dapat dibaca.');

        $xp = new DOMXPath($dom);
        $keluar = [];

        foreach ($xp->query('//*[local-name()="Placemark"]') as $pm) {
            /** @var DOMElement $pm */
            $props = [];
            if ($n = self::anak($xp, $pm, 'name')) $props['name'] = trim($n->textContent);
            if ($d = self::anak($xp, $pm, 'description')) $props['description'] = trim($d->textContent);
            foreach ($xp->query('.//*[local-name()="SimpleData"]', $pm) as $sd) {
                /** @var DOMElement $sd */
                $props[$sd->getAttribute('name')] = trim($sd->textContent);
            }
            foreach ($xp->query('.//*[local-name()="Data"]', $pm) as $dt) {
                /** @var DOMElement $dt */
                $v = self::anak($xp, $dt, 'value');
                $props[$dt->getAttribute('name')] = $v ? trim($v->textContent) : '';
            }

            foreach (self::geometriKml($xp, $pm) as $g) {
                $keluar[] = ['props' => $props, 'geometry' => $g];
                if (count($keluar) > self::BATAS_FITUR) throw new RuntimeException('Berkas memuat lebih dari '.self::BATAS_FITUR.' fitur.');
            }
        }

        return $keluar;
    }

    private static function anak(DOMXPath $xp, DOMElement $el, string $nama): ?DOMElement
    {
        $n = $xp->query('./*[local-name()="'.$nama.'"]', $el)->item(0);

        return $n instanceof DOMElement ? $n : null;
    }

    /** @return list<array<string, mixed>> */
    private static function geometriKml(DOMXPath $xp, DOMElement $pm): array
    {
        $g = [];

        foreach ($xp->query('.//*[local-name()="Polygon"]', $pm) as $poly) {
            $cincin = [];
            foreach ($xp->query('./*[local-name()="outerBoundaryIs"]//*[local-name()="coordinates"]', $poly) as $c) {
                $cincin[] = self::koordinat($c->textContent);
            }
            foreach ($xp->query('./*[local-name()="innerBoundaryIs"]//*[local-name()="coordinates"]', $poly) as $c) {
                $cincin[] = self::koordinat($c->textContent);
            }
            $cincin = array_values(array_filter($cincin, fn ($r) => count($r) >= 3));
            if ($cincin) $g[] = ['type' => 'Polygon', 'coordinates' => $cincin];
        }
        foreach ($xp->query('.//*[local-name()="LineString"]/*[local-name()="coordinates"]', $pm) as $c) {
            $t = self::koordinat($c->textContent);
            if (count($t) >= 2) $g[] = ['type' => 'LineString', 'coordinates' => $t];
        }
        foreach ($xp->query('.//*[local-name()="Point"]/*[local-name()="coordinates"]', $pm) as $c) {
            $t = self::koordinat($c->textContent);
            if ($t) $g[] = ['type' => 'Point', 'coordinates' => $t[0]];
        }

        return $g;
    }

    /** "lng,lat[,alt] lng,lat …" → [[lng, lat], …] */
    private static function koordinat(string $teks): array
    {
        $titik = [];
        foreach (preg_split('/\s+/', trim($teks)) ?: [] as $p) {
            $b = explode(',', $p);
            if (count($b) >= 2 && is_numeric($b[0]) && is_numeric($b[1])) $titik[] = [(float) $b[0], (float) $b[1]];
        }

        return $titik;
    }

    /** @return list<array{props: array<string, mixed>, geometry: array<string, mixed>}> */
    public static function dariGeojson(array $gj): array
    {
        $keluar = [];
        $dorong = function (array $f) use (&$keluar) {
            $g = $f['geometry'] ?? null;
            if (!is_array($g) || !isset($g['type'])) return;
            $props = is_array($f['properties'] ?? null) ? $f['properties'] : [];

            foreach (self::pecah($g) as $tunggal) {
                $keluar[] = ['props' => $props, 'geometry' => $tunggal];
                if (count($keluar) > self::BATAS_FITUR) throw new RuntimeException('Berkas memuat lebih dari '.self::BATAS_FITUR.' fitur.');
            }
        };

        $jenis = $gj['type'] ?? null;
        if ($jenis === 'FeatureCollection') foreach ((array) ($gj['features'] ?? []) as $f) { if (is_array($f)) $dorong($f); }
        elseif ($jenis === 'Feature') $dorong($gj);
        elseif ($jenis && (isset($gj['coordinates']) || isset($gj['geometries']))) $dorong(['geometry' => $gj, 'properties' => []]);
        else throw new RuntimeException('Bukan GeoJSON: tidak ada FeatureCollection, Feature, atau geometri.');

        return $keluar;
    }

    /** @return list<array<string, mixed>> geometri tunggal */
    private static function pecah(array $g): array
    {
        return match ($g['type']) {
            'GeometryCollection' => array_merge(...array_map(fn ($x) => is_array($x) ? self::pecah($x) : [], (array) ($g['geometries'] ?? [])) ?: [[]]),
            'MultiPolygon' => array_map(fn ($c) => ['type' => 'Polygon', 'coordinates' => $c], (array) $g['coordinates']),
            'MultiLineString' => array_map(fn ($c) => ['type' => 'LineString', 'coordinates' => $c], (array) $g['coordinates']),
            'MultiPoint' => array_map(fn ($c) => ['type' => 'Point', 'coordinates' => $c], (array) $g['coordinates']),
            'Polygon', 'LineString', 'Point' => [$g],
            default => [],
        };
    }

    /** Luas cincin poligon dalam hektar (shoelace pada bidang ekuirektangular, seperti polygonAreaHa_). */
    public static function luasHa(array $cincin): float
    {
        $n = count($cincin);
        if ($n < 3) return 0.0;

        $R = 6378137.0;
        $lat = array_sum(array_map(fn ($p) => (float) $p[1], $cincin)) / $n * M_PI / 180;
        $kx = cos($lat);
        $jumlah = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $a = $cincin[$i];
            $b = $cincin[($i + 1) % $n];
            $x1 = $a[0] * M_PI / 180 * $R * $kx; $y1 = $a[1] * M_PI / 180 * $R;
            $x2 = $b[0] * M_PI / 180 * $R * $kx; $y2 = $b[1] * M_PI / 180 * $R;
            $jumlah += $x1 * $y2 - $x2 * $y1;
        }

        return abs($jumlah) / 2 / 10000;
    }

    /**
     * Properti fitur → kolom Peta Spasial (pick() di Code.gs).
     *
     * @return array<string, mixed>
     */
    public static function keBaris(array $fitur, string $sumber, int $urutan): array
    {
        $p = $fitur['props'];
        $g = $fitur['geometry'];
        $ambil = function (array $kunci) use ($p) {
            foreach ($kunci as $k) {
                foreach ($p as $pk => $v) {
                    if (mb_strtolower((string) $pk) === $k && $v !== null && trim((string) $v) !== '') return trim((string) $v);
                }
            }

            return null;
        };

        $luas = Hitung::angkaAtauNull($ambil(['luas_ha', 'luas', 'luasha', 'area_ha', 'hectares']));
        if (!$luas && $g['type'] === 'Polygon' && isset($g['coordinates'][0])) {
            $luas = round(self::luasHa($g['coordinates'][0]), 6);
        }

        $kategori = $ambil(['kategori', 'category', 'type'])
            ?? match ($g['type']) { 'Polygon' => 'Blok', 'Point' => 'Titik Tanam', default => 'Batas Area' };
        $tahun = Hitung::angkaAtauNull($ambil(['year', 'tahun']));

        return [
            'nama_fitur' => mb_substr($ambil(['name', 'nama', 'nama_fitur', 'blok', 'block', 'label']) ?? 'Fitur '.$urutan, 0, 150),
            'kategori' => mb_substr($kategori, 0, 30),
            'petak' => self::potong($ambil(['petak', 'petak_', 'plot']), 60),
            'week' => self::potong($ambil(['week', 'minggu']), 30),
            'month' => self::potong($ambil(['month', 'bulan']), 20),
            'year' => $tahun && $tahun >= 1900 && $tahun <= 2100 ? (int) $tahun : null,
            'luas_ha' => $luas,
            'geojson' => json_encode($g, JSON_UNESCAPED_UNICODE),
            'sumber_file' => mb_substr($sumber, 0, 190),
        ];
    }

    private static function potong(?string $v, int $n): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $n);
    }

    /**
     * Geometri yang siap digambar: koordinat dibulatkan 6 desimal (±11 cm)
     * dan — bila $maksTitik diberikan — cincin panjang dijarangkan supaya
     * dasbor tidak mengunduh ratusan kilobyte koordinat untuk peta kecil.
     */
    public static function untukPeta(?string $geojson, ?int $maksTitik = null): ?array
    {
        $g = $geojson ? json_decode($geojson, true) : null;
        if (!is_array($g) || !isset($g['type'], $g['coordinates'])) return null;

        $bulat = fn (array $p) => [round((float) $p[0], 6), round((float) $p[1], 6)];
        $jarang = function (array $titik) use ($maksTitik, $bulat) {
            $titik = array_map($bulat, $titik);
            $n = count($titik);
            if (!$maksTitik || $n <= $maksTitik) return $titik;
            $langkah = (int) ceil($n / $maksTitik);
            $keluar = [];
            for ($i = 0; $i < $n; $i += $langkah) $keluar[] = $titik[$i];
            $keluar[] = $titik[$n - 1];

            return $keluar;
        };

        return match ($g['type']) {
            'Polygon' => ['type' => 'Polygon', 'coordinates' => array_map($jarang, $g['coordinates'])],
            'LineString' => ['type' => 'LineString', 'coordinates' => $jarang($g['coordinates'])],
            'Point' => ['type' => 'Point', 'coordinates' => $bulat($g['coordinates'])],
            default => null,
        };
    }
}
