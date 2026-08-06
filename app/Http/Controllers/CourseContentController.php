<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Course, Material, Module, Quiz, QuizQuestion};
use Illuminate\Http\Request;

/** Satu halaman untuk mengelola modul, materi, kuis & soal dari sebuah kursus. */
class CourseContentController extends Controller
{
    public function manage(Course $course)
    {
        $course->load(['modules.materials', 'quizzes.questions']);
        return view('manage.course', compact('course'));
    }

    /* ---------- MODUL ---------- */
    public function storeModule(Request $r, Course $course)
    {
        $d = $r->validate([
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:1000'],
        ]);
        $d['order_index'] = (int) $course->modules()->max('order_index') + 1;
        $course->modules()->create($d);
        ActivityLog::write('Tambah modul', $d['title'].' — '.$course->title);

        return back()->with('ok', 'Modul ditambahkan.');
    }

    public function updateModule(Request $r, Module $module)
    {
        $d = $r->validate([
            'title'       => ['required','string','max:200'],
            'description' => ['nullable','string','max:1000'],
            'order_index' => ['nullable','integer','min:1'],
        ]);
        $d['order_index'] = $d['order_index'] ?? $module->order_index ?? 1;   // kolom NOT NULL
        $module->update($d);
        return back()->with('ok', 'Modul diperbarui.');
    }

    public function destroyModule(Module $module)
    {
        $module->delete();
        return back()->with('ok', 'Modul dihapus.');
    }

    /* ---------- MATERI ---------- */
    public function storeMaterial(Request $r, Module $module)
    {
        $d = $r->validate([
            'title'       => ['required','string','max:200'],
            'type'        => ['nullable','in:pptx,video,pdf,document'],
            'url'         => ['nullable','url','max:500'],
            'description' => ['nullable','string','max:1000'],
        ]);
        $d['course_id']   = $module->course_id;
        $d['order_index'] = (int) $module->materials()->max('order_index') + 1;
        $module->materials()->create($d);

        return back()->with('ok', 'Materi ditambahkan.');
    }

    public function destroyMaterial(Material $material)
    {
        $material->delete();
        return back()->with('ok', 'Materi dihapus.');
    }

    /* ---------- KUIS ---------- */
    public function storeQuiz(Request $r, Course $course)
    {
        $d = $r->validate([
            'title'      => ['required','string','max:200'],
            'pass_score' => ['required','integer','min:0','max:100'],
        ]);
        $course->quizzes()->create($d);
        ActivityLog::write('Tambah kuis', $d['title'].' — '.$course->title);

        return back()->with('ok', 'Kuis ditambahkan.');
    }

    public function destroyQuiz(Quiz $quiz)
    {
        $quiz->delete();
        return back()->with('ok', 'Kuis dihapus.');
    }

    /* ---------- SOAL ---------- */
    public function storeQuestion(Request $r, Quiz $quiz)
    {
        $d = $r->validate([
            'question'      => ['required','string','max:1000'],
            'options'       => ['required','array','min:2','max:6'],
            'options.*'     => ['required','string','max:300'],
            'correct_index' => ['required','integer','min:0'],
        ]);
        $d['options'] = array_values($d['options']);
        abort_if($d['correct_index'] >= count($d['options']), 422, 'Kunci jawaban di luar pilihan.');
        $d['order_index'] = (int) $quiz->questions()->max('order_index') + 1;
        $quiz->questions()->create($d);

        return back()->with('ok', 'Soal ditambahkan.');
    }

    public function destroyQuestion(QuizQuestion $question)
    {
        $question->delete();
        return back()->with('ok', 'Soal dihapus.');
    }
}
