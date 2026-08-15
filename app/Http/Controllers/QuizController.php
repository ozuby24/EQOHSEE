<?php

namespace App\Http\Controllers;

use App\Models\{Quiz, QuizAttempt};
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function show(Quiz $quiz)
    {
        $quiz->load('questions');

        return Inertia::render('Kuis/Kerjakan', [
            'judul'    => $quiz->title,
            'subjudul' => $quiz->questions->count().' soal · nilai lulus '.$quiz->pass_score,

            'kuis' => [
                'judul'      => $quiz->title,
                'jumlahSoal' => $quiz->questions->count(),
                'nilaiLulus' => (int) $quiz->pass_score,
            ],

            // Kunci jawaban tidak ikut dikirim. Menilai di peramban
            // berarti jawabannya ada di perangkat peserta, dan siapa pun
            // yang membuka panel pengembang lulus tanpa membaca soalnya.
            'soal' => $quiz->questions->map(fn ($q) => [
                'id'      => $q->id,
                'soal'    => $q->question,
                'pilihan' => (array) ($q->options ?? []),
            ])->all(),

            'tautan' => ['kirim' => route('quizzes.submit', $quiz)],
        ]);
    }

    /** Dinilai di SERVER — kunci jawaban tidak pernah sampai ke peramban. */
    public function submit(Request $request, Quiz $quiz)
    {
        $answers   = (array) $request->input('answers', []);
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

        // Dialihkan ke alamat hasilnya sendiri, bukan digambar langsung
        // dari POST: halaman yang digambar dari POST tidak bisa dimuat
        // ulang tanpa mengirim ulang jawabannya.
        return redirect()->route('quizzes.result', [$quiz, $attempt]);
    }

    public function hasil(Quiz $quiz, QuizAttempt $attempt)
    {
        abort_unless($attempt->quiz_id === $quiz->id, 404);
        abort_unless($attempt->user_id === auth()->id() || auth()->user()->isAdmin(), 403);

        $total = $quiz->questions()->count();

        return Inertia::render('Kuis/Hasil', [
            'judul'    => 'Hasil Kuis',
            'subjudul' => $quiz->title,

            'hasil' => [
                'nilai'      => (int) $attempt->score,
                'lulus'      => (bool) $attempt->passed,
                // Jumlah benar diturunkan dari nilainya, bukan disimpan
                // terpisah: dua angka yang mengukur hal sama akan
                // berselisih begitu salah satunya berubah.
                'benar'      => $total ? (int) round($attempt->score / 100 * $total) : 0,
                'total'      => $total,
                'nilaiLulus' => (int) $quiz->pass_score,
                'kuis'       => $quiz->title,
            ],

            'tautan' => [
                'ulangi'    => route('quizzes.show', $quiz),
                'dashboard' => route('dashboard'),
            ],
        ]);
    }
}
