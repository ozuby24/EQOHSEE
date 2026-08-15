<?php
namespace App\Http\Controllers;
use App\Models\{Course, Module, Enrollment, ModuleCompletion, Note};
use Illuminate\Http\Request;
use Inertia\Inertia;
class LearnController extends Controller
{
    /** Daftar ke kursus — memverifikasi kode akses dari trainer */
    public function enroll(Request $request, Course $course)
    {
        $me = auth()->user();

        if ($course->require_code && !$me->isAdmin() && !$me->isTrainer()) {
            $kode = strtoupper(trim((string) $request->input('access_code')));
            if ($kode === '' || $kode !== strtoupper((string) $course->access_code)) {
                return back()->withErrors(['access_code' => 'Kode akses salah. Mintalah kode kepada trainer Anda.']);
            }
        }

        Enrollment::firstOrCreate(
            ['user_id' => $me->id, 'course_id' => $course->id],
            ['progress' => 0, 'status' => 'ongoing']
        );
        return redirect()->route('learn.show', $course)->with('ok','Kamu terdaftar di kursus ini.');
    }

    /** Halaman belajar — dijaga kode akses bila kursus mensyaratkannya */
    public function show(Course $course)
    {
        $enrollment = Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->first();

        if (!$enrollment) {
            // Belum terdaftar: minta kode bila disyaratkan (admin & trainer dikecualikan)
            $me = auth()->user();
            if ($course->require_code && !$me->isAdmin() && !$me->isTrainer()) {
                return Inertia::render('Belajar/Kode', [
                    'judul'    => $course->title,
                    'subjudul' => 'Kursus ini memerlukan kode akses dari trainer',

                    'kursus' => ['judul' => $course->title],
                    'tautan' => [
                        'buka'   => route('courses.enroll', $course),
                        'daftar' => route('courses.index'),
                    ],
                ]);
            }
            $enrollment = Enrollment::create([
                'user_id' => auth()->id(), 'course_id' => $course->id,
                'progress' => 0, 'status' => 'ongoing',
            ]);
        }
        $course->load(['modules.materials','quizzes']);
        $done  = ModuleCompletion::where('user_id', auth()->id())
                    ->whereIn('module_id', $course->modules->pluck('id'))->pluck('module_id')->all();
        $notes = Note::where('user_id', auth()->id())
                    ->whereIn('module_id', $course->modules->pluck('id'))->pluck('content','module_id')->all();
        return Inertia::render('Belajar/Kursus', [
            'judul'    => $course->title,
            'subjudul' => 'Modul, materi, dan catatan pribadi',

            'kursus' => [
                'judul'      => $course->title,
                'keterangan' => $course->description ?: null,
                'progres'    => (int) $enrollment->progress,
                'selesai'    => $enrollment->progress >= 100,
            ],

            'modul' => $course->modules->map(fn ($m) => [
                'id'         => $m->id,
                'urutan'     => (int) $m->order_index,
                'judul'      => $m->title,
                'keterangan' => $m->description ?: null,
                'selesai'    => in_array($m->id, $done, true),
                'catatan'    => (string) ($notes[$m->id] ?? ''),
                'materi'     => $m->materials->map(fn ($x) => [
                    'id' => $x->id, 'judul' => $x->title,
                    'jenis' => $x->type ?: 'file', 'url' => $x->url ?: null,
                ])->all(),
                'urlSelesai' => route('modules.complete', $m),
                'urlCatatan' => route('notes.save', $m),
            ])->all(),

            'kuis' => $course->quizzes->map(fn ($q) => [
                'id'         => $q->id,
                'judul'      => $q->title,
                'nilaiLulus' => (int) $q->pass_score,
                'url'        => route('quizzes.show', $q),
            ])->all(),

            'tautan' => ['sertifikat' => route('certificates.store', $course)],
        ]);
    }

    /** Tandai modul selesai + hitung ulang progres */
    public function complete(Module $module)
    {
        ModuleCompletion::firstOrCreate(['user_id' => auth()->id(), 'module_id' => $module->id]);
        $this->recalc($module->course_id);
        return back()->with('ok','Modul ditandai selesai.');
    }

    /** Simpan catatan pribadi per modul */
    public function saveNote(Request $request, Module $module)
    {
        $request->validate(['content' => ['nullable','string','max:5000']]);
        Note::updateOrCreate(
            ['user_id' => auth()->id(), 'module_id' => $module->id],
            ['content' => $request->input('content')]
        );
        return back()->with('ok','Catatan tersimpan.');
    }

    private function recalc(int $courseId): void
    {
        $course = Course::with('modules')->find($courseId);
        $total  = $course->modules->count();
        if (!$total) return;
        $done = ModuleCompletion::where('user_id', auth()->id())
                  ->whereIn('module_id', $course->modules->pluck('id'))->count();
        $progress = (int) round($done / $total * 100);
        Enrollment::where('user_id', auth()->id())->where('course_id', $courseId)
            ->update(['progress' => $progress, 'status' => $progress >= 100 ? 'finished' : 'ongoing']);
    }
}
