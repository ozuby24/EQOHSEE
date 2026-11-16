<?php

namespace App\Support;

/**
 * SMKP Minerba — mesin hitung audit 7 elemen.
 *
 * Sumber kebenaran: resources/data/smkp/elemen.json, disalin dari Lampiran II
 * Kepdirjen Minerba 185.K/37.04/DJB/2019. Tiap sub-elemen membawa kolom 'ref'
 * berisi halaman uraian penerapannya (hal. 336-400); kriteria tiap nilai
 * butir ada di resources/data/smkp/rubrik.json (hal. 412-599).
 *
 * Penilaian berbasis POIN, bukan kesesuaian. Auditor mengisi nilai capaian
 * 0..maks pada tiap butir. Nilai maksimum butir mengikuti Formulir Kriteria
 * Audit (hal. 605-608) sebagaimana dikoreksi oleh Daftar Koreksi dan
 * Klarifikasi Lampiran II, sama dengan nilai tertinggi yang punya deskripsi
 * pada kriteria penilaian (1 sampai 4), dan seluruhnya berjumlah 339 poin
 * bila seluruh butir berlaku.
 *
 * Perhitungan mengikuti Keterangan Formulir Kriteria Audit (Kepdirjen hal. 608):
 *
 *   Total Nilai Elemen          = Σ nilai sub elemen + nilai sub-sub elemen
 *   Total Nilai Elemen Maksimal = Σ nilai maksimal butir yang dinilai
 *   Persentase Nilai Elemen     = Total Nilai Elemen ÷ Total Nilai Elemen
 *                                 Maksimal × Persentase Nilai Maksimal (bobot)
 *   Total Persentase            = Σ Persentase Nilai Elemen
 *
 * Butir "N/A" tidak berlaku bagi perusahaan yang diaudit sehingga tidak
 * dinilai: nilai maksimalnya tidak ikut dijumlahkan ke Total Nilai Elemen
 * Maksimal, jadi tidak menghukum persentase elemennya. Bobot elemen tetap
 * bobot Kepdirjen; hanya elemen yang seluruh butirnya N/A yang dikeluarkan
 * dari pembagi bobot (nilai akhir dinormalkan). Butir yang BELUM dinilai
 * dihitung nol agar skor tidak terlihat tinggi palsu saat audit baru
 * berjalan sebagian.
 *
 * Kategori temuan diturunkan DARI nilai, bukan dipilih auditor — sesuai kolom
 * "KATEGORI TEMUAN (Berdasarkan Nilai)" pada formulir kriteria:
 *   capaian < 50%          → Ketidaksesuaian Mayor
 *   capaian 50% s.d. <100% → Ketidaksesuaian Minor
 *   capaian 100%           → Kesesuaian
 *
 * Sub-elemen yang berincian (sub-sub-elemen, mis. IV.3.1..IV.3.10) dinilai
 * lewat rinciannya, tetapi kategori Mayor hanya melekat pada sub-elemen
 * (sesuai lembar kerja pelatihan auditor):
 *   - capaian sub-elemen Mayor → satu temuan Mayor berkode sub-elemen;
 *   - selain itu → tiap rincian yang belum penuh menjadi temuan Minor
 *     tersendiri (rincian hanya mengenal Minor, tidak pernah Mayor).
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
    private static ?array $rubrik = null;

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

    /**
     * Kriteria penilaian resmi satu butir: deskripsi tiap nilai 0..maks dan
     * kondisi N/A. Butir II.5 dan III.3 punya dua versi (pemegang IUP dan
     * pemegang IUJP) di kunci 'varian'.
     *
     * @return array{hal:int,nilai?:array,na?:?string,varian?:array,catatan?:string}|null
     */
    public static function rubrik(string $kode): ?array
    {
        self::$rubrik ??= json_decode(
            file_get_contents(resource_path('data/smkp/rubrik.json')), true
        );

        return self::$rubrik['butir'][$kode] ?? null;
    }

    /**
     * Uraian penerapan satu butir untuk Laporan Audit (Gambaran Umum
     * Penerapan SMKP): deskripsi tingkat nilai resmi pada rubrik, dengan
     * subjek "pemegang IUP, IUPK, … atau IUJP" diganti nama perusahaan.
     * Butir II.5 dan III.3 memakai versi IUJP untuk perusahaan jasa.
     */
    public static function uraianPenerapan(string $kode, float $nilai, string $pt, bool $jasa = false): ?string
    {
        $r = self::rubrik($kode);
        if (!$r) return null;
        if (isset($r['varian'])) {
            $r = collect($r['varian'])->firstWhere('untuk', $jasa ? 'iujp' : 'iup') ?? $r['varian'][0];
        }
        foreach ((array) ($r['nilai'] ?? []) as $n) {
            if ((int) $n['n'] === (int) round($nilai)) return self::denganNamaPerusahaan((string) $n['teks'], $pt);
        }

        return null;
    }

    /** "Tidak ada bukti yang menunjukkan pemegang IUP, … telah melakukan X" → "PT A belum melakukan X". */
    public static function denganNamaPerusahaan(string $teks, string $pt): string
    {
        $entitas = '[Pp]emegang (?:IUJP\\b|IUP, IUPK, IUP Operasi Produksi khusus untuk Pengolahan dan\/atau Pemurnian, (?:atau )?(?:IPR|IUJP)(?:, atau IUJP)?)';
        $t = preg_replace('/^Tidak ada bukti yang menunjukkan (?:bahwa )?'.$entitas.' telah /u', $pt.' belum ', $teks);
        $t = preg_replace('/^Terdapat bukti yang menunjukkan (?:bahwa )?'.$entitas.' /u', $pt.' ', $t);

        return preg_replace('/'.$entitas.'/u', $pt, $t);
    }

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

    /**
     * Nilai butir pada skala nilai maksimumnya yang berlaku: angka 0..maks,
     * 'N/A', atau null bila belum dinilai.
     *
     * Butir yang nilai maksimumnya dikoreksi ke Kepdirjen (mis. III.2.1 dari 4
     * menjadi 2) membawa 'maks_lama' bila nilainya diberikan pada skala lama
     * (ditandai migrasi 2026_10_20_000001). Sampai dinilai ulang, nilai itu
     * disetarakan sebanding — 3 dari 4 menjadi 1,5 dari 2 — agar capaian dan
     * kategori temuannya tidak berubah diam-diam. Nilai di atas maksimum
     * tanpa penanda tidak boleh mengangkat capaian dan dijepit.
     */
    public static function nilaiSetara(array $hasil, array $b)
    {
        $v = self::nilaiButir($hasil, $b['kode']);
        if (!is_float($v)) return $v;

        $maks = (float) $b['maks'];
        $lama = (float) ($hasil[$b['kode']]['maks_lama'] ?? 0);
        if ($lama > 0 && $lama != $maks) $v = $v / $lama * $maks;

        return max(0.0, min($v, $maks));
    }

    /**
     * Butir yang perlu dinilai ulang: nilainya masih pada skala lama
     * ('maks_lama'), melebihi nilai maksimum yang berlaku, atau diberikan
     * sebelum deskripsi tingkatnya dikoreksi Daftar Koreksi dan Klarifikasi
     * Lampiran II ('koreksi' = nomor koreksinya; nilainya tetap dihitung
     * apa adanya sampai auditor memeriksanya kembali).
     *
     * @return array<string,array{v:float,maks:int,maks_lama:?int,koreksi:?int,setara:float,elemen:string}> kunci = kode butir
     */
    public static function perluDinilaiUlang(array $hasil): array
    {
        $out = [];
        foreach (self::butir() as $b) {
            $v = self::nilaiButir($hasil, $b['kode']);
            if (!is_float($v)) continue;

            $lama = (int) ($hasil[$b['kode']]['maks_lama'] ?? 0);
            $skalaLama = $lama > 0 && $lama !== (int) $b['maks'];
            $koreksi = (int) ($hasil[$b['kode']]['koreksi'] ?? 0);
            if (!$skalaLama && !$koreksi && $v <= $b['maks']) continue;

            $out[$b['kode']] = [
                'v'         => $v,
                'maks'      => (int) $b['maks'],
                'maks_lama' => $skalaLama ? $lama : null,
                'koreksi'   => $koreksi ?: null,
                'setara'    => self::nilaiSetara($hasil, $b),
                'elemen'    => $b['elemen'],
            ];
        }
        return $out;
    }

    public static function dikecualikan(array $hasil, string $kode): bool
    {
        return self::nilaiButir($hasil, $kode) === self::NA;
    }

    /* ================= perhitungan ================= */

    /**
     * Rekap satu sub-elemen.
     *
     * @return array{maks:int,maksPenuh:int,nilai:float,berlaku:int,dinilai:int,total:int,
     *               capaian:float,kategori:array,butir:array}
     *         maks = Σ maks butir berlaku (tanpa N/A); maksPenuh = Σ maks seluruh butir
     */
    public static function rekapSub(array $sub, array $hasil): array
    {
        $maks = 0; $maksPenuh = 0; $nilai = 0.0; $berlaku = 0; $dinilai = 0; $rinci = [];

        foreach (self::butirSub($sub) as $b) {
            $v = self::nilaiSetara($hasil, $b);
            $rinci[$b['kode']] = $v;
            $maksPenuh += (int) $b['maks'];

            if ($v === self::NA) continue;          // di luar lingkup perusahaan

            $berlaku++;
            $maks += (int) $b['maks'];
            if ($v !== null) {
                $dinilai++;
                $nilai += $v;
            }
        }

        $capaian = $maks > 0 ? $nilai / $maks : 0.0;

        return [
            'maks'     => $maks,
            'maksPenuh'=> $maksPenuh,
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
     * @return array{maks:int,maksPenuh:int,nilai:float,capaian:float,bobot:int,
     *               skor:float,berlaku:int,dinilai:int,total:int,sub:array}
     *         maks = Total Nilai Elemen Maksimal (tanpa butir N/A); skor = Persentase Nilai Elemen
     */
    public static function rekapElemen(array $elemen, array $hasil): array
    {
        $maks = 0; $maksPenuh = 0; $nilai = 0.0; $berlaku = 0; $dinilai = 0; $total = 0; $sub = [];

        foreach ($elemen['sub'] as $s) {
            $r = self::rekapSub($s, $hasil);
            $sub[$s['kode']] = $r;
            $maks    += $r['maks'];
            $maksPenuh += $r['maksPenuh'];
            $nilai   += $r['nilai'];
            $berlaku += $r['berlaku'];
            $dinilai += $r['dinilai'];
            $total   += $r['total'];
        }

        $capaian = $maks > 0 ? $nilai / $maks : 0.0;
        $bobot   = (int) ($elemen['bobot'] ?? 0);

        return [
            'maks'    => $maks,
            'maksPenuh' => $maksPenuh,
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
     * Total Persentase = Σ Persentase Nilai Elemen. Elemen yang seluruh
     * butirnya N/A dikeluarkan dari pembagi bobot, lalu hasilnya dinormalkan
     * ke skala 100 agar tetap sebanding antar perusahaan yang lingkup
     * operasinya berbeda.
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

        // Tingkat dibaca dari angka yang tampil (dua desimal): 84,9977 tampil
        // 85,00 dan harus berlabel sama dengan 85.
        $akhir = round($bobotTerpakai > 0 ? $skor / $bobotTerpakai * 100 : 0.0, 2);

        return [
            'nilai'         => $nilai,
            'maks'          => $maks,
            'skor'          => $akhir,
            'bobotTerpakai' => $bobotTerpakai,
            'berlaku'       => $berlaku,
            'dinilai'       => $dinilai,
            'total'         => $total,
            'tingkat'       => $dinilai > 0 ? self::tingkatDari($akhir) : self::tingkatBelum(),
            'elemen'        => $elemen,
        ];
    }

    /* ================= kategori & tingkat ================= */

    /** Kategori temuan dari capaian 0..1 — ambangnya dari berkas acuan. */
    public static function kategoriDari(float $capaian): array
    {
        // Dibulatkan agar galat pecahan biner tidak menurunkan kategori tepat di ambang.
        $persen = round($capaian * 100, 9);
        foreach (self::kategori() as $k) {
            if ($persen >= (float) $k['min']) return $k;
        }
        return end(self::kategori()) ?: ['kode' => 'mayor', 'label' => 'Ketidaksesuaian Mayor', 'warna' => '#DC2626'];
    }

    /** Satu kategori temuan menurut kodenya ('mayor', 'minor', 'kesesuaian'). */
    public static function kategoriKode(string $kode): array
    {
        foreach (self::kategori() as $k) {
            if ($k['kode'] === $kode) return $k;
        }
        return ['kode' => $kode, 'label' => 'Ketidaksesuaian '.ucfirst($kode), 'warna' => '#D97706'];
    }

    /** Penanda untuk sub-elemen yang seluruhnya di luar lingkup. */
    public static function kategoriNa(): array
    {
        return ['kode' => 'na', 'label' => 'Tidak Berlaku', 'min' => null, 'warna' => '#9AA3AE'];
    }

    /** Penanda audit yang belum satu butir pun dinilai — bukan "Perlu Perhatian Serius". */
    public static function tingkatBelum(): array
    {
        return ['min' => null, 'label' => 'Belum dinilai', 'warna' => '#94A3B8'];
    }

    /** Tingkat penerapan dari nilai akhir 0..100. */
    public static function tingkatDari(float $skor): array
    {
        foreach (self::tingkat() as $t) {
            if ($skor >= (float) $t['min']) return $t;
        }
        return ['min' => 0, 'label' => 'Perlu Perhatian Serius', 'warna' => '#DC2626'];
    }

    /* ================= referensi kriteria ================= */

    private static ?array $nama = null;

    /** Nama tiap kode kriteria (elemen, sub-elemen, rincian), berurutan menurut acuan. */
    public static function namaKriteria(): array
    {
        if (self::$nama !== null) return self::$nama;

        $nama = [];
        foreach (self::elemen() as $e) {
            $nama[$e['kode']] = $e['nama'];
            foreach ($e['sub'] as $s) {
                $nama[$s['kode']] = $s['nama'];
                foreach ($s['subsub'] ?? [] as $x) $nama[$x['kode']] = $x['nama'];
            }
        }
        return self::$nama = $nama;
    }

    /** Posisi kode kriteria pada acuan, untuk mengurutkan temuan; kode asing di akhir. */
    public static function urutanKode(string $kode): int
    {
        $i = array_search($kode, array_keys(self::namaKriteria()), true);
        return $i === false ? PHP_INT_MAX : $i;
    }

    /**
     * Referensi sebuah temuan: elemen, sub-elemen, dan sub-sub-elemen (rincian)
     * yang tidak dipenuhi, beserta kalimat "tidak sesuai dengan Sub Elemen …,
     * Sub-sub Elemen …" untuk penutup uraian (lihat uraianTemuan).
     *
     * @return array{elemen:string,sub:string,subsub:string,kalimat:string}
     */
    public static function referensi(string $kode): array
    {
        $nama = self::namaKriteria();
        $bagian = array_values(array_filter(explode('.', trim($kode)), fn ($x) => $x !== ''));
        $tulis = fn (int $n) => count($bagian) >= $n
            ? trim(($k = implode('.', array_slice($bagian, 0, $n))).' — '.($nama[$k] ?? ''), ' —')
            : '';

        $ref = ['elemen' => $tulis(1), 'sub' => $tulis(2), 'subsub' => count($bagian) > 2 ? $tulis(count($bagian)) : ''];
        $sebut = fn (string $x) => str_replace(' — ', ' ', $x);
        $ref['kalimat'] = match (true) {
            $ref['subsub'] !== '' => 'tidak sesuai dengan Sub Elemen '.$sebut($ref['sub']).', Sub-sub Elemen '.$sebut($ref['subsub']).'.',
            $ref['sub'] !== ''    => 'tidak sesuai dengan Sub Elemen '.$sebut($ref['sub']).'.',
            $ref['elemen'] !== '' => 'tidak sesuai dengan Elemen '.$sebut($ref['elemen']).'.',
            default               => '',
        };
        return $ref;
    }

    /**
     * Pernyataan ketidaksesuaian: isian pertama yang benar-benar menguraikan
     * masalah. Isian yang hanya mengulang nama kriteria (bawaan saat temuan
     * diangkat) dilewati — namanya sudah tercantum di referensi.
     */
    public static function pernyataanTemuan(string $kode, ?string ...$calon): string
    {
        $namaKriteria = mb_strtolower(trim((string) (self::namaKriteria()[trim($kode)] ?? '')));
        foreach ($calon as $teks) {
            $teks = trim((string) $teks);
            if ($teks !== '' && mb_strtolower($teks) !== $namaKriteria) return $teks;
        }
        return '';
    }

    /**
     * Uraian temuan untuk laporan dan rekapitulasi: pernyataan masalah lalu
     * "Hal ini tidak sesuai dengan Sub Elemen …, Sub-sub Elemen …" dalam satu
     * paragraf, kecuali pernyataannya sudah menyebut ketidaksesuaiannya.
     * Tanpa pernyataan, kalimat referensinya saja.
     */
    public static function uraianTemuan(string $kode, ?string ...$calon): string
    {
        $isi = self::pernyataanTemuan($kode, ...$calon);
        $ref = self::referensi($kode)['kalimat'];
        if ($ref === '') return $isi;
        if ($isi === '') return mb_strtoupper(mb_substr($ref, 0, 1)).mb_substr($ref, 1);
        // Deskripsi bertemplat sudah menyebut acuannya ("… sehingga tidak sesuai dengan …").
        if (mb_stripos($isi, 'tidak sesuai dengan') !== false) return $isi;

        return (preg_match('/[.!?]$/u', $isi) ? $isi : $isi.'.').' Hal ini '.$ref;
    }

    /**
     * Referensi temuan di kalimat deskripsi: "Sub Elemen I.4 – Komunikasi
     * Kebijakan"; temuan rincian ditambah ", Sub-sub Elemen II.2.1 – …".
     */
    public static function acuanTemuan(string $kode): string
    {
        $nama = self::namaKriteria();
        $b = array_values(array_filter(explode('.', trim($kode)), fn ($x) => $x !== ''));
        $sebut = function (string $label, int $n) use ($b, $nama) {
            $k = implode('.', array_slice($b, 0, $n));
            return ($nama[$k] ?? '') !== '' ? "{$label} {$k} – {$nama[$k]}" : "{$label} {$k}";
        };

        return match (true) {
            count($b) > 2   => $sebut('Sub Elemen', 2).', '.$sebut('Sub-sub Elemen', count($b)),
            count($b) === 2 => $sebut('Sub Elemen', 2),
            count($b) === 1 => $sebut('Elemen', 1),
            default         => '',
        };
    }

    /**
     * Deskripsi Formulir Ketidaksesuaian disusun dari isian temuan:
     * "Ditemukan bukti {uraian masalah}, berdasarkan {bukti} di {lokasi},
     * sehingga tidak sesuai dengan {referensi}." Bagian yang kosong
     * dilewati. Harus sama persis dengan deskripsiDari() di Smkp/Temuan.vue.
     */
    public static function deskripsiTemuan(string $kode, ?string $uraian, ?string $bukti, ?string $lokasi): string
    {
        $masalah = self::potongan(self::pernyataanTemuan($kode, $uraian));
        $bukti = self::potongan($bukti);
        $lokasi = self::potongan($lokasi);
        $acuan = self::acuanTemuan($kode);

        $teks = $masalah !== '' ? "Ditemukan bukti {$masalah}" : 'Ditemukan ketidaksesuaian';
        if ($bukti !== '') $teks .= ", berdasarkan {$bukti}";
        if ($lokasi !== '') $teks .= " di {$lokasi}";
        if ($acuan !== '') $teks .= ", sehingga tidak sesuai dengan {$acuan}";

        return $teks.'.';
    }

    /** Isian sebagai bagian kalimat: satu baris, tanpa tanda baca penutup, huruf awal kata biasa dikecilkan. */
    private static function potongan(?string $x): string
    {
        $x = preg_replace('/[ \t\r\n]+/', ' ', (string) $x);
        $x = preg_replace(['/^ +/', '/[ .;,:]+$/'], '', $x);
        if (preg_match('/^\p{Lu}\p{Ll}+(?![\p{L}\p{N}])/u', $x)) $x = mb_strtolower(mb_substr($x, 0, 1)).mb_substr($x, 1);

        return $x;
    }

    /* ================= temuan ================= */

    /**
     * Ketidaksesuaian, urut dari yang terberat (Mayor lalu Minor), masing-masing
     * menurut urutan sub-elemen dan sub-sub-elemen acuan.
     *
     * Sub-elemen tanpa rincian: satu temuan bila capaiannya di bawah 100%.
     * Sub-elemen berincian: menunggu seluruh rincian yang berlaku dinilai,
     * lalu satu temuan Mayor berkode sub-elemen bila capaian sub-elemennya
     * Mayor; selain itu satu temuan Minor per rincian yang belum penuh —
     * Mayor tidak melekat pada rincian.
     *
     * Nilai dibaca lewat Smkp::nilaiSetara() agar butir yang masih bernilai
     * skala lama (lihat 'maks_lama') ikut disetarakan sebelum dikategorikan.
     */
    public static function temuan(array $hasil): array
    {
        $out = [];
        $minor = self::kategoriKode('minor');
        foreach (self::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $baris = fn (string $kode, string $uraian, array $kategori, float $capaian, float $nilai, int $maks, string $ket) => [
                    'kode'    => $kode,
                    'uraian'  => $uraian,
                    'elemen'  => $e['kode'] . '. ' . $e['nama'],
                    'jenis'   => $kategori['kode'],
                    'label'   => $kategori['label'],
                    'capaian' => $capaian,
                    'nilai'   => $nilai,
                    'maks'    => $maks,
                    'ref'     => $s['ref'] ?? null,
                    'ket'     => $ket,
                ];

                $rinci = !empty($s['subsub']);
                if ($rinci) {
                    $r = self::rekapSub($s, $hasil);
                    if ($r['berlaku'] === 0 || $r['dinilai'] < $r['berlaku']) continue; // kategori sub-elemen belum pasti
                    if ($r['kategori']['kode'] === 'mayor') {
                        $ket = [];
                        foreach (array_keys($r['butir']) as $k) {
                            $isi = trim((string) ($hasil[$k]['ket'] ?? ''));
                            if ($isi !== '') $ket[] = "{$k}: {$isi}";
                        }
                        $out[] = $baris($s['kode'], $s['nama'], $r['kategori'], $r['capaian'], $r['nilai'], $r['maks'], implode("\n", $ket));
                        continue;
                    }
                }

                foreach (self::butirSub($s) as $b) {
                    $v = self::nilaiSetara($hasil, $b);
                    if ($v === self::NA) continue;  // di luar lingkup
                    if ($v === null) continue;       // belum dinilai

                    $nilai    = (float) $v;
                    $capaian  = $b['maks'] > 0 ? $nilai / (float) $b['maks'] : 0.0;
                    $kategori = self::kategoriDari($capaian);
                    if ($kategori['kode'] === 'kesesuaian') continue; // sudah sesuai
                    if ($rinci) $kategori = $minor;                    // rincian tidak pernah Mayor

                    $out[] = $baris($b['kode'], $b['nama'], $kategori, $capaian, $nilai, (int) $b['maks'], (string) ($hasil[$b['kode']]['ket'] ?? ''));
                }
            }
        }

        usort($out, fn ($a, $b) => self::kunciUrutTemuan($a['jenis'], $a['kode'])
                               <=> self::kunciUrutTemuan($b['jenis'], $b['kode']));
        return $out;
    }

    /**
     * Jenis temuan sebuah sub-elemen menurut nilainya: 'mayor', 'minor',
     * 'kesesuaian', atau null bila di luar lingkup / belum tuntas dinilai.
     */
    public static function jenisTemuan(array $sub, array $hasil): ?string
    {
        $r = self::rekapSub($sub, $hasil);
        if ($r['berlaku'] === 0 || $r['dinilai'] < $r['berlaku']) return null;

        return $r['kategori']['kode'];
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

    /** Kunci urut temuan: kategori terberat dulu, lalu urutan sub-elemen/sub-sub-elemen acuan. */
    public static function kunciUrutTemuan(string $jenis, string $kode): array
    {
        return [['kritikal' => 0, 'mayor' => 1, 'minor' => 2][$jenis] ?? 3, self::urutanKode($kode)];
    }

    /**
     * Beri nomor ketidaksesuaian: penomoran berjalan terpisah per kategori
     * (AIC-MYR-01-2025, AIC-MYR-02-2025, lalu AIC-MNR-01-2025 …), mengikuti
     * urutan $temuan.
     *
     * @param  array $temuan hasil Smkp::temuan()
     * @return array temuan yang sama, masing-masing bertambah kunci 'nomor'
     */
    public static function beriNomor(array $temuan, int $tahun, ?string $kode = null): array
    {
        $urut = [];
        foreach ($temuan as $i => $t) {
            $jenis = $t['jenis'];
            $urut[$jenis] = ($urut[$jenis] ?? 0) + 1;
            $temuan[$i]['nomor'] = self::nomorTemuan($jenis, $urut[$jenis], $tahun, $kode);
        }
        return $temuan;
    }

    /** Singkatan kategori pada nomor ketidaksesuaian. */
    public static function singkatJenis(string $jenis): string
    {
        return match ($jenis) { 'mayor' => 'MYR', 'minor' => 'MNR', 'kritikal' => 'KRT', default => 'OBS' };
    }

    /**
     * Format nomor satu ketidaksesuaian: (kode perusahaan)-(kategori)-(urutan)-(tahun),
     * mis. AIC-MYR-01-2025, NJA-MNR-03-2026. Tanpa kode: MYR-01-2025.
     */
    public static function nomorTemuan(string $jenis, int $urutan, int $tahun, ?string $kode = null): string
    {
        $kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $kode));
        return ($kode !== '' ? "{$kode}-" : '').sprintf('%s-%02d-%d', self::singkatJenis($jenis), $urutan, $tahun);
    }

    /**
     * Urutan yang tertera pada nomor kategori itu; 0 bila bukan. Selain format di
     * atas, nomor ketikan lama "01-MYR-2025" / "NJA/01/MYR/2026" ikut terbaca
     * agar nomor baru melanjutkannya, tidak mengulang dari 01.
     */
    public static function urutanNomor(string $nomor, string $jenis): int
    {
        $s = preg_quote(self::singkatJenis($jenis), '/');
        $nomor = trim($nomor);
        if (preg_match('/(?:^|[-\/])'.$s.'[-\/](\d{1,4})[-\/]\d{4}$/i', $nomor, $m)) return (int) $m[1];
        return preg_match('/(?:^|[-\/])(\d{1,4})[-\/]'.$s.'[-\/]\d{4}$/i', $nomor, $m) ? (int) $m[1] : 0;
    }

    /**
     * Ekspresi ORDER BY yang mengurutkan jenis temuan dari terberat.
     * CASE WHEN adalah SQL baku sehingga berlaku di SQLite, MySQL, maupun
     * PostgreSQL — berbeda dengan FIELD() yang khusus MySQL.
     */
    public static function urutJenisSql(string $kolom = 'jenis'): string
    {
        return "CASE {$kolom} WHEN 'kritikal' THEN 0 WHEN 'mayor' THEN 1 WHEN 'minor' THEN 2 ELSE 3 END";
    }
}
