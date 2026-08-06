<?php
namespace App\Http\Controllers;
use App\Models\{Quiz, QuizAttempt};
use Illuminate\Http\Request;
class QuizController extends Controller
{
    public function show(Quiz $quiz)
    {
        $quiz->load('questions');
        return view('quizzes.show', compact('quiz'));
    }

    /** Dinilai di SERVER (lebih aman daripada desain lama yang menilai di browser) */
    public function submit(Request $request, Quiz $quiz)
    {
        $answers = (array) $request->input('answers', []);
        $questions = $quiz->questions;
        $correct = 0;
        foreach ($questions as $q) {
            if (isset($answers[$q->id]) && (int) $answers[$q->id] === (int) $q->correct_index) $correct++;
        }
        $total  = $questions->count();
        $score  = $total ? (int) round($correct / $total * 100) : 0;
        $passed = $score >= $quiz->pass_score;

        $attempt = QuizAttempt::create([
            'user_id' => auth()->id(), 'quiz_id' => $quiz->id,
            'score' => $score, 'passed' => $passed,
        ]);
        return view('quizzes.result', compact('quiz','attempt','correct','total'));
    }
}
