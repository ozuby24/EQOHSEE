<?php

namespace App\Support;

/**
 * Mengambil warna khas sebuah logo.
 *
 * Dipakai agar latar aplikasi menyesuaikan identitas perusahaan yang
 * diunggah, bukan memakai satu warna tetap untuk semua penyewa.
 *
 * Yang dicari bukan warna paling sering — logo umumnya berlatar putih atau
 * transparan, sehingga warna terbanyak justru latar yang harus dibuang.
 * Yang dicari warna paling "bersuara": cukup jenuh dan tidak terlalu gelap
 * atau terang, sebab warna semacam itulah yang orang sebut ketika ditanya
 * "logonya warna apa".
 */
final class WarnaLogo
{
    /** Piksel yang lebih transparan dari ini dianggap latar. */
    private const ALFA_MIN = 96;

    /**
     * Warna khas sebuah berkas gambar, atau null bila tidak dapat dibaca.
     *
     * @return array{terang:string,gelap:string}|null
     */
    public static function dari(string $berkas): ?array
    {
        if (!is_readable($berkas) || !function_exists('imagecreatefromstring')) return null;

        $isi = @file_get_contents($berkas);
        if ($isi === false) return null;

        $img = @imagecreatefromstring($isi);
        if ($img === false) return null;

        $lebar  = imagesx($img);
        $tinggi = imagesy($img);
        if ($lebar < 1 || $tinggi < 1) { imagedestroy($img); return null; }

        // Dicacah pada kisi rapat, bukan setiap piksel: logo 2000px punya
        // empat juta piksel dan hasilnya tidak berbeda berarti.
        $langkah = max(1, (int) floor(min($lebar, $tinggi) / 72));

        $ember = [];
        for ($y = 0; $y < $tinggi; $y += $langkah) {
            for ($x = 0; $x < $lebar; $x += $langkah) {
                $w = imagecolorat($img, $x, $y);

                // Alfa GD: 0 = pekat, 127 = tembus pandang penuh.
                $a = 127 - (($w >> 24) & 0x7F);
                if ($a * 2 < self::ALFA_MIN) continue;

                $r = ($w >> 16) & 0xFF;
                $g = ($w >> 8) & 0xFF;
                $b = $w & 0xFF;

                [$h, $s, $l] = self::keHsl($r, $g, $b);

                // Buang yang praktis putih, hitam, atau abu-abu: warna itu
                // ada di hampir semua logo dan tidak membedakan apa pun.
                if ($l > 0.93 || $l < 0.07 || $s < 0.18) continue;

                // Dikelompokkan per 12 derajat rona supaya gradasi halus
                // dalam satu warna terhitung sebagai satu suara.
                $kunci = (int) floor($h / 12);
                $ember[$kunci] ??= ['bobot' => 0.0, 'r' => 0, 'g' => 0, 'b' => 0, 'n' => 0];

                // Yang lebih jenuh dan sedang terangnya diberi suara lebih
                // besar — itu yang mata baca sebagai "warna logonya".
                $bobot = $s * (1 - abs($l - 0.5) * 1.4);

                $ember[$kunci]['bobot'] += $bobot;
                $ember[$kunci]['r'] += $r;
                $ember[$kunci]['g'] += $g;
                $ember[$kunci]['b'] += $b;
                $ember[$kunci]['n']++;
            }
        }

        imagedestroy($img);

        if (!$ember) return null;

        uasort($ember, fn ($a, $b) => $b['bobot'] <=> $a['bobot']);
        $menang = reset($ember);

        $r = (int) round($menang['r'] / $menang['n']);
        $g = (int) round($menang['g'] / $menang['n']);
        $b = (int) round($menang['b'] / $menang['n']);

        [$h, $s, $l] = self::keHsl($r, $g, $b);

        // Dua turunan dengan rona yang sama: satu untuk aksen di tema
        // terang, satu untuk dasar bilah samping yang gelap. Terangnya
        // dipatok supaya logo yang sangat pucat atau sangat pekat tetap
        // menghasilkan pasangan warna yang terbaca.
        return [
            'terang' => self::keHex($h, max(0.35, min(0.72, $s)), min(0.46, max(0.30, $l))),
            'gelap'  => self::keHex($h, max(0.30, min(0.60, $s)), 0.14),
        ];
    }

    /** @return array{0:float,1:float,2:float} rona 0–360, jenuh 0–1, terang 0–1 */
    public static function keHsl(int $r, int $g, int $b): array
    {
        $r /= 255; $g /= 255; $b /= 255;

        $maks = max($r, $g, $b);
        $min  = min($r, $g, $b);
        $l    = ($maks + $min) / 2;
        $d    = $maks - $min;

        if ($d == 0) return [0.0, 0.0, $l];

        $s = $l > 0.5 ? $d / (2 - $maks - $min) : $d / ($maks + $min);

        $h = match (true) {
            $maks === $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $maks === $g => (($b - $r) / $d) + 2,
            default      => (($r - $g) / $d) + 4,
        };

        return [$h * 60, $s, $l];
    }

    public static function keHex(float $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60  => [$c, $x, 0.0],
            $h < 120 => [$x, $c, 0.0],
            $h < 180 => [0.0, $c, $x],
            $h < 240 => [0.0, $x, $c],
            $h < 300 => [$x, 0.0, $c],
            default  => [$c, 0.0, $x],
        };

        return sprintf('#%02X%02X%02X',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255));
    }
}
