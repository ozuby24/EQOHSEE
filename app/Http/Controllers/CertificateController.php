<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Certificate, Company, Course, Enrollment, PostTrainingEvaluation, Signatory};
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Support\Berkas;

class CertificateController extends Controller
{
    public const TEMPLATE = [
        'klasik'   => 'Klasik — bingkai formal',
        'modern'   => 'Modern — aksen gradien',
        'minimal'  => 'Minimal — bersih & lapang',
        'korporat' => 'Korporat — kop perusahaan',
    ];

    public function index()
    {
        $me = auth()->user();

        // Terbitkan otomatis untuk kursus yang sudah selesai
        $this->autoIssue();

        $certificates = Certificate::with(['course','user','company','signatory'])
            ->when(!$me->isAdmin(), fn($q) => $q->where('user_id', $me->id))
            ->latest('issued_at')->get();

        $menungguEvaluasi = $this->menungguEvaluasi();
        $tunda = $menungguEvaluasi->pluck('course_id')->all();

        $claimable = Enrollment::with('course')->where('user_id', $me->id)->where('status','finished')
            ->whereNotIn('course_id', Certificate::where('user_id', $me->id)->pluck('course_id'))
            ->whereNotIn('course_id', $tunda ?: [0])->get();

        return Inertia::render('Sertifikat/Daftar', [
            'judul'    => 'Sertifikat',
            'subjudul' => 'Sertifikat pelatihan yang sudah terbit dan yang menunggu',

            'sertifikat' => $certificates->map(fn (Certificate $c) => [
                'id'      => $c->id,
                'kursus'  => $c->course_title,
                'nomor'   => $c->certificate_number,
                'tanggal' => $c->issued_at?->format('d M Y'),
                'penerima'=> $c->recipient_name,
                'url'     => route('certificates.show', $c),
            ])->all(),

            // "Siap diterbitkan" hanya muncul untuk kursus yang memang
            // tidak mensyaratkan evaluasi trainer; yang mensyaratkannya
            // ada di daftar menunggu, bukan di sini.
            'siapTerbit' => $claimable->map(fn ($en) => [
                'kursus' => $en->course?->title,
                'url'    => route('certificates.store', $en->course),
            ])->all(),

            'menungguEvaluasi' => $menungguEvaluasi->map(fn ($en) => $en->course?->title)
                ->filter()->values()->all(),

            'admin' => $me->isAdmin(),
        ]);
    }

    /**
     * Terbit otomatis. Alur: kursus selesai → dievaluasi trainer → sertifikat terbit.
     * Kursus yang tidak mensyaratkan evaluasi langsung terbit setelah selesai.
     */
    private function autoIssue(): void
    {
        $selesai = Enrollment::with('course')->where('user_id', auth()->id())->where('status','finished')->get();

        foreach ($selesai as $en) {
            $c = $en->course;
            if (!$c || !($c->auto_certificate ?? true)) continue;
            if (Certificate::where('user_id', auth()->id())->where('course_id', $en->course_id)->exists()) continue;

            $evaluasi = PostTrainingEvaluation::where('user_id', auth()->id())
                            ->where('course_id', $en->course_id)->latest()->first();

            if (($c->require_evaluation ?? true) && !$evaluasi) continue;   // tunggu penilaian trainer

            $this->buat($c, $en, $evaluasi);
        }
    }

    /** Kursus selesai yang masih menunggu penilaian trainer */
    private function menungguEvaluasi()
    {
        $dinilai = PostTrainingEvaluation::where('user_id', auth()->id())->pluck('course_id')->all();

        return Enrollment::with('course')->where('user_id', auth()->id())->where('status','finished')->get()
            ->filter(fn($en) => $en->course
                             && ($en->course->require_evaluation ?? true)
                             && !in_array($en->course_id, $dinilai, true)
                             && !Certificate::where('user_id', auth()->id())->where('course_id', $en->course_id)->exists())
            ->values();
    }

    /** Terbit manual (tombol) */
    public function store(Course $course)
    {
        $en = Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->firstOrFail();
        abort_unless($en->status === 'finished', 403, 'Kursus belum selesai.');

        $evaluasi = PostTrainingEvaluation::where('user_id', auth()->id())
                        ->where('course_id', $course->id)->latest()->first();

        if (($course->require_evaluation ?? true) && !$evaluasi) {
            return back()->withErrors(['cert' => 'Sertifikat terbit setelah trainer menyelesaikan evaluasi pelatihan.']);
        }

        $cert = Certificate::where('user_id', auth()->id())->where('course_id', $course->id)->first()
              ?? $this->buat($course, $en, $evaluasi);

        return redirect()->route('certificates.show', $cert)->with('ok', 'Sertifikat diterbitkan.');
    }

    /**
     * Nomor sertifikat berikutnya bagi satu perusahaan pada tahun ini.
     *
     * Dua hal yang diperbaiki dari cara lama, dan keduanya menghasilkan
     * nomor yang terlihat wajar:
     *
     * - Dihitung PER PERUSAHAAN. Penghitung lama mencakup seluruh
     *   sertifikat pada tahun berjalan, sehingga nomor PT A melompat
     *   setiap kali PT B menerbitkan satu. Prefiksnya per perusahaan,
     *   urutannya tidak — dan gabungan itu terbaca sebagai penomoran
     *   per perusahaan yang bocor.
     *
     * - Diturunkan dari nomor TERTINGGI, bukan dari jumlah baris.
     *   Dengan count()+1, satu sertifikat yang dihapus membuat nomor
     *   berikutnya mengulang nomor yang sudah pernah terbit — dua lembar
     *   bernomor sama, dan keduanya lolos verifikasi barcode.
     *
     * Urutan dibaca kembali dari nomor yang sudah ada, bukan disimpan di
     * penghitung tersendiri: penghitung yang terpisah dari datanya akan
     * berselisih dengan data itu cepat atau lambat, dan yang menang
     * biasanya bukan yang benar.
     */
    private function nomorBerikutnya(?int $companyId, string $prefiks): string
    {
        $tahun = now()->year;

        $terpakai = Certificate::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId),
                               fn ($q) => $q->whereNull('company_id'))
            ->whereYear('issued_at', $tahun)
            ->pluck('certificate_number');

        $tertinggi = 0;
        foreach ($terpakai as $n) {
            if (preg_match('#/(\d+)$#', (string) $n, $c)) {
                $tertinggi = max($tertinggi, (int) $c[1]);
            }
        }

        return sprintf('%s/%s/%s/%04d', $prefiks, 'SRT', $tahun, $tertinggi + 1);
    }

    private function buat(Course $course, Enrollment $en, ?PostTrainingEvaluation $evaluasi = null): Certificate
    {
        $u = auth()->user();

        // Pengguna tanpa perusahaan tidak dititipkan ke perusahaan mana
        // pun. Sebelumnya diambilkan Company::orderBy('id')->first(),
        // sehingga sertifikatnya diam-diam tercatat milik perusahaan
        // ber-id terkecil — lengkap dengan kop dan logonya.
        $co = $u->company_id ? Company::find($u->company_id) : null;

        $pfx   = $co?->doc_no_prefix ?: 'EQ';
        $nomor = $this->nomorBerikutnya($co?->id, $pfx);
        $kode  = strtoupper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        // Penanda tangan perusahaan penerima, lalu penanda tangan pusat.
        // Tidak pernah milik perusahaan lain: lebih baik lembar tanpa
        // tanda tangan daripada lembar yang mencantumkan pejabat yang
        // tidak pernah menyetujuinya.
        $ttd = Signatory::withoutGlobalScopes()->untukPerusahaan($co?->id)->first();

        $cert = Certificate::create([
            'user_id'            => $u->id,
            'course_id'          => $course->id,
            'company_id'         => $co?->id,
            'template'           => $course->cert_template ?: 'klasik',
            'certificate_number' => $nomor,
            'verification_code'  => $kode,
            'recipient_name'     => $u->name,
            'course_title'       => $course->title,
            'final_score'        => $evaluasi?->overall_score ?: $en->progress,
            'signed_by_name'     => $ttd?->name,
            'signatory_id'       => $ttd?->id,
            'issued_at'          => now(),
        ]);
        ActivityLog::write('Terbitkan sertifikat', $course->title.' — '.$u->name);

        return $cert;
    }

    public function show(Certificate $certificate)
    {
        abort_unless($certificate->user_id === auth()->id() || auth()->user()->isAdmin(), 403);
        $certificate->load(['course','signatory','user','company.owner']);

        $logo = $certificate->company?->effectiveLogo();

        return Inertia::render('Sertifikat/Lembar', [
            'judul'    => 'Sertifikat',
            'subjudul' => $certificate->certificate_number,

            'c' => [
                'template'   => $certificate->template ?: 'klasik',
                'penerima'   => $certificate->recipient_name,
                'kursus'     => $certificate->course_title,
                'nomor'      => $certificate->certificate_number,
                'nilai'      => $certificate->final_score,
                'terbit'     => $certificate->issued_at?->translatedFormat('d F Y'),
                'pemilik'    => $certificate->company?->ownerName(),
                'lokasi'     => $certificate->company?->location,
                'logo'       => $logo ? Berkas::terbuka($logo) : null,
                'ttdNama'    => $certificate->signed_by_name ?: '—',
                'ttdJabatan' => $certificate->signatory?->title ?: 'Penanggung Jawab',
                'ttdGambar'  => Berkas::url($certificate->signatory, 'ttd'),

                // Barcode digambar di server sebagai SVG. Menggambarnya di
                // peramban berarti aturan pengkodeannya ada dua salinan,
                // dan lembar yang dicetak bisa memuat kode yang tidak
                // dikenali halaman verifikasinya sendiri.
                'barcodeSvg' => \App\Support\Barcode::svg($certificate->barcodeText(), 40, 1.4),
                'barcodeTeks'=> $certificate->barcodeText(),
            ],

            'markUrl' => asset('brand/eqohsee-mark.png'),

            'tautan' => [
                'verifikasi' => route('certificates.verify',
                    $certificate->verification_code ?: $certificate->certificate_number),
                'daftar' => route('certificates.index'),
            ],
        ]);
    }

    /** Halaman verifikasi publik (tanpa login) — tujuan pemindaian barcode */
    public function verify(string $kode)
    {
        $c = Certificate::with(['course','user','company','signatory'])
                ->where('verification_code', $kode)
                ->orWhere('certificate_number', $kode)->first();

        return Inertia::render('Certificates/Verify', [
            'kode' => $kode,
            'c' => $c ? [
                'penerima' => $c->recipient_name,
                'kursus' => $c->course_title,
                'perusahaan' => $c->company?->ownerName() ?: '—',
                'nomor' => $c->certificate_number,
                'kodeVerifikasi' => $c->verification_code,
                'nilai' => $c->final_score ?: '—',
                'terbit' => $c->issued_at?->format('d F Y'),
                'ditandatangani' => $c->signed_by_name ?: '—',
            ] : null,
        ]);
    }
}
