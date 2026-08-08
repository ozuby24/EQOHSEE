<?php

namespace App\Support;

/**
 * SMKP Minerba — mesin hitung audit 7 elemen.
 *
 * Sumber kebenaran: resources/data/smkp/elemen.json
 *
 * Rumus:
 *   nilai kriteria  = 1 (Sesuai) · 0,5 (Minor) · 0 (Mayor) · dikecualikan (Tidak Berlaku)
 *   capaian elemen  = Σ nilai kriteria berlaku / jumlah kriteria berlaku
 *   skor elemen     = capaian elemen × bobot elemen
 *   skor total      = Σ skor elemen                            · maks 100
 *
 * Kriteria "Tidak Berlaku" dikeluarkan dari pembagi sehingga tidak menghukum
 * perusahaan atas kriteria yang memang tidak relevan dengan operasinya.
 * Kriteria yang BELUM dinilai dihitung sebagai nol — sama seperti mesin PTPKKP —
 * agar skor tidak terlihat tinggi palsu saat audit baru berjalan sebagian.
 *
 * Bentuk $hasil:
 *   $hasil[kodeKriteria] = ['n' => 'sesuai'|'minor'|'mayor'|'na', 'ket' => string, 'bukti' => string]
 */
class Smkp
{
    private static ?array $ref = null;

    /* ================= referensi ================= */

    public static function ref(): array
    {
        return self::$ref ??= json_decode(
            file_get_contents(resource_path('data/smkp/elemen.json')), true
        );
    }

    public static function meta(): array     { return self::ref()['meta'] ?? []; }
    public static function elemen(): array   { return self::ref()['elemen'] ?? []; }
    public static function penilaian(): array { return self::ref()['penilaian'] ?? []; }
    public static function predikat(): array { return self::ref()['predikat'] ?? []; }

    /** Nilai numerik sebuah kode penilaian; null berarti dikecualikan. */
    public static function nilaiDari(?string $kode): ?float
    {
        foreach (self::penilaian() as $p) {
            if ($p['kode'] === $kode) return $p['nilai'] === null ? null : (float) $p['nilai'];
        }
        return null;
    }

    /** Apakah kode penilaian mengecualikan kriteria dari pembagi. */
    public static function dikecualikan(?string $kode): bool
    {
        return $kode === 'na';
    }

    /** Label + warna sebuah kode penilaian. */
    public static function labelPenilaian(?string $kode): array
    {
        foreach (self::penilaian() as $p) {
            if ($p['kode'] === $kode) return $p;
        }
        return ['kode' => null, 'label' => 'Belum dinilai', 'nilai' => 0, 'warna' => '#D6DAE0'];
    }

    /** Seluruh kode kriteria, berurutan. */
    public static function kodeKriteria(): array
    {
        $out = [];
        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                foreach ($s['kriteria'] as $k) $out[] = $k['kode'];
            }
        }
        return $out;
    }

    public static function jumlahKriteria(): int
    {
        return count(self::kodeKriteria());
    }

    /* ================= perhitungan ================= */

    /**
     * Rekap satu sub-elemen.
     *
     * @return array{berlaku:int,dinilai:int,total:int,nilai:float,capaian:float,rincian:array}
     */
    public static function rekapSub(array $sub, array $hasil): array
    {
        $berlaku = 0; $dinilai = 0; $nilai = 0.0; $rincian = [];

        foreach ($sub['kriteria'] as $k) {
            $kode = $hasil[$k['kode']]['n'] ?? null;
            $rincian[$k['kode']] = $kode;

            if (self::dikecualikan($kode)) continue;

            $berlaku++;
            if ($kode !== null) {
                $dinilai++;
                $nilai += (float) (self::nilaiDari($kode) ?? 0);
            }
        }

        return [
            'berlaku' => $berlaku,
            'dinilai' => $dinilai,
            'total'   => count($sub['kriteria']),
            'nilai'   => $nilai,
            'capaian' => $berlaku > 0 ? $nilai / $berlaku : 0.0,
            'rincian' => $rincian,
        ];
    }

    /**
     * Rekap satu elemen beserta seluruh sub-elemennya.
     *
     * @return array{berlaku:int,dinilai:int,total:int,nilai:float,capaian:float,bobot:int,skor:float,sub:array}
     */
    public static function rekapElemen(array $elemen, array $hasil): array
    {
        $berlaku = 0; $dinilai = 0; $total = 0; $nilai = 0.0; $sub = [];

        foreach ($elemen['sub'] as $s) {
            $r = self::rekapSub($s, $hasil);
            $sub[$s['kode']] = $r;
            $berlaku += $r['berlaku'];
            $dinilai += $r['dinilai'];
            $total   += $r['total'];
            $nilai   += $r['nilai'];
        }

        $capaian = $berlaku > 0 ? $nilai / $berlaku : 0.0;
        $bobot   = (int) ($elemen['bobot'] ?? 0);

        return [
            'berlaku' => $berlaku,
            'dinilai' => $dinilai,
            'total'   => $total,
            'nilai'   => $nilai,
            'capaian' => $capaian,
            'bobot'   => $bobot,
            'skor'    => $capaian * $bobot,
            'sub'     => $sub,
        ];
    }

    /**
     * Rekap seluruh audit.
     *
     * Elemen yang seluruh kriterianya "Tidak Berlaku" dikeluarkan dari pembagi
     * bobot, lalu skor dinormalkan ke skala 100 agar tetap sebanding.
     *
     * @return array{skor:float,capaian:float,berlaku:int,dinilai:int,total:int,
     *               bobotTerpakai:int,predikat:array,elemen:array}
     */
    public static function rekap(array $hasil): array
    {
        $elemen = []; $skor = 0.0; $bobotTerpakai = 0;
        $berlaku = 0; $dinilai = 0; $total = 0;

        foreach (self::elemen() as $e) {
            $r = self::rekapElemen($e, $hasil);
            $elemen[$e['kode']] = $r;

            $berlaku += $r['berlaku'];
            $dinilai += $r['dinilai'];
            $total   += $r['total'];

            if ($r['berlaku'] > 0) {
                $skor          += $r['skor'];
                $bobotTerpakai += $r['bobot'];
            }
        }

        // Normalkan ke skala 100 bila ada elemen yang seluruhnya tidak berlaku.
        $skorAkhir = $bobotTerpakai > 0 ? $skor / $bobotTerpakai * 100 : 0.0;

        return [
            'skor'          => round($skorAkhir, 2),
            'capaian'       => $berlaku > 0 ? $skorAkhir / 100 : 0.0,
            'berlaku'       => $berlaku,
            'dinilai'       => $dinilai,
            'total'         => $total,
            'bobotTerpakai' => $bobotTerpakai,
            'predikat'      => self::predikatDari($skorAkhir),
            'elemen'        => $elemen,
        ];
    }

    /** Predikat untuk sebuah skor 0..100. */
    public static function predikatDari(float $skor): array
    {
        foreach (self::predikat() as $p) {
            if ($skor >= (float) $p['min']) return $p;
        }
        return ['min' => 0, 'label' => 'Buruk', 'warna' => '#E5484D'];
    }

    /**
     * Daftar temuan (ketidaksesuaian) dari hasil audit, siap dijadikan CAR.
     *
     * @return array<int,array{kode:string,uraian:string,elemen:string,sub:string,jenis:string,ket:string}>
     */
    public static function temuan(array $hasil): array
    {
        $out = [];
        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                foreach ($s['kriteria'] as $k) {
                    $n = $hasil[$k['kode']]['n'] ?? null;
                    if ($n !== 'minor' && $n !== 'mayor') continue;

                    $out[] = [
                        'kode'    => $k['kode'],
                        'uraian'  => $k['uraian'],
                        'elemen'  => $e['kode'] . '. ' . $e['nama'],
                        'sub'     => $s['kode'] . ' ' . $s['nama'],
                        'jenis'   => $n,
                        'label'   => self::labelPenilaian($n)['label'],
                        'ket'     => (string) ($hasil[$k['kode']]['ket'] ?? ''),
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * Ekspresi ORDER BY yang mengurutkan jenis temuan dari terberat.
     *
     * MySQL punya FIELD(), tetapi aplikasi ini juga berjalan di SQLite dan
     * PostgreSQL — CASE WHEN adalah SQL baku sehingga berlaku di semuanya.
     */
    public static function urutJenisSql(string $kolom = 'jenis'): string
    {
        return "CASE {$kolom} WHEN 'mayor' THEN 1 WHEN 'minor' THEN 2 ELSE 3 END";
    }

    /** Hitung temuan per jenis: ['mayor' => n, 'minor' => n]. */
    public static function hitungTemuan(array $hasil): array
    {
        $n = ['mayor' => 0, 'minor' => 0];
        foreach ($hasil as $h) {
            $k = $h['n'] ?? null;
            if (isset($n[$k])) $n[$k]++;
        }
        return $n;
    }
}
