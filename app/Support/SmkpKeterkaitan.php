<?php

namespace App\Support;

/**
 * Keterkaitan antar elemen SMKP sebagai acuan penilaian (port DBEST #23).
 *
 * Data: resources/data/smkp/keterkaitan.json — keterkaitan utama antar
 * elemen dan sub elemen kriteria Lampiran II Kepdirjen Minerba
 * 185.K/37.04/DJB/2019. Di halaman Penilaian setiap sub elemen memajang
 * keterkaitannya beserta status penilaian sisi terkait (kategori, nilai,
 * capaian), dan peringatan bila hasilnya tidak sejalan — sub elemen ini
 * Kesesuaian padahal sisi yang menjadi dasarnya Ketidaksesuaian Mayor, atau
 * sebaliknya — supaya bukti objektif kedua sisi diperiksa ulang. Halaman
 * Kriteria & Acuan memuat peta lengkapnya.
 */
class SmkpKeterkaitan
{
    private static ?array $data = null;

    /** @return array{meta:array,kelompok:array} */
    public static function data(): array
    {
        if (self::$data === null) {
            $f = resource_path('data/smkp/keterkaitan.json');
            $j = is_readable($f) ? json_decode((string) file_get_contents($f), true) : null;
            self::$data = is_array($j) ? $j : ['meta' => [], 'kelompok' => []];
        }

        return self::$data;
    }

    /** Kode $t bersinggungan dengan $kode: sama, leluhurnya (elemen/sub), atau turunannya (sub-sub). */
    public static function bersinggungan(string $t, string $kode): bool
    {
        return $t !== '' && $kode !== ''
            && ($t === $kode || str_starts_with($kode, $t.'.') || str_starts_with($t, $kode.'.'));
    }

    /**
     * Keterkaitan sebuah sub elemen.
     *
     * @return list<array{kelompok:string,label:string,uraian:string,umum:bool,sisi:list<string>,terkait:list<string>}>
     */
    public static function untuk(string $kode): array
    {
        $out = [];
        foreach ((array) (self::data()['kelompok'] ?? []) as $g) {
            foreach ((array) ($g['tautan'] ?? []) as $t) {
                foreach ([['a', 'b'], ['b', 'a']] as [$ini, $lain]) {
                    $kena = array_values(array_filter(array_map('strval', (array) ($t[$ini] ?? [])),
                        fn ($x) => self::bersinggungan($x, $kode)));
                    if (!$kena) continue;

                    $out[] = [
                        'kelompok' => (string) ($g['judul'] ?? ''),
                        'label'    => (string) ($t['label'] ?? ''),
                        'uraian'   => (string) ($t['uraian'] ?? ''),
                        'umum'     => !empty($t['umum']),
                        'sisi'     => $kena,
                        'terkait'  => array_values(array_map('strval', (array) ($t[$lain] ?? []))),
                    ];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * Status penilaian satu kode — elemen, sub elemen, atau butir/sub-sub.
     *
     * @return array{kode:string,nama:string,jenis:?string,elemen:string,sub:?string,nilai:?float,maks:?int,
     *               capaian:?float,kategori:?string,label:string,warna:string,dinilai:int,berlaku:int}
     */
    public static function status(string $kode, array $rekap, array $hasil): array
    {
        $bag = explode('.', $kode);
        $el  = $bag[0];
        $re  = $rekap['elemen'][$el] ?? null;
        $out = [
            'kode' => $kode, 'nama' => Smkp::namaKriteria()[$kode] ?? '', 'jenis' => null,
            'elemen' => $el, 'sub' => count($bag) >= 2 ? $bag[0].'.'.$bag[1] : null,
            'nilai' => null, 'maks' => null, 'capaian' => null, 'kategori' => null,
            'label' => 'Belum dinilai', 'warna' => '#94A3B8', 'dinilai' => 0, 'berlaku' => 0,
        ];
        if (!$re) return $out;

        if (count($bag) === 1) {
            $mayor = 0; $minor = 0;
            foreach ((array) $re['sub'] as $rs) {
                if ($rs['berlaku'] > 0 && $rs['dinilai'] >= $rs['berlaku']) {
                    $mayor += ($rs['kategori']['kode'] ?? '') === 'mayor' ? 1 : 0;
                    $minor += ($rs['kategori']['kode'] ?? '') === 'minor' ? 1 : 0;
                }
            }
            $out = array_merge($out, [
                'jenis' => 'elemen', 'nilai' => $re['nilai'], 'maks' => $re['maks'], 'capaian' => $re['capaian'],
                'dinilai' => (int) $re['dinilai'], 'berlaku' => (int) $re['berlaku'],
            ]);
            if ($re['dinilai'] > 0) {
                $out['label'] = 'capaian '.number_format($re['capaian'] * 100, 1, ',', '.').'%'
                    .($mayor ? ' · '.$mayor.' sub mayor' : '').($minor ? ' · '.$minor.' sub minor' : '');
                $out['warna'] = $mayor ? '#DC2626' : ($minor ? '#D97706' : '#16A34A');
                $out['kategori'] = $re['dinilai'] >= $re['berlaku'] ? ($mayor ? 'mayor' : ($minor ? 'minor' : 'kesesuaian')) : null;
            }
            return $out;
        }

        $rs = $re['sub'][$bag[0].'.'.$bag[1]] ?? null;
        if (count($bag) === 2 && $rs) {
            $out = array_merge($out, [
                'jenis' => 'sub', 'nilai' => $rs['nilai'], 'maks' => $rs['maks'], 'capaian' => $rs['capaian'],
                'dinilai' => (int) $rs['dinilai'], 'berlaku' => (int) $rs['berlaku'],
            ]);
            if ($rs['berlaku'] === 0) {
                $out['label'] = Smkp::NA;
            } elseif ($rs['dinilai'] >= $rs['berlaku']) {
                $out['kategori'] = $rs['kategori']['kode'];
                $out['label']    = $rs['kategori']['label'];
                $out['warna']    = $rs['kategori']['warna'];
            } elseif ($rs['dinilai'] > 0) {
                $out['label'] = 'dinilai '.$rs['dinilai'].'/'.$rs['berlaku'].' butir';
            }
            return $out;
        }

        // Butir / sub-sub elemen.
        $out['jenis'] = 'butir';
        foreach (Smkp::butir() as $b) {
            if ($b['kode'] !== $kode) continue;
            $v = Smkp::nilaiSetara($hasil, $b);
            $out['maks'] = (int) $b['maks'];
            $out['berlaku'] = $v === Smkp::NA ? 0 : 1;
            if ($v === Smkp::NA) {
                $out['label'] = Smkp::NA;
            } elseif (is_float($v) && $b['maks'] > 0) {
                $k = Smkp::kategoriDari($v / (float) $b['maks']);
                $out = array_merge($out, [
                    'nilai' => $v, 'capaian' => $v / (float) $b['maks'], 'dinilai' => 1,
                    'kategori' => $k['kode'], 'label' => $k['label'], 'warna' => $k['warna'],
                ]);
            }
            break;
        }

        return $out;
    }

    /**
     * Keterkaitan sub elemen beserta status sisi terkait dan peringatan
     * konsistensi. $kategoriIni = kategori sub elemen ini bila sudah tuntas
     * dinilai ('kesesuaian'|'minor'|'mayor'), selain itu null.
     *
     * @return list<array> tiap butir dari untuk() ditambah 'status' dan 'periksa'
     */
    public static function denganStatus(string $kodeSub, array $rekap, array $hasil, ?string $kategoriIni = null): array
    {
        $out = [];
        foreach (self::untuk($kodeSub) as $t) {
            $t['status'] = array_map(fn ($k) => self::status($k, $rekap, $hasil), $t['terkait']);

            // Peringatan hanya untuk keterkaitan khusus (bukan dokumentasi
            // umum) dan sisi terkait sub/butir yang sudah pasti kategorinya.
            $t['periksa'] = [];
            if (!$t['umum'] && $kategoriIni !== null) {
                foreach ($t['status'] as $s) {
                    if ($s['kategori'] === null || $s['jenis'] === 'elemen') continue;
                    if (($kategoriIni === 'kesesuaian' && $s['kategori'] === 'mayor')
                        || ($kategoriIni === 'mayor' && $s['kategori'] === 'kesesuaian')) {
                        $t['periksa'][] = $s['kode'].' ('.$s['label'].')';
                    }
                }
            }
            $out[] = $t;
        }

        return $out;
    }

    /** Kategori sub elemen bila sudah tuntas dinilai, selain itu null. */
    public static function kategoriTuntas(array $rekapSub): ?string
    {
        return $rekapSub['berlaku'] > 0 && $rekapSub['dinilai'] >= $rekapSub['berlaku']
            ? ($rekapSub['kategori']['kode'] ?? null)
            : null;
    }
}
