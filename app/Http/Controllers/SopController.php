<?php

namespace App\Http\Controllers;

use App\Models\{SopEvaluation, SopEvaluationAttempt};
use Illuminate\Http\Request;
use Inertia\Inertia;

class SopController extends Controller
{
    public function index()
    {
        $evaluations = SopEvaluation::with('procedure')->where('is_active', true)
                                    ->orderBy('position')->get();

        /* `passed` boolean tidak dapat dijadikan MAX() di PostgreSQL —
           fungsi max(boolean) memang tidak ada di sana. SQLite dan MySQL
           menyimpan boolean sebagai angka sehingga MAX(passed) bekerja,
           dan halaman ini karena itu berjalan mulus di sini lalu
           memulangkan galat 500 di server.

           CASE WHEN mengubahnya menjadi angka lebih dulu dan berlaku
           pada ketiganya: pada PostgreSQL kondisinya boolean sungguhan,
           pada SQLite dan MySQL angka 0/1 yang sudah bernilai benar
           atau salah. */
        $best = SopEvaluationAttempt::where('user_id', auth()->id())
                  ->selectRaw('evaluation_id, MAX(score) as best, '
                      .'MAX(CASE WHEN passed THEN 1 ELSE 0 END) as lulus')
                  ->groupBy('evaluation_id')->get()->keyBy('evaluation_id');

        return Inertia::render('Sop/Daftar', [
            'judul'    => 'Evaluasi SOP',
            'subjudul' => 'Uji pemahaman terhadap prosedur kerja',

            'evaluasi' => $evaluations->map(function (SopEvaluation $ev) use ($best) {
                $b = $best[$ev->id] ?? null;

                return [
                    'id'         => $ev->id,
                    'judul'      => $ev->title,
                    'keterangan' => $ev->description ?: null,
                    'prosedur'   => $ev->procedure
                        ? trim(($ev->procedure->code ? $ev->procedure->code.' · ' : '').$ev->procedure->title)
                        : null,
                    'durasi'     => (int) $ev->duration_minutes,
                    'nilaiLulus' => (int) $ev->passing_score,
                    'terbaik'    => $b ? (int) $b->best : null,
                    'lulus'      => $b ? (bool) $b->lulus : false,
                    'url'        => route('sop.show', $ev),
                ];
            })->all(),
        ]);
    }

    /** Soal dikirim TANPA kunci jawaban. */
    public function show(SopEvaluation $evaluation)
    {
        $questions = $evaluation->questions()->get()->map(fn ($q) => [
            'id'      => $q->id,
            'soal'    => $q->question,
            'pilihan' => (array) ($q->options ?? []),
        ]);

        return Inertia::render('Sop/Kerjakan', [
            'judul'    => $evaluation->title,
            'subjudul' => $questions->count().' soal · lulus ≥ '.$evaluation->passing_score,

            'ev' => [
                'judul'      => $evaluation->title,
                'jumlahSoal' => $questions->count(),
                'nilaiLulus' => (int) $evaluation->passing_score,
                'durasiDetik'=> (int) $evaluation->duration_minutes * 60,
            ],

            'soal'   => $questions->all(),
            'tautan' => ['kirim' => route('sop.grade', $evaluation)],
        ]);
    }

    /** Penilaian 100% di server — kunci jawaban tidak pernah sampai ke peramban. */
    public function grade(Request $request, SopEvaluation $evaluation)
    {
        $answers   = (array) $request->input('answers', []);
        $questions = $evaluation->questions()->get();

        $correct = 0;
        foreach ($questions as $q) {
            if (isset($answers[$q->id]) && (int) $answers[$q->id] === (int) $q->correct_index) $correct++;
        }

        $total  = $questions->count();
        $score  = $total ? (int) round($correct / $total * 100) : 0;
        $passed = $score >= $evaluation->passing_score;

        $attempt = SopEvaluationAttempt::create([
            'user_id'       => auth()->id(),
            'evaluation_id' => $evaluation->id,
            'procedure_id'  => $evaluation->procedure_id,
            'score' => $score, 'total' => $total, 'correct' => $correct,
            'passed' => $passed, 'answers' => $answers,
        ]);

        // Lihat catatan yang sama pada QuizController::submit().
        return redirect()->route('sop.result', [$evaluation, $attempt]);
    }

    public function hasil(SopEvaluation $evaluation, SopEvaluationAttempt $attempt)
    {
        abort_unless($attempt->evaluation_id === $evaluation->id, 404);
        abort_unless($attempt->user_id === auth()->id() || auth()->user()->isAdmin(), 403);

        return Inertia::render('Sop/Hasil', [
            'judul'    => 'Hasil Evaluasi SOP',
            'subjudul' => $evaluation->title,

            'hasil' => [
                'nilai'      => (int) $attempt->score,
                'lulus'      => (bool) $attempt->passed,
                'benar'      => (int) $attempt->correct,
                'total'      => (int) $attempt->total,
                'nilaiLulus' => (int) $evaluation->passing_score,
                'evaluasi'   => $evaluation->title,
            ],

            'tautan' => [
                'ulangi' => route('sop.show', $evaluation),
                'daftar' => route('sop.index'),
            ],
        ]);
    }
}
