<?php

namespace App\Support;

/**
 * Susunan kegiatan pelaksanaan audit SMKP yang terstruktur — port DBEST
 * (SusunanKegiatan, formulir Rencana Audit IV.067a).
 *
 * Satu sesi audit pada kolom "Fungsi / Area / Departemen / Kegiatan yang
 * akan diaudit" ditulis seperti contoh formulir:
 *
 *   Peninjauan Dokumen, Rekaman, Wawancara :      <- metode
 *   Kantor PT …                                    <- area
 *   Kepala Teknik Tambang                          <- fungsi / jabatan / departemen
 *   1. Audit Elemen I Kebijakan
 *      Sub Elemen 1.1, 1.5
 *   2. Audit Elemen III Organisasi dan Personel
 *      Sub Elemen 3.3, 3.7
 *      Sub Sub Elemen 3.2.1, 3.12.1
 *
 * Kriteria disimpan sebagai kode Lampiran II ("I.1, III.2.1", boleh ditulis
 * "1.1, 3.2.1"); daftar bernomornya disusun di sini sehingga layar, lembar
 * cetak, dan unggahan Excel selalu seragam. Baris lama (hanya tanggal,
 * waktu, kegiatan, auditi, auditor) tetap terbaca apa adanya.
 */
final class SmkpSusunan
{
    /** Kolom satu baris; 'auditi' = fungsi / jabatan / departemen yang diaudit. */
    public const KOLOM = ['tanggal', 'waktu', 'area', 'metode', 'auditi', 'kriteria', 'kegiatan', 'auditor'];

    public const MAKS = ['tanggal' => 10, 'waktu' => 50, 'area' => 300, 'metode' => 300, 'auditi' => 500,
                         'kriteria' => 1500, 'kegiatan' => 3000, 'auditor' => 500];

    public const SARAN_METODE = [
        'Peninjauan Dokumen, Rekaman, Wawancara',
        'Peninjauan Dokumen, Rekaman',
        'Wawancara',
        'Observasi Lapangan',
        'Observasi Lapangan, Wawancara',
        'Inspeksi Lapangan, Pengukuran',
    ];

    public const SARAN_FUNGSI = [
        'Kepala Teknik Tambang (KTT)',
        'Penanggung Jawab Operasional (PJO)',
        'Komite Keselamatan Pertambangan',
        'Departemen OHSE',
        'Departemen Mining Operation',
        'Departemen Engineering',
        'Departemen Human Resources',
        'Departemen Supply Chain Management',
        'Departemen Finance & Accounting',
    ];

    public const SARAN_AREA = ['Kantor', 'Area Penambangan (PIT)', 'Area Workshop', 'Port', 'Area CPP / CHF', 'Fuel Station', 'Area Reklamasi & Nursery'];

    private const ANGKA = ['I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5, 'VI' => 6, 'VII' => 7];

    /**
     * Kode kriteria dari teks bebas, sah dan unik, urut Lampiran II:
     * "I.1, 1.5; III.2.1 IV" → ['I.1', 'I.5', 'III.2.1', 'IV']. Kode angka
     * (1.1, 3.2.1) diubah ke Romawi; kode elemen saja (IV) = seluruh elemen.
     *
     * @return list<string>
     */
    public static function kode(?string $teks): array
    {
        $urutan = self::urutan();
        if (!preg_match_all('/\b(VII|VI|IV|V|III|II|I|[1-7])(?:\s*\.\s*(\d{1,2}))?(?:\s*\.\s*(\d{1,2}))?\b/u',
            mb_strtoupper((string) $teks), $m, PREG_SET_ORDER)) {
            return [];
        }
        $romawi = array_flip(self::ANGKA);
        $out = [];
        foreach ($m as $x) {
            $el = ctype_digit($x[1]) ? ($romawi[(int) $x[1]] ?? null) : $x[1];
            // Angka tanpa titik ("1") bukan kode elemen — bisa bagian teks lain.
            if ($el === null || (ctype_digit($x[1]) && (($x[2] ?? '') === ''))) continue;
            $k = $el.(($x[2] ?? '') !== '' ? '.'.(int) $x[2] : '').(($x[3] ?? '') !== '' ? '.'.(int) $x[3] : '');
            if (isset($urutan[$k])) $out[$k] = $urutan[$k];
        }
        asort($out);

        return array_keys($out);
    }

    /** Kode elemen, sub-elemen, dan sub-sub elemen → urutan Lampiran II. */
    private static function urutan(): array
    {
        static $u = null;
        if ($u === null) {
            $u = [];
            foreach (Smkp::elemen() as $e) {
                $u[$e['kode']] = count($u);
                foreach ($e['sub'] as $s) {
                    $u[$s['kode']] = count($u);
                    foreach ((array) ($s['subsub'] ?? []) as $x) $u[$x['kode']] = count($u);
                }
            }
        }

        return $u;
    }

    /** "III.2.1" → "3.2.1" (penomoran formulir). */
    public static function angka(string $kode): string
    {
        $p = explode('.', $kode);
        $p[0] = (string) (self::ANGKA[$p[0]] ?? $p[0]);

        return implode('.', $p);
    }

    /**
     * Daftar elemen yang diaudit dari kode kriteria:
     * [['kode' => 'III', 'judul' => 'Audit Elemen III Organisasi dan Personel',
     *   'baris' => ['Sub Elemen 3.3, 3.7', 'Sub Sub Elemen 3.2.1']], ..]
     *
     * @param  string|list<string>  $kriteria
     */
    public static function elemen(string|array|null $kriteria): array
    {
        $kode = is_array($kriteria) ? $kriteria : self::kode($kriteria);
        if (!$kode) return [];

        $per = [];
        foreach ($kode as $k) {
            $p = explode('.', $k);
            $per[$p[0]][count($p)][] = $k;
        }
        $out = [];
        foreach (Smkp::elemen() as $e) {
            if (!isset($per[$e['kode']])) continue;
            $g = $per[$e['kode']];
            $baris = [];
            if (!empty($g[2])) $baris[] = 'Sub Elemen '.implode(', ', array_map([self::class, 'angka'], $g[2]));
            if (!empty($g[3])) $baris[] = 'Sub Sub Elemen '.implode(', ', array_map([self::class, 'angka'], $g[3]));
            if (!$baris) $baris[] = 'Seluruh sub elemen';
            $out[] = ['kode' => $e['kode'], 'judul' => 'Audit Elemen '.$e['kode'].' '.$e['nama'], 'baris' => $baris];
        }

        return $out;
    }

    /** Isian formulir → baris tersimpan: rapi, kode kriteria baku, baris kosong dibuang. */
    public static function bersih(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $b = [];
            foreach (self::KOLOM as $k) {
                $v = str_replace(["\r\n", "\r"], "\n", trim((string) ($r[$k] ?? '')));
                $b[$k] = mb_substr($v, 0, self::MAKS[$k]);
            }
            $b['kriteria'] = implode(', ', self::kode($b['kriteria']));
            $isi = $b;
            unset($isi['tanggal']);
            if (implode('', $isi) === '') continue;
            $out[] = $b;
        }

        return $out;
    }

    /** Isi sel "Fungsi / Area / Departemen / Kegiatan" satu baris untuk ditampilkan. */
    public static function isi(array $b): array
    {
        $t = fn (string $k) => trim((string) ($b[$k] ?? ''));
        $metode = $t('metode');

        return [
            'metode'   => $metode === '' ? '' : rtrim($metode, ' :').' :',
            'area'     => $t('area'),
            'fungsi'   => $t('auditi'),
            'elemen'   => self::elemen($t('kriteria')),
            'kegiatan' => $t('kegiatan'),
        ];
    }

    /** Isi sel sebagai teks polos (taksiran tinggi baris dan unduhan). */
    public static function teks(array $b): string
    {
        $i = self::isi($b);
        $baris = array_values(array_filter([$i['metode'], $i['area'], $i['fungsi']], 'strlen'));
        foreach ($i['elemen'] as $n => $e) {
            $baris[] = ($n + 1).'. '.$e['judul'];
            foreach ($e['baris'] as $x) $baris[] = '   '.$x;
        }
        if ($i['kegiatan'] !== '') $baris[] = $i['kegiatan'];

        return implode("\n", $baris);
    }
}
