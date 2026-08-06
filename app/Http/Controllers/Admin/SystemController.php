<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Certificate, Company, Course, Enrollment, Material, Module, News,
    PostTrainingEvaluation, Procedure, Quiz, QuizAttempt, Signatory, SopEvaluation,
    SopEvaluationAttempt, TpkkpAssessment, TpkkpResponse, User};
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function index()
    {
        $server = [
            'Aplikasi'     => config('app.name'),
            'Laravel'      => app()->version(),
            'PHP'          => PHP_VERSION,
            'Basis data'   => config('database.default'),
            'Lingkungan'   => app()->environment(),
            'Mode debug'   => config('app.debug') ? 'AKTIF' : 'nonaktif',
            'Zona waktu'   => config('app.timezone'),
            'Waktu server' => now()->format('d M Y · H:i:s'),
        ];
        try {
            $free = @disk_free_space(base_path()); $total = @disk_total_space(base_path());
            $server['Ruang disk'] = ($free && $total) ? $this->human($free).' / '.$this->human($total) : '—';
        } catch (\Throwable $e) { $server['Ruang disk'] = '—'; }

        // Statistik per modul — mencakup seluruh website
        $modul = [
            'Inti' => [
                'route' => null,
                'items' => ['Pengguna' => User::count(), 'Perusahaan' => Company::count(), 'Log aktivitas' => ActivityLog::count()],
            ],
            'LMS — Learning' => [
                'route' => 'courses.index',
                'items' => [
                    'Kursus' => Course::count(), 'Modul' => Module::count(), 'Materi' => Material::count(),
                    'Kuis' => Quiz::count(), 'Pendaftaran' => Enrollment::count(),
                    'Percobaan kuis' => QuizAttempt::count(), 'Sertifikat' => Certificate::count(),
                    'Penanda tangan' => Signatory::count(), 'Evaluasi trainer' => PostTrainingEvaluation::count(),
                    'Prosedur' => Procedure::count(), 'Evaluasi SOP' => SopEvaluation::count(),
                    'Percobaan SOP' => SopEvaluationAttempt::count(), 'Berita' => News::count(),
                ],
            ],
            'Safety Maturity Level' => [
                'route' => 'tpkkp.index',
                'items' => [
                    'Penilaian' => TpkkpAssessment::count(),
                    'Responden kuesioner' => TpkkpResponse::count(),
                    'Sudah dinilai' => TpkkpAssessment::whereNotNull('scores')->get()
                                        ->filter(fn($a) => !empty($a->scores))->count(),
                ],
            ],
        ];

        $roles = [
            'Administrator' => User::where('is_admin', true)->count(),
            'Trainer'       => User::where('lms_role', 'trainer')->count(),
            'KTT'           => User::where('lms_role', 'ktt')->count(),
            'Auditor'       => User::where('audit_role', 'auditor')->count(),
            'Perusahaan'    => User::where('audit_role', 'company')->count(),
            'Peserta'       => User::where(fn($q) => $q->whereNull('lms_role')->orWhere('lms_role','trainee'))->count(),
            'Nonaktif'      => User::where('active', false)->count(),
        ];

        $companies = Company::withCount('users')->orderBy('name')->get();
        $logs = ActivityLog::latest()->take(30)->get();

        return view('admin.system', compact('server','modul','roles','companies','logs'));
    }

    public function clearLogs()
    {
        ActivityLog::truncate();
        ActivityLog::write('Bersihkan log', 'Log aktivitas dikosongkan');
        return back()->with('ok', 'Log aktivitas dibersihkan.');
    }

    /** Aksi pemeliharaan sistem */
    public function maintenance(string $aksi)
    {
        $peta = [
            'cache'  => ['optimize:clear', 'Cache aplikasi dibersihkan.'],
            'view'   => ['view:clear',     'Cache tampilan dibersihkan.'],
            'config' => ['config:clear',   'Cache konfigurasi dibersihkan.'],
            'route'  => ['route:clear',    'Cache rute dibersihkan.'],
        ];
        abort_unless(isset($peta[$aksi]), 404);

        Artisan::call($peta[$aksi][0]);
        ActivityLog::write('Pemeliharaan sistem', $peta[$aksi][0]);

        return back()->with('ok', $peta[$aksi][1]);
    }

    private function human(float $b): string
    {
        $u = ['B','KB','MB','GB','TB']; $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return round($b, 1).' '.$u[$i];
    }
}
