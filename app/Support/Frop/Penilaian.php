<?php

namespace App\Support\Frop;

use Carbon\CarbonImmutable;

/**
 * Aturan penilaian observasi operator loader — program FROP
 * (Field Reliability & Operator Performance), Operation People
 * Development.
 *
 * ── Sumbernya ──
 *
 * Berkas kerja "Observasi PTY-CT Loader OPD CAM": sheet Akumulatif
 * Observasi, Plan CT Reference, Operator Performance, KPI Report,
 * Dashboard Daily, dan Panduan Pengisian. Seluruh ambang dan urutan
 * kesimpulan di bawah diambil dari rumus berkas itu, sel demi sel.
 *
 * ── Yang SENGAJA berbeda dari berkasnya ──
 *
 * Rumus berkas itu memuat beberapa kesalahan yang, bila ditiru, akan
 * ikut pindah ke aplikasi dengan wibawa yang lebih besar daripada
 * lembar kerja. Masing-masing diukur terhadap 101 baris observasi yang
 * ada sebelum diputuskan:
 *
 * 1. Rekomendasi coaching (kolom AQ) pada 90 baris membandingkan CT
 *    dengan angka mati 22 detik, bukan Plan CT material (24/28/31).
 *    Akibatnya 52 dari 101 baris berstatus "ON TARGET" sekaligus
 *    disarankan coaching. Di sini rekomendasi membandingkan dengan Plan
 *    CT sesi itu — sama seperti kolom Status di sebelahnya.
 *
 * 2. "Imprv CT vs Prev" (AM) mengurangkan CT baris sebelumnya, yang
 *    pada 94 dari 100 kasus milik operator LAIN. Di sini pembandingnya
 *    sesi sebelumnya dari operator yang sama.
 *
 * 3. Tren operator (Operator Performance M) = CT terbaik − CT terburuk,
 *    yang selalu ≤ 0, sehingga setiap operator selalu "↑ Membaik". Di
 *    sini tren = CT sesi terakhir − CT sesi pertama, menurut tanggal.
 *
 * 4. % ON TARGET total (KPI K7) membagi dengan seluruh sel berumus,
 *    termasuk yang kosong: 89/295 = 30,2%, padahal 89/101 = 88,1%.
 *    Di sini penyebutnya sesi yang benar-benar punya CT.
 *
 * 5. Status AVG CT (KPI M8) membandingkan rata-rata CT dengan 22 detik.
 *    Karena Plan CT berbeda per material, rata-rata CT tidak dapat
 *    dibandingkan dengan satu angka; di sini yang dinilai rata-rata
 *    SELISIH terhadap plan masing-masing sesi.
 *
 * 6. Kesimpulan (AC) menganggap sesi tanpa data PTY sebagai "produksi
 *    tercapai", karena di Excel teks kosong selalu ≥ 0,9. Di sini sesi
 *    tanpa PTY disebut apa adanya.
 *
 * 7. Recurring Flag membandingkan teks '1' dengan angka 1; di Excel
 *    teks selalu lebih besar daripada angka, sehingga SEMUA temuan
 *    bertanda berulang. Di sini keberulangan dihitung dari kemunculan
 *    kategori masalah yang sama pada unit yang sama sebelumnya.
 */
final class Penilaian
{
    /* ═══════════════ acuan ═══════════════ */

    /** Plan CT per tingkat kesulitan material — sheet "Plan CT Reference". */
    public const PLAN_CT = ['easy' => 24, 'average' => 28, 'severe' => 31];

    public const LEVEL = [
        'easy' => [
            'label'  => 'Easy',
            'contoh' => 'OB Blasting OK, Soft Soil, Lumpur Padat, Pasiran Lunak, Clay Lunak, Material Dozingan Dozer',
        ],
        'average' => [
            'label'  => 'Average',
            'contoh' => 'Clay Alot, Sandy Soil, Dry Soil, Blastingan Setengah Keras/Bolder, Ripping',
        ],
        'severe' => [
            'label'  => 'Severe',
            'contoh' => 'OB Non Blasting, Blasting Keras, Material Kedudukan Lembek, Lumpur Cair',
        ],
    ];

    /**
     * Pembagian Plan CT ke komponennya — Dashboard Daily D25:D28.
     *
     * Plan tiap komponen = Plan CT × rasio / 22. Spotting TIDAK termasuk:
     * ia waktu truk bermanuver, bukan gerak excavator, dan Panduan
     * menegaskan Aktual CT = Digging + SWL + Dump + SWE.
     */
    public const RASIO_KOMPONEN = ['digging' => 9, 'swl' => 6, 'dump' => 3, 'swe' => 4];

    public const KOMPONEN = [
        'digging' => ['label' => 'Digging', 'ket' => 'Komponen terbesar – kunci CT'],
        'swl'     => ['label' => 'Swing Loaded (SWL)', 'ket' => 'Pengaruh sudut swing'],
        'dump'    => ['label' => 'Dump', 'ket' => 'Waktu buang material'],
        'swe'     => ['label' => 'Swing Empty (SWE)', 'ket' => 'Kembali ke posisi dig'],
    ];

    /** Loading time standar: 1 menit 30 detik (AC: TIME(0,1,30)). */
    public const LOADING_MAKS = 90;

    /** Jumlah passing standar per hauler (AC: N Passing > 5). */
    public const N_PASSING_MAKS = 5;

    /** Spotting dan digging dianggap tinggi di atas 10 detik (AQ, Spot Flag). */
    public const SPOTTING_TINGGI = 10;
    public const DIGGING_TINGGI  = 10;

    /** Spotting rata-rata operator dianggap tinggi di atas 20 detik (Operator Performance P). */
    public const SPOTTING_RATA_TINGGI = 20;

    /** Ambang pencapaian PTY. */
    public const PTY_TERCAPAI = 0.9;
    public const PTY_CUKUP    = 0.75;

    /* ═══════════════ satu sesi ═══════════════ */

    public static function planCt(?string $level): ?float
    {
        return isset(self::PLAN_CT[$level]) ? (float) self::PLAN_CT[$level] : null;
    }

    /**
     * Aktual CT = Digging + SWL + Dump + SWE.
     *
     * Null bila salah satunya kosong. Excel hanya memeriksa kolom
     * Digging, sehingga komponen lain yang kosong dihitung nol dan CT-nya
     * tampak lebih cepat daripada kenyataan.
     */
    public static function aktualCt(array $s): ?float
    {
        $n = 0.0;

        foreach (array_keys(self::RASIO_KOMPONEN) as $k) {
            if (! is_numeric($s[$k] ?? null)) return null;
            $n += (float) $s[$k];
        }

        return round($n, 2);
    }

    public static function selisih(array $s): ?float
    {
        $a = self::aktualCt($s);
        $p = self::plan($s);

        return $a === null || $p === null ? null : round($a - $p, 2);
    }

    /** Plan CT sesi: yang tercatat pada sesinya, atau menurut levelnya. */
    public static function plan(array $s): ?float
    {
        return is_numeric($s['plan_ct'] ?? null) ? (float) $s['plan_ct'] : self::planCt($s['level'] ?? null);
    }

    public static function onTarget(array $s): ?bool
    {
        $a = self::aktualCt($s);
        $p = self::plan($s);

        return $a === null || $p === null ? null : $a <= $p;
    }

    /** Pencapaian PTY (0–n), atau null bila target/aktual belum diisi. */
    public static function pencapaian(array $s): ?float
    {
        $t = $s['target_pty'] ?? null;
        $a = $s['aktual_pty'] ?? null;

        if (! is_numeric($t) || ! is_numeric($a) || (float) $t <= 0) return null;

        return (float) $a / (float) $t;
    }

    public static function gapBcm(array $s): ?float
    {
        $t = $s['target_pty'] ?? null;
        $a = $s['aktual_pty'] ?? null;

        return is_numeric($t) && is_numeric($a) ? (float) $a - (float) $t : null;
    }

    /**
     * Rincian komponen terhadap plannya.
     *
     * @return list<array{kunci:string,label:string,ket:string,aktual:?float,plan:?float,selisih:?float,ok:?bool,porsi:?float}>
     */
    public static function komponen(array $s): array
    {
        $plan  = self::plan($s);
        $total = self::aktualCt($s);
        $out   = [];

        foreach (self::RASIO_KOMPONEN as $k => $rasio) {
            $aktual = is_numeric($s[$k] ?? null) ? (float) $s[$k] : null;
            $p      = $plan === null ? null : round($plan * $rasio / array_sum(self::RASIO_KOMPONEN), 1);

            $out[] = [
                'kunci'   => $k,
                'label'   => self::KOMPONEN[$k]['label'],
                'ket'     => self::KOMPONEN[$k]['ket'],
                'aktual'  => $aktual,
                'plan'    => $p,
                'selisih' => $aktual === null || $p === null ? null : round($aktual - $p, 2),
                'ok'      => $aktual === null || $p === null ? null : $aktual <= $p,
                'porsi'   => $aktual === null || ! $total ? null : $aktual / $total,
            ];
        }

        return $out;
    }

    public static function spotTinggi(array $s): bool
    {
        return is_numeric($s['spotting'] ?? null) && (float) $s['spotting'] > self::SPOTTING_TINGGI;
    }

    /**
     * Kesimpulan hasil observasi — kolom AC, urutan pemeriksaannya sama.
     *
     * @return array{kode:string,teks:string,nada:string}
     */
    public static function kesimpulan(array $s): array
    {
        $on = self::onTarget($s);

        if ($on === null) {
            return ['kode' => 'belum', 'teks' => 'CT belum dapat dinilai – komponen cycle time belum lengkap', 'nada' => 'netral'];
        }

        if (! $on) {
            return ['kode' => 'ct_over', 'teks' => 'CT belum tercapai – fokus perbaikan digging/spotting', 'nada' => 'gawat'];
        }

        $pty = self::pencapaian($s);

        if ($pty === null) {
            return ['kode' => 'pty_kosong', 'teks' => 'CT tercapai – data PTY belum diisi, produksi belum dapat dinilai', 'nada' => 'netral'];
        }

        if ($pty >= self::PTY_TERCAPAI) {
            return ['kode' => 'tercapai', 'teks' => 'CT & Produksi tercapai – pertahankan', 'nada' => 'baik'];
        }

        $loading = self::loadingLewat($s);
        $pass    = self::passingLewat($s);
        $heap    = ($s['bucket_heap'] ?? null) === false;

        if ($loading && $pass && $heap) {
            return ['kode' => 'loading_pass_heap', 'teks' => 'CT tercapai tapi produksi rendah: Loading time over (N Passing >5, bucket tidak heap)', 'nada' => 'ingat'];
        }
        if ($loading) {
            return ['kode' => 'loading', 'teks' => 'CT tercapai tapi produksi rendah: Loading time melebihi standar (>1:30)', 'nada' => 'ingat'];
        }
        if ($pass) {
            return ['kode' => 'passing', 'teks' => 'CT tercapai tapi produksi rendah: N Passing melebihi standar (>5x)', 'nada' => 'ingat'];
        }
        if ($heap) {
            return ['kode' => 'heap', 'teks' => 'CT tercapai tapi produksi rendah: Bucket tidak heap', 'nada' => 'ingat'];
        }

        return ['kode' => 'faktor_lain', 'teks' => 'CT tercapai, produksi belum optimal – cek faktor lain (cuaca/jarak hauling)', 'nada' => 'ingat'];
    }

    public static function loadingLewat(array $s): bool
    {
        return is_numeric($s['loading_detik'] ?? null) && (int) $s['loading_detik'] > self::LOADING_MAKS;
    }

    /**
     * N Passing dari isian bebas.
     *
     * Berkasnya memuat angka, teks angka ('5'), dan rentang ('5-6').
     * Rentang diambil BATAS ATASNYA: "5–6 bucket" berarti sebagian hauler
     * butuh enam passing, dan itulah yang melampaui standar. Excel
     * membandingkan teks '5' > 5 dan menjawab benar, sehingga delapan
     * sesi berpassing lima ikut dinyatakan melebihi standar.
     */
    public static function angkaPassing(mixed $v): ?float
    {
        if (is_numeric($v)) return (float) $v;

        if (is_string($v) && preg_match_all('/\d+(?:[.,]\d+)?/', $v, $m)) {
            return max(array_map(fn ($x) => (float) str_replace(',', '.', $x), $m[0]));
        }

        return null;
    }

    public static function passingLewat(array $s): bool
    {
        $n = self::angkaPassing($s['n_passing'] ?? null);

        return $n !== null && $n > self::N_PASSING_MAKS;
    }

    /**
     * Rekomendasi coaching — kolom AQ, dibandingkan dengan Plan CT sesi.
     *
     * @return array{kode:string,teks:string}
     */
    public static function rekomendasi(array $s): array
    {
        $on = self::onTarget($s);

        if ($on === null) return ['kode' => 'belum', 'teks' => '–'];
        if ($on)          return ['kode' => 'pertahankan', 'teks' => 'Pertahankan Performa'];

        if (is_numeric($s['digging'] ?? null) && (float) $s['digging'] > self::DIGGING_TINGGI) {
            return ['kode' => 'digging', 'teks' => 'Coaching: Teknik Digging – kurangi beban bucket'];
        }
        if (self::spotTinggi($s)) {
            return ['kode' => 'spotting', 'teks' => 'Coaching: Spotting – koordinasi hauler rotation'];
        }

        return ['kode' => 'metode', 'teks' => 'Coaching: Cek metode operasi & posisi track'];
    }

    /**
     * Status keseluruhan satu sesi — Dashboard Daily Q13.
     *
     * Berkasnya membandingkan CT TERBAIK operator dari semua sesi dengan
     * Plan CT sesi terpilih, sehingga sesi yang OVER tetap dapat berlabel
     * EXCELLENT asalkan operatornya pernah cepat di sesi lain. Di sini
     * yang dinilai CT sesi itu sendiri.
     *
     * @return array{kode:string,teks:string,nada:string}
     */
    public static function statusSesi(array $s): array
    {
        $on  = self::onTarget($s);
        $pty = self::pencapaian($s) ?? 0.0;

        if ($on === null) return ['kode' => 'belum', 'teks' => '–', 'nada' => 'netral'];

        if ($on && $pty >= self::PTY_TERCAPAI) return ['kode' => 'excellent', 'teks' => 'EXCELLENT', 'nada' => 'baik'];
        if ($on && $pty >= self::PTY_CUKUP)    return ['kode' => 'good', 'teks' => 'GOOD', 'nada' => 'baik'];
        if ($on)                               return ['kode' => 'ct_ok_pty_low', 'teks' => 'CT OK – PTY LOW', 'nada' => 'ingat'];
        if ($pty >= self::PTY_TERCAPAI)        return ['kode' => 'good_pty', 'teks' => 'GOOD – PTY OK', 'nada' => 'ingat'];

        return ['kode' => 'critical', 'teks' => 'CRITICAL', 'nada' => 'gawat'];
    }

    /* ═══════════════ temuan ═══════════════ */

    /**
     * Kategori masalah. Empat yang pertama adalah kategori "Ringkasan
     * Frekuensi Masalah" pada sheet Problem & CA Tracker; dua berikutnya
     * ditambahkan karena temuan tentang kondisi unit dan jalan cukup
     * sering muncul dan tidak cocok dengan keempatnya.
     */
    public const KATEGORI = [
        'unit'     => 'Kondisi Unit',
        'jalan'    => 'Jalan & Lingkungan',
        'hauler'   => 'Hauler Rotation / Spotting',
        'material' => 'Material Hard / Free Dig',
        'metode'   => 'Posisi / Metode Exc',
        'front'    => 'Front Condition (Boulder/Undulating)',
        'lain'     => 'Lainnya',
    ];

    /**
     * Kata kunci per kategori, DIPERIKSA BERURUTAN: pasangan pertama
     * yang cocok menang. Satu kategori boleh muncul lebih dari sekali.
     *
     * Urutannya menentukan dan diuji terhadap seluruh butir temuan pada
     * berkas asli:
     * - "kuku bucket tumpul" kondisi unit, bukan teknik bucket;
     * - "jalan disposal undulating" masalah jalan, bukan front;
     * - kondisi TANAH front ("base front lembek sehingga hauler amblas",
     *   "front rump up menyebabkan hauler selip") diperiksa SEBELUM
     *   hauler, karena haulernya hanya korban dari front itu;
     * - "MF 0.9" adalah match factor loader–hauler, jadi masalah hauler.
     */
    private const KATA_KUNCI = [
        ['unit',     ['tumpul', 'low power', 'lemot', 'lambat', 'swing brake', 'kendor', 'problem']],
        ['jalan',    ['jalan', 'debu', 'kabut', 'asap', 'rambu', 'persimpangan', 'simpangan', 'akses keluar']],
        ['front',    ['amblas', 'lembek', 'rump up', 'undulating', 'boulder', 'slippery', 'genangan', 'ber air',
                      'berair', 'pasir', 'penyempitan', 'after rain', 'hujan']],
        ['hauler',   ['hauler', 'menggantung', 'spotting', 'mundur', 'manuver', 'cross loading', 'continue', 'mf ']],
        ['material', ['material', 'materil', 'keras', 'alot', 'a lot', 'freedig', 'free dig', 'lengket',
                      'blasting', 'bolder']],
        ['metode',   ['track', 'sudut', 'double bench', 'doble bench', 'top loading', 'jenjang', 'kedudukan',
                      'dudukan', 'sprocket', 'idler', 'reposisi', 'heap', 'travel', 'double side', 'miring',
                      'sloping', 'scraping', 'penimbunan', 'slope', 'kombinasi boom']],
        ['front',    ['front', 'fornt', 'fron ', 'font', 'sempit', 'crowded', 'crowder', 'air', 'blast hole',
                      'tanggul', 'menanjak', 'grade', 'blind spot']],
    ];

    /**
     * Butir temuan dari satu teks bebas.
     *
     * Dipisah titik koma, koma, ATAU titik yang diikuti spasi. Sebagian
     * besar observasi memakai titik koma, tetapi yang terbaru menulis
     * "Track melintang bench, sudut passing 90°. Front undulating" —
     * dipisah titik koma saja, seluruh kalimat itu menjadi satu butir
     * dan hanya kategori pertamanya yang terhitung. Titik di dalam angka
     * ("MF 0.9") tidak ikut memisah karena tidak diikuti spasi.
     *
     * @return list<string>
     */
    public static function butirTemuan(?string $teks): array
    {
        $out = [];

        foreach (preg_split('/[;,]|\.(?=\s|$)/u', (string) $teks) as $b) {
            $b = trim(preg_replace('/\s+/u', ' ', $b), " .\t\n");
            if ($b !== '' && $b !== '-' && $b !== '–') $out[] = $b;
        }

        return $out;
    }

    public static function kategoriButir(string $butir): string
    {
        $t = ' '.mb_strtolower($butir).' ';

        foreach (self::KATA_KUNCI as [$kat, $kata]) {
            foreach ($kata as $k) {
                if (str_contains($t, $k)) return $kat;
            }
        }

        return 'lain';
    }

    /** @return list<string> kategori unik dari satu teks temuan, berurutan kemunculan. */
    public static function kategoriTemuan(?string $teks): array
    {
        $out = [];

        foreach (self::butirTemuan($teks) as $b) {
            $k = self::kategoriButir($b);
            if (! in_array($k, $out, true)) $out[] = $k;
        }

        return $out;
    }

    /** Kunci pembanding unit: "EX 750", "Ex 750", "EX750" adalah unit yang sama. */
    public static function kunciUnit(?string $unit): string
    {
        $u = mb_strtoupper(preg_replace('/\s+/', '', (string) $unit));

        /* "EX699/PC1250" dan "EX699" juga unit yang sama; yang di
           belakang garis miring adalah modelnya. */
        return explode('/', $u)[0];
    }

    public static function kunciOrang(?string $nama): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $nama)));
    }

    /* ═══════════════ satu operator ═══════════════ */

    /**
     * Rekap satu operator — sheet Operator Performance.
     *
     * @param  list<array>  $sesi  sesi operator itu, berurutan tanggal naik
     */
    public static function rekapOperator(array $sesi): array
    {
        $ct   = array_values(array_filter(array_map([self::class, 'aktualCt'], $sesi), fn ($v) => $v !== null));
        $n    = count($sesi);
        $on   = count(array_filter($sesi, fn ($s) => self::onTarget($s) === true));
        $dgn  = count(array_filter($sesi, fn ($s) => self::onTarget($s) !== null));
        $pty  = array_values(array_filter(array_map([self::class, 'pencapaian'], $sesi), fn ($v) => $v !== null));
        $heap = count(array_filter($sesi, fn ($s) => ($s['bucket_heap'] ?? null) === true));

        $rata = fn (array $a) => $a ? array_sum($a) / count($a) : null;
        $kolom = fn (string $k) => array_values(array_filter(array_map(
            fn ($s) => is_numeric($s[$k] ?? null) ? (float) $s[$k] : null, $sesi), fn ($v) => $v !== null));

        $konsistensi = $dgn ? (float) ($on / $dgn) : null;
        $tren        = count($ct) >= 2 ? round(end($ct) - $ct[0], 2) : null;

        $r = [
            'total'       => $n,
            'rata_ct'     => $rata($ct),
            'terbaik'     => $ct ? min($ct) : null,
            'terburuk'    => $ct ? max($ct) : null,
            'on_target'   => $on,
            'konsistensi' => $konsistensi,
            'rata_spot'   => $rata($kolom('spotting')),
            'rata_dig'    => $rata($kolom('digging')),
            'rata_pty'    => $rata($pty),
            'heap'        => $n ? (float) ($heap / $n) : null,
            'tren'        => $tren,
            'arah'        => $tren === null ? null : ($tren < 0 ? 'membaik' : ($tren > 0 ? 'menurun' : 'stabil')),
            'performer'   => self::performer($konsistensi, $rata($pty)),
        ];

        $r['catatan'] = self::catatanCoaching($r);

        return $r;
    }

    /**
     * Label performer operator.
     *
     * Berkas asalnya menurunkan label ini dari konsistensi CT SAJA,
     * sementara catatan coaching di sebelahnya memeriksa PTY lebih
     * dulu — sehingga operator yang sama berlabel "Top Performer" dan
     * bercatatan "Average – PTY 76%" atau "Perlu Coaching – PTY rendah"
     * sekaligus. Di sini keduanya memakai ambang yang sama: PTY rata-rata
     * di bawah 60% berarti perlu coaching, dan Top Performer menuntut
     * PTY rata-rata minimal 80% di samping konsistensi CT.
     *
     * @return array{kode:string,teks:string,nada:string}
     */
    public static function performer(?float $konsistensi, ?float $pty = null): array
    {
        if ($konsistensi === null) return ['kode' => 'belum', 'teks' => '–', 'nada' => 'netral'];

        if ($konsistensi < 0.5 || ($pty !== null && $pty < 0.6)) {
            return ['kode' => 'coaching', 'teks' => 'Perlu Coaching', 'nada' => 'gawat'];
        }
        if ($konsistensi >= 0.8 && ($pty === null || $pty >= 0.8)) {
            return ['kode' => 'top', 'teks' => 'Top Performer', 'nada' => 'baik'];
        }

        return ['kode' => 'average', 'teks' => 'Average', 'nada' => 'ingat'];
    }

    /** Catatan coaching operator — Operator Performance kolom P, urutannya sama. */
    public static function catatanCoaching(array $r): string
    {
        if (! $r['total']) return 'Belum ada data observasi';

        $pct = fn (?float $v) => $v === null ? '–' : round($v * 100).'%';
        $pty = $r['rata_pty'];
        $kon = $r['konsistensi'];
        $hp  = $r['heap'] ?? 0;

        if ($pty !== null && $pty < 0.6) {
            $t = 'Perlu Coaching – PTY rendah ('.$pct($pty).'); fokus isian bucket penuh & kurangi delay spotting';
        } elseif ($kon !== null && $kon < 0.5) {
            $t = 'Perlu Coaching – CT belum konsisten ('.$pct($kon).' on-target); review teknik digging & spotting';
        } elseif ($hp < 0.8) {
            $t = 'Heap belum optimal ('.$pct($hp).' sesi heap); jaga bucket terisi penuh & rata demi PTY';
        } elseif (($pty !== null && $pty < 0.8) || ($kon !== null && $kon < 0.8)) {
            $t = 'Average – PTY '.$pct($pty).' & CT '.$pct($kon).' on-target; fokus konsistensi & stabilkan PTY';
        } elseif ($pty !== null && $pty >= 0.8 && $kon !== null && $kon >= 0.8 && $hp >= 0.999) {
            $t = 'Top Performer – PTY '.$pct($pty).' & CT konsisten ('.$pct($kon).'); pertahankan & jadi role model';
        } else {
            $t = 'Average – PTY '.$pct($pty).' & CT '.$pct($kon).'; jaga konsistensi';
        }

        if ($r['arah'] === 'menurun') $t .= '; tren CT menurun';
        if ($r['rata_spot'] !== null && $r['rata_spot'] > self::SPOTTING_RATA_TINGGI) {
            $t .= '; spotting tinggi ('.round($r['rata_spot']).'s)';
        }

        return $t;
    }

    /* ═══════════════ KPI periode ═══════════════ */

    /** Target KPI — sheet KPI Report kolom F. */
    public const TARGET_KPI = [
        'sesi_mingguan' => 10,     // 2 sesi/hari
        'on_target'     => 0.8,
        'pty'           => 0.9,
        'ca_closed'     => 0.7,
        'berulang'      => 0.2,
        'operator'      => 10,
    ];

    /**
     * Pekan dalam satu bulan, sama dengan KPI Report: 1–7, 8–14, 15–21,
     * 22–akhir bulan.
     *
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    public static function pekan(int $tahun, int $bulan): array
    {
        $awal  = CarbonImmutable::create($tahun, $bulan, 1)->startOfDay();
        $akhir = $awal->endOfMonth()->startOfDay();

        return [
            [$awal, $awal->addDays(6)],
            [$awal->addDays(7), $awal->addDays(13)],
            [$awal->addDays(14), $awal->addDays(20)],
            [$awal->addDays(21), $akhir],
        ];
    }

    /**
     * Keberulangan: tiap pasangan (sesi, kategori) ditandai berulang bila
     * kategori yang sama sudah tercatat pada UNIT yang sama di sesi yang
     * lebih awal dalam kumpulan yang diberikan.
     *
     * Per unit, bukan seluruh situs: "front undulating" yang muncul di
     * dua excavator berbeda adalah dua front, bukan satu masalah yang
     * kembali. Yang perlu ditindaklanjuti adalah masalah yang kembali di
     * tempat yang sama sesudah tindakan perbaikannya dinyatakan selesai.
     *
     * @param  list<array>  $sesi  berurutan tanggal naik; tiap butir punya 'id','unit','temuan'
     * @return array<int|string, list<string>>  id sesi => kategori yang berulang
     */
    public static function berulang(array $sesi): array
    {
        $pernah = [];
        $out    = [];

        foreach ($sesi as $s) {
            $u = self::kunciUnit($s['unit'] ?? '');
            $out[$s['id']] = [];

            foreach (self::kategoriTemuan($s['temuan'] ?? '') as $k) {
                if (isset($pernah[$u][$k])) $out[$s['id']][] = $k;
                $pernah[$u][$k] = true;
            }
        }

        return $out;
    }

    /**
     * KPI satu bulan — sheet KPI Report.
     *
     * @param  list<array>  $sesi   sesi bulan itu, berurutan tanggal naik
     * @param  array|null   $ulang  hasil berulang() atas SELURUH riwayat. Tanpa
     *                              itu keberulangan dihitung dari awal bulan saja,
     *                              dan masalah yang kembali pada pekan pertama
     *                              bulan berikutnya tidak pernah terhitung.
     */
    public static function kpi(array $sesi, int $tahun, int $bulan, ?array $ulang = null): array
    {
        $pekan   = self::pekan($tahun, $bulan);
        $ulang ??= self::berulang($sesi);
        $perPekan = array_fill(0, 4, []);

        foreach ($sesi as $s) {
            $t = CarbonImmutable::parse($s['tanggal'])->startOfDay();
            foreach ($pekan as $i => [$a, $z]) {
                if ($t->betweenIncluded($a, $z)) { $perPekan[$i][] = $s; break; }
            }
        }

        $hitung = function (array $kumpulan) use ($ulang) {
            $dinilai = array_filter($kumpulan, fn ($s) => self::onTarget($s) !== null);
            $on      = array_filter($dinilai, fn ($s) => self::onTarget($s));
            $sel     = array_map([self::class, 'selisih'], $dinilai);
            $ct      = array_map([self::class, 'aktualCt'], $dinilai);
            $pty     = array_values(array_filter(array_map([self::class, 'pencapaian'], $kumpulan), fn ($v) => $v !== null));

            $bertemuan = array_filter($kumpulan, fn ($s) => self::kategoriTemuan($s['temuan'] ?? '') !== []);
            $closed    = array_filter($bertemuan, fn ($s) => ($s['status_ca'] ?? '') === 'Closed');

            $masalah = 0; $ulangN = 0;
            foreach ($kumpulan as $s) {
                $masalah += count(self::kategoriTemuan($s['temuan'] ?? ''));
                $ulangN  += count($ulang[$s['id']] ?? []);
            }

            $bagi = fn ($a, $b) => $b ? (float) ($a / $b) : null;

            return [
                'sesi'      => count($kumpulan),
                'on_target' => $bagi(count($on), count($dinilai)),
                'rata_ct'   => $ct ? array_sum($ct) / count($ct) : null,
                'rata_sel'  => $sel ? array_sum($sel) / count($sel) : null,
                'pty'       => $pty ? array_sum($pty) / count($pty) : null,
                'ca_closed' => $bagi(count($closed), count($bertemuan)),
                'berulang'  => $bagi($ulangN, $masalah),
                'operator'  => count(array_unique(array_map(fn ($s) => self::kunciOrang($s['operator'] ?? ''), $kumpulan))),
            ];
        };

        $pekanan = array_map($hitung, $perPekan);
        $total   = $hitung($sesi);

        return [
            'pekan'  => array_map(fn ($p) => [$p[0]->toDateString(), $p[1]->toDateString()], $pekan),
            'nilai'  => $pekanan,
            'total'  => $total,
            'target_sesi_bulan' => self::TARGET_KPI['sesi_mingguan'] * count($pekan),
        ];
    }
}
