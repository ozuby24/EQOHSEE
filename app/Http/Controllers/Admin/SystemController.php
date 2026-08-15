<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Certificate, Company, Course, Enrollment, Material, Module, News,
    PostTrainingEvaluation, Procedure, Quiz, QuizAttempt, Signatory, SopEvaluation,
    SopEvaluationAttempt, TpkkpAssessment, TpkkpResponse, User};
use App\Support\{DataContoh, Ikon};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SystemController extends Controller
{
    public function index()
    {
        $server = [
            'Aplikasi'     => config('app.name'),
            'Laravel'      => app()->version(),
            'PHP'          => PHP_VERSION,
            'Basis data'   => config('database.default'),
            'Lingkungan'   => app()->environment(),
            'Mode debug'   => config('app.debug') ? 'AKTIF' : 'nonaktif',
            'Zona waktu'   => config('app.timezone'),
            'Waktu server' => now()->format('d M Y · H:i:s'),
        ];
        try {
            $free = @disk_free_space(base_path()); $total = @disk_total_space(base_path());
            $server['Ruang disk'] = ($free && $total) ? $this->human($free).' / '.$this->human($total) : '—';
        } catch (\Throwable $e) { $server['Ruang disk'] = '—'; }

        // Statistik per modul — mencakup seluruh website
        $modul = [
            'Inti' => [
                'route' => null,
                'items' => ['Pengguna' => User::count(), 'Perusahaan' => Company::count(), 'Log aktivitas' => ActivityLog::count()],
            ],
            'LMS — Learning' => [
                'route' => 'courses.index',
                'items' => [
                    'Kursus' => Course::count(), 'Modul' => Module::count(), 'Materi' => Material::count(),
                    'Kuis' => Quiz::count(), 'Pendaftaran' => Enrollment::count(),
                    'Percobaan kuis' => QuizAttempt::count(), 'Sertifikat' => Certificate::count(),
                    'Penanda tangan' => Signatory::count(), 'Evaluasi trainer' => PostTrainingEvaluation::count(),
                    'Prosedur' => Procedure::count(), 'Evaluasi SOP' => SopEvaluation::count(),
                    'Percobaan SOP' => SopEvaluationAttempt::count(), 'Berita' => News::count(),
                ],
            ],
            'Safety Maturity Level' => [
                'route' => 'tpkkp.index',
                'items' => [
                    'Penilaian' => TpkkpAssessment::count(),
                    'Responden kuesioner' => TpkkpResponse::count(),
                    'Sudah dinilai' => TpkkpAssessment::whereNotNull('scores')->get()
                                        ->filter(fn($a) => !empty($a->scores))->count(),
                ],
            ],
        ];

        $roles = [
            'Administrator' => User::where('is_admin', true)->count(),
            'Trainer'       => User::where('lms_role', 'trainer')->count(),
            'KTT'           => User::where('lms_role', 'ktt')->count(),
            'Auditor'       => User::where('audit_role', 'auditor')->count(),
            'Perusahaan'    => User::where('audit_role', 'company')->count(),
            'Peserta'       => User::where(fn($q) => $q->whereNull('lms_role')->orWhere('lms_role','trainee'))->count(),
            'Nonaktif'      => User::where('active', false)->count(),
        ];

        $companies = Company::withCount('users')->orderBy('name')->get();
        $logs = ActivityLog::latest()->take(30)->get();

        // Aktivitas tujuh hari terakhir, dihitung sekali di sini.
        // Menghitungnya di peramban berarti seluruh log harus dikirim
        // hanya untuk menggambar tujuh angka.
        $hari = collect(range(6, 0))->map(fn ($i) => now()->subDays($i));
        $tren = $hari->map(fn ($d) => [
            'label'  => $d->isoFormat('dd'),
            'jumlah' => $logs->filter(fn ($l) => $l->created_at && $l->created_at->isSameDay($d))->count(),
        ])->values()->all();

        return Inertia::render('Admin/Sistem', [
            'judul'    => 'Pusat Kendali Sistem',
            'subjudul' => 'Status server, statistik modul, dan log aktivitas',

            'server' => array_map(fn ($k) => ['label' => $k, 'nilai' => (string) $server[$k]],
                                  array_keys($server)),

            'modul' => array_map(fn ($nama) => [
                'nama'  => $nama,
                'url'   => $modul[$nama]['route'] ? route($modul[$nama]['route']) : null,
                'items' => array_map(fn ($label) => [
                    'label' => $label,
                    'nilai' => $modul[$nama]['items'][$label],
                    'ikon'  => Ikon::untuk($label),
                ], array_keys($modul[$nama]['items'])),
            ], array_keys($modul)),

            'peran' => array_map(fn ($k) => ['label' => $k, 'jumlah' => $roles[$k]], array_keys($roles)),
            'tren'  => $tren,

            'perusahaan' => $companies->map(fn ($c) => [
                'id'        => $c->id,
                'nama'      => $c->name,
                'komoditas' => $c->commodity ?: null,
                'lokasi'    => $c->location ?: null,
                'pekerja'   => $c->totalWorkers(),
                'pengguna'  => $c->users_count,
                'urlUbah'   => route('admin.companies.edit', $c),

                'demo'      => (bool) $c->demo,
                // Dihitung hanya untuk perusahaan contoh: menghitungnya
                // untuk semua berarti tiga puluh kueri per perusahaan
                // pada halaman yang tidak memerlukannya.
                'isi'       => $c->demo ? DataContoh::rincianIsi($c) : null,
                'urlTandai' => route('admin.system.demo.tandai', $c),
                'urlMuat'   => route('admin.system.demo.muat', $c),
            ])->all(),

            'log' => $logs->map(fn ($l) => [
                'id'      => $l->id,
                'aksi'    => $l->action,
                'modul'   => $l->module,
                'detail'  => $l->detail,
                'oleh'    => $l->username ?: null,
                'waktu'   => $l->created_at?->format('d M · H:i'),
            ])->all(),

            'pintasan' => [
                ['url' => route('admin.companies.index'), 'label' => 'Kelola Perusahaan',
                 'sub' => 'tambah · ubah · hapus', 'warna' => '#2E6BE6',
                 'ikon' => 'M3 21V8l7-4 7 4v13M17 21V11l4 2v8M8 21v-4h4v4'],
                ['url' => route('admin.users.index'), 'label' => 'Kelola Pengguna',
                 'sub' => 'peran & akses', 'warna' => '#0FA08F',
                 'ikon' => 'M9 8a3.2 3.2 0 100 6.4 3.2 3.2 0 000-6.4zM3.5 20a5.5 5.5 0 0111 0M17 8.5a3 3 0 010 5.4M20.5 20a5 5 0 00-3-4.6'],
                ['url' => route('signatories.index'), 'label' => 'Penanda Tangan',
                 'sub' => 'sertifikat', 'warna' => '#F57C00',
                 'ikon' => 'M12 3l2 4 4 .6-3 3 .8 4-3.8-2-3.8 2 .8-4-3-3 4-.6zM6 21s2-4 6-4 6 4 6 4'],
                ['url' => route('kuesioner.admin'), 'label' => 'Kuesioner PTPKKP',
                 'sub' => 'tautan & hasil', 'warna' => '#22C55E',
                 'ikon' => 'M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h5'],
            ],

            'tautan' => [
                'perusahaanBaru' => route('admin.companies.create'),
                'bersihkanLog'   => route('admin.system.logs.clear'),
            ],

            'pemeliharaan' => array_map(fn ($a) => [
                'aksi'  => $a[0],
                'label' => $a[1],
                'url'   => route('admin.system.maintenance', $a[0]),
            ], [
                ['cache',  'Bersihkan semua cache'],
                ['view',   'Cache tampilan'],
                ['config', 'Cache konfigurasi'],
                ['route',  'Cache rute'],
            ]),
        ]);
    }

    public function clearLogs()
    {
        ActivityLog::truncate();
        ActivityLog::write('Bersihkan log', 'Log aktivitas dikosongkan');
        return back()->with('ok', 'Log aktivitas dibersihkan.');
    }

    /** Aksi pemeliharaan sistem */
    public function maintenance(string $aksi)
    {
        $peta = [
            'cache'  => ['optimize:clear', 'Cache aplikasi dibersihkan.'],
            'view'   => ['view:clear',     'Cache tampilan dibersihkan.'],
            'config' => ['config:clear',   'Cache konfigurasi dibersihkan.'],
            'route'  => ['route:clear',    'Cache rute dibersihkan.'],
        ];
        abort_unless(isset($peta[$aksi]), 404);

        Artisan::call($peta[$aksi][0]);
        ActivityLog::write('Pemeliharaan sistem', $peta[$aksi][0]);

        return back()->with('ok', $peta[$aksi][1]);
    }

    /* ═══════════ data contoh ═══════════ */

    /**
     * Tandai atau lepas tanda perusahaan contoh.
     *
     * Yang dijaga di sini adalah arah MENANDAI, bukan melepasnya:
     * menandai berarti membuka perusahaan itu bagi pemuat yang membuang
     * seluruh datanya. Perusahaan yang sudah berisi tidak boleh ditandai
     * begitu saja — bukan karena tidak mungkin disengaja, melainkan
     * karena kesengajaan itu perlu dikatakan dengan kalimat, bukan
     * dengan satu klik pada baris yang salah.
     */
    public function tandaiContoh(Request $request, Company $company)
    {
        $data = $request->validate([
            'demo'  => ['required', 'boolean'],
            'sadar' => ['nullable', 'string'],
        ]);

        if ($data['demo'] && !$company->demo) {
            $isi = DataContoh::hitungIsi($company);

            if ($isi > 0 && ($data['sadar'] ?? null) !== $company->name) {
                return back()->withErrors(['demo' =>
                    'Perusahaan "'.$company->name.'" sudah berisi '.number_format($isi, 0, ',', '.')
                    .' baris data. Menandainya sebagai perusahaan contoh membuat seluruh data itu '
                    .'dapat dibuang oleh tombol muat ulang. Ketik nama perusahaannya persis untuk '
                    .'menegaskan bahwa itu memang yang dimaksud.']);
            }
        }

        $company->update(['demo' => $data['demo']]);

        ActivityLog::write($data['demo'] ? 'Tandai perusahaan contoh' : 'Lepas tanda perusahaan contoh',
            $company->name, 'sistem');

        return back()->with('ok', $data['demo']
            ? $company->name.' ditandai sebagai perusahaan contoh.'
            : 'Tanda perusahaan contoh dilepas dari '.$company->name.'.');
    }

    /**
     * Muat ulang data contoh satu perusahaan.
     *
     * Dibungkus transaksi: pemuatan yang gagal di tengah meninggalkan
     * perusahaan yang datanya sudah terbuang tetapi belum terisi —
     * keadaan yang lebih buruk daripada kedua ujungnya.
     */
    public function muatContoh(Company $company)
    {
        try {
            $hasil = DB::transaction(fn () => DataContoh::muat($company, auth()->user()));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['demo' => $e->getMessage()]);
        }

        ActivityLog::write('Muat ulang data contoh',
            $company->name.' · '.$hasil['dihapus'].' dibuang, '
            .array_sum($hasil['dibuat']).' dibuat', 'sistem');

        $pesan = 'Data contoh '.$company->name.' dimuat ulang: '
            .$hasil['dihapus'].' baris dibuang, '.array_sum($hasil['dibuat']).' baris dibuat.';

        // Catatan pemuat dinaikkan menjadi galat, bukan disisipkan ke
        // pesan berhasil: yang paling berguna dilaporkannya adalah
        // "datanya masuk tetapi masih draf", dan itu terbaca sebagai
        // kegagalan hitungan bila tidak menonjol.
        return $hasil['catatan'] === []
            ? back()->with('ok', $pesan)
            : back()->with('ok', $pesan)->withErrors(['demo' => implode(' ', $hasil['catatan'])]);
    }

    private function human(float $b): string
    {
        $u = ['B','KB','MB','GB','TB']; $i = 0;
        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }
        return round($b, 1).' '.$u[$i];
    }
}
