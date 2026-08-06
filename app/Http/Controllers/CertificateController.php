<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Certificate, Company, Course, Enrollment, PostTrainingEvaluation, Signatory};
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    public const TEMPLATE = [
        'klasik'   => 'Klasik — bingkai formal',
        'modern'   => 'Modern — aksen gradien',
        'minimal'  => 'Minimal — bersih & lapang',
        'korporat' => 'Korporat — kop perusahaan',
    ];

    public function index()
    {
        $me = auth()->user();

        // Terbitkan otomatis untuk kursus yang sudah selesai
        $this->autoIssue();

        $certificates = Certificate::with(['course','user','company','signatory'])
            ->when(!$me->isAdmin(), fn($q) => $q->where('user_id', $me->id))
            ->latest('issued_at')->get();

        $menungguEvaluasi = $this->menungguEvaluasi();
        $tunda = $menungguEvaluasi->pluck('course_id')->all();

        $claimable = Enrollment::with('course')->where('user_id', $me->id)->where('status','finished')
            ->whereNotIn('course_id', Certificate::where('user_id', $me->id)->pluck('course_id'))
            ->whereNotIn('course_id', $tunda ?: [0])->get();

        return view('certificates.index', compact('certificates','claimable','menungguEvaluasi'));
    }

    /**
     * Terbit otomatis. Alur: kursus selesai → dievaluasi trainer → sertifikat terbit.
     * Kursus yang tidak mensyaratkan evaluasi langsung terbit setelah selesai.
     */
    private function autoIssue(): void
    {
        $selesai = Enrollment::with('course')->where('user_id', auth()->id())->where('status','finished')->get();

        foreach ($selesai as $en) {
            $c = $en->course;
            if (!$c || !($c->auto_certificate ?? true)) continue;
            if (Certificate::where('user_id', auth()->id())->where('course_id', $en->course_id)->exists()) continue;

            $evaluasi = PostTrainingEvaluation::where('user_id', auth()->id())
                            ->where('course_id', $en->course_id)->latest()->first();

            if (($c->require_evaluation ?? true) && !$evaluasi) continue;   // tunggu penilaian trainer

            $this->buat($c, $en, $evaluasi);
        }
    }

    /** Kursus selesai yang masih menunggu penilaian trainer */
    private function menungguEvaluasi()
    {
        $dinilai = PostTrainingEvaluation::where('user_id', auth()->id())->pluck('course_id')->all();

        return Enrollment::with('course')->where('user_id', auth()->id())->where('status','finished')->get()
            ->filter(fn($en) => $en->course
                             && ($en->course->require_evaluation ?? true)
                             && !in_array($en->course_id, $dinilai, true)
                             && !Certificate::where('user_id', auth()->id())->where('course_id', $en->course_id)->exists())
            ->values();
    }

    /** Terbit manual (tombol) */
    public function store(Course $course)
    {
        $en = Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->firstOrFail();
        abort_unless($en->status === 'finished', 403, 'Kursus belum selesai.');

        $evaluasi = PostTrainingEvaluation::where('user_id', auth()->id())
                        ->where('course_id', $course->id)->latest()->first();

        if (($course->require_evaluation ?? true) && !$evaluasi) {
            return back()->withErrors(['cert' => 'Sertifikat terbit setelah trainer menyelesaikan evaluasi pelatihan.']);
        }

        $cert = Certificate::where('user_id', auth()->id())->where('course_id', $course->id)->first()
              ?? $this->buat($course, $en, $evaluasi);

        return redirect()->route('certificates.show', $cert)->with('ok', 'Sertifikat diterbitkan.');
    }

    private function buat(Course $course, Enrollment $en, ?PostTrainingEvaluation $evaluasi = null): Certificate
    {
        $u  = auth()->user();
        $co = $u->company_id ? Company::find($u->company_id) : Company::orderBy('id')->first();

        $urut  = Certificate::whereYear('issued_at', now()->year)->count() + 1;
        $pfx   = $co?->doc_no_prefix ?: 'EQ';
        $nomor = sprintf('%s/%s/%s/%04d', $pfx, 'SRT', now()->year, $urut);
        $kode  = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        $ttd = Signatory::where('is_active', true)->first();

        $cert = Certificate::create([
            'user_id'            => $u->id,
            'course_id'          => $course->id,
            'company_id'         => $co?->id,
            'template'           => $course->cert_template ?: 'klasik',
            'certificate_number' => $nomor,
            'verification_code'  => $kode,
            'recipient_name'     => $u->name,
            'course_title'       => $course->title,
            'final_score'        => $evaluasi?->overall_score ?: $en->progress,
            'signed_by_name'     => $ttd?->name,
            'signatory_id'       => $ttd?->id,
            'issued_at'          => now(),
        ]);
        ActivityLog::write('Terbitkan sertifikat', $course->title.' — '.$u->name);

        return $cert;
    }

    public function show(Certificate $certificate)
    {
        abort_unless($certificate->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
        $certificate->load(['course','signatory','user','company.owner']);

        return view('certificates.show', ['c' => $certificate]);
    }

    /** Halaman verifikasi publik (tanpa login) — tujuan pemindaian barcode */
    public function verify(string $kode)
    {
        $c = Certificate::with(['course','user','company','signatory'])
                ->where('verification_code', $kode)
                ->orWhere('certificate_number', $kode)->first();

        return view('certificates.verify', compact('c','kode'));
    }
}
