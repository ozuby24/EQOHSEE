<?php

namespace App\Support;

/**
 * SMKP Minerba — mesin hitung audit 7 elemen.
 *
 * Sumber kebenaran: resources/data/smkp/elemen.json, disalin dari Lampiran II
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
 * Angka ambangnya hanya ada di berkas acuan. Tidak ada satu pun yang ditulis
 * ulang di kode — termasuk pada penentuan mayor sub-elemen berincian, yang
 * membaca kategori agregatnya alih-alih membandingkan dengan 0.5.
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
        return self::$ref ??= json_decode(
            file_get_contents(resource_path('data/smkp/elemen.json')), true
        );
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

    /**
     * Urutan dokumen seluruh kode yang dapat menjadi temuan.
     *
     * Memuat sub-elemen DAN rinciannya, sebab temuan melekat pada keduanya:
     * mayor pada sub-elemen, minor pada rinciannya. Peta ini yang menjaga
     * nomor NC tetap pada urutan berkas kriteria, bukan pada urutan berat
     * yang berpindah setiap kali satu temuan ditutup.
     *
     * @return array<string,int> kode → posisi
     */
    public static function urutanKriteria(): array
    {
        $urut = [];
        $n    = 0;

        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $urut[$s['kode']] ??= $n++;

                foreach (self::butirSub($s) as $b) {
                    $urut[$b['kode']] ??= $n++;
                }
            }
        }

        return $urut;
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
     * Ketidaksesuaian, urut sesuai urutan dokumen — elemen, sub-elemen,
     * lalu rinciannya.
     *
     * TINGKAT MELEKATNYA TEMUAN BERBEDA MENURUT BENTUK SUB-ELEMENNYA, dan
     * ini bukan kerapian melainkan aturan pada Formulir Kriteria:
     *
     *   sub-elemen tanpa rincian
     *       kategorinya dari capaiannya sendiri, melekat pada sub-elemen.
     *
     *   sub-elemen dengan rincian, AGREGATNYA jatuh ke mayor
     *       MAYOR melekat pada SUB-ELEMEN, bukan pada rinciannya. Yang
     *       gagal dinyatakan gagal sebagai satu kesatuan; memecahnya
     *       menjadi beberapa mayor per rincian melipatgandakan satu
     *       kegagalan yang sama.
     *
     *   sub-elemen dengan rincian, AGREGATNYA masih di atas ambang mayor
     *       tiap RINCIAN yang belum penuh menjadi MINOR tersendiri.
     *       Sub-elemennya secara keseluruhan masih berjalan, jadi yang
     *       ditagih perbaikannya adalah rincian yang tertinggal — dan
     *       masing-masing perlu tindakan perbaikannya sendiri.
     *
     * Perhatikan akibatnya: rincian bernilai nol di dalam sub-elemen yang
     * agregatnya sehat tetap MINOR, bukan mayor. Mayor adalah pernyataan
     * tentang sub-elemen, bukan tentang satu butir.
     *
     * URUTANNYA URUTAN DOKUMEN, bukan urutan berat. Nomor NC diturunkan
     * dari urutan ini dan disebut dalam rapat penutupan serta
     * surat-menyurat sesudahnya; urutan berat membuat nomor berpindah
     * setiap kali sebuah nilai berubah, sehingga "temuan nomor 3" pada
     * risalah rapat menunjuk temuan yang berbeda seminggu kemudian.
     */
    public static function temuan(array $hasil): array
    {
        $out = [];

        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $r = self::rekapSub($s, $hasil);
                if ($r['berlaku'] === 0) continue;   // seluruhnya di luar lingkup
                if ($r['dinilai'] === 0) continue;   // belum dinilai

                // Sub-elemen tanpa rincian: dinilai sebagai dirinya sendiri.
                if (empty($s['subsub'])) {
                    if ($r['kategori']['kode'] === 'kesesuaian') continue;

                    $out[] = self::barisTemuan($e, $s, null, $r['kategori']['kode'],
                        $r['capaian'], $r['nilai'], $r['maks'], $hasil);
                    continue;
                }

                // Berincian dan agregatnya gagal: satu mayor pada sub-elemen.
                // Ambangnya dibaca dari kategori, bukan ditulis sebagai angka
                // di sini — satu-satunya tempat ambang boleh berubah adalah
                // berkas acuan, dan angka yang tersalin ke kode akan bertahan
                // diam-diam sesudah berkasnya diubah.
                if ($r['kategori']['kode'] === 'mayor') {
                    $out[] = self::barisTemuan($e, $s, null, 'mayor',
                        $r['capaian'], $r['nilai'], $r['maks'], $hasil);
                    continue;
                }

                // Berincian dan agregatnya berjalan: rincian yang tertinggal
                // ditagih satu per satu.
                foreach (self::butirSub($s) as $b) {
                    $v = self::nilaiButir($hasil, $b['kode']);
                    if ($v === null || $v === self::NA) continue;

                    $maks    = (int) ($b['maks'] ?? 0);
                    $nilai   = max(0, min((float) $v, (float) $maks));
                    $capaian = $maks > 0 ? $nilai / $maks : 0.0;
                    if ($capaian >= 1) continue;

                    $out[] = self::barisTemuan($e, $s, $b, 'minor',
                        $capaian, $nilai, $maks, $hasil);
                }
            }
        }

        return $out;
    }

    /**
     * Satu baris temuan. $butir null berarti temuan melekat pada
     * sub-elemennya; berisi berarti melekat pada rincian di bawahnya.
     */
    private static function barisTemuan(
        array $elemen, array $sub, ?array $butir,
        string $jenis, float $capaian, float $nilai, int $maks, array $hasil
    ): array {
        $sasaran = $butir ?? $sub;

        /* Label mengikuti JENIS yang sudah ditetapkan, bukan dihitung ulang
           dari capaian. Keduanya sengaja dapat berbeda: rincian bernilai nol
           di dalam sub-elemen yang agregatnya sehat berjenis minor, padahal
           capaiannya sendiri jatuh di bawah ambang mayor. Menghitung ulang
           label dari capaian membuat satu baris berbunyi "minor" pada
           kolom jenis dan "Ketidaksesuaian Mayor" pada kolom label. */
        $label = collect(self::kategori())->firstWhere('kode', $jenis)['label'] ?? $jenis;

        return [
            'kode'    => $sasaran['kode'],
            'uraian'  => $sasaran['nama'],
            'elemen'  => $elemen['kode'].'. '.$elemen['nama'],
            // Rincian membawa induknya supaya lembar rekapitulasi dapat
            // menyebut sub-elemen mana yang ditagih, bukan kode telanjang.
            'induk'   => $butir ? $sub['kode'].' '.$sub['nama'] : null,
            'jenis'   => $jenis,
            'label'   => $label,
            'capaian' => $capaian,
            'nilai'   => $nilai,
            'maks'    => $maks,
            // Halaman acuan selalu milik sub-elemennya; rincian tidak
            // punya halaman tersendiri pada lampiran.
            'ref'     => $sub['ref'] ?? null,
            'ket'     => (string) ($hasil[$sasaran['kode']]['ket'] ?? ''),
        ];
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
     * Beri nomor ketidaksesuaian — DUA nomor untuk tiap temuan.
     *
     *   nomor  NC-01, NC-02, …   satu urutan berjalan untuk seluruh temuan,
     *                            apa pun jenisnya. Inilah yang disebut dalam
     *                            rapat penutupan: "temuan nomor 3".
     *   kode   {AWALAN}-MAY-01   urutan terpisah per jenis, berawalan kode
     *          {AWALAN}-MIN-01   dokumen perusahaan yang diaudit. Inilah yang
     *                            dipakai dalam surat-menyurat antar-perusahaan,
     *                            tempat "NC-01" saja tidak cukup menunjuk.
     *
     * Keduanya mengikuti urutan yang diberikan — urutan dokumen dari
     * Smkp::temuan(). Menomori menurut berat akan memindahkan nomor setiap
     * kali sebuah nilai berubah, dan risalah rapat yang menyebut nomor lama
     * langsung menunjuk temuan yang salah.
     *
     * @param  array  $temuan hasil Smkp::temuan()
     * @param  string $awalan kode dokumen perusahaan; 'NC' bila tak ada
     * @return array  temuan yang sama, bertambah kunci 'nomor' dan 'kode_nc'
     */
    public static function beriNomor(array $temuan, string $awalan = 'NC'): array
    {
        $awalan = strtoupper(trim($awalan)) ?: 'NC';
        $urut   = 0;
        $per    = [];

        foreach ($temuan as $i => $t) {
            $jenis = $t['jenis'];
            $per[$jenis] = ($per[$jenis] ?? 0) + 1;

            $temuan[$i]['nomor']   = sprintf('NC-%02d', ++$urut);
            $temuan[$i]['kode_nc'] = self::nomorTemuan($jenis, $per[$jenis], $awalan);
        }

        return $temuan;
    }

    /** Format kode satu ketidaksesuaian: CAM-MAY-01 / CAM-MIN-01. */
    public static function nomorTemuan(string $jenis, int $urutan, string $awalan = 'NC'): string
    {
        $bagian = $jenis === 'mayor' ? 'MAY' : ($jenis === 'minor' ? 'MIN' : 'OBS');

        return sprintf('%s-%s-%02d', strtoupper(trim($awalan)) ?: 'NC', $bagian, $urutan);
    }

    /**
     * Ekspresi ORDER BY yang mengurutkan jenis temuan dari terberat.
     * CASE WHEN adalah SQL baku sehingga berlaku di SQLite, MySQL, maupun
     * PostgreSQL — berbeda dengan FIELD() yang khusus MySQL.
     */
    /**
     * Menyeragamkan nilai kolom `jenis` sebuah temuan menjadi kode.
     *
     * Kolomnya menyimpan DUA BENTUK, dan keduanya sah menurut asalnya:
     * `angkatTemuan` menulis kode pendek ('mayor'), sedangkan pemuat
     * data contoh dan baris lama menulis labelnya penuh
     * ('Ketidaksesuaian Mayor'). Sebuah rekap yang membandingkan
     * dengan salah satu bentuk saja akan MENGHITUNG NOL untuk separuh
     * barisnya — tanpa satu galat, tanpa satu baris yang tampak salah;
     * yang terlihat hanyalah perusahaan yang seolah tidak punya temuan.
     *
     * Terjadi sungguhan: dasbor performa melaporkan "0 mayor · 0 minor"
     * atas audit yang tabelnya berisi belasan temuan.
     *
     * Labelnya dibaca dari berkas acuan, bukan ditulis ulang di sini —
     * mengubah bunyi label pada acuan tidak boleh memutus pencocokan
     * ini.
     *
     * @return string 'mayor' | 'minor' | 'obs'
     */
    public static function kodeJenis(?string $jenis): string
    {
        $j = trim((string) $jenis);
        if ($j === '') return 'obs';

        foreach (self::kategori() as $k) {
            if (! in_array($k['kode'], ['mayor', 'minor'], true)) continue;

            if (strcasecmp($j, $k['kode']) === 0 || strcasecmp($j, $k['label']) === 0) {
                return $k['kode'];
            }
        }

        return 'obs';
    }

    public static function urutJenisSql(string $kolom = 'jenis'): string
    {
        return "CASE {$kolom} WHEN 'mayor' THEN 1 WHEN 'minor' THEN 2 ELSE 3 END";
    }
}
