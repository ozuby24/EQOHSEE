<?php

namespace Eqohsee\SmkpAudit\Support;

use Illuminate\Container\Container;

/**
 * SMKP Minerba — mesin hitung audit 7 elemen.
 *
 * Sumber kebenaran: resources/data/elemen.json pada paket ini, disalin dari Lampiran II
 * Kepdirjen Minerba 185.K/37.04/DJB/2019 (hal. 336-400). Tiap sub-elemen
 * membawa kolom 'ref' berisi halaman acuannya sehingga setiap angka dapat
 * ditelusuri kembali ke sumbernya.
 *
 * Penilaian berbasis POIN, bukan kesesuaian. Auditor mengisi nilai capaian
 * 0..maks pada tiap butir; tiap butir punya nilai maksimum sendiri (2 sampai 4)
 * dan seluruhnya berjumlah 349 poin bila seluruh butir berlaku.
 *
 *   capaian butir   = nilai / maks
 *   capaian elemen  = Σ nilai butir berlaku / Σ maks butir berlaku
 *   nilai elemen    = capaian elemen × bobot elemen        (bobot: 10..35%)
 *   nilai akhir     = Σ nilai elemen ÷ Σ bobot terpakai × 100
 *
 * Butir "N/A" tidak berlaku bagi perusahaan yang diaudit dan dikeluarkan dari
 * pembagi, sehingga tidak menghukum capaian. Butir yang BELUM dinilai dihitung
 * nol agar skor tidak terlihat tinggi palsu saat audit baru berjalan sebagian.
 *
 * Kategori temuan diturunkan DARI nilai, bukan dipilih auditor — sesuai kolom
 * "KATEGORI TEMUAN (Berdasarkan Nilai)" pada formulir kriteria:
 *   capaian < 50%          → Ketidaksesuaian Mayor
 *   capaian 50% s.d. <100% → Ketidaksesuaian Minor
 *   capaian 100%           → Kesesuaian
 *
 * Bentuk $hasil (satu baris per butir yang dinilai, kunci = kode butir):
 *   $hasil['II.2.1'] = ['v' => 3, 'ket' => '...', 'bukti' => '...']
 *   $hasil['IV.3.10'] = ['v' => 'N/A']
 */
class Smkp
{
    /** Nilai yang menandai butir tidak berlaku bagi perusahaan. */
    public const NA = 'N/A';

    private static ?array $ref = null;

    /* ================= referensi ================= */

    public static function ref(): array
    {
        return self::$ref ??= json_decode(file_get_contents(self::berkas()), true);
    }

    /**
     * Berkas acuan yang dipakai.
     *
     * Salinan bawaan paket dipakai kecuali aplikasi induk menunjuk berkasnya
     * sendiri lewat config('smkp.acuan') — itulah cara menyunting kriteria
     * tanpa menyentuh paket. Pembacaan config dijaga `function_exists` supaya
     * mesin hitung ini tetap dapat dipakai di luar Laravel, termasuk oleh uji
     * paket yang tidak membangkitkan aplikasi sama sekali.
     */
    public static function berkas(): string
    {
        $sendiri = self::setelanAcuan();

        if (is_string($sendiri) && $sendiri !== '' && is_file($sendiri)) return $sendiri;

        return dirname(__DIR__, 2).'/resources/data/elemen.json';
    }

    /**
     * config('smkp.acuan'), bila memang ada aplikasi yang menyediakannya.
     *
     * `function_exists('config')` saja tidak cukup: helper Laravel ikut termuat
     * begitu illuminate/support ada di autoload, sehingga fungsinya SELALU ada
     * — dan memanggilnya tanpa aplikasi yang bangkit melempar
     * ReflectionException("Class config does not exist"), bukan mengembalikan
     * null. Yang ditanyakan di sini karena itu wadahnya, bukan fungsinya.
     */
    private static function setelanAcuan(): ?string
    {
        if (!class_exists(Container::class)) return null;

        $wadah = Container::getInstance();

        return $wadah->bound('config') ? $wadah->make('config')->get('smkp.acuan') : null;
    }

    /** Membuang acuan yang sudah terbaca — dipakai uji yang menukar berkasnya. */
    public static function lupakan(): void
    {
        self::$ref = null;
    }

    public static function meta(): array     { return self::ref()['meta'] ?? []; }
    public static function elemen(): array   { return self::ref()['elemen'] ?? []; }
    public static function kategori(): array { return self::ref()['kategori'] ?? []; }
    public static function tingkat(): array  { return self::ref()['tingkat'] ?? []; }

    /** Total nilai maksimum bila seluruh butir berlaku (butir N/A menguranginya). */
    public static function totalNilai(): int
    {
        return (int) (self::meta()['total_nilai'] ?? 0);
    }

    /**
     * Butir yang benar-benar dinilai pada sebuah sub-elemen.
     *
     * Sub-elemen yang punya rincian sub-sub dinilai lewat sub-subnya, bukan
     * langsung — mengisi keduanya akan menghitung ganda. Karena itu nilai
     * maksimum sub-elemen semacam itu dijumlahkan dari rinciannya, tidak
     * disimpan terpisah di berkas acuan.
     *
     * @return array<int,array{kode:string,nama:string,maks:int}>
     */
    public static function butirSub(array $sub): array
    {
        if (!empty($sub['subsub'])) {
            return array_values(array_filter(
                $sub['subsub'],
                fn ($x) => ($x['maks'] ?? null) !== null
            ));
        }

        return ($sub['maks'] ?? null) === null
            ? []
            : [['kode' => $sub['kode'], 'nama' => $sub['nama'], 'maks' => $sub['maks']]];
    }

    /** Nilai maksimum sebuah sub-elemen, dijumlahkan dari butirnya. */
    public static function maksSub(array $sub): int
    {
        return array_sum(array_column(self::butirSub($sub), 'maks'));
    }

    /** Nilai maksimum sebuah elemen, dijumlahkan dari sub-elemennya. */
    public static function maksElemen(array $elemen): int
    {
        return array_sum(array_map([self::class, 'maksSub'], $elemen['sub']));
    }

    /** Seluruh butir yang dapat dinilai, berurutan. */
    public static function butir(): array
    {
        $out = [];
        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                foreach (self::butirSub($s) as $b) {
                    $out[] = $b + ['elemen' => $e['kode'], 'sub' => $s['kode']];
                }
            }
        }
        return $out;
    }

    public static function jumlahButir(): int
    {
        return count(self::butir());
    }

    /* ================= pembacaan nilai ================= */

    /** Nilai mentah sebuah butir: angka, 'N/A', atau null bila belum dinilai. */
    public static function nilaiButir(array $hasil, string $kode)
    {
        $v = $hasil[$kode]['v'] ?? null;
        if ($v === null || $v === '') return null;
        if (is_string($v) && strcasecmp($v, self::NA) === 0) return self::NA;
        return is_numeric($v) ? (float) $v : null;
    }

    public static function dikecualikan(array $hasil, string $kode): bool
    {
        return self::nilaiButir($hasil, $kode) === self::NA;
    }

    /* ================= perhitungan ================= */

    /**
     * Rekap satu sub-elemen.
     *
     * @return array{maks:int,nilai:float,berlaku:int,dinilai:int,total:int,
     *               capaian:float,kategori:array,butir:array}
     */
    public static function rekapSub(array $sub, array $hasil): array
    {
        $maks = 0; $nilai = 0.0; $berlaku = 0; $dinilai = 0; $rinci = [];

        foreach (self::butirSub($sub) as $b) {
            $v = self::nilaiButir($hasil, $b['kode']);
            $rinci[$b['kode']] = $v;

            if ($v === self::NA) continue;          // di luar lingkup perusahaan

            $berlaku++;
            $maks += (int) $b['maks'];
            if ($v !== null) {
                $dinilai++;
                // Nilai di luar rentang tidak boleh mengangkat capaian.
                $nilai += max(0, min((float) $v, (float) $b['maks']));
            }
        }

        $capaian = $maks > 0 ? $nilai / $maks : 0.0;

        return [
            'maks'     => $maks,
            'nilai'    => $nilai,
            'berlaku'  => $berlaku,
            'dinilai'  => $dinilai,
            'total'    => count(self::butirSub($sub)),
            'capaian'  => $capaian,
            'kategori' => $berlaku > 0 ? self::kategoriDari($capaian) : self::kategoriNa(),
            'butir'    => $rinci,
        ];
    }

    /**
     * Rekap satu elemen beserta sub-elemennya.
     *
     * @return array{maks:int,nilai:float,capaian:float,bobot:int,skor:float,
     *               berlaku:int,dinilai:int,total:int,sub:array}
     */
    public static function rekapElemen(array $elemen, array $hasil): array
    {
        $maks = 0; $nilai = 0.0; $berlaku = 0; $dinilai = 0; $total = 0; $sub = [];

        foreach ($elemen['sub'] as $s) {
            $r = self::rekapSub($s, $hasil);
            $sub[$s['kode']] = $r;
            $maks    += $r['maks'];
            $nilai   += $r['nilai'];
            $berlaku += $r['berlaku'];
            $dinilai += $r['dinilai'];
            $total   += $r['total'];
        }

        $capaian = $maks > 0 ? $nilai / $maks : 0.0;
        $bobot   = (int) ($elemen['bobot'] ?? 0);

        return [
            'maks'    => $maks,
            'nilai'   => $nilai,
            'capaian' => $capaian,
            'bobot'   => $bobot,
            'skor'    => $capaian * $bobot,
            'berlaku' => $berlaku,
            'dinilai' => $dinilai,
            'total'   => $total,
            'sub'     => $sub,
        ];
    }

    /**
     * Rekap seluruh audit.
     *
     * Elemen yang seluruh butirnya N/A dikeluarkan dari pembagi bobot, lalu
     * hasilnya dinormalkan ke skala 100 agar tetap sebanding antar perusahaan
     * yang lingkup operasinya berbeda.
     *
     * @return array{nilai:float,maks:int,skor:float,bobotTerpakai:int,
     *               berlaku:int,dinilai:int,total:int,tingkat:array,elemen:array}
     */
    public static function rekap(array $hasil): array
    {
        $elemen = []; $skor = 0.0; $bobotTerpakai = 0;
        $maks = 0; $nilai = 0.0; $berlaku = 0; $dinilai = 0; $total = 0;

        foreach (self::elemen() as $e) {
            $r = self::rekapElemen($e, $hasil);
            $elemen[$e['kode']] = $r;

            $maks    += $r['maks'];
            $nilai   += $r['nilai'];
            $berlaku += $r['berlaku'];
            $dinilai += $r['dinilai'];
            $total   += $r['total'];

            if ($r['berlaku'] > 0) {
                $skor          += $r['skor'];
                $bobotTerpakai += $r['bobot'];
            }
        }

        $akhir = $bobotTerpakai > 0 ? $skor / $bobotTerpakai * 100 : 0.0;

        return [
            'nilai'         => $nilai,
            'maks'          => $maks,
            'skor'          => round($akhir, 2),
            'bobotTerpakai' => $bobotTerpakai,
            'berlaku'       => $berlaku,
            'dinilai'       => $dinilai,
            'total'         => $total,
            'tingkat'       => self::tingkatDari($akhir),
            'elemen'        => $elemen,
        ];
    }

    /* ================= kategori & tingkat ================= */

    /** Kategori temuan dari capaian 0..1 — ambangnya dari berkas acuan. */
    public static function kategoriDari(float $capaian): array
    {
        $persen = $capaian * 100;
        foreach (self::kategori() as $k) {
            if ($persen >= (float) $k['min']) return $k;
        }
        return end(self::kategori()) ?: ['kode' => 'mayor', 'label' => 'Ketidaksesuaian Mayor', 'warna' => '#DC2626'];
    }

    /** Penanda untuk sub-elemen yang seluruhnya di luar lingkup. */
    public static function kategoriNa(): array
    {
        return ['kode' => 'na', 'label' => 'Tidak Berlaku', 'min' => null, 'warna' => '#9AA3AE'];
    }

    /** Tingkat penerapan dari nilai akhir 0..100. */
    public static function tingkatDari(float $skor): array
    {
        foreach (self::tingkat() as $t) {
            if ($skor >= (float) $t['min']) return $t;
        }
        return ['min' => 0, 'label' => 'Perlu Perhatian Serius', 'warna' => '#DC2626'];
    }

    /* ================= temuan ================= */

    /**
     * Ketidaksesuaian tingkat sub-elemen, urut dari yang terberat.
     *
     * Temuan dicatat pada tingkat sub-elemen — bukan tiap butir — karena itulah
     * satuan yang dipakai pada Formulir Rekapitulasi Ketidaksesuaian.
     */
    public static function temuan(array $hasil): array
    {
        $out = [];
        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $r = self::rekapSub($s, $hasil);
                if ($r['berlaku'] === 0) continue;                    // di luar lingkup
                if ($r['dinilai'] === 0) continue;                    // belum dinilai
                if ($r['kategori']['kode'] === 'kesesuaian') continue; // sudah sesuai

                $out[] = [
                    'kode'    => $s['kode'],
                    'uraian'  => $s['nama'],
                    'elemen'  => $e['kode'] . '. ' . $e['nama'],
                    'jenis'   => $r['kategori']['kode'],
                    'label'   => $r['kategori']['label'],
                    'capaian' => $r['capaian'],
                    'nilai'   => $r['nilai'],
                    'maks'    => $r['maks'],
                    'ref'     => $s['ref'] ?? null,
                    'ket'     => (string) ($hasil[$s['kode']]['ket'] ?? ''),
                ];
            }
        }

        usort($out, fn ($a, $b) => [$a['jenis'] === 'minor', $a['capaian']]
                               <=> [$b['jenis'] === 'minor', $b['capaian']]);
        return $out;
    }

    /** Hitung temuan per jenis. */
    public static function hitungTemuan(array $hasil): array
    {
        $n = ['mayor' => 0, 'minor' => 0];
        foreach (self::temuan($hasil) as $t) {
            if (isset($n[$t['jenis']])) $n[$t['jenis']]++;
        }
        return $n;
    }

    /**
     * Beri nomor ketidaksesuaian sesuai format pada Formulir Rekapitulasi.
     *
     * Penomoran berjalan terpisah per jenis — NC-MYR-01, NC-MYR-02, lalu
     * NC-MNR-01 dan seterusnya — bukan satu urutan gabungan, sebagaimana
     * pada dokumen audit.
     *
     * @param  array $temuan hasil Smkp::temuan()
     * @return array temuan yang sama, masing-masing bertambah kunci 'nomor'
     */
    public static function beriNomor(array $temuan): array
    {
        $urut = [];
        foreach ($temuan as $i => $t) {
            $jenis = $t['jenis'];
            $urut[$jenis] = ($urut[$jenis] ?? 0) + 1;
            $temuan[$i]['nomor'] = self::nomorTemuan($jenis, $urut[$jenis]);
        }
        return $temuan;
    }

    /** Format nomor satu ketidaksesuaian: NC-MYR-01 / NC-MNR-01. */
    public static function nomorTemuan(string $jenis, int $urutan): string
    {
        $awalan = $jenis === 'mayor' ? 'MYR' : ($jenis === 'minor' ? 'MNR' : 'OBS');
        return sprintf('NC-%s-%02d', $awalan, $urutan);
    }

    /**
     * Ekspresi ORDER BY yang mengurutkan jenis temuan dari terberat.
     * CASE WHEN adalah SQL baku sehingga berlaku di SQLite, MySQL, maupun
     * PostgreSQL — berbeda dengan FIELD() yang khusus MySQL.
     */
    public static function urutJenisSql(string $kolom = 'jenis'): string
    {
        return "CASE {$kolom} WHEN 'mayor' THEN 1 WHEN 'minor' THEN 2 ELSE 3 END";
    }
}
