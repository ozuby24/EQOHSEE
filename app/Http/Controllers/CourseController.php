<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Support\{Kategori, Sampul};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

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

        return Inertia::render('Kursus/Daftar', [
            'judul'    => 'Kursus',
            'subjudul' => 'Katalog pelatihan beserta modul dan kuisnya',

            'kursus' => array_map(fn (Course $c) => [
                'id'          => $c->id,
                'judul'       => $c->title,
                'keterangan'  => $c->description ?: null,
                'kategori'    => $c->category ?: null,
                'nadaKategori'=> $c->category ? Kategori::nada($c->category) : null,
                'inisial'     => mb_strtoupper(mb_substr($c->title, 0, 1)),
                // Sampul memakai aturan yang sama dengan dashboard lewat
                // App\Support\Sampul. Katalog ini sempat menampilkan kotak
                // gradasi berhuruf sementara dashboard memasang foto
                // lapangan, padahal keduanya menampilkan kursus yang sama.
                'sampul'      => Sampul::untuk($c),
                'perluKode'   => (bool) $c->require_code,
                'diikuti'     => in_array($c->id, $diikuti, true),
                'jumlahModul' => $c->modules_count,
                'urlBelajar'  => route('learn.show', $c),
                'urlDetail'   => route('courses.show', $c),
                'urlKelola'   => route('manage.course', $c),
                'urlHapus'    => route('courses.destroy', $c),
            ], $courses->items()),

            'halaman' => [
                'kini'   => $courses->currentPage(),
                'akhir'  => $courses->lastPage(),
                'total'  => $courses->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $courses->linkCollection()->all()),
            ],

            'f'    => ['q' => $q, 'kategori' => $kat, 'status' => (string) $status, 'urut' => (string) $urut],
            'opsi' => [
                'kategori' => $kategori->values()->all(),
                'status'   => [
                    ['nilai' => 'diikuti', 'label' => 'Sedang diikuti'],
                    ['nilai' => 'belum',   'label' => 'Belum diikuti'],
                ],
                'urut' => [
                    ['nilai' => 'baru',  'label' => 'Terbaru'],
                    ['nilai' => 'judul', 'label' => 'Judul A–Z'],
                    ['nilai' => 'modul', 'label' => 'Modul terbanyak'],
                ],
            ],

            'bolehKelola' => Gate::allows('admin'),
            'tautan'      => ['daftar' => route('courses.index'), 'buat' => route('courses.create')],
        ]);
    }

    public function create()
    {
        return $this->formulir(new Course());
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

        return Inertia::render('Kursus/Detail', [
            'judul'    => $course->title,
            'subjudul' => $course->category ?: 'Kursus',

            'kursus' => [
                'judul'       => $course->title,
                'keterangan'  => $course->description ?: null,
                'kategori'    => $course->category ?: null,
                'nadaKategori'=> $course->category ? Kategori::nada($course->category) : null,
                'gambar'      => $course->image ? asset('storage/'.$course->image) : null,
            ],

            'modul' => $course->modules->map(fn ($m) => [
                'id'         => $m->id,
                'urutan'     => (int) $m->order_index,
                'judul'      => $m->title,
                'keterangan' => $m->description ?: null,
                'materi'     => $m->materials->map(fn ($x) => [
                    'id' => $x->id, 'judul' => $x->title, 'jenis' => $x->type ?: 'file',
                ])->all(),
            ])->all(),

            'bolehUbah' => Gate::allows('admin'),
            'tautan'    => [
                'belajar' => route('learn.show', $course),
                'ubah'    => route('courses.edit', $course),
                'daftar'  => route('courses.index'),
            ],
        ]);
    }

    public function edit(Course $course)
    {
        return $this->formulir($course);
    }

    /** Formulir kursus, dipakai bersama oleh create dan edit. */
    private function formulir(Course $c)
    {
        return Inertia::render('Kursus/Form', [
            'judul'    => $c->exists ? 'Edit Kursus' : 'Kursus Baru',
            'subjudul' => $c->exists ? $c->title : 'Daftarkan kursus beserta kode akses dan aturan sertifikatnya',

            'tersimpan' => $c->exists,

            'awal' => [
                'title'       => (string) ($c->title ?? ''),
                'category'    => (string) ($c->category ?? ''),
                'description' => (string) ($c->description ?? ''),
                'access_code' => (string) ($c->access_code ?: Course::kodeBaru()),
                'cert_template'      => (string) ($c->cert_template ?: 'klasik'),
                'require_code'       => (bool) $c->require_code,
                'auto_certificate'   => $c->exists ? (bool) $c->auto_certificate : true,
                'require_evaluation' => $c->exists ? (bool) $c->require_evaluation : true,
            ],

            'gambar' => $c->image ? asset('storage/'.$c->image) : null,

            'opsi' => [
                'sertifikat' => array_map(
                    fn ($k) => ['nilai' => $k, 'label' => CertificateController::TEMPLATE[$k]],
                    array_keys(CertificateController::TEMPLATE),
                ),
            ],

            'tautan' => [
                'simpan' => $c->exists ? route('courses.update', $c) : route('courses.store'),
                'batal'  => route('courses.index'),
            ],
        ]);
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
