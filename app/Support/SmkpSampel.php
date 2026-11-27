<?php

namespace App\Support;

/**
 * Kalkulator jumlah sampel Rencana Audit (port DBEST #23, MetodeSampel).
 *
 * Hasil kalkulator ditulis di kotak sampel sebagai satu baris baku
 *   "Jumlah sampel: 55 dari populasi 120 manpower (Slovin, e 10%)"
 * sehingga ikut tercetak dan ikut unduhan Word, dan dihitung ulang setiap
 * kali Rencana disimpan (normalSampel): angka yang tidak sesuai dengan
 * caranya dibetulkan, kecuali yang "ditetapkan auditor".
 *
 * Pasangan layarnya resources/js/smkp/lib/jumlahSampel.ts — rumusnya harus
 * sama; SmkpSampelTest membandingkan keduanya lewat node.
 */
class SmkpSampel
{
    /** Cara menentukan jumlah sampel (n) dari populasi (N). */
    public const CARA_HITUNG = [
        'slovin'      => ['label' => 'Rumus Slovin', 'ket' => 'n = N ÷ (1 + N × e²), e = batas kesalahan (bawaan 10%). Untuk populasi besar yang seragam, mis. manpower atau pekerja.'],
        'akar'        => ['label' => 'Best practice (√N)', 'ket' => 'n = √N dibulatkan ke atas (pola sampling IAF MD 1). Untuk populasi seragam: unit, kendaraan, alat, atau dokumen sejenis.'],
        'triangulasi' => ['label' => 'Triangulasi', 'ket' => 'n = 3: satu bukti dari setiap sumber — dokumen/rekaman, wawancara, dan observasi — yang saling menguatkan.'],
        'satu'        => ['label' => 'One sample one population', 'ket' => 'Satu sampel dari setiap populasi: N = jumlah populasi/kelompok (departemen, shift, area, jenis unit), n = N.'],
        'sensus'      => ['label' => 'Sensus (total sampling)', 'ket' => 'n = N: seluruh populasi diperiksa. Untuk populasi kecil atau berisiko tinggi.'],
        'persen'      => ['label' => 'Persentase', 'ket' => 'n = p% × N dibulatkan ke atas (bawaan 10%).'],
        'manual'      => ['label' => 'Ditetapkan auditor', 'ket' => 'n diisi auditor sesuai pertimbangan profesional (tidak melebihi N).'],
    ];

    /** Saran objek populasi pada kalkulator. */
    public const OBJEK_POPULASI = ['manpower', 'pekerja', 'pengawas', 'unit', 'kendaraan', 'alat', 'dokumen', 'rekaman', 'prosedur', 'izin kerja', 'area', 'lokasi kerja', 'departemen', 'shift', 'kontraktor'];

    /** "Jumlah sampel: n dari populasi N objek (cara)" — kurung cara boleh tidak ada. */
    public const POLA_JUMLAH = '/^jumlah\s+sampel\s*:?\s*(\d{1,7})\s*(?:dari|\/)\s*(?:populasi\s*)?(\d{1,7})\s*([^()]{0,60}?)\s*(?:\(([^()]{0,80})\))?\s*$/iu';

    /** Parameter cara yang sah: e (%) Slovin 1–30, p (%) persentase 1–100, n ditetapkan auditor; selain itu null. */
    public static function paramHitung(string $cara, mixed $param): float|int|null
    {
        $x = is_numeric($param) ? (float) $param
            : (is_string($param) && is_numeric(str_replace(',', '.', $param)) ? (float) str_replace(',', '.', $param) : null);

        return match ($cara) {
            'slovin' => $x === null ? 10.0 : max(1.0, min(30.0, round($x, 1))),
            'persen' => $x === null ? 10.0 : max(1.0, min(100.0, round($x, 1))),
            'manual' => $x === null ? null : (int) round($x),
            default  => null,
        };
    }

    /**
     * Jumlah sampel n (1..N) untuk populasi N menurut cara; null bila
     * populasi atau cara tidak sah. Dihitung dengan bilangan bulat supaya
     * pembulatan ke atas tidak meleset karena pecahan biner (120 manpower,
     * e 10% = 55).
     */
    public static function hitungSampel(string $cara, mixed $populasi, mixed $param = null): ?int
    {
        $N = (int) $populasi;
        if ($N < 1 || $N > 9999999 || !isset(self::CARA_HITUNG[$cara])) return null;

        $p = self::paramHitung($cara, $param);
        switch ($cara) {
            case 'slovin':
                $e10  = (int) round($p * 10);              // e dalam permil
                $bagi = 1000000 + $N * $e10 * $e10;
                $n    = intdiv($N * 1000000 + $bagi - 1, $bagi);
                break;
            case 'akar':
                $n = (int) ceil(sqrt($N));
                while ($n * $n < $N) $n++;
                while ($n > 1 && ($n - 1) * ($n - 1) >= $N) $n--;
                break;
            case 'triangulasi':
                $n = 3;
                break;
            case 'persen':
                $n = intdiv($N * (int) round($p * 10) + 999, 1000);
                break;
            case 'manual':
                if ($p === null) return null;
                $n = $p;
                break;
            default: // satu, sensus
                $n = $N;
        }

        return max(1, min((int) $n, $N));
    }

    /** "10" / "7,5" untuk persen di label. */
    private static function persen(float $x): string
    {
        return rtrim(rtrim(number_format($x, 1, ',', ''), '0'), ',');
    }

    /** Label cara di dalam kurung baris jumlah: "Slovin, e 10%". */
    public static function labelHitung(string $cara, mixed $param = null): string
    {
        $p = self::paramHitung($cara, $param);

        return match ($cara) {
            'slovin'      => 'Slovin, e '.self::persen((float) $p).'%',
            'akar'        => 'best practice √N',
            'triangulasi' => 'triangulasi 3 sumber',
            'satu'        => 'one sample one population',
            'sensus'      => 'sensus',
            'persen'      => 'persentase '.self::persen((float) $p).'%',
            default       => 'ditetapkan auditor',
        };
    }

    /** Objek populasi aman untuk baris jumlah: tanpa kurung, satu baris, maks 60 huruf. */
    public static function objekPopulasi(mixed $objek): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', str_replace(['(', ')', '[', ']'], ' ', (string) $objek)));

        return mb_substr($t, 0, 60);
    }

    /** Baris baku hasil kalkulator; null bila populasi/cara tidak sah. */
    public static function barisJumlah(string $cara, mixed $populasi, mixed $objek = '', mixed $param = null): ?string
    {
        $n = self::hitungSampel($cara, $populasi, $param);
        if ($n === null) return null;

        $obj = self::objekPopulasi($objek);

        return 'Jumlah sampel: '.$n.' dari populasi '.(int) $populasi.($obj !== '' ? ' '.$obj : '')
            .' ('.self::labelHitung($cara, $param).')';
    }

    /**
     * Baca baris jumlah sampel.
     *
     * @return array{n:int,populasi:int,objek:string,cara:string,param:mixed}|null null bila bukan baris jumlah
     */
    public static function bacaJumlah(mixed $baris): ?array
    {
        $l = trim((string) preg_replace('/[ \t\x{00A0}]+/u', ' ', (string) $baris));
        if (!preg_match(self::POLA_JUMLAH, $l, $m)) return null;

        $teks  = mb_strtolower(trim((string) ($m[4] ?? '')));
        $angka = preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $teks, $x) ? str_replace(',', '.', $x[1]) : null;
        $cara  = 'manual';
        $param = (int) $m[1];

        if (str_contains($teks, 'slovin')) {
            $cara = 'slovin'; $param = $angka;
        } elseif (preg_match('/√|akar|best\s*practice|iaf/u', $teks)) {
            $cara = 'akar';
        } elseif (str_contains($teks, 'triangulasi')) {
            $cara = 'triangulasi';
        } elseif (preg_match('/one\s*sample|satu\s*sampel/u', $teks)) {
            $cara = 'satu';
        } elseif (preg_match('/sensus|total\s*sampling|seluruh/u', $teks)) {
            $cara = 'sensus';
        } elseif ($angka !== null || str_contains($teks, 'persen')) {
            $cara = 'persen'; $param = $angka;
        }

        return ['n' => (int) $m[1], 'populasi' => (int) $m[2], 'objek' => self::objekPopulasi($m[3] ?? ''), 'cara' => $cara, 'param' => $param];
    }

    /** Baris jumlah sampel yang dihitung ulang & dibakukan; null bila bukan baris jumlah. */
    public static function normalJumlah(mixed $baris): ?string
    {
        $j = self::bacaJumlah($baris);

        return $j ? self::barisJumlah($j['cara'], $j['populasi'], $j['objek'], $j['param']) : null;
    }

    /** Seluruh isi kotak sampel dengan setiap baris jumlah dihitung ulang; baris lain tidak disentuh. */
    public static function normalSampel(string $teks): string
    {
        if (stripos($teks, 'sampel') === false) return $teks;

        $baris = preg_split('/\r\n|\r|\n/', $teks);
        foreach ($baris as $i => $b) {
            // Penanda daftar di depan (mis. "- ") dipertahankan.
            preg_match('/^\s*(?:[-•*]\s+)?/u', $b, $awal);
            $awal = $awal[0] ?? '';
            $n = self::normalJumlah(mb_substr($b, mb_strlen($awal)));
            if ($n !== null) $baris[$i] = $awal.$n;
        }

        return implode("\n", $baris);
    }
}
