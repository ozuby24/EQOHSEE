<?php
namespace App\Http\Controllers;

use App\Models\{Course, Enrollment, Certificate, HazardReport, Inspection, KoObject,
    Document, News, Procedure, SmkpAudit, SmkpFinding, SopEvaluationAttempt,
    TpkkpAssessment, User};
use App\Support\{Kategori, Media, Sampul, Waktu};
use Illuminate\Support\Str;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $enrollments = Enrollment::with('course')->where('user_id', $user->id)->latest()->get();

        $data = [
            'judul'       => 'Dashboard',
            'subjudul'    => 'Kelola pembelajaran dan tingkatkan kompetensi Anda',
            'sapa'        => Waktu::sapaan(),
            'nama'        => trim(explode(' ', $user->name)[0]),
            'hero'        => Media::url('galeri/budaya.jpg'),
            'lanjut'      => ($lanjut = $enrollments->firstWhere('status', 'ongoing') ?? $enrollments->first())
                ? route('learn.show', $lanjut->course)
                : route('courses.index'),
            'enrollments' => $enrollments->take(3)->map(function ($e) {
                $c = $e->course;
                $jumlah = $c?->modules()->count() ?? 0;
                return [
                    'judul'    => $c?->title ?? 'Kursus',
                    'deskripsi'=> Str::limit($c?->description, 78),
                    'sampul'   => $c ? Sampul::untuk($c) : null,
                    'kategori' => $c?->category,
                    'nada'     => $c?->category ? Kategori::nada($c->category) : null,
                    'status'   => $e->status,
                    'progress' => (int) $e->progress,
                    'modul'    => $jumlah,
                    'menit'    => max(10, $jumlah * 15),
                    'belajar'  => $c ? route('learn.show', $c) : route('courses.index'),
                    'detail'   => $c ? route('courses.show', $c) : route('courses.index'),
                ];
            })->values()->all(),
            'certificates'=> Certificate::where('user_id',$user->id)->count(),
            'sopPassed'   => SopEvaluationAttempt::where('user_id',$user->id)->where('passed',true)->count(),
            'news'        => News::latest('published_at')->take(3)->get()->map(fn ($n) => [
                'judul'    => $n->title,
                'cuplikan' => Str::limit(strip_tags($n->content), 74),
                'tanggal'  => optional($n->published_at ?? $n->created_at)->translatedFormat('d M'),
                'url'      => route('news.show', $n),
            ])->values()->all(),
            'modul'       => collect($this->ringkasModul())->map(fn ($m) => $m + [
                'url' => route($m['rute']),
            ])->all(),
            'ringkas'     => $this->ringkasBelajar($enrollments),
            'kategori'    => collect($this->ringkasKategori())->map(fn ($k) => $k + ['nada' => Kategori::nada($k['nama'])])->all(),
            'pekan'       => $this->kemajuanPekan($user->id),
            'admin'       => null,
        ];
        if ($user->isAdmin()) {
            $data['admin'] = [
                'users'      => User::count(),
                'courses'    => Course::count(),
                'procedures' => Procedure::count(),
                'certs'      => Certificate::count(),
            ];
        }
        return Inertia::render('Dashboard', $data);
    }

    /**
     * Angka pembuka dashboard.
     *
     * Kemajuan rata-rata dihitung dari pendaftaran yang benar-benar ada;
     * tanpa pendaftaran, yang benar adalah nol, bukan seratus. Membagi
     * dengan nol lalu menampilkan 100% adalah kesalahan yang paling
     * meyakinkan bentuknya.
     */
    private function ringkasBelajar($enrollments): array
    {
        $selesai = $enrollments->where('status', 'finished')->count();
        $berjalan = $enrollments->where('status', 'ongoing')->count();

        return [
            'total'    => Course::count(),
            'diikuti'  => $enrollments->count(),
            'selesai'  => $selesai,
            'berjalan' => $berjalan,
            'kemajuan' => $enrollments->count() > 0
                ? (int) round($enrollments->avg('progress'))
                : 0,
            'belum'    => max(0, Course::count() - $enrollments->count()),
        ];
    }

    /** Kategori kursus beserta jumlahnya, untuk pintasan di kaki halaman. */
    private function ringkasKategori(): array
    {
        return Course::selectRaw('category, COUNT(*) as jumlah')
            ->whereNotNull('category')->where('category', '<>', '')
            ->groupBy('category')->orderByDesc('jumlah')
            ->get()->map(fn ($r) => ['nama' => $r->category, 'jumlah' => (int) $r->jumlah])
            ->all();
    }

    /**
     * Kemajuan tujuh hari terakhir.
     *
     * Yang dihitung adalah modul yang diselesaikan tiap hari, bukan
     * kemajuan rata-rata — rata-rata hanya bergerak saat kursus baru
     * didaftarkan dan menghasilkan garis datar yang menyesatkan.
     */
    private function kemajuanPekan(int $userId): array
    {
        $mulai = now()->subDays(6)->startOfDay();

        $perHari = \Illuminate\Support\Facades\Schema::hasTable('module_completions')
            ? \Illuminate\Support\Facades\DB::table('module_completions')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $mulai)
                ->selectRaw('DATE(created_at) as hari, COUNT(*) as jumlah')
                ->groupBy('hari')->pluck('jumlah', 'hari')
            : collect();

        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $t = now()->subDays($i);
            $out[] = [
                'label' => $t->translatedFormat('D'),
                'nilai' => (int) ($perHari[$t->toDateString()] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Ringkasan lintas modul untuk pintasan di dashboard.
     * Angka yang ditonjolkan adalah yang butuh perhatian (belum tuntas),
     * bukan sekadar jumlah total.
     */
    private function ringkasModul(): array
    {
        return [
            [
                'nama'  => 'Hazard Report',
                'ket'   => 'Laporan bahaya belum tuntas',
                'nilai' => HazardReport::where('status','<>','Closed')->count(),
                'total' => HazardReport::count(),
                'ikon'  => 'hazard',
                'rute'  => 'hazard.index',
                'warna' => '#F0921E',
            ],
            [
                'nama'  => 'Inspeksi',
                'ket'   => 'Inspeksi masih berjalan',
                'nilai' => Inspection::where('status','<>','Selesai')->count(),
                'total' => Inspection::count(),
                'ikon'  => 'inspeksi',
                'rute'  => 'inspeksi.index',
                'warna' => '#0FA08F',
            ],
            [
                'nama'  => 'Keselamatan Operasi',
                'ket'   => 'Objek perlu ditindak',
                'nilai' => KoObject::whereDate('pm_berikutnya','<',now())->count(),
                'total' => KoObject::count(),
                'ikon'  => 'objek',
                'rute'  => 'ko.index',
                'warna' => '#1093B8',
            ],
            [
                'nama'  => 'Audit SMKP',
                'ket'   => 'Temuan belum ditutup',
                'nilai' => SmkpFinding::where('status','<>','Closed')->count(),
                'total' => SmkpAudit::count(),
                'ikon'  => 'audit',
                'rute'  => 'smkp.index',
                'warna' => '#4FA82E',
            ],
            [
                'nama'  => 'ISO & Dokumen',
                'ket'   => 'Dokumen lewat masa tinjau',
                'nilai' => Document::where('status','berlaku')->whereNotNull('tanggal_tinjau')
                             ->whereDate('tanggal_tinjau','<',now())->count(),
                'total' => Document::count(),
                'ikon'  => 'materi',
                'rute'  => 'dokumen.index',
                'warna' => '#17A2DC',
            ],
            [
                'nama'  => 'Safety Maturity',
                'ket'   => 'Penilaian PTPKKP',
                'nilai' => TpkkpAssessment::count(),
                'total' => TpkkpAssessment::count(),
                'ikon'  => 'penilaian',
                'rute'  => 'tpkkp.index',
                'warna' => '#2E6BE6',
            ],
        ];
    }
}
