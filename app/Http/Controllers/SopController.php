<?php
namespace App\Http\Controllers;
use App\Models\{SopEvaluation, SopEvaluationAttempt};
use Illuminate\Http\Request;
class SopController extends Controller
{
    public function index()
    {
        $evaluations = SopEvaluation::with('procedure')->where('is_active', true)->orderBy('position')->get();
        $best = SopEvaluationAttempt::where('user_id', auth()->id())
                  ->selectRaw('evaluation_id, MAX(score) as best, MAX(passed) as lulus')
                  ->groupBy('evaluation_id')->get()->keyBy('evaluation_id');
        return view('sop.index', compact('evaluations','best'));
    }

    /** Soal dikirim TANPA kunci jawaban */
    public function show(SopEvaluation $evaluation)
    {
        $questions = $evaluation->questions()->get()->map(fn ($q) => [
            'id' => $q->id, 'question' => $q->question, 'options' => $q->options,
        ]);
        return view('sop.show', compact('evaluation','questions'));
    }

    /** Penilaian 100% di server — kunci jawaban tidak pernah sampai ke browser */
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
        return view('sop.result', compact('evaluation','attempt'));
    }
}
