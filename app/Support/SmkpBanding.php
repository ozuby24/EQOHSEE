<?php

namespace App\Support;

use App\Models\SmkpAudit;

/**
 * Membandingkan sebuah audit SMKP dengan audit tahun-tahun sebelumnya.
 *
 * MENGAPA PEMBANDINGNYA PENTING, DAN MENGAPA BUKAN SEKADAR SELISIH.
 *
 * Nilai akhir sebuah audit adalah satu angka. Angka itu menjawab
 * "berapa", tidak menjawab "apakah kami membaik" — dan pertanyaan kedua
 * itulah yang ditanyakan manajemen pada rapat tinjauan, serta yang
 * ditanyakan Inspektur Tambang ketika sebuah elemen tetap rendah tiga
 * tahun berturut-turut.
 *
 * Menjawabnya menuntut lebih dari mengurangkan dua angka:
 *
 * - PEMBAGINYA BERUBAH ANTAR TAHUN. Butir yang tahun lalu N/A dapat
 *   menjadi berlaku tahun ini — tambang membuka bagian bawah tanah,
 *   mulai memakai bahan peledak. Membandingkan nilai MUTLAK di situ
 *   melaporkan penurunan yang sesungguhnya perluasan lingkup. Karena
 *   itu yang dibandingkan CAPAIAN (0..1), bukan poin.
 *
 * - BUTIR YANG BELUM DINILAI BUKAN BUTIR BERNILAI NOL. Audit yang baru
 *   berjalan sepertiga akan tampak anjlok dibanding tahun lalu bila
 *   keduanya diperlakukan sama. Butir yang belum dinilai tahun ini
 *   dikeluarkan dari perbandingan dan dihitung terpisah, sehingga
 *   "turun 40 poin" tidak pernah berarti "belum selesai dinilai".
 *
 * - KONSISTENSI BUKAN KENAIKAN. Butir yang bertahan sempurna dua tahun
 *   dan butir yang bertahan buruk dua tahun sama-sama "tidak berubah",
 *   dan menyamakan keduanya di layar membuat yang kedua tidak pernah
 *   terlihat. Keduanya dipisah: `tetap_baik` dan `tetap_rendah`.
 */
final class SmkpBanding
{
    /** Ambang capaian yang disebut "bertahan baik". */
    public const AMBANG_BAIK = 1.0;

    /** Ambang capaian yang disebut "bertahan rendah" — sama dengan ambang mayor. */
    public const AMBANG_RENDAH = 0.5;

    /**
     * Audit tahun sebelumnya bagi sebuah audit.
     *
     * Yang dicari bukan `tahun - 1` melainkan TAHUN TERBESAR YANG LEBIH
     * KECIL. Audit tidak selalu tahunan: perusahaan yang melewatkan satu
     * periode tetap punya pembanding, dan menuntut tahun persis
     * sebelumnya membuat pembandingnya hilang sama sekali — tanpa satu
     * pun tanda bahwa sesungguhnya ada.
     *
     * Dibatasi perusahaan yang sama. Batas itu ditulis tegas di sini dan
     * tidak menggantungkan diri pada scope: audit dibaca lewat relasi
     * dari sebuah baris yang sudah lolos scope, dan pembanding milik
     * perusahaan lain akan menghasilkan grafik yang membandingkan dua
     * perusahaan berbeda sebagai satu garis.
     */
    public static function sebelumnya(SmkpAudit $audit): ?SmkpAudit
    {
        return SmkpAudit::query()
            ->where('company_id', $audit->company_id)
            ->where('tahun', '<', $audit->tahun)
            ->where('id', '!=', $audit->id)
            ->orderByDesc('tahun')
            ->first();
    }

    /**
     * Seluruh audit satu perusahaan, urut tahun menaik.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,SmkpAudit>
     */
    public static function riwayat(?int $companyId)
    {
        return SmkpAudit::query()
            ->when($companyId !== null, fn ($q) => $q->where('company_id', $companyId))
            ->when($companyId === null, fn ($q) => $q->whereNull('company_id'))
            ->orderBy('tahun')
            ->orderBy('id')
            ->get();
    }

    /**
     * Nilai tiap butir pada sebuah audit, siap disandingkan.
     *
     * @return array<string,array{v:int|string|null,maks:int,capaian:?float}>
     */
    public static function butir(?SmkpAudit $audit): array
    {
        if (!$audit) return [];

        $hasil = (array) ($audit->hasil ?? []);
        $out   = [];

        foreach (Smkp::butir() as $b) {
            $v    = Smkp::nilaiButir($hasil, $b['kode']);
            $maks = (int) ($b['maks'] ?? 0);

            $out[$b['kode']] = [
                'v'       => $v,
                'maks'    => $maks,
                'capaian' => is_numeric($v) && $maks > 0
                    ? max(0.0, min(1.0, (float) $v / $maks))
                    : null,
            ];
        }

        return $out;
    }

    /**
     * Sandingan lengkap satu audit dengan pembandingnya.
     *
     * Bentuk keluarannya sengaja rata dan berkunci kode: layar penilaian
     * membacanya per butir, dan mencari di dalam daftar bersarang pada
     * tiap penggambaran baris adalah cara termudah membuat halaman
     * berisi 349 butir terasa berat.
     *
     * @return array{
     *   ada:bool, tahun:?int, tahunKini:int,
     *   butir:array<string,array>, sub:array<string,array>,
     *   elemen:array<string,array>, akhir:array
     * }
     */
    public static function untuk(SmkpAudit $audit, ?SmkpAudit $lalu = null): array
    {
        $lalu ??= self::sebelumnya($audit);

        $kini    = self::butir($audit);
        $terdulu = self::butir($lalu);

        /* HANYA BUTIR YANG PUNYA SESUATU UNTUK DIKATAKAN yang masuk
           daftar. Butir tanpa nilai tahun lalu memulangkan empat kunci
           bernilai null, dan 349 baris semacam itu menambah sekitar
           20 KB pada muatan tiap pembukaan halaman penilaian — demi
           keterangan yang seluruhnya berbunyi "tidak ada".

           Sisi Vue membacanya lewat pencarian berkunci dan sudah
           memulangkan null bagi kode yang tidak ada, jadi ketiadaan
           barisnya berarti persis sama dengan barisnya yang kosong.
           Ada uji yang menjaga batas muatan itu; ia menemukan
           pembengkakan ini. */
        $butir = [];
        foreach ($kini as $kode => $k) {
            $l = $terdulu[$kode] ?? null;

            if (($l['v'] ?? null) === null) continue;

            $butir[$kode] = [
                'lalu'      => $l['v'],
                'lalu_maks' => $l['maks'],
                'arah'      => self::arah($k['capaian'], $l['capaian']),
                'selisih'   => self::selisih($k['capaian'], $l['capaian']),
            ];
        }

        $hasilKini = (array) ($audit->hasil ?? []);
        $hasilLalu = (array) ($lalu?->hasil ?? []);

        $sub = [];
        $elemen = [];

        foreach (Smkp::elemen() as $e) {
            $rk = Smkp::rekapElemen($e, $hasilKini);
            $rl = $lalu ? Smkp::rekapElemen($e, $hasilLalu) : null;

            $elemen[$e['kode']] = [
                'nama'    => $e['nama'],
                'bobot'   => (int) ($e['bobot'] ?? 0),
                'kini'    => round($rk['capaian'] * 100, 1),
                'lalu'    => $rl ? round($rl['capaian'] * 100, 1) : null,
                'selisih' => $rl ? round(($rk['capaian'] - $rl['capaian']) * 100, 1) : null,
                'arah'    => self::arah($rk['capaian'], $rl['capaian'] ?? null),
            ];

            foreach ($e['sub'] as $s) {
                $sk = Smkp::rekapSub($s, $hasilKini);
                $sl = $lalu ? Smkp::rekapSub($s, $hasilLalu) : null;

                $sub[$s['kode']] = [
                    'nama'    => $s['nama'],
                    'elemen'  => $e['kode'],
                    'kini'    => round($sk['capaian'] * 100, 1),
                    'lalu'    => $sl ? round($sl['capaian'] * 100, 1) : null,
                    'selisih' => $sl ? round(($sk['capaian'] - $sl['capaian']) * 100, 1) : null,
                    'arah'    => self::arah($sk['capaian'], $sl['capaian'] ?? null),
                    'dinilai' => $sk['dinilai'],
                    'berlaku' => $sk['berlaku'],
                ];
            }
        }

        $rkAkhir = Smkp::rekap($hasilKini);
        $rlAkhir = $lalu ? Smkp::rekap($hasilLalu) : null;

        return [
            'ada'       => $lalu !== null,
            'tahun'     => $lalu?->tahun,
            'tahunKini' => (int) $audit->tahun,
            'butir'     => $butir,
            'sub'       => $sub,
            'elemen'    => $elemen,
            'akhir'     => [
                'kini'    => $rkAkhir['skor'],
                'lalu'    => $rlAkhir['skor'] ?? null,
                'selisih' => $rlAkhir ? round($rkAkhir['skor'] - $rlAkhir['skor'], 2) : null,
                'tingkatKini' => $rkAkhir['tingkat'],
                'tingkatLalu' => $rlAkhir['tingkat'] ?? null,
            ],
        ];
    }

    /**
     * Konsistensi antar tahun, dikelompokkan menurut yang perlu dilihat.
     *
     * Empat golongan, dan keempatnya sengaja dipisah:
     *
     *   naik         membaik — dirayakan, tetapi tidak menuntut apa-apa
     *   turun        memburuk — inilah yang pertama dibaca
     *   tetap_rendah bertahan di bawah ambang mayor dua tahun berturut.
     *                Ini golongan yang paling mudah hilang: selisihnya
     *                nol, jadi setiap tampilan yang mengurutkan menurut
     *                perubahan menaruhnya di tengah dan tidak seorang
     *                pun melihatnya, padahal ia yang paling lama rusak.
     *   tetap_baik   bertahan sempurna — bahan lembar OFI
     *
     * @return array{naik:list<array>,turun:list<array>,tetap_rendah:list<array>,tetap_baik:list<array>}
     */
    public static function konsistensi(array $banding): array
    {
        $out = ['naik' => [], 'turun' => [], 'tetap_rendah' => [], 'tetap_baik' => []];

        if (!$banding['ada']) return $out;

        foreach ($banding['sub'] as $kode => $s) {
            if ($s['lalu'] === null) continue;

            $baris = ['kode' => $kode] + $s;

            if ($s['selisih'] > 0)      { $out['naik'][]  = $baris; continue; }
            if ($s['selisih'] < 0)      { $out['turun'][] = $baris; continue; }

            if ($s['kini'] >= self::AMBANG_BAIK * 100)        $out['tetap_baik'][]   = $baris;
            elseif ($s['kini'] < self::AMBANG_RENDAH * 100)   $out['tetap_rendah'][] = $baris;
        }

        usort($out['naik'],  fn ($a, $b) => $b['selisih'] <=> $a['selisih']);
        usort($out['turun'], fn ($a, $b) => $a['selisih'] <=> $b['selisih']);
        usort($out['tetap_rendah'], fn ($a, $b) => $a['kini'] <=> $b['kini']);

        return $out;
    }

    /** naik | turun | tetap | baru | null — null berarti tak dapat dibandingkan. */
    private static function arah(?float $kini, ?float $lalu): ?string
    {
        if ($kini === null) return null;      // belum dinilai tahun ini
        if ($lalu === null) return 'baru';    // butir baru berlaku, atau audit pertama

        return match (true) {
            $kini > $lalu => 'naik',
            $kini < $lalu => 'turun',
            default       => 'tetap',
        };
    }

    private static function selisih(?float $kini, ?float $lalu): ?float
    {
        return $kini === null || $lalu === null ? null : round(($kini - $lalu) * 100, 1);
    }
}
