<?php
namespace App\Http\Controllers;

use App\Models\{Course, Enrollment, Certificate, HazardReport, Inspection, KoObject,
    Document, News, Procedure, SmkpAudit, SmkpFinding, SopEvaluationAttempt,
    TpkkpAssessment, User};

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $data = [
            'enrollments' => Enrollment::with('course')->where('user_id',$user->id)->latest()->get(),
            'certificates'=> Certificate::where('user_id',$user->id)->count(),
            'sopPassed'   => SopEvaluationAttempt::where('user_id',$user->id)->where('passed',true)->count(),
            'news'        => News::latest('published_at')->take(3)->get(),
            'modul'       => $this->ringkasModul(),
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
        return view('dashboard', $data);
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
