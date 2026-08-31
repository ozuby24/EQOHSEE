<?php

namespace App\Http\Controllers;

use App\Models\Investigasi\{
    AkarMasalah, Bukti, HierarkiKendali, Insiden, Investigasi, JenisInsiden,
    Jejak, KlasifikasiCedera, KlasifikasiRegulasi, Kronologi, Lokasi,
    Pembelajaran, Taksonomi, Temuan, Tim, Tindakan
};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Investigasi\{MasterInvestigasi, NomorInvestigasi, TahapInvestigasi, Triase};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Investigasi kecelakaan — register insiden, triase, dan ruang kerjanya.
 *
 * SATU CONTROLLER UNTUK TIGA LAYAR, dan itu disengaja. Insiden, triase,
 * dan investigasi adalah satu proses yang dilalui berurutan oleh orang
 * yang sama; memecahnya menjadi tiga controller berarti tiga tempat yang
 * harus diubah bersamaan setiap kali satu langkah bergeser, dan yang
 * tertinggal tidak menimbulkan galat — hanya layar yang menunjukkan
 * keadaan yang sudah tidak berlaku.
 */
class InvestigasiController extends Controller
{
    /* ═══════════════════ DASBOR ═══════════════════ */

    /**
     * Ringkasan yang menjawab "apa yang menunggu saya", bukan "berapa banyak".
     *
     * Angka besar tanpa tautan tidak dibuat di sini. Kartu bertuliskan
     * "24 insiden" yang tidak dapat ditekan hanya memberi tahu bahwa
     * datanya banyak; yang dicari pembacanya selalu barisnya.
     */
    public function dasbor()
    {
        $insiden = Insiden::with('investigasi')->orderByDesc('tanggal_kejadian')->get();

        $berjalan = Investigasi::whereIn('insiden_id', $insiden->pluck('id'))
            ->with('insiden')->where('status', 'berjalan')->get();

        return Inertia::render('Investigasi/Dasbor', $this->bersama() + [
            'ringkas' => [
                'insiden'       => $insiden->count(),
                'belumTriase'   => $insiden->filter(fn (Insiden $i) => ! $i->sudahDitriase())->count(),
                'tanpaBerkas'   => $insiden->filter(fn (Insiden $i) => $i->sudahDitriase() && ! $i->investigasi)->count(),
                'berjalan'      => $berjalan->count(),
                'terlambatLapor' => $insiden->filter(fn (Insiden $i) => $i->terlambatLapor())->count(),
            ],

            /* Sebaran per level — dan yang belum ditriase ikut disebut
               sebagai golongan tersendiri, bukan dibuang. Insiden tanpa
               level bukan insiden ringan; ia insiden yang belum dinilai
               siapa pun, dan itu keadaan yang lebih perlu terlihat. */
            'perLevel' => collect(Triase::NAMA_LEVEL)->map(fn ($nama, $k) => [
                'level'  => $k,
                'nama'   => $nama,
                'jumlah' => $insiden->where('level_investigasi', $k)->count(),
            ])->values()->all(),

            'perTahap' => collect(TahapInvestigasi::URUTAN)->map(fn ($nama, $k) => [
                'tahap'  => $k,
                'nama'   => $nama,
                'jumlah' => $berjalan->where('tahap', $k)->count(),
            ])->values()->all(),

            'perluTindakan' => $this->perluTindakan($insiden, $berjalan),

            'tindakanTelat' => Tindakan::with(['temuan.investigasi.insiden', 'pic'])
                ->whereIn('status', ['terbuka', 'berjalan'])
                ->whereNotNull('tenggat')->whereDate('tenggat', '<', now())
                ->orderBy('tenggat')->limit(12)->get()
                ->map(fn (Tindakan $t) => [
                    'id'      => $t->id,
                    'nomor'   => $t->no_tindakan,
                    'uraian'  => $t->uraian,
                    'tenggat' => $t->tenggat?->toDateString(),
                    'telat'   => $t->telatHari(),
                    'pic'     => $t->pic?->name ?? $t->pic_nama,
                    'invId'   => $t->temuan?->investigasi_id,
                    'invNo'   => $t->temuan?->investigasi?->no_investigasi,
                ])->values(),
        ]);
    }

    /**
     * Apa yang menunggu tindakan, disusun menurut mendesaknya.
     *
     * Urutannya bukan abjad dan bukan tanggal: yang di atas adalah yang
     * melanggar tenggat regulasi, sesudahnya yang belum dinilai sama
     * sekali, baru yang sedang berjalan. Daftar yang diurutkan menurut
     * tanggal membuat pelanggaran hukum berada di bawah pekerjaan rutin.
     */
    private function perluTindakan($insiden, $berjalan): array
    {
        $out = [];

        foreach ($insiden->filter(fn (Insiden $i) => $i->terlambatLapor()) as $i) {
            $out[] = [
                'jenis'  => 'terlambat-lapor',
                'label'  => 'Lewat tenggat lapor KaIT',
                'nomor'  => $i->no_insiden,
                'judul'  => $i->judul,
                'id'     => $i->id,
                'tautan' => 'insiden',
            ];
        }

        foreach ($insiden->filter(fn (Insiden $i) => ! $i->sudahDitriase()) as $i) {
            $out[] = [
                'jenis'  => 'belum-triase',
                'label'  => 'Belum ditriase',
                'nomor'  => $i->no_insiden,
                'judul'  => $i->judul,
                'id'     => $i->id,
                'tautan' => 'triase',
            ];
        }

        foreach ($berjalan as $inv) {
            $kurang = TahapInvestigasi::yangKurang($inv);

            if (! $kurang) continue;

            $out[] = [
                'jenis'  => 'tahap-tertahan',
                'label'  => TahapInvestigasi::URUTAN[$inv->tahap] ?? $inv->tahap,
                'nomor'  => $inv->no_investigasi,
                'judul'  => $kurang[0],
                'id'     => $inv->id,
                'tautan' => 'investigasi',
            ];
        }

        return array_slice($out, 0, 15);
    }

    /* ═══════════════════ INSIDEN ═══════════════════ */

    public function insiden(Request $request)
    {
        $baris = Insiden::with(['jenis', 'lokasi', 'investigasi', 'pelapor'])
            ->when($cari = trim((string) $request->get('q')), fn ($q) => $q->where(
                fn ($w) => $w->where('judul', 'like', "%{$cari}%")
                    ->orWhere('no_insiden', 'like', "%{$cari}%")))
            ->when($request->get('level'), fn ($q, $l) => $q->where('level_investigasi', $l))
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal_kejadian')->orderByDesc('id')
            ->get();

        return Inertia::render('Investigasi/Insiden', $this->bersama() + [
            'baris'  => $baris->map(fn (Insiden $i) => $this->barisInsiden($i))->values(),
            'saring' => [
                'q'      => $cari,
                'level'  => $request->get('level'),
                'status' => $request->get('status'),
            ],
        ]);
    }

    public function insidenBaru()
    {
        return Inertia::render('Investigasi/InsidenForm', $this->bersama());
    }

    public function insidenSimpan(Request $request)
    {
        $data = $request->validate($this->aturanInsiden());

        $insiden = Insiden::create($data + [
            'no_insiden'      => NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN),
            'pelapor_id'      => auth()->id(),
            'dilaporkan_pada' => now(),
            'status'          => 'dilaporkan',
        ]);

        Jejak::catat('Insiden dilaporkan', $insiden->judul, null, $insiden->id);

        /* Dialihkan LANGSUNG ke triase, bukan kembali ke daftar.
           Insiden yang tercatat tanpa level adalah insiden yang belum
           dinilai siapa pun, dan langkah yang dipisahkan satu klik dari
           penyimpanan adalah langkah yang paling sering tidak pernah
           dikerjakan. */
        return redirect()->route('investigasi.triase', $insiden)
            ->with('ok', 'Insiden '.$insiden->no_insiden.' tercatat. Lanjutkan dengan triase.');
    }

    public function insidenDetail(Insiden $insiden)
    {
        $insiden->load(['jenis', 'lokasi', 'cedera', 'regulasi', 'pelapor', 'orang', 'investigasi']);

        return Inertia::render('Investigasi/InsidenDetail', $this->bersama() + [
            'insiden' => $this->barisInsiden($insiden) + [
                'kronologi'      => $insiden->kronologi,
                'tindakanSegera' => $insiden->tindakan_segera,
                'lokasiRinci'    => $insiden->lokasi_rinci,
                'aktivitas'      => $insiden->aktivitas,
                'kriteriaKurang' => $insiden->kriteriaKurang(),
                'orang'          => $insiden->orang->map(fn ($o) => [
                    'id' => $o->id, 'nama' => $o->nama, 'jabatan' => $o->jabatan,
                    'perusahaan' => $o->perusahaan, 'peran' => $o->peran,
                    'bagianTubuh' => $o->bagian_tubuh, 'rincianCedera' => $o->rincian_cedera,
                    'hariHilang' => $o->hari_hilang,
                ])->values(),
                'pemicu' => $this->pemicu($insiden),
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function aturanInsiden(): array
    {
        return [
            'judul'            => ['required', 'string', 'max:200'],
            'tanggal_kejadian' => ['required', 'date'],
            'waktu_kejadian'   => ['nullable', 'date_format:H:i'],
            'lokasi_id'        => ['nullable', 'exists:inv_lokasi,id'],
            'lokasi_rinci'     => ['nullable', 'string', 'max:200'],
            'aktivitas'        => ['nullable', 'string', 'max:200'],
            'jenis_insiden_id' => ['nullable', 'exists:inv_jenis_insiden,id'],
            'klasifikasi_cedera_id' => ['nullable', 'exists:inv_klasifikasi_cedera,id'],
            'kronologi'        => ['nullable', 'string', 'max:5000'],
            'tindakan_segera'  => ['nullable', 'string', 'max:2000'],

            /* Enam pemicu saran lapis 3. Divalidasi DI SINI dan punya
               medan di layar — kode lapis 3 yang benar tanpa jalan
               mengisi kolomnya adalah kode yang tidak akan pernah
               berjalan, dan ujinya tetap hijau memakai data contoh. */
            'p_shift_malam'      => ['nullable', 'boolean'],
            'p_lembur_panjang'   => ['nullable', 'boolean'],
            'p_sop_tidak_ada'    => ['nullable', 'boolean'],
            'p_belum_dilatih'    => ['nullable', 'boolean'],
            'p_inspeksi_absen'   => ['nullable', 'boolean'],
            'p_insiden_berulang' => ['nullable', 'boolean'],
        ];
    }

    /* ═══════════════════ TRIASE ═══════════════════ */

    public function triase(Insiden $insiden)
    {
        return Inertia::render('Investigasi/Triase', $this->bersama() + [
            'insiden' => $this->barisInsiden($insiden),
            'matriks' => Triase::matriks(),
            'label'   => [
                'kemungkinan' => Triase::LABEL_KEMUNGKINAN,
                'keparahan'   => Triase::LABEL_KEPARAHAN,
            ],
            'kriteria' => Triase::KRITERIA,
            'metode'   => Triase::METODE,
            'penyetuju' => Triase::PENYETUJU,
        ]);
    }

    public function triaseSimpan(Request $request, Insiden $insiden)
    {
        $data = $request->validate([
            'kemungkinan'         => ['required', 'integer', 'min:1', 'max:5'],
            'keparahan'           => ['required', 'integer', 'min:1', 'max:5'],
            'keparahan_potensial' => ['nullable', 'integer', 'min:1', 'max:5'],
            'klasifikasi_regulasi_id' => ['nullable', 'exists:inv_klasifikasi_regulasi,id'],
            'k1_benar_terjadi'      => ['nullable', 'boolean'],
            'k2_mencederai_pekerja' => ['nullable', 'boolean'],
            'k3_akibat_kegiatan'    => ['nullable', 'boolean'],
            'k4_jam_kerja'          => ['nullable', 'boolean'],
            'k5_wilayah_usaha'      => ['nullable', 'boolean'],
        ]);

        Triase::terapkan($insiden, $data);

        Jejak::catat('Triase', $insiden->level_investigasi.' · skor '.$insiden->skor_risiko,
            null, $insiden->id);

        return redirect()->route('investigasi.insiden.detail', $insiden)
            ->with('ok', 'Triase tersimpan: '.Triase::NAMA_LEVEL[$insiden->level_investigasi].'.');
    }

    /* ═══════════════════ INVESTIGASI ═══════════════════ */

    /**
     * Membuka berkas investigasi bagi sebuah insiden.
     *
     * Memakai POST, bukan tautan GET: aksinya membuat baris baru, dan
     * tautan GET dapat terpicu prefetch peramban tanpa pengguna
     * menyentuh apa pun.
     */
    public function investigasiBuka(Insiden $insiden)
    {
        abort_unless($insiden->sudahDitriase(), 422,
            'Insiden harus ditriase lebih dahulu — levelnya yang menentukan jalur investigasinya.');

        $inv = $insiden->investigasi ?: Investigasi::create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id'     => $insiden->id,
            'ketua_id'       => auth()->id(),
        ]);

        $insiden->update(['status' => 'diselidiki']);

        Jejak::catat('Investigasi dibuka', $inv->no_investigasi, $inv->id, $insiden->id);

        return redirect()->route('investigasi.detail', $inv);
    }

    public function investigasi(Request $request)
    {
        $baris = Investigasi::with(['insiden', 'ketua'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->get();

        return Inertia::render('Investigasi/Daftar', $this->bersama() + [
            'baris'  => $baris->map(fn (Investigasi $i) => $this->barisInvestigasi($i))->values(),
            'saring' => ['status' => $request->get('status')],
        ]);
    }

    public function detail(Investigasi $investigasi)
    {
        $investigasi->load([
            'insiden.jenis', 'insiden.lokasi', 'ketua', 'tim.user',
            'kronologi', 'bukti.pengunci', 'akar.taksonomi', 'akar.bukti',
            'temuan.tindakan.hierarki', 'temuan.tindakan.pic', 'pembelajaran',
        ]);

        return Inertia::render('Investigasi/RuangKerja', $this->bersama() + [
            'inv' => $this->barisInvestigasi($investigasi) + [
                'tujuan'       => $investigasi->tujuan,
                'ruangLingkup' => $investigasi->ruang_lingkup,

                'rel'          => TahapInvestigasi::rel($investigasi),
                'tugas'        => TahapInvestigasi::tugas($investigasi->tahap, $investigasi->level()),
                'kurang'       => TahapInvestigasi::yangKurang($investigasi),
                'bolehMaju'    => TahapInvestigasi::bolehMaju($investigasi),
                'kurangTutup'  => TahapInvestigasi::yangKurangUntukTutup($investigasi),
                'metodeWajib'  => Triase::metode($investigasi->level()),
                'penyetuju'    => Triase::PENYETUJU[$investigasi->level()] ?? null,

                'tim' => $investigasi->tim->map(fn (Tim $t) => [
                    'id' => $t->id, 'nama' => $t->user?->name, 'peran' => $t->peran_tim,
                ])->values(),

                'kronologi' => $investigasi->kronologi->map(fn (Kronologi $k) => [
                    'id' => $k->id, 'waktu' => $k->waktu?->format('Y-m-d H:i'),
                    'peristiwa' => $k->peristiwa, 'keterangan' => $k->keterangan,
                    'penyebab' => $k->penyebab,
                ])->values(),

                'bukti' => $investigasi->bukti->map(fn (Bukti $b) => [
                    'id' => $b->id, 'nomor' => $b->no_bukti, 'jenis' => $b->jenis,
                    'jenisLabel' => Bukti::JENIS[$b->jenis] ?? $b->jenis,
                    'judul' => $b->judul, 'keterangan' => $b->keterangan,
                    'sumber' => $b->sumber,
                    'tanggal' => $b->dikumpulkan_pada?->toDateString(),
                    'sidik' => $b->sidikPendek(),
                    'dikunci' => $b->dikunci,
                    'pengunci' => $b->pengunci?->name,
                    'url' => $b->berkas ? Berkas::url($b, 'evd') : null,
                ])->values(),

                'akar' => $investigasi->akar->map(fn (AkarMasalah $a) => [
                    'id' => $a->id, 'uraian' => $a->uraian, 'metode' => $a->metode,
                    'taksonomi' => $a->taksonomi ? [
                        'kode' => $a->taksonomi->kode,
                        'label' => $a->taksonomi->label,
                        'kategori' => $a->taksonomi->kategori,
                        'metode' => $a->taksonomi->metode,
                    ] : null,
                    'bukti' => $a->bukti->pluck('no_bukti')->values(),
                    'disokong' => $a->bukti->isNotEmpty(),
                ])->values(),

                'temuan' => $investigasi->temuan->map(fn (Temuan $t) => [
                    'id' => $t->id, 'nomor' => $t->no_temuan, 'uraian' => $t->uraian,
                    'rekomendasi' => $t->rekomendasi, 'tingkat' => $t->tingkat,
                    'akarId' => $t->akar_id,
                    'tindakan' => $t->tindakan->map(fn (Tindakan $x) => [
                        'id' => $x->id, 'nomor' => $x->no_tindakan, 'uraian' => $x->uraian,
                        'status' => $x->status, 'statusLabel' => Tindakan::STATUS[$x->status] ?? $x->status,
                        'tenggat' => $x->tenggat?->toDateString(),
                        'telat' => $x->telatHari(),
                        'pic' => $x->pic?->name ?? $x->pic_nama,
                        'hierarki' => $x->hierarki?->nama,
                        'tingkat' => $x->hierarki?->tingkat,
                    ])->values(),
                ])->values(),

                'pembelajaran' => $investigasi->pembelajaran->map(fn (Pembelajaran $p) => [
                    'id' => $p->id, 'judul' => $p->judul, 'ringkasan' => $p->ringkasan,
                    'pesanKunci' => $p->pesan_kunci,
                    'tanggal' => $p->diterbitkan_pada?->toDateString(),
                ])->values(),

                'rantai' => [
                    'bukti'    => $investigasi->bukti->count(),
                    'akar'     => $investigasi->akar->count(),
                    'temuan'   => $investigasi->temuan->count(),
                    'tindakan' => $investigasi->temuan->sum(fn (Temuan $t) => $t->tindakan->count()),
                ],
            ],
        ]);
    }

    /* ═══════════════════ bentuk baris ═══════════════════ */

    private function barisInsiden(Insiden $i): array
    {
        return [
            'id'        => $i->id,
            'nomor'     => $i->no_insiden,
            'judul'     => $i->judul,
            'tanggal'   => $i->tanggal_kejadian?->toDateString(),
            'waktu'     => $i->waktu_kejadian,
            'jenis'     => $i->jenis?->nama,
            'lokasi'    => $i->lokasi?->nama,
            'pelapor'   => $i->pelapor?->name,
            'status'    => $i->status,

            'kemungkinan' => $i->kemungkinan,
            'keparahan'   => $i->keparahan,
            'keparahanPotensial' => $i->keparahan_potensial,
            'skor'      => $i->skor_risiko,
            'pita'      => $i->pita_risiko,
            'warnaPita' => Triase::warnaPita($i->pita_risiko),
            'level'     => $i->level_investigasi,
            'levelNama' => $i->level_investigasi ? Triase::NAMA_LEVEL[$i->level_investigasi] : null,
            'ditriase'  => $i->sudahDitriase(),

            'wajibLapor'     => $i->wajib_lapor_kait,
            'tenggatLapor'   => $i->tenggat_lapor?->format('Y-m-d H:i'),
            'tenggatSelidik' => $i->tenggat_selidik?->format('Y-m-d H:i'),
            'terlambatLapor' => $i->terlambatLapor(),
            'regulasi'       => $i->regulasi?->nama,
            'cedera'         => $i->cedera?->nama,

            'invId'    => $i->investigasi?->id,
            'invNomor' => $i->investigasi?->no_investigasi,
        ];
    }

    private function barisInvestigasi(Investigasi $i): array
    {
        return [
            'id'      => $i->id,
            'nomor'   => $i->no_investigasi,
            'judul'   => $i->insiden?->judul,
            'insiden' => $i->insiden ? [
                'id' => $i->insiden->id, 'nomor' => $i->insiden->no_insiden,
                'tanggal' => $i->insiden->tanggal_kejadian?->toDateString(),
            ] : null,
            'ketua'   => $i->ketua?->name,
            'ketuaId' => $i->ketua_id,
            'prioritas' => $i->prioritas,
            'target'  => $i->target_selesai?->toDateString(),
            'tahap'   => $i->tahap,
            'tahapNama' => TahapInvestigasi::URUTAN[$i->tahap] ?? $i->tahap,
            'status'  => $i->status,
            'level'   => $i->level(),
            'levelNama' => $i->level() ? Triase::NAMA_LEVEL[$i->level()] : null,
            'pita'    => $i->insiden?->pita_risiko,
            'warnaPita' => Triase::warnaPita($i->insiden?->pita_risiko),
            'skor'    => $i->insiden?->skor_risiko,
            'ditutup' => $i->ditutup_pada?->format('Y-m-d H:i'),
        ];
    }

    /** Enam pemicu saran lapis 3 yang dicentang pelapor. */
    private function pemicu(Insiden $i): array
    {
        $peta = [
            'p_shift_malam'      => 'Shift malam',
            'p_lembur_panjang'   => 'Jam kerja panjang / lembur',
            'p_sop_tidak_ada'    => 'SOP tidak tersedia',
            'p_belum_dilatih'    => 'Pekerja belum dilatih',
            'p_inspeksi_absen'   => 'Inspeksi tidak pernah dilakukan',
            'p_insiden_berulang' => 'Kejadian serupa pernah terjadi',
        ];

        return collect($peta)->filter(fn ($l, $k) => (bool) $i->{$k})->values()->all();
    }

    /** Prop yang dipakai setiap layar modul ini. */
    private function bersama(): array
    {
        return [
            'judul'    => 'Investigasi Kecelakaan',
            'subjudul' => 'Dari laporan insiden sampai pembelajaran yang diterbitkan',
            'opsi' => [
                'jenisInsiden' => JenisInsiden::orderBy('urutan')->get(['id', 'nama', 'kelompok']),
                'cedera'       => KlasifikasiCedera::orderBy('urutan')->get(['id', 'nama', 'keterangan']),
                'regulasi'     => KlasifikasiRegulasi::orderBy('urutan')->get(['id', 'nama', 'wajib_lapor_kait', 'keterangan']),
                'lokasi'       => Lokasi::where('aktif', true)->orderBy('nama')->get(['id', 'nama', 'area']),
                'hierarki'     => HierarkiKendali::orderBy('tingkat')->get(['id', 'nama', 'tingkat', 'keterangan']),
                'orang'        => User::orderBy('name')->get(['id', 'name']),
                'peranTim'     => ['Ketua', 'Anggota', 'Ahli teknis', 'Wakil pekerja', 'Notulis'],
                'jenisBukti'   => Bukti::JENIS,
                'statusTindakan' => Tindakan::STATUS,
                'tingkatTemuan'  => Temuan::TINGKAT,
                'tahap'        => TahapInvestigasi::URUTAN,
                'level'        => Triase::NAMA_LEVEL,
            ],
        ];
    }
}
