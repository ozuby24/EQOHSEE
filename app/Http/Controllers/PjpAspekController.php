<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use App\Models\PjpEvaluasi;
use App\Models\PjpLaporan;
use App\Models\SmkpChecklistCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Beranda modul dan ketiga halaman aspek pemantauan PJP.
 *
 * Ketiga halaman aspek menampilkan SELURUH PJP terdaftar, bukan sebagian.
 * Yang berbeda hanyalah skor mana yang ditonjolkan sebagai `capaian` pada
 * grafik batang halaman itu:
 *
 *   persyaratan → skor checklist prakualifikasi SMKP
 *   pelaporan   → skor kepatuhan pelaporan (null bila belum pernah unggah)
 *   evaluasi    → skor rata-rata evaluasi terakhir (null bila belum dinilai)
 *
 * Ini nilai per HALAMAN, bukan sifat PJP-nya. Satu perusahaan muncul di
 * ketiganya sekaligus dengan tiga angka berbeda, dan itu memang bentuk
 * yang benar: checklist, laporan, dan evaluasi berjalan bersamaan.
 */
class PjpAspekController extends Controller
{
    /** Beranda modul: ringkasan lintas aspek. */
    public function index()
    {
        $statusJumlah = Pjp::statusCountsFor();

        return Inertia::render('Pjp/Beranda', [
            'judul'    => 'Perusahaan Jasa Pertambangan',
            'subjudul' => 'Pemantauan dan pengelolaan PJP lewat tiga aspek yang berjalan bersamaan.',

            'ringkas' => [
                'total'             => array_sum($statusJumlah),
                'aktif'             => $statusJumlah['aktif'],
                'perluTindakLanjut' => $statusJumlah['perlu_tindak_lanjut'],
                'tidakAktif'        => $statusJumlah['tidak_aktif'],
            ],

            'statusJumlah' => $statusJumlah,
            'statusOpsi'   => Pjp::STATUS,

            /*
             * Dua daftar yang sengaja berbeda pertanyaannya.
             *
             * "belumLaporan" menjawab tunggakan bulan berjalan — kosong
             * sebelum tanggal batas terlewati, sebab sebelum itu belum
             * ada yang terlambat.
             *
             * "perluPerhatian" menjawab capaian lintas aspek: lima PJP
             * dengan skor gabungan terendah di bawah ambang.
             */
            'belumLaporan'   => Pjp::belumLaporanBulananBulanIni()->values(),
            'perluPerhatian' => Pjp::perluPerhatian(),
            'ambangPerhatian' => Pjp::AMBANG_PERHATIAN,
            'batasTanggal'   => PjpLaporan::BATAS_TANGGAL_LAPORAN,

            'tautan' => $this->tautan(),
        ]);
    }

    public function persyaratan(Request $request)
    {
        return $this->aspek($request, 'Pjp/Persyaratan', 'persyaratan');
    }

    public function pelaporan(Request $request)
    {
        return $this->aspek($request, 'Pjp/Pelaporan', 'pelaporan');
    }

    public function evaluasi(Request $request)
    {
        return $this->aspek($request, 'Pjp/Evaluasi', 'evaluasi');
    }

    /** Tanya jawab modul — daftar tetap, tanpa data. */
    public function bantuan()
    {
        return Inertia::render('Pjp/Bantuan', [
            'judul'    => 'Bantuan Modul Perusahaan Jasa Pertambangan',
            'subjudul' => 'Aspek pemantauan, checklist persyaratan, pelaporan, dan evaluasi kinerja.',
            'faq'      => $this->faq(),
            'tautan'   => $this->tautan(),
        ]);
    }

    /* ---------- bantu ---------- */

    private function aspek(Request $request, string $halaman, string $metrik)
    {
        $pjps = $this->semuaPjp($request);

        $baris = $pjps->map(function (Pjp $pjp) use ($metrik) {
            $baris = [
                'id'              => $pjp->id,
                'nama_perusahaan' => $pjp->nama_perusahaan,
                'status'          => $pjp->status,
                'statusLabel'     => Pjp::STATUS[$pjp->status] ?? $pjp->status,
                'capaian'         => null,
            ];

            if ($metrik === 'persyaratan') {
                $skor = $pjp->smkpScore();
                $baris['smkpPersentase'] = $skor['persentase'];
                $baris['kategoriRisiko'] = $skor['kategori_risiko'];
                $baris['capaian']        = $skor['persentase'];
            }

            if ($metrik === 'pelaporan') {
                $baris['capaian'] = $pjp->pelaporanScore();
            }

            if ($metrik === 'evaluasi') {
                $terakhir = $pjp->evaluasis->first();

                $baris['evaluasiTerakhir'] = $terakhir ? [
                    'periode'        => $terakhir->periodeSingkat(),
                    'skor_rata_rata' => $terakhir->skor_rata_rata,
                ] : null;

                /*
                 * Riwayat diurutkan naik menurut (tahun, semester) supaya
                 * garis tren dibaca dari kiri ke kanan. Relasinya sendiri
                 * mengurutkan turun — itu yang dibutuhkan "evaluasi
                 * terakhir", dan menyeragamkan keduanya berarti salah
                 * satunya menjadi salah.
                 */
                $baris['riwayatEvaluasi'] = $pjp->evaluasis
                    ->sortBy(fn (PjpEvaluasi $e) => [$e->tahun, $e->semester])
                    ->map(fn (PjpEvaluasi $e) => [
                        'tahun'          => $e->tahun,
                        'semester'       => $e->semester,
                        'periode'        => $e->periodeSingkat(),
                        'skor_rata_rata' => $e->skor_rata_rata,
                    ])->values()->all();

                $baris['capaian'] = $terakhir?->skor_rata_rata;
            }

            return $baris;
        })->values()->all();

        return Inertia::render($halaman, [
            'judul'    => $this->judulAspek($metrik),
            'subjudul' => $this->subjudulAspek($metrik),

            'pjps'         => $baris,
            'saring'       => [
                'cari'   => $request->query('cari', ''),
                'status' => $request->query('status', ''),
            ],
            'statusJumlah' => Pjp::statusCountsFor(),
            'statusOpsi'   => Pjp::STATUS,

            'checklistRingkas' => $metrik === 'persyaratan' ? [
                'kategori'   => SmkpChecklistCategory::count(),
                'pertanyaan' => \App\Models\SmkpChecklistItem::count(),
                'totalBobot' => SmkpChecklistCategory::TOTAL_BOBOT,
            ] : null,

            'jenisLaporan' => $metrik === 'pelaporan' ? PjpLaporan::JENIS : null,
            'batasTanggal' => PjpLaporan::BATAS_TANGGAL_LAPORAN,
            'bulanTriwulanDibuka' => PjpLaporan::bulanTriwulanDibuka(),

            'tautan' => $this->tautan() + ['terapkan' => route("pjp.{$metrik}")],
        ]);
    }

    /**
     * Seluruh PJP beserta relasi yang dibutuhkan skornya, dimuat sekali.
     *
     * Eager load di sini bukan penghalusan melainkan syarat: tanpa itu,
     * tiap baris menanyakan jawabannya, laporannya, dan evaluasinya
     * sendiri — sekitar lima kueri per PJP, pada halaman yang memang
     * menggambar seluruh PJP.
     */
    private function semuaPjp(Request $request): Collection
    {
        return Pjp::query()
            ->with(['laporans', 'evaluasis', 'smkpChecklistAnswers'])
            ->filter($request->query('cari'), $request->query('status'))
            ->latest()
            ->get();
    }

    private function judulAspek(string $metrik): string
    {
        return match ($metrik) {
            'persyaratan' => 'Persyaratan, Seleksi, dan Penetapan',
            'pelaporan'   => 'Tanggung Jawab, Pemantauan, dan Pelaporan',
            'evaluasi'    => 'Evaluasi Kinerja',
        };
    }

    private function subjudulAspek(string $metrik): string
    {
        return match ($metrik) {
            'persyaratan' => 'Prakualifikasi calon PJP lewat checklist SMKP berbobot, seleksi, hingga penetapan resmi.',
            'pelaporan'   => 'Kepatuhan PJP mengirim dokumen wajib beserta ketepatan waktu dan kesesuaian isinya.',
            'evaluasi'    => 'Penilaian kinerja per semester pada aspek teknis, keselamatan dan kesehatan, serta lingkungan.',
        };
    }

    private function tautan(): array
    {
        return [
            'beranda'     => route('pjp.index'),
            'persyaratan' => route('pjp.persyaratan'),
            'pelaporan'   => route('pjp.pelaporan'),
            'evaluasi'    => route('pjp.evaluasi'),
            'daftar'      => route('pjp.daftar'),
            'baru'        => route('pjp.baru'),
            'bantuan'     => route('pjp.bantuan'),
            'detail'      => route('pjp.detail', ['pjp' => '__ID__']),
            'checklistUntuk' => route('pjp.checklist', ['pjp' => '__ID__']),
        ];
    }

    /**
     * Tanya jawab modul.
     *
     * Ditulis di server, bukan di dalam komponen Vue: isinya menjelaskan
     * aturan yang hidup di model (tanggal batas, bulan triwulan, cara
     * skor dihitung), dan menaruhnya di sisi peramban membuat penjelasan
     * itu terpisah dari aturan yang dijelaskannya.
     */
    private function faq(): array
    {
        $batas  = PjpLaporan::BATAS_TANGGAL_LAPORAN;
        $bulan  = PjpLaporan::bulanTriwulanDibuka();
        $ambang = Pjp::AMBANG_PERHATIAN;

        return [
            [
                'tanya' => 'Apa yang dipantau modul ini?',
                'jawab' => 'Perusahaan Jasa Pertambangan (PJP) yang bekerja di wilayah izin, lewat tiga aspek '
                    .'yang berjalan bersamaan untuk setiap PJP: Persyaratan, Seleksi, dan Penetapan; '
                    .'Tanggung Jawab, Pemantauan, dan Pelaporan; serta Evaluasi Kinerja. Seluruh PJP '
                    .'terdaftar tampil di ketiga halaman itu sekaligus, masing-masing dengan skornya sendiri.',
            ],
            [
                'tanya' => 'Kenapa satu PJP muncul di ketiga halaman aspek sekaligus?',
                'jawab' => 'Karena ketiganya bukan tahapan berurutan. Checklist persyaratan, unggahan laporan, '
                    .'dan penilaian kinerja adalah tiga hal yang dapat diisi kapan saja untuk perusahaan yang '
                    .'sama, tanpa harus menunggu yang lain selesai.',
            ],
            [
                'tanya' => 'Apa itu checklist Persyaratan PJP?',
                'jawab' => 'Checklist prakualifikasi SMKP: 17 kategori dan 126 pertanyaan berbobot, dipakai '
                    .'menilai kepatuhan sebuah PJP sebelum ditetapkan menjadi rekanan aktif.',
            ],
            [
                'tanya' => 'Bagaimana skor Persyaratan dihitung?',
                'jawab' => 'Jumlah (bobot × nilai ÷ 3) dibagi jumlah bobot yang dinilai, dari kategori A–P '
                    .'yang berbobot total '.SmkpChecklistCategory::TOTAL_BOBOT.'. Pertanyaan bernilai N/A '
                    .'dikeluarkan dari perhitungan maupun dari pembaginya, sedangkan pertanyaan yang belum '
                    .'dijawab tetap ikut sebagai pembagi dengan skor 0 — tanpa itu, mengisi satu pertanyaan '
                    .'saja sudah berbunyi 100%.',
            ],
            [
                'tanya' => 'Kenapa Dokumen Legalitas terpisah dari skor?',
                'jawab' => 'Akta pendirian, NIB, IUJP, dan NPWP tidak punya tingkatan "cukup memadai" — '
                    .'dokumennya ada atau tidak ada. Karena itu ia dihitung sebagai kelengkapan lulus/belum '
                    .'dan berdiri di luar skor '.SmkpChecklistCategory::TOTAL_BOBOT.' poin.',
            ],
            [
                'tanya' => 'Apa arti label "Kritis" pada kategori risiko?',
                'jawab' => 'Terbalik dari dugaan yang wajar: label itu menyatakan tingkat risiko pekerjaan '
                    .'yang LAYAK dipercayakan kepada PJP tersebut, bukan tingkat bahaya PJP-nya. Skor tinggi '
                    .'berarti layak untuk pekerjaan berisiko kritis, jadi skor 100% memang berlabel "Kritis".',
            ],
            [
                'tanya' => 'Dokumen apa saja yang wajib diunggah?',
                'jawab' => 'Empat jenis: Data SPIP (Sarana, Prasarana, Instalasi & Peralatan), Target Sasaran '
                    .'Program (TSP), Laporan Bulanan, dan Laporan Triwulan — semuanya dari halaman detail PJP.',
            ],
            [
                'tanya' => 'Kapan sebuah dokumen dihitung tepat waktu?',
                'jawab' => "Bila diunggah pada atau sebelum tanggal {$batas} bulan berjalan. Aturan ini berlaku "
                    .'untuk semua jenis dokumen, dan dihitung dari waktu unggahnya, bukan diketik manual.',
            ],
            [
                'tanya' => 'Kapan Laporan Triwulan bisa diunggah?',
                'jawab' => "Hanya pada bulan {$bulan}. Di luar bulan itu formulir unggahnya disembunyikan dan "
                    .'unggahan tetap ditolak server bila dicoba lewat jalan lain.',
            ],
            [
                'tanya' => 'Apa itu skor Kepatuhan Pelaporan?',
                'jawab' => 'Rata-rata dua hal: persentase dokumen yang diunggah tepat waktu, dan persentase '
                    .'dokumen yang dinilai "sesuai" isinya — yang kedua hanya dihitung dari dokumen yang sudah '
                    .'dinilai. PJP yang belum pernah mengunggah apa pun tertulis "Belum ada laporan", bukan 0%.',
            ],
            [
                'tanya' => 'Bagaimana Evaluasi Kinerja diisi?',
                'jawab' => 'Lewat kartu Evaluasi Kinerja di halaman detail PJP, per semester, dengan tiga skor '
                    .'0–100: Teknis, Keselamatan & Kesehatan, dan Lingkungan. Rata-ratanya dihitung otomatis. '
                    .'Mengisi ulang semester yang sama akan menimpa nilainya, bukan menambah baris baru.',
            ],
            [
                'tanya' => 'Apa arti warna pada grafik capaian?',
                'jawab' => 'Baik (≥ 80), Perlu Perhatian (60–79), Perlu Tindak Lanjut (40–59), dan Kritis '
                    .'(< 40). Grafiknya selalu diurutkan dari skor terendah supaya yang paling butuh tindak '
                    .'lanjut terlihat lebih dulu.',
            ],
            [
                'tanya' => 'Bagaimana angka "Paling Perlu Perhatian" di beranda dihitung?',
                'jawab' => "Diambil dari skor TERENDAH di antara ketiga aspek yang datanya tersedia, lalu "
                    ."disaring di bawah {$ambang}. Terendah, bukan rata-rata — supaya satu aspek yang buruk "
                    .'tidak tertutup oleh dua aspek lain yang baik.',
            ],
            [
                'tanya' => 'Bagaimana mengekspor datanya?',
                'jawab' => 'Tombol Ekspor CSV di halaman Data PJP mengunduh seluruh PJP beserta ketiga skornya '
                    .'mengikuti penyaring yang sedang aktif. Untuk satu PJP, tombol Cetak di halaman detailnya '
                    .'membuka lembar siap cetak atau simpan sebagai PDF.',
            ],
        ];
    }
}
