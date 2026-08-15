<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Course, Enrollment, PostTrainingEvaluation, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EvaluationController extends Controller
{
    public const REKOMENDASI = [
        'Sangat Direkomendasikan',
        'Direkomendasikan',
        'Perlu Pendampingan',
        'Perlu Pelatihan Ulang',
    ];

    /** Trainer/admin melihat semua; peserta hanya miliknya sendiri */
    public function index()
    {
        $me = auth()->user();
        $bolehMenilai = $me->isAdmin() || $me->isTrainer();

        $evaluations = PostTrainingEvaluation::with(['user','course','trainer'])
            ->when(!$bolehMenilai, fn($q) => $q->where('user_id', $me->id))
            ->latest()->paginate(15);

        // Daftar tunggu penilaian trainer:
        //   peserta yang MENYELESAIKAN kursus  ATAU  sudah MENGERJAKAN kuis kursus itu,
        //   dan belum pernah dievaluasi.
        $menunggu = collect();
        if ($bolehMenilai) {
            $sudah = PostTrainingEvaluation::select('user_id','course_id')->get()
                        ->map(fn($e) => $e->user_id.'-'.$e->course_id)->all();

            // pasangan (user, course) yang sudah mengerjakan kuis
            $kuis = \App\Models\QuizAttempt::with('quiz')->get()
                ->filter(fn($a) => $a->quiz && $a->quiz->course_id)
                ->map(fn($a) => $a->user_id.'-'.$a->quiz->course_id)->unique()->all();

            $menunggu = Enrollment::with(['user','course'])->get()
                ->filter(fn($en) => $en->status === 'finished'
                                 || in_array($en->user_id.'-'.$en->course_id, $kuis, true))
                ->reject(fn($en) => in_array($en->user_id.'-'.$en->course_id, $sudah, true))
                ->values();
        }

        return Inertia::render('Evaluasi/Daftar', [
            'judul'    => $bolehMenilai ? 'Evaluasi Pelatihan' : 'Evaluasi Saya',
            'subjudul' => 'Penilaian peserta setelah pelatihan selesai',

            'evaluasi' => array_map(fn (PostTrainingEvaluation $ev) => [
                'id'       => $ev->id,
                'peserta'  => $ev->user?->name,
                'kursus'   => $ev->course?->title,
                'trainer'  => $ev->trainer_name ?: $ev->trainer?->name,
                'tanggal'  => $ev->created_at?->format('d M Y'),
                'nilai'    => (int) $ev->overall_score,
                'rekomendasi' => $ev->recommendation,
                'url'      => route('evaluations.show', $ev),
            ], $evaluations->items()),

            'halaman' => [
                'kini'   => $evaluations->currentPage(),
                'akhir'  => $evaluations->lastPage(),
                'total'  => $evaluations->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $evaluations->linkCollection()->all()),
            ],

            'menunggu' => $menunggu->map(fn ($en) => [
                'peserta' => $en->user?->name,
                'kursus'  => $en->course?->title,
                'url'     => route('evaluations.create', ['user' => $en->user_id, 'course' => $en->course_id]),
            ])->all(),

            'bolehMenilai' => $bolehMenilai,
            'tautan'       => ['buat' => route('evaluations.create')],
        ]);
    }

    public function create(Request $request)
    {
        $ev = new PostTrainingEvaluation();
        $ev->user_id   = $request->get('user');     // prefill dari daftar "menunggu"
        $ev->course_id = $request->get('course');

        return $this->formulir($ev);
    }

    /** Peta peserta → kursus yang diambil, agar pilihan kursus mengikuti peserta */
    private function kursusPeserta(): array
    {
        $peta = [];
        foreach (Enrollment::with('course')->get() as $en) {
            if ($en->course) $peta[$en->user_id][] = ['id' => $en->course_id, 'title' => $en->course->title];
        }
        return $peta;
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Kunci opsional dibaca dengan ??, bukan langsung. Aturan
        // 'nullable' tidak menaruh kunci apa pun ke hasil validasi ketika
        // medannya tidak ikut terkirim, dan membacanya langsung berakhir
        // sebagai 500 — bukan pesan yang bisa diperbaiki pengirimnya.
        $data['trainer_id']    = auth()->id();
        $data['trainer_name']  = ($data['trainer_name'] ?? null) ?: auth()->user()->name;
        $data['overall_score'] = $this->rerata($data);
        $data['enrollment_id'] = Enrollment::where('user_id', $data['user_id'])
                                    ->where('course_id', $data['course_id'] ?? null)->value('id');

        $ev = PostTrainingEvaluation::create($data);
        ActivityLog::write('Evaluasi trainer', 'Menilai '.$ev->user->name.' — '.optional($ev->course)->title);

        return redirect()->route('evaluations.show', $ev)->with('ok', 'Evaluasi tersimpan.');
    }

    public function show(PostTrainingEvaluation $evaluation)
    {
        $me = auth()->user();
        abort_unless($me->isAdmin() || $me->isTrainer() || $evaluation->user_id === $me->id, 403);
        $evaluation->load(['user','course','trainer']);

        return Inertia::render('Evaluasi/Detail', [
            'judul'    => 'Hasil Evaluasi',
            'subjudul' => $evaluation->user?->name ?? '—',

            'ev' => [
                'peserta'     => $evaluation->user?->name,
                'kursus'      => $evaluation->course?->title,
                'trainer'     => $evaluation->trainer_name ?: $evaluation->trainer?->name,
                'tanggal'     => $evaluation->created_at?->translatedFormat('d F Y'),
                'nilai'       => (int) $evaluation->overall_score,
                'rekomendasi' => $evaluation->recommendation,
                'strengths'   => $evaluation->strengths,
                'improvements'=> $evaluation->improvements,
                'notes'       => $evaluation->notes,
            ],

            'rincian' => [
                ['label' => 'Pengetahuan',  'nilai' => (int) $evaluation->knowledge_score],
                ['label' => 'Keterampilan', 'nilai' => (int) $evaluation->skill_score],
                ['label' => 'Sikap',        'nilai' => (int) $evaluation->attitude_score],
                ['label' => 'Keselamatan',  'nilai' => (int) $evaluation->safety_score],
            ],

            'bolehUbah' => Gate::allows('trainer'),

            'tautan' => [
                'daftar' => route('evaluations.index'),
                'ubah'   => route('evaluations.edit', $evaluation),
                'hapus'  => route('evaluations.destroy', $evaluation),
            ],
        ]);
    }

    public function edit(PostTrainingEvaluation $evaluation)
    {
        return $this->formulir($evaluation);
    }

    /** Formulir evaluasi, dipakai bersama oleh create dan edit. */
    private function formulir(PostTrainingEvaluation $ev)
    {
        return Inertia::render('Evaluasi/Form', [
            'judul'    => $ev->exists ? 'Edit Evaluasi' : 'Nilai Peserta',
            'subjudul' => 'Penilaian pasca-pelatihan; nilai akhir dihitung sebagai rata-rata',

            'tersimpan' => $ev->exists,

            'awal' => [
                'user_id'         => $ev->user_id ? (string) $ev->user_id : '',
                'course_id'       => $ev->course_id ? (string) $ev->course_id : '',
                'trainer_name'    => (string) ($ev->trainer_name ?: auth()->user()->name),
                'knowledge_score' => (string) ($ev->knowledge_score ?? 80),
                'skill_score'     => (string) ($ev->skill_score ?? 80),
                'attitude_score'  => (string) ($ev->attitude_score ?? 80),
                'safety_score'    => (string) ($ev->safety_score ?? 80),
                'recommendation'  => (string) ($ev->recommendation ?? ''),
                'strengths'       => (string) ($ev->strengths ?? ''),
                'improvements'    => (string) ($ev->improvements ?? ''),
                'notes'           => (string) ($ev->notes ?? ''),
            ],

            'opsi' => [
                'peserta' => User::orderBy('name')->get()
                    ->map(fn ($p) => ['nilai' => (string) $p->id, 'label' => $p->name])->all(),
                'kursus'  => Course::orderBy('title')->get()
                    ->map(fn ($c) => ['nilai' => (string) $c->id, 'label' => $c->title])->all(),
                'rekomendasi' => self::REKOMENDASI,
            ],

            // Peta peserta → kursus yang benar-benar diambilnya. Tanpa ini
            // trainer harus mencari di seluruh katalog kursus, dan salah
            // pilih menghasilkan evaluasi yang menempel pada kursus yang
            // tidak pernah diikuti orangnya.
            'kursusPeserta' => (object) $this->kursusPeserta(),

            'medan' => [
                ['nama' => 'knowledge_score', 'label' => 'Pengetahuan',  'ket' => 'Pemahaman materi & teori'],
                ['nama' => 'skill_score',     'label' => 'Keterampilan', 'ket' => 'Penerapan praktik di lapangan'],
                ['nama' => 'attitude_score',  'label' => 'Sikap',        'ket' => 'Disiplin, kerja sama, inisiatif'],
                ['nama' => 'safety_score',    'label' => 'Keselamatan',  'ket' => 'Kepatuhan prosedur & APD'],
            ],

            'catatan' => [
                ['nama' => 'strengths',    'label' => 'Kekuatan',           'ph' => 'Hal yang sudah baik…'],
                ['nama' => 'improvements', 'label' => 'Perlu ditingkatkan', 'ph' => 'Area yang perlu diperbaiki…'],
                ['nama' => 'notes',        'label' => 'Catatan tambahan',   'ph' => 'Catatan lain…'],
            ],

            'tautan' => [
                'simpan' => $ev->exists ? route('evaluations.update', $ev) : route('evaluations.store'),
                'batal'  => route('evaluations.index'),
            ],
        ]);
    }

    public function update(Request $request, PostTrainingEvaluation $evaluation)
    {
        $data = $this->validated($request);
        $data['overall_score'] = $this->rerata($data);
        $evaluation->update($data);
        ActivityLog::write('Ubah evaluasi', 'Evaluasi #'.$evaluation->id);

        return redirect()->route('evaluations.show', $evaluation)->with('ok', 'Evaluasi diperbarui.');
    }

    public function destroy(PostTrainingEvaluation $evaluation)
    {
        $evaluation->delete();
        ActivityLog::write('Hapus evaluasi', 'Evaluasi #'.$evaluation->id);

        return redirect()->route('evaluations.index')->with('ok', 'Evaluasi dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id'         => ['required','exists:users,id'],
            'course_id'       => ['nullable','exists:courses,id'],
            'trainer_name'    => ['nullable','string','max:150'],
            'knowledge_score' => ['required','integer','min:0','max:100'],
            'skill_score'     => ['required','integer','min:0','max:100'],
            'attitude_score'  => ['required','integer','min:0','max:100'],
            'safety_score'    => ['required','integer','min:0','max:100'],
            'recommendation'  => ['nullable', Rule::in(self::REKOMENDASI)],
            'strengths'       => ['nullable','string','max:2000'],
            'improvements'    => ['nullable','string','max:2000'],
            'notes'           => ['nullable','string','max:2000'],
        ]);
    }

    private function rerata(array $d): int
    {
        return (int) round((
            $d['knowledge_score'] + $d['skill_score'] +
            $d['attitude_score']  + $d['safety_score']
        ) / 4);
    }
}
