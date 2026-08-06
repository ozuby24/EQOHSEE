<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Course;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->get('q'));
        $kat    = trim((string) $request->get('kategori'));
        $status = $request->get('status');            // diikuti | belum
        $urut   = $request->get('urut', 'baru');      // baru | judul | modul

        $diikuti = \App\Models\Enrollment::where('user_id', auth()->id())->pluck('course_id')->all();

        $courses = Course::withCount('modules')
            ->when($q, fn($b) => $b->where(fn($w) =>
                $w->where('title','like',"%$q%")->orWhere('description','like',"%$q%")))
            ->when($kat, fn($b) => $b->where('category', $kat))
            ->when($status === 'diikuti', fn($b) => $b->whereIn('id', $diikuti ?: [0]))
            ->when($status === 'belum',   fn($b) => $b->whereNotIn('id', $diikuti ?: [0]))
            ->when($urut === 'judul', fn($b) => $b->orderBy('title'))
            ->when($urut === 'modul', fn($b) => $b->orderByDesc('modules_count'))
            ->when($urut === 'baru',  fn($b) => $b->latest())
            ->paginate(12)->withQueryString();

        $kategori = Course::whereNotNull('category')->where('category','<>','')
                        ->distinct()->orderBy('category')->pluck('category');

        return view('courses.index', compact('courses','q','kat','status','urut','kategori','diikuti'));
    }

    public function create()
    {
        return view('courses.form', ['course' => new Course()]);
    }

    public function store(Request $request)
    {
        $course = Course::create($this->validated($request));
        ActivityLog::write('Buat kursus', $course->title);
        return redirect()->route('courses.index')->with('ok', 'Kursus dibuat.');
    }

    public function show(Course $course)
    {
        $course->load(['modules.materials', 'quizzes']);
        return view('courses.show', compact('course'));
    }

    public function edit(Course $course)
    {
        return view('courses.form', compact('course'));
    }

    public function update(Request $request, Course $course)
    {
        $course->update($this->validated($request));
        ActivityLog::write('Ubah kursus', $course->title);
        return redirect()->route('courses.index')->with('ok', 'Kursus diperbarui.');
    }

    public function destroy(Course $course)
    {
        $judul = $course->title;
        $course->delete();
        ActivityLog::write('Hapus kursus', $judul);
        return redirect()->route('courses.index')->with('ok', 'Kursus dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'category'    => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'image', 'max:2048'],
            'cert_template'    => ['nullable','string','max:30'],
            'access_code'      => ['nullable','string','max:20'],
            'require_code'     => ['nullable','boolean'],
            'require_evaluation' => ['nullable','boolean'],
            'auto_certificate' => ['nullable','boolean'],
        ]);

        $data['cert_template']    = $data['cert_template'] ?? 'klasik';
        $data['auto_certificate']   = (bool) ($data['auto_certificate'] ?? false);
        $data['require_code']       = (bool) ($data['require_code'] ?? false);
        $data['require_evaluation'] = (bool) ($data['require_evaluation'] ?? false);
        $data['access_code']        = strtoupper(trim((string) ($data['access_code'] ?? ''))) ?: Course::kodeBaru();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('courses', 'public');
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
