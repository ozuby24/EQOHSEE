<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Course, Enrollment, PostTrainingEvaluation, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvaluationController extends Controller
{
    public const REKOMENDASI = [
        'Sangat Direkomendasikan',
        'Direkomendasikan',
        'Perlu Pendampingan',
        'Perlu Pelatihan Ulang',
    ];

    /** Trainer/admin melihat semua; peserta hanya miliknya sendiri */
    public function index()
    {
        $me = auth()->user();
        $bolehMenilai = $me->isAdmin() || $me->isTrainer();

        $evaluations = PostTrainingEvaluation::with(['user','course','trainer'])
            ->when(!$bolehMenilai, fn($q) => $q->where('user_id', $me->id))
            ->latest()->paginate(15);

        // Daftar tunggu penilaian trainer:
        //   peserta yang MENYELESAIKAN kursus  ATAU  sudah MENGERJAKAN kuis kursus itu,
        //   dan belum pernah dievaluasi.
        $menunggu = collect();
        if ($bolehMenilai) {
            $sudah = PostTrainingEvaluation::select('user_id','course_id')->get()
                        ->map(fn($e) => $e->user_id.'-'.$e->course_id)->all();

            // pasangan (user, course) yang sudah mengerjakan kuis
            $kuis = \App\Models\QuizAttempt::with('quiz')->get()
                ->filter(fn($a) => $a->quiz && $a->quiz->course_id)
                ->map(fn($a) => $a->user_id.'-'.$a->quiz->course_id)->unique()->all();

            $menunggu = Enrollment::with(['user','course'])->get()
                ->filter(fn($en) => $en->status === 'finished'
                                 || in_array($en->user_id.'-'.$en->course_id, $kuis, true))
                ->reject(fn($en) => in_array($en->user_id.'-'.$en->course_id, $sudah, true))
                ->values();
        }

        return view('evaluations.index', compact('evaluations','bolehMenilai','menunggu'));
    }

    public function create(Request $request)
    {
        $ev = new PostTrainingEvaluation();
        $ev->user_id   = $request->get('user');     // prefill dari daftar "menunggu"
        $ev->course_id = $request->get('course');

        return view('evaluations.form', [
            'evaluation'  => $ev,
            'peserta'     => User::orderBy('name')->get(),
            'courses'     => Course::orderBy('title')->get(),
            'rekomendasi' => self::REKOMENDASI,
            'kursusPeserta' => $this->kursusPeserta(),
        ]);
    }

    /** Peta peserta → kursus yang diambil, agar pilihan kursus mengikuti peserta */
    private function kursusPeserta(): array
    {
        $peta = [];
        foreach (Enrollment::with('course')->get() as $en) {
            if ($en->course) $peta[$en->user_id][] = ['id' => $en->course_id, 'title' => $en->course->title];
        }
        return $peta;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['trainer_id']    = auth()->id();
        $data['trainer_name']  = $data['trainer_name'] ?: auth()->user()->name;
        $data['overall_score'] = $this->rerata($data);
        $data['enrollment_id'] = Enrollment::where('user_id', $data['user_id'])
                                    ->where('course_id', $data['course_id'])->value('id');

        $ev = PostTrainingEvaluation::create($data);
        ActivityLog::write('Evaluasi trainer', 'Menilai '.$ev->user->name.' — '.optional($ev->course)->title);

        return redirect()->route('evaluations.show', $ev)->with('ok', 'Evaluasi tersimpan.');
    }

    public function show(PostTrainingEvaluation $evaluation)
    {
        $me = auth()->user();
        abort_unless($me->isAdmin() || $me->isTrainer() || $evaluation->user_id === $me->id, 403);
        $evaluation->load(['user','course','trainer']);

        return view('evaluations.show', compact('evaluation'));
    }

    public function edit(PostTrainingEvaluation $evaluation)
    {
        return view('evaluations.form', [
            'evaluation'  => $evaluation,
            'peserta'     => User::orderBy('name')->get(),
            'courses'     => Course::orderBy('title')->get(),
            'rekomendasi' => self::REKOMENDASI,
            'kursusPeserta' => $this->kursusPeserta(),
        ]);
    }

    public function update(Request $request, PostTrainingEvaluation $evaluation)
    {
        $data = $this->validated($request);
        $data['overall_score'] = $this->rerata($data);
        $evaluation->update($data);
        ActivityLog::write('Ubah evaluasi', 'Evaluasi #'.$evaluation->id);

        return redirect()->route('evaluations.show', $evaluation)->with('ok', 'Evaluasi diperbarui.');
    }

    public function destroy(PostTrainingEvaluation $evaluation)
    {
        $evaluation->delete();
        ActivityLog::write('Hapus evaluasi', 'Evaluasi #'.$evaluation->id);

        return redirect()->route('evaluations.index')->with('ok', 'Evaluasi dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id'         => ['required','exists:users,id'],
            'course_id'       => ['nullable','exists:courses,id'],
            'trainer_name'    => ['nullable','string','max:150'],
            'knowledge_score' => ['required','integer','min:0','max:100'],
            'skill_score'     => ['required','integer','min:0','max:100'],
            'attitude_score'  => ['required','integer','min:0','max:100'],
            'safety_score'    => ['required','integer','min:0','max:100'],
            'recommendation'  => ['nullable', Rule::in(self::REKOMENDASI)],
            'strengths'       => ['nullable','string','max:2000'],
            'improvements'    => ['nullable','string','max:2000'],
            'notes'           => ['nullable','string','max:2000'],
        ]);
    }

    private function rerata(array $d): int
    {
        return (int) round((
            $d['knowledge_score'] + $d['skill_score'] +
            $d['attitude_score']  + $d['safety_score']
        ) / 4);
    }
}
