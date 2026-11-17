<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Course, Material, MaterialAttachment, Module, Quiz, QuizQuestion};
use App\Support\Materi;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Satu halaman untuk mengelola modul, materi, kuis & soal dari sebuah kursus. */
class CourseContentController extends Controller
{
    public function manage(Course $course)
    {
        $course->load(['modules.materials', 'quizzes.questions']);

        return Inertia::render('Kursus/Kelola', [
            'judul'    => 'Kelola: '.$course->title,
            'subjudul' => 'Modul, materi, kuis, dan soalnya',

            'kursus' => [
                'judul'     => $course->title,
                'kode'      => $course->access_code ?: null,
                'perluKode' => (bool) $course->require_code,
            ],

            'modul' => $course->modules->map(fn ($m) => [
                'id'         => $m->id,
                'urutan'     => (int) $m->order_index,
                'judul'      => $m->title,
                'keterangan' => $m->description ?: null,
                'materi'     => $m->materials->map(fn ($x) => [
                    'id'       => $x->id,
                    'judul'    => $x->title,
                    'jenis'    => $x->type ?: 'file',
                    'label'    => Materi::label($x->type),

                    /* Penanda materi yang belum punya ikhtisar.
                       Tanpa ini, satu-satunya cara mengetahui materi mana
                       yang masih kosong ikhtisarnya adalah membuka
                       keduapuluhnya satu per satu — dan yang terlewat
                       baru ketahuan dari peserta. */
                    'lengkap'  => $x->description
                                  && !empty($x->outcomes)
                                  && $x->duration_minutes,

                    'urlAtur'  => route('manage.material.edit', $x),
                    'urlHapus' => route('manage.material.destroy', $x),
                ])->all(),
                'urlHapus'       => route('manage.module.destroy', $m),
                'urlTambahMateri'=> route('manage.material.store', $m),
            ])->all(),

            'kuis' => $course->quizzes->map(fn ($q) => [
                'id'         => $q->id,
                'judul'      => $q->title,
                'nilaiLulus' => (int) $q->pass_score,
                // Kunci jawaban ikut dikirim DI SINI dan hanya di sini:
                // halaman ini khusus admin, dan pengelola perlu melihat
                // jawaban benarnya untuk memeriksa soal yang sudah dibuat.
                // Halaman pengerjaan tidak pernah menerimanya.
                'soal' => $q->questions->map(fn ($x) => [
                    'id'      => $x->id,
                    'soal'    => $x->question,
                    'jawaban' => ((array) $x->options)[$x->correct_index] ?? '—',
                    'urlHapus'=> route('manage.question.destroy', $x),
                ])->all(),
                'urlHapus'      => route('manage.quiz.destroy', $q),
                'urlTambahSoal' => route('manage.question.store', $q),
            ])->all(),

            'tautan' => [
                'pratinjau'   => route('learn.show', $course),
                'info'        => route('courses.edit', $course),
                'tambahModul' => route('manage.module.store', $course),
                'tambahKuis'  => route('manage.quiz.store', $course),
            ],
        ]);
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

    /**
     * Ikhtisar satu materi: apa yang dipelajari, prasyarat, durasi, lampiran.
     *
     * Halaman TERSENDIRI, bukan tambahan medan pada formulir sebaris di
     * halaman kelola. Formulir sebaris itu dipakai untuk menambah materi
     * cepat-cepat sambil menyusun kerangka kursus; menempelkan tujuh
     * medan lagi ke sana akan membuat pekerjaan yang paling sering
     * dilakukan menjadi yang paling berat.
     */
    public function editMaterial(Material $material)
    {
        $material->load(['lampiran', 'module', 'course']);

        return Inertia::render('Kursus/Materi', [
            'judul'    => 'Ikhtisar: '.$material->title,
            'subjudul' => trim(($material->course?->title ?? '').' · '.($material->module?->title ?? '')," ·"),

            'awal' => [
                'title'            => (string) $material->title,
                'type'             => (string) ($material->type ?: 'document'),
                'url'              => (string) ($material->url ?? ''),
                'description'      => (string) ($material->description ?? ''),

                /* Dikirim sebagai TEKS berbaris, bukan larik.
                   Kotak isian berbaris adalah cara paling wajar menulis
                   daftar, dan mengubahnya menjadi larik di sisi Vue
                   berarti aturan pemisahnya hidup di tempat yang tidak
                   dapat diuji sisi server. Pemisahannya di v() di bawah. */
                'outcomes'         => implode("\n", (array) ($material->outcomes ?? [])),

                'prerequisite'     => (string) ($material->prerequisite ?? ''),
                'duration_minutes' => $material->duration_minutes ? (string) $material->duration_minutes : '',
                'content'          => (string) ($material->content ?? ''),
                'sop_url'          => (string) ($material->sop_url ?? ''),
            ],

            /* Pratinjau keputusan semat, ditampilkan di formulirnya.
               Pengelola yang menempelkan tautan Google Drive perlu tahu
               SEKARANG bahwa materinya akan tergambar sebagai kartu
               tautan, bukan sesudah dua puluh peserta bertanya kenapa
               videonya tidak muncul. */
            'semat' => Materi::semat($material->url),

            'lampiran' => $material->lampiran->map(fn ($l) => [
                'id' => $l->id, 'judul' => $l->title, 'url' => $l->url,
                'urlHapus' => route('manage.attachment.destroy', $l),
            ])->all(),

            'tautan' => [
                'simpan'         => route('manage.material.update', $material),
                'tambahLampiran' => route('manage.attachment.store', $material),
                'pratinjau'      => route('learn.materi', ['course' => $material->course_id, 'material' => $material->id]),
                'batal'          => route('manage.course', $material->course_id),
            ],
        ]);
    }

    public function updateMaterial(Request $r, Material $material)
    {
        $material->update($this->medanMateri($r));

        return redirect()->route('manage.course', $material->course_id)
            ->with('ok', 'Ikhtisar materi diperbarui.');
    }

    public function storeAttachment(Request $r, Material $material)
    {
        $d = $r->validate([
            'title' => ['required', 'string', 'max:200'],
            'url'   => ['required', 'url', 'max:1000'],
        ], [], ['title' => 'judul lampiran', 'url' => 'tautan lampiran']);

        $d['order_index'] = (int) $material->lampiran()->max('order_index') + 1;
        $material->lampiran()->create($d);

        return back()->with('ok', 'Lampiran ditambahkan.');
    }

    public function destroyAttachment(MaterialAttachment $attachment)
    {
        $attachment->delete();

        return back()->with('ok', 'Lampiran dihapus.');
    }

    public function destroyMaterial(Material $material)
    {
        $material->delete();
        return back()->with('ok', 'Materi dihapus.');
    }

    /**
     * Medan ikhtisar materi, sesudah divalidasi dan dirapikan.
     *
     * `outcomes` datang sebagai teks berbaris dan keluar sebagai larik.
     * Baris kosong dan spasi menggantung dibuang di sini — bukan di
     * sisi Vue: yang dikirim langsung lewat API, atau lewat formulir
     * yang belum ditulis, harus menerima pembersihan yang sama.
     */
    private function medanMateri(Request $r): array
    {
        $d = $r->validate([
            'title'            => ['required', 'string', 'max:200'],
            'type'             => ['nullable', 'in:pptx,video,pdf,document'],
            'url'              => ['nullable', 'url', 'max:500'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'outcomes'         => ['nullable', 'string', 'max:2000'],
            'prerequisite'     => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'content'          => ['nullable', 'string'],
            'sop_url'          => ['nullable', 'url', 'max:500'],
        ], [], [
            'outcomes'         => 'yang akan dipelajari',
            'prerequisite'     => 'prasyarat',
            'duration_minutes' => 'durasi',
            'sop_url'          => 'tautan SOP',
        ]);

        $d['outcomes'] = array_values(array_filter(array_map(
            'trim', preg_split('/\r\n|\r|\n/', (string) ($d['outcomes'] ?? '')) ?: [],
        ), fn ($b) => $b !== ''));

        /* Larik kosong disimpan sebagai null, bukan sebagai [].
           Keduanya terbaca sama di layar, tetapi [] pada kolom JSON
           membuat `whereNull('outcomes')` — cara paling wajar mencari
           materi yang belum diisi ikhtisarnya — melewatkan seluruhnya. */
        if ($d['outcomes'] === []) $d['outcomes'] = null;

        /* `??`, bukan `?:`.
           validate() hanya mengembalikan kunci yang MEMANG ada pada
           permintaannya, dan medan durasi yang dikosongkan tidak ikut
           terkirim sama sekali. `?:` pada kunci yang tidak ada adalah
           galat, dan galatnya menjatuhkan penyimpanan seluruh ikhtisar
           — bukan hanya durasinya. */
        $d['duration_minutes'] = ($d['duration_minutes'] ?? null) ?: null;

        return $d;
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
