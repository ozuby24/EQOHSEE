<?php
namespace App\Http\Controllers;

use App\Models\{Course, Enrollment, Certificate, HazardReport, Inspection, KoObject,
    Document, News, Procedure, SmkpAudit, SmkpFinding, SopEvaluationAttempt,
    TpkkpAssessment, User};
use App\Support\{BelajarGrafik, Kategori, Media, Pengumuman, Sampul, Waktu};
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $kini = Carbon::now();

        /* Rentang dipilih dari layar dan dibatasi daftar tertutup. Nilai
           bebas dari URL berarti seseorang dapat meminta 100.000 hari
           dan menunggu selamanya sambil mengunci basis datanya. */
        $hari = (int) $request->get('hari', BelajarGrafik::RENTANG_BAWAAN);
        if (!in_array($hari, BelajarGrafik::RENTANG, true)) $hari = BelajarGrafik::RENTANG_BAWAAN;

        $enrollments = Enrollment::with('course')->where('user_id', $user->id)->latest()->get();

        /* Dihitung SEBELUM literalnya: elemen larik tidak dapat
           menunjuk elemen lain di dalam literal yang sama, dan kop
           memerlukan tujuan yang sama dengan kartu di bawahnya. */

        $lanjut = $enrollments->firstWhere('status', 'ongoing') ?? $enrollments->first();

        $tujuanLanjut = $lanjut ? route('learn.show', $lanjut->course) : route('courses.index');


        $data = [
            'judul'       => 'Dashboard',
            'subjudul'    => 'Kelola pembelajaran dan tingkatkan kompetensi Anda',
            'sapa'        => Waktu::sapaan(),
            'nama'        => trim(explode(' ', $user->name)[0]),
            'hero'        => Media::url('galeri/budaya.jpg'),
            'lanjut'      => $tujuanLanjut,
            'enrollments' => $enrollments->take(3)->map(function ($e) {
                $c = $e->course;
                $jumlah = $c?->modules()->count() ?? 0;
                return [
                    'judul'    => $c?->title ?? 'Kursus',
                    'deskripsi'=> Str::limit($c?->description, 78),
                    'sampul'   => $c ? Sampul::untuk($c) : null,
                    'kategori' => $c?->category,
                    'nada'     => $c?->category ? Kategori::nada($c->category) : null,
                    'status'   => $e->status,
                    'progress' => (int) $e->progress,
                    'modul'    => $jumlah,
                    'menit'    => max(10, $jumlah * 15),
                    'belajar'  => $c ? route('learn.show', $c) : route('courses.index'),
                    'detail'   => $c ? route('courses.show', $c) : route('courses.index'),
                ];
            })->values()->all(),
            'certificates'=> Certificate::where('user_id',$user->id)->count(),
            'sopPassed'   => SopEvaluationAttempt::where('user_id',$user->id)->where('passed',true)->count(),
            /* Muatan PENUH, bukan sekadar cuplikan bertaut.
               Pengumuman kini terbuka sebagai pop-out di halaman ini,
               dan pop-out yang masih harus mengambil isinya lewat
               jaringan akan gagal terbuka justru di sambungan site yang
               lambat. Cacat itu sudah pernah terjadi pada pop-out
               pengenalan dan berubah menjadi kotak galat yang muncul
               lagi setiap kali halamannya disegarkan.

               Tiga baris, dan hanya tiga. Isi utuh sepuluh pengumuman
               pada setiap pembukaan dasbor adalah harga yang dibayar
               terus-menerus; tiga adalah yang memang tergambar. */
            'news' => Pengumuman::kueri($user->id)->latest('published_at')->take(3)->get()
                ->map(fn ($n) => Pengumuman::muatan($n, 74))->values()->all(),
            'modul'       => collect($this->ringkasModul())->map(fn ($m) => $m + [
                'url' => route($m['rute']),
            ])->all(),
            'ringkas'     => $this->ringkasBelajar($enrollments),
            'kategori'    => collect($this->ringkasKategori())->map(fn ($k) => $k + ['nada' => Kategori::nada($k['nama'])])->all(),
            'admin'       => null,

            'hari'     => $hari,
            'opsiHari' => BelajarGrafik::RENTANG,

            /* ── grafik ──
               Milik orang ini, bukan seluruh situs; angka seluruh situs
               sudah punya rumahnya di /dasbor. `penyelesaian` adalah
               satu-satunya yang melihat orang lain, dan karena itu ia
               hanya diisi bagi pelatih dan administrator. */
            'grafik' => [
                'kegiatan'    => BelajarGrafik::kegiatan($user->id, $kini, $hari),
                'kemajuan'    => BelajarGrafik::kemajuanKursus($enrollments),
                'status'      => BelajarGrafik::statusKursus($enrollments, Course::count()),
                'nilai'       => BelajarGrafik::sebaranNilai($user->id),
                'sertifikat'  => BelajarGrafik::sertifikatBulanan($user->id, $kini),
                'penyelesaian' => $user->isAdmin() || $user->isTrainer()
                    ? BelajarGrafik::penyelesaianKursus()
                    : null,
            ],

            /* SPANDUK HALAMAN INI DILIPAT KE DALAM KOP KERANGKA.
               Sebelumnya halaman ini menggambar hero besarnya sendiri
               tepat di bawah kop — dua spanduk bertumpuk setinggi
               separuh layar, dengan sapaan yang sama tercetak tiga kali
               (bilah atas, kop, spanduk). Yang tersisa dari spanduk itu
               adalah satu-satunya bagian yang memang perlu: tombol
               menuju kursus yang sedang dikerjakan. */
            'kop' => [
                'aksi' => [
                    'label' => $enrollments->isNotEmpty()
                        ? 'Lanjutkan Pembelajaran' : 'Jelajahi Kursus',
                    'url'   => $tujuanLanjut,
                ],
            ],
        ];
        if ($user->isAdmin()) {
            $data['admin'] = [
                'users'      => User::count(),
                'courses'    => Course::count(),
                'procedures' => Procedure::count(),
                'certs'      => Certificate::count(),
            ];
        }
        return Inertia::render('Dashboard', $data);
    }

    /**
     * Angka pembuka dashboard.
     *
     * Kemajuan rata-rata dihitung dari pendaftaran yang benar-benar ada;
     * tanpa pendaftaran, yang benar adalah nol, bukan seratus. Membagi
     * dengan nol lalu menampilkan 100% adalah kesalahan yang paling
     * meyakinkan bentuknya.
     */
    private function ringkasBelajar($enrollments): array
    {
        $selesai = $enrollments->where('status', 'finished')->count();
        $berjalan = $enrollments->where('status', 'ongoing')->count();

        return [
            'total'    => Course::count(),
            'diikuti'  => $enrollments->count(),
            'selesai'  => $selesai,
            'berjalan' => $berjalan,
            'kemajuan' => $enrollments->count() > 0
                ? (int) round($enrollments->avg('progress'))
                : 0,
            'belum'    => max(0, Course::count() - $enrollments->count()),
        ];
    }

    /** Kategori kursus beserta jumlahnya, untuk pintasan di kaki halaman. */
    private function ringkasKategori(): array
    {
        return Course::selectRaw('category, COUNT(*) as jumlah')
            ->whereNotNull('category')->where('category', '<>', '')
            ->groupBy('category')->orderByDesc('jumlah')
            ->get()->map(fn ($r) => ['nama' => $r->category, 'jumlah' => (int) $r->jumlah])
            ->all();
    }

    /**
     * Ringkasan lintas modul untuk pintasan di dashboard.
     * Angka yang ditonjolkan adalah yang butuh perhatian (belum tuntas),
     * bukan sekadar jumlah total.
     */
    private function ringkasModul(): array
    {
        return [
            [
                'nama'  => 'Hazard Report',
                'ket'   => 'Laporan bahaya belum tuntas',
                'nilai' => HazardReport::where('status','<>','Closed')->count(),
                'total' => HazardReport::count(),
                'ikon'  => 'hazard',
                'rute'  => 'hazard.index',
                'warna' => '#F0921E',
            ],
            [
                'nama'  => 'Inspeksi',
                'ket'   => 'Inspeksi masih berjalan',
                'nilai' => Inspection::where('status','<>','Selesai')->count(),
                'total' => Inspection::count(),
                'ikon'  => 'inspeksi',
                'rute'  => 'inspeksi.index',
                'warna' => '#0FA08F',
            ],
            [
                'nama'  => 'Keselamatan Operasi',
                'ket'   => 'Objek perlu ditindak',
                'nilai' => KoObject::whereDate('pm_berikutnya','<',now())->count(),
                'total' => KoObject::count(),
                'ikon'  => 'objek',
                'rute'  => 'ko.index',
                'warna' => '#1093B8',
            ],
            [
                'nama'  => 'Audit SMKP',
                'ket'   => 'Temuan belum ditutup',
                'nilai' => SmkpFinding::where('status','<>','Closed')->count(),
                'total' => SmkpAudit::count(),
                'ikon'  => 'audit',
                'rute'  => 'smkp.index',
                'warna' => '#4FA82E',
            ],
            [
                'nama'  => 'ISO & Dokumen',
                'ket'   => 'Dokumen lewat masa tinjau',
                'nilai' => Document::where('status','berlaku')->whereNotNull('tanggal_tinjau')
                             ->whereDate('tanggal_tinjau','<',now())->count(),
                'total' => Document::count(),
                'ikon'  => 'materi',
                'rute'  => 'dokumen.index',
                'warna' => '#17A2DC',
            ],
            [
                'nama'  => 'Safety Maturity',
                'ket'   => 'Penilaian PTPKKP',
                'nilai' => TpkkpAssessment::count(),
                'total' => TpkkpAssessment::count(),
                'ikon'  => 'penilaian',
                'rute'  => 'tpkkp.index',
                'warna' => '#2E6BE6',
            ],
        ];
    }
}
