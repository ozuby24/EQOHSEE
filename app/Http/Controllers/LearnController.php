<?php
namespace App\Http\Controllers;

use App\Models\{Course, Material, MaterialCompletion, MaterialDiscussion, Module,
                Enrollment, ModuleCompletion, Note};
use App\Support\Materi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $enrollment = $this->pendaftaran($course);

        if (!$enrollment instanceof Enrollment) return $enrollment;   // halaman minta kode

        $course->load(['modules.materials', 'quizzes']);

        $done    = $this->modulSelesai($course);
        $materiS = $this->materiSelesai($course);

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
                'materi'     => $m->materials->map(fn ($x) => $this->barisMateri($x, $materiS))->all(),
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

    /**
     * Satu materi: ikhtisar, isinya, kurikulum, dan tanya jawabnya.
     *
     * Satu halaman untuk keduanya — ikhtisar dan ruang belajar — bukan
     * dua alamat. Dua alamat berarti dua klik untuk sampai ke isi SETIAP
     * KALI materi yang sama dibuka lagi, dan materi yang sedang
     * dikerjakan memang dibuka berulang kali. Ikhtisarnya menjadi
     * keadaan awal halaman, bukan halaman tersendiri: ia yang tergambar
     * sebelum tombol "Mulai" ditekan, dan materi yang sudah selesai
     * membuka isinya langsung.
     */
    public function materi(Course $course, Material $material)
    {
        $enrollment = $this->pendaftaran($course);

        if (!$enrollment instanceof Enrollment) return $enrollment;

        /* Materi milik kursus LAIN yang nomornya kebetulan ditebak tidak
           boleh terbuka lewat alamat kursus ini. Pengikatan model
           Laravel memuat keduanya secara terpisah dan tidak pernah
           memeriksa hubungannya sendiri — di sinilah pemeriksaan itu
           harus ditulis. */
        abort_unless((int) $material->course_id === (int) $course->id, 404);

        $course->load(['modules.materials', 'quizzes']);
        $material->load(['lampiran', 'pertanyaan.user', 'pertanyaan.jawaban.user']);

        $materiS = $this->materiSelesai($course);
        $done    = $this->modulSelesai($course);

        /* Urutan datar seluruh materi kursus ini. Tombol sebelumnya dan
           berikutnya melompati batas modul, sebagaimana orang membaca
           kursus: modul berikutnya adalah kelanjutan, bukan tempat lain. */
        $datar = $course->modules->flatMap->materials->values();
        $ke    = $datar->search(fn ($x) => $x->id === $material->id);

        $modul = $course->modules->firstWhere('id', $material->module_id);

        $semuaMateri = $datar->count();
        $materiTuntas = count(array_intersect($materiS, $datar->pluck('id')->all()));

        return Inertia::render('Belajar/Materi', [
            /* Kop halaman menyebut KURSUS dan MODULNYA, bukan materinya.
               Materinya sudah menjadi judul besar di dalam kartu tepat
               di bawahnya, dan judul yang sama tergambar dua kali
               berurutan membuat kop terbaca sebagai gema, bukan sebagai
               keterangan tempat. */
            'judul'    => $course->title,
            'subjudul' => $modul ? "Modul {$modul->order_index}. {$modul->title}" : 'Materi',

            'kursus' => [
                'judul'   => $course->title,
                'progres' => (int) $enrollment->progress,
                'url'     => route('learn.show', $course),

                /* Angka MATERI, terpisah dari progres kursus.
                 *
                 * Kurikulum di sebelahnya menggambar centang per materi,
                 * dan bilah yang menyebut persentase MODUL di atasnya
                 * membantah centang itu: dua materi tuntas dari tiga,
                 * tetapi "0% selesai" — sebab modulnya memang belum
                 * tuntas. Yang membacanya menyimpulkan salah satunya
                 * rusak.
                 *
                 * Progres kursus tetap dihitung dari modul dan tetap
                 * dipakai sertifikat; yang berubah hanya angka mana yang
                 * digambar di sebelah centang materi. */
                'materiTuntas' => $materiTuntas,
                'materiTotal'  => $semuaMateri,
            ],

            'materi' => [
                'id'          => $material->id,
                'judul'       => $material->title,
                'keterangan'  => $material->description ?: null,
                'jenis'       => strtolower((string) ($material->type ?: 'file')),
                'jenisLabel'  => Materi::label($material->type),
                'jenisIkon'   => Materi::ikon($material->type),
                'jenisNada'   => Materi::nada($material->type),
                'durasi'      => Materi::durasi($material->duration_minutes),
                'hasil'       => array_values(array_filter((array) $material->outcomes)),
                'prasyarat'   => $material->prerequisite ?: null,
                'isiRingkas'  => Materi::isi($material),

                'bacaan' => trim((string) $material->content) !== '' ? $material->content : null,
                'tautan' => $material->url ?: null,
                'semat'  => Materi::semat($material->url),
                'sop'    => $material->sop_url ?: null,

                'lampiran' => $material->lampiran->map(fn ($l) => [
                    'id' => $l->id, 'judul' => $l->title, 'url' => $l->url,
                ])->all(),

                'selesai'    => in_array($material->id, $materiS, true),
                'urlSelesai' => route('materials.complete', $material),
                'urlTanya'   => route('materials.tanya', $material),

                'nomor' => $ke === false ? null : $ke + 1,
                'dari'  => $datar->count(),
            ],

            'modul' => $modul ? [
                'urutan' => (int) $modul->order_index,
                'judul'  => $modul->title,
            ] : null,

            'kurikulum' => $course->modules->map(fn ($m) => [
                'id'      => $m->id,
                'urutan'  => (int) $m->order_index,
                'judul'   => $m->title,
                'selesai' => in_array($m->id, $done, true),
                'materi'  => $m->materials->map(fn ($x) => $this->barisMateri($x, $materiS) + [
                    'kini' => $x->id === $material->id,
                ])->all(),
            ])->all(),

            'catatan' => [
                'isi' => (string) (Note::where('user_id', auth()->id())
                            ->where('module_id', $material->module_id)->value('content') ?? ''),

                /* Catatan melekat pada MODUL, bukan pada materi, dan
                   halamannya mengatakan itu apa adanya. Catatan yang
                   tampak milik satu materi tetapi sebenarnya dibagi
                   sepuluh materi lain adalah kejutan yang baru ketahuan
                   ketika seseorang mengira catatannya hilang. */
                'url' => $material->module_id ? route('notes.save', $material->module_id) : null,
                'modul' => $modul?->title,
            ],

            'tanya' => $material->pertanyaan->map(fn ($t) => $this->barisTanya($t))->all(),

            'jelajah' => [
                'sebelum' => $ke !== false && $ke > 0
                    ? $this->tautanMateri($course, $datar[$ke - 1]) : null,
                'sesudah' => $ke !== false && $ke + 1 < $datar->count()
                    ? $this->tautanMateri($course, $datar[$ke + 1]) : null,
            ],
        ]);
    }

    /** Tandai satu materi selesai; modulnya ikut selesai bila semuanya tuntas. */
    public function selesaiMateri(Material $material)
    {
        MaterialCompletion::firstOrCreate([
            'user_id' => auth()->id(), 'material_id' => $material->id,
        ]);

        $this->rapikanModul($material->module_id);

        return back()->with('ok', 'Materi ditandai selesai.');
    }

    /** Tanya atau jawab pada satu materi. */
    public function tanya(Request $request, Material $material)
    {
        $d = $request->validate([
            'body'      => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ], [], ['body' => 'isi pertanyaan']);

        $induk = null;

        if (!empty($d['parent_id'])) {
            /* Induknya harus pertanyaan pada materi INI, dan harus
               pertanyaan — bukan jawaban. Tanpa keduanya, satu nomor yang
               ditebak menempelkan jawaban pada utas materi lain, dan
               balasan atas balasan membuat utas berlapis yang tidak
               tergambar di mana pun. */
            $induk = MaterialDiscussion::where('id', $d['parent_id'])
                ->where('material_id', $material->id)
                ->whereNull('parent_id')
                ->firstOrFail();
        }

        MaterialDiscussion::create([
            'material_id' => $material->id,
            'user_id'     => auth()->id(),
            'parent_id'   => $induk?->id,
            'body'        => $d['body'],
        ]);

        return back(fallback: route('learn.show', $material->course_id))
            ->with('ok', $induk ? 'Jawaban terkirim.' : 'Pertanyaan terkirim.');
    }

    /** Hapus pertanyaan atau jawaban sendiri; admin dan trainer boleh yang mana pun. */
    public function hapusTanya(MaterialDiscussion $discussion)
    {
        $me = auth()->user();

        abort_unless(
            $discussion->user_id === $me->id || $me->isAdmin() || $me->isTrainer(),
            403, 'Hanya penulisnya, trainer, atau administrator yang dapat menghapus ini.',
        );

        $discussion->delete();

        return back()->with('ok', 'Terhapus.');
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

    /* ================= pembantu ================= */

    /**
     * Pendaftaran orang ini pada kursus, atau halaman minta kode.
     *
     * Mengembalikan dua jenis nilai yang berbeda, dan itu disengaja:
     * halaman kursus dan halaman materi keduanya harus melewati
     * penjagaan yang sama persis, dan penjagaan yang disalin ke dua
     * tempat akan berselisih pada perubahan pertama — dengan akibat
     * kursus berkode yang terbuka lewat alamat materinya.
     */
    private function pendaftaran(Course $course): Enrollment|\Inertia\Response
    {
        $enrollment = Enrollment::where('user_id', auth()->id())
            ->where('course_id', $course->id)->first();

        if ($enrollment) return $enrollment;

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

        return Enrollment::create([
            'user_id' => auth()->id(), 'course_id' => $course->id,
            'progress' => 0, 'status' => 'ongoing',
        ]);
    }

    /** @return list<int> */
    private function modulSelesai(Course $course): array
    {
        return ModuleCompletion::where('user_id', auth()->id())
            ->whereIn('module_id', $course->modules->pluck('id'))->pluck('module_id')->all();
    }

    /** @return list<int> */
    private function materiSelesai(Course $course): array
    {
        return MaterialCompletion::where('user_id', auth()->id())
            ->whereIn('material_id', $course->modules->flatMap->materials->pluck('id'))
            ->pluck('material_id')->all();
    }

    /** Bentuk satu baris materi, sama di daftar kursus maupun di kurikulum. */
    private function barisMateri(Material $x, array $selesai): array
    {
        return [
            'id'      => $x->id,
            'judul'   => $x->title,
            'jenis'   => strtolower((string) ($x->type ?: 'file')),
            'label'   => Materi::label($x->type),
            'ikon'    => Materi::ikon($x->type),
            'durasi'  => Materi::durasi($x->duration_minutes),
            'selesai' => in_array($x->id, $selesai, true),
            'url'     => route('learn.materi', ['course' => $x->course_id, 'material' => $x->id]),
        ];
    }

    private function tautanMateri(Course $course, Material $x): array
    {
        return [
            'judul' => $x->title,
            'url'   => route('learn.materi', ['course' => $course->id, 'material' => $x->id]),
        ];
    }

    private function barisTanya(MaterialDiscussion $t): array
    {
        return [
            'id'      => $t->id,
            'isi'     => $t->body,
            'nama'    => $t->user?->name ?? 'Pengguna',
            'jabatan' => $t->user?->position ?: null,
            'waktu'   => $t->created_at?->diffForHumans(),
            'milikku' => $t->user_id === auth()->id(),
            'bolehHapus' => $t->user_id === auth()->id() || Gate::allows('admin') || Gate::allows('trainer'),
            'urlHapus'   => route('materials.tanya.hapus', $t),
            'jawaban' => $t->jawaban->map(fn ($j) => [
                'id'      => $j->id,
                'isi'     => $j->body,
                'nama'    => $j->user?->name ?? 'Pengguna',
                'jabatan' => $j->user?->position ?: null,
                'waktu'   => $j->created_at?->diffForHumans(),
                'bolehHapus' => $j->user_id === auth()->id() || Gate::allows('admin') || Gate::allows('trainer'),
                'urlHapus'   => route('materials.tanya.hapus', $j),
            ])->all(),
        ];
    }

    /**
     * Modul yang seluruh materinya tuntas ikut ditandai selesai.
     *
     * Progres kursus TETAP dihitung dari modul. Yang berubah hanya
     * caranya menjadi selesai — dan itu penting: sertifikat yang sudah
     * terbit menyebut angka yang dihitung dengan rumus lama, dan rumus
     * baru akan mengubah angka pada lembar yang sudah dipegang orang.
     *
     * Penjagaan `isEmpty()` di bawah TIDAK terjangkau dari pemanggil
     * yang ada sekarang: modul yang dilewatkan ke sini selalu modul
     * milik materi yang baru ditandai, dan modul itu menurut
     * definisinya punya sekurangnya satu materi. Ia tetap ada sebagai
     * penjaga bagi pemanggil berikutnya — nol dari nol adalah
     * "seluruhnya tuntas" menurut matematika, dan menurut peserta
     * adalah modul kosong yang menyelesaikan dirinya sendiri. Tidak ada
     * uji yang menutupinya, dan itu disebutkan di sini supaya tidak
     * dikira ada.
     */
    private function rapikanModul(?int $moduleId): void
    {
        if (!$moduleId) return;

        $modul = Module::with('materials')->find($moduleId);

        if (!$modul || $modul->materials->isEmpty()) return;

        $tuntas = MaterialCompletion::where('user_id', auth()->id())
            ->whereIn('material_id', $modul->materials->pluck('id'))->count();

        if ($tuntas < $modul->materials->count()) return;

        ModuleCompletion::firstOrCreate(['user_id' => auth()->id(), 'module_id' => $modul->id]);

        $this->recalc($modul->course_id);
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
