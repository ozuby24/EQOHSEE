<?php

namespace App\Support;

use App\Models\{Certificate, Course, Enrollment, ModuleCompletion, QuizAttempt,
    SopEvaluationAttempt};
use Illuminate\Support\Carbon;

/**
 * Deret angka untuk grafik dasbor pembelajaran.
 *
 * MILIK SATU ORANG, bukan seluruh situs. Halaman /dashboard menjawab
 * pertanyaan seorang peserta tentang dirinya sendiri; angka seluruh
 * situs sudah punya rumahnya di /dasbor. Mencampur keduanya membuat
 * "60% selesai" dapat berarti dua hal yang berbeda pada satu layar —
 * dan yang membaca tidak punya cara tahu yang mana.
 *
 * Satu-satunya pengecualian ditandai tegas: `penyelesaianKursus()`
 * memang seluruh peserta, dan hanya diberikan kepada pelatih dan
 * administrator yang memang bertugas melihat orang lain.
 *
 * TIGA ATURAN, sama dengan DasborGrafik dan dengan alasan yang sama:
 *
 *  1. Hari kosong dikembalikan NOL, bukan dilewati. Grafik yang
 *     melompati hari sepi menyambung dua titik berjauhan menjadi garis
 *     landai, dan berhentinya belajar terbaca sebagai penurunan pelan.
 *
 *  2. Penyebut nol memulangkan NULL, bukan nol dan bukan seratus.
 *
 *  3. Kelompok kosong tetap disebut. Donat yang menyembunyikan irisan
 *     bernilai nol membuat "tidak ada yang gagal" tidak dapat
 *     dibedakan dari "nilainya belum diisi".
 */
final class BelajarGrafik
{
    /** Pilihan rentang hari yang boleh diminta dari layar. */
    public const RENTANG = [7, 30, 90];

    public const RENTANG_BAWAAN = 30;

    /**
     * Pita nilai, dari yang terendah.
     *
     * Batasnya 70 mengikuti `pass_score` bawaan kuis di aplikasi ini,
     * sehingga pita "60–69" jatuh persis di sebelah bawah ambang lulus
     * alih-alih memotongnya di tengah. "70–79" diberi warna ingat, bukan
     * baik: ia memang lulus, tetapi lulus dengan selisih satu jawaban —
     * dan itulah yang perlu dilihat orang yang membaca grafik ini.
     *
     * Kunci `keadaan` HARUS salah satu yang benar-benar ada pada
     * resources/js/Grafik/warna.ts — baik, ingat, serius, gawat,
     * netral. Kunci asing tidak menimbulkan galat apa pun: batangnya
     * digambar tanpa warna, dan grafiknya tetap terlihat utuh.
     *
     * @var list<array{label:string,min:int,maks:int,keadaan:string}>
     */
    private const PITA = [
        ['label' => 'Di bawah 60', 'min' => 0,  'maks' => 59,  'keadaan' => 'gawat'],
        ['label' => '60–69',       'min' => 60, 'maks' => 69,  'keadaan' => 'serius'],
        ['label' => '70–79',       'min' => 70, 'maks' => 79,  'keadaan' => 'ingat'],
        ['label' => '80–89',       'min' => 80, 'maks' => 89,  'keadaan' => 'baik'],
        ['label' => '90–100',      'min' => 90, 'maks' => 100, 'keadaan' => 'baik'],
    ];

    /**
     * Kegiatan belajar harian: modul yang dituntaskan dan percobaan
     * kuis maupun evaluasi SOP.
     *
     * KEDUANYA BERDAMPINGAN karena satu tanpa yang lain menyesatkan.
     * Modul yang dibuka tanpa satu pun kuis dikerjakan adalah membaca,
     * bukan belajar; kuis yang diulang tanpa modul baru adalah
     * mengejar nilai. Yang membedakan keduanya hanya terlihat bila
     * digambar pada sumbu waktu yang sama.
     *
     * @return array{label:list<string>,modul:list<int>,uji:list<int>}
     */
    public static function kegiatan(int $userId, Carbon $kini, int $hari): array
    {
        $mulai = $kini->copy()->subDays($hari - 1)->startOfDay();

        $modul = self::perHari(ModuleCompletion::query(), $userId, $mulai);
        $kuis  = self::perHari(QuizAttempt::query(), $userId, $mulai);
        $sop   = self::perHari(SopEvaluationAttempt::query(), $userId, $mulai);

        $label = []; $mod = []; $uji = [];

        foreach (self::deretHari($mulai, $kini) as $t) {
            $k = $t->toDateString();

            $label[] = $t->format('d/m');
            $mod[]   = (int) ($modul[$k] ?? 0);
            $uji[]   = (int) ($kuis[$k] ?? 0) + (int) ($sop[$k] ?? 0);
        }

        return ['label' => $label, 'modul' => $mod, 'uji' => $uji];
    }

    /**
     * Kemajuan tiap kursus yang diikuti, dari yang paling tertinggal.
     *
     * DARI YANG TERENDAH, bukan tertinggi. Daftar ini menjawab "mana
     * yang harus saya kerjakan berikutnya", dan yang menjawabnya adalah
     * kursus yang paling jauh dari selesai — bukan yang hampir tuntas.
     * Karena itu pemanggilnya memakai `apaAdanya` pada grafik batang.
     *
     * @return list<array{label:string,nilai:int,keadaan:string,url:string}>
     */
    public static function kemajuanKursus($enrollments): array
    {
        $baris = [];

        foreach ($enrollments as $e) {
            $c = $e->course;
            if (!$c) continue;

            $n = max(0, min(100, (int) $e->progress));

            $baris[] = [
                'label'   => $c->title,
                'nilai'   => $n,
                'keadaan' => $n >= 100 ? 'baik' : ($n > 0 ? 'ingat' : 'netral'),
                'url'     => route('learn.show', $c),
            ];
        }

        usort($baris, fn ($a, $b) => $a['nilai'] <=> $b['nilai']);

        return $baris;
    }

    /**
     * Status seluruh kursus yang tersedia bagi orang ini.
     *
     * "Belum diikuti" DIHITUNG, bukan dihilangkan: tanpa irisan itu,
     * donatnya menggambarkan seorang peserta yang telah menyelesaikan
     * segalanya padahal ia baru mendaftar satu dari enam.
     *
     * @return list<array{label:string,nilai:int,keadaan:string}>
     */
    public static function statusKursus($enrollments, int $totalKursus): array
    {
        $selesai  = $enrollments->where('status', 'finished')->count();
        $berjalan = $enrollments->where('status', 'ongoing')->count();

        /* Pendaftaran yang statusnya bukan keduanya tetap terhitung
           "berjalan": ia sudah didaftarkan, dan menghitungnya sebagai
           "belum diikuti" akan membuat jumlah irisannya melampaui
           jumlah kursus yang ada. */
        $lain = max(0, $enrollments->count() - $selesai - $berjalan);

        return [
            ['label' => 'Selesai',       'nilai' => $selesai,          'keadaan' => 'baik'],
            ['label' => 'Berjalan',      'nilai' => $berjalan + $lain, 'keadaan' => 'ingat'],
            ['label' => 'Belum diikuti', 'nilai' => max(0, $totalKursus - $enrollments->count()),
                                         'keadaan' => 'netral'],
        ];
    }

    /**
     * Sebaran nilai seluruh percobaan kuis dan evaluasi SOP.
     *
     * SELURUH PERCOBAAN, bukan yang terbaik saja. Nilai terbaik
     * menjawab "apakah saya lulus"; sebaran seluruh percobaan menjawab
     * "seberapa jauh saya dari lulus" — dan hanya yang kedua yang
     * berguna bagi orang yang belum lulus.
     *
     * @return list<array{label:string,nilai:int,keadaan:string}>
     */
    public static function sebaranNilai(int $userId): array
    {
        $nilai = QuizAttempt::where('user_id', $userId)->pluck('score')
            ->merge(SopEvaluationAttempt::where('user_id', $userId)->pluck('score'))
            ->filter(fn ($n) => $n !== null)
            ->map(fn ($n) => (float) $n);

        return collect(self::PITA)->map(fn ($p) => [
            'label'   => $p['label'],
            'nilai'   => $nilai->filter(fn ($n) => $n >= $p['min'] && $n <= $p['maks'])->count(),
            'keadaan' => $p['keadaan'],
        ])->values()->all();
    }

    /**
     * Sertifikat yang terbit per bulan, enam bulan ke belakang.
     *
     * DeretBulan, bukan `subMonths($i)` dari tanggal hari ini: yang
     * kedua meluap pada tanggal 29–31 sehingga satu bulan terhitung dua
     * kali dan bulan lain tidak pernah muncul — grafiknya tetap enam
     * batang, dua di antaranya berlabel sama, dan justru karena itu ia
     * tidak terlihat salah.
     *
     * @return list<array{label:string,nilai:int}>
     */
    public static function sertifikatBulanan(int $userId, Carbon $kini, int $bulan = 6): array
    {
        $keluar = [];

        foreach (DeretBulan::mundur($kini, $bulan) as $b) {
            $keluar[] = [
                'label' => $b->translatedFormat('M y'),
                'nilai' => Certificate::where('user_id', $userId)
                    ->whereYear('issued_at', $b->year)
                    ->whereMonth('issued_at', $b->month)->count(),
            ];
        }

        return $keluar;
    }

    /**
     * SELURUH PESERTA — hanya untuk pelatih dan administrator.
     *
     * Berapa persen peserta yang mendaftar telah menyelesaikan tiap
     * kursus. Kursus tanpa satu pun pendaftar memulangkan `null`, bukan
     * nol: "0% selesai" pada kursus yang belum pernah dibuka siapa pun
     * menuduh kursusnya, padahal yang terjadi tidak ada yang mendaftar.
     *
     * @return list<array{label:string,nilai:int,keadaan:string,peserta:int}>
     */
    public static function penyelesaianKursus(int $batas = 8): array
    {
        $per = Enrollment::selectRaw('course_id, count(*) as peserta,'
                .' sum(case when status = ? then 1 else 0 end) as selesai', ['finished'])
            ->groupBy('course_id')->get()->keyBy('course_id');

        $keluar = [];

        foreach (Course::orderBy('title')->get() as $c) {
            $b = $per[$c->id] ?? null;
            $peserta = (int) ($b->peserta ?? 0);

            if ($peserta === 0) continue;

            $persen = (int) round(((int) $b->selesai / $peserta) * 100);

            $keluar[] = [
                'label'   => $c->title,
                'nilai'   => $persen,
                'peserta' => $peserta,
                'keadaan' => $persen >= 80 ? 'baik' : ($persen >= 40 ? 'ingat' : 'gawat'),
            ];
        }

        usort($keluar, fn ($a, $b) => $a['nilai'] <=> $b['nilai']);

        return array_slice($keluar, 0, $batas);
    }

    /* ═══════════ pembantu ═══════════ */

    /**
     * Jumlah baris milik satu orang per tanggal, sejak `$mulai`.
     *
     * @return array<string,int>
     */
    private static function perHari($kueri, int $userId, Carbon $mulai): array
    {
        return $kueri->where('user_id', $userId)
            ->where('created_at', '>=', $mulai)
            ->selectRaw('date(created_at) as hari, count(*) as n')
            ->groupBy('hari')->pluck('n', 'hari')
            ->map(fn ($n) => (int) $n)->all();
    }

    /**
     * Tiap hari dalam rentang, tanpa lubang.
     *
     * @return list<Carbon>
     */
    private static function deretHari(Carbon $mulai, Carbon $sampai): array
    {
        $keluar = [];

        for ($h = $mulai->copy(); $h->lte($sampai); $h->addDay()) {
            $keluar[] = $h->copy();
        }

        return $keluar;
    }
}
