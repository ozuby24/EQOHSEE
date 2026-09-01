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
use Illuminate\Validation\Rule;
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


    /* ═══════════════════ AKSI TULIS ═══════════════════
     *
     * SELURUHNYA POST, tanpa kecuali — termasuk penghapusan dan
     * pemajuan tahap. Tautan GET yang menghapus bukti atau memajukan
     * tahap dapat terpicu prefetch peramban tanpa pengguna menyentuh
     * apa pun, dan pada berkas yang dapat diminta Inspektur Tambang,
     * satu penghapusan yang tidak disengaja tidak dapat dijelaskan
     * kepada siapa pun.
     *
     * Yang sudah DITUTUP tidak lagi menerima perubahan. Penjagaannya
     * satu tempat — `pastikanBerjalan()` — bukan diulang di tiap
     * metode: penjagaan yang disalin ke dua belas tempat akan
     * tertinggal di tempat ketiga belas, dan yang tertinggal tidak
     * menimbulkan galat, hanya berkas tertutup yang diam-diam berubah
     * isinya sesudah ditandatangani.
     */

    /** Investigasi yang sudah ditutup tidak menerima perubahan apa pun. */
    private function pastikanBerjalan(Investigasi $inv): void
    {
        abort_unless($inv->berjalan(), 422,
            'Investigasi '.$inv->no_investigasi.' sudah ditutup. '
            .'Buka kembali lebih dahulu bila memang perlu diubah.');
    }

    public function simpanKeterangan(Request $request, Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $investigasi->update($request->validate([
            'ketua_id'       => ['nullable', 'exists:users,id'],
            'target_selesai' => ['nullable', 'date'],
            'prioritas'      => ['nullable', 'in:rendah,sedang,tinggi'],
            'tujuan'         => ['nullable', 'string', 'max:2000'],
            'ruang_lingkup'  => ['nullable', 'string', 'max:2000'],
        ]));

        return back()->with('ok', 'Keterangan investigasi tersimpan.');
    }

    /* ── tim ── */

    public function timTambah(Request $request, Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $data = $request->validate([
            'user_id'   => ['required', 'exists:users,id'],
            'peran_tim' => ['nullable', 'string', 'max:60'],
        ]);

        /* firstOrCreate, bukan create: menambahkan orang yang sama dua
           kali melanggar kunci unik dan memulangkan galat basis data
           mentah ke layar. Yang benar bukan galat melainkan tidak
           terjadi apa-apa. */
        $investigasi->tim()->firstOrCreate(
            ['user_id' => $data['user_id']],
            ['peran_tim' => $data['peran_tim'] ?? null],
        );

        return back()->with('ok', 'Anggota tim ditambahkan.');
    }

    public function timHapus(Investigasi $investigasi, Tim $tim)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($tim->investigasi_id === $investigasi->id, 404);

        $tim->delete();

        return back()->with('ok', 'Anggota tim dilepas.');
    }

    /* ── kronologi ── */

    public function kronologiTambah(Request $request, Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $data = $request->validate([
            'waktu'      => ['nullable', 'date'],
            'peristiwa'  => ['required', 'string', 'max:300'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'penyebab'   => ['nullable', 'boolean'],
        ]);

        $investigasi->kronologi()->create($data + [
            'urutan' => (int) $investigasi->kronologi()->max('urutan') + 1,
        ]);

        return back()->with('ok', 'Peristiwa ditambahkan ke kronologi.');
    }

    public function kronologiHapus(Investigasi $investigasi, Kronologi $kronologi)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($kronologi->investigasi_id === $investigasi->id, 404);

        $kronologi->delete();

        return back()->with('ok', 'Peristiwa dihapus.');
    }

    /* ── bukti ── */

    public function buktiTambah(Request $request, Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $data = $request->validate([
            'jenis'            => ['required', Rule::in(array_keys(Bukti::JENIS))],
            'judul'            => ['required', 'string', 'max:200'],
            'keterangan'       => ['nullable', 'string', 'max:2000'],
            'sumber'           => ['nullable', 'string', 'max:200'],
            'dikumpulkan_pada' => ['nullable', 'date'],
            'berkas'           => ['nullable', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,mp4,mov,doc,docx,xls,xlsx'],
        ]);

        $jalur = null;
        $sidik = null;

        if ($f = $request->file('berkas')) {
            /* Sidik jarinya dihitung dari berkas SEBELUM disimpan, saat
               ia masih di jalur unggahan sementara. Dihitung sesudah
               tersimpan pun sama nilainya — yang penting ia dihitung
               SEKALI lalu tidak pernah dihitung ulang. Menghitungnya
               ulang tiap kali barisnya dibaca akan membuat berkas yang
               diganti tetap terlihat cocok dengan catatannya, dan
               seluruh gunanya hilang. */
            $sidik = hash_file('sha256', $f->getRealPath());
            $jalur = Berkas::simpan($f, 'investigasi/bukti');
        }

        $investigasi->bukti()->create([
            'no_bukti'         => NomorInvestigasi::terbitkan(NomorInvestigasi::BUKTI),
            'jenis'            => $data['jenis'],
            'judul'            => $data['judul'],
            'keterangan'       => $data['keterangan'] ?? null,
            'sumber'           => $data['sumber'] ?? null,
            'dikumpulkan_pada' => $data['dikumpulkan_pada'] ?? now()->toDateString(),
            'berkas'           => $jalur,
            'mime'             => $f?->getClientMimeType(),
            'sha256'           => $sidik,
            'dikumpulkan_oleh' => auth()->id(),
        ]);

        Jejak::catat('Bukti ditambahkan', $data['judul'], $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Bukti dicatat.');
    }

    /**
     * Kunci sebuah bukti.
     *
     * SATU ARAH, dan tidak ada rute membukanya kembali. Kunci yang
     * dapat dibuka lagi tidak menjamin apa pun — ia hanya menambah satu
     * langkah bagi siapa pun yang hendak mengganti isinya, dan langkah
     * yang dapat dilewati bukan penjagaan melainkan hiasan.
     */
    public function buktiKunci(Investigasi $investigasi, Bukti $bukti)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($bukti->investigasi_id === $investigasi->id, 404);

        if ($bukti->dikunci) return back();

        $bukti->update([
            'dikunci'      => true,
            'dikunci_pada' => now(),
            'dikunci_oleh' => auth()->id(),
        ]);

        Jejak::catat('Bukti dikunci', $bukti->no_bukti, $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Bukti '.$bukti->no_bukti.' dikunci.');
    }

    public function buktiHapus(Investigasi $investigasi, Bukti $bukti)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($bukti->investigasi_id === $investigasi->id, 404);

        abort_if($bukti->dikunci, 422,
            'Bukti '.$bukti->no_bukti.' sudah dikunci dan tidak dapat dihapus. '
            .'Itulah gunanya dikunci.');

        Berkas::buang($bukti->berkas);
        $bukti->delete();

        Jejak::catat('Bukti dihapus', $bukti->no_bukti, $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Bukti dihapus.');
    }

    /* ── akar masalah ── */

    public function akarTambah(Request $request, Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $data = $request->validate([
            'uraian'       => ['required', 'string', 'max:2000'],
            'metode'       => ['nullable', 'string', 'max:20'],
            'taksonomi_id' => ['nullable', 'exists:inv_taksonomi,id'],
            'bukti'        => ['array'],
            'bukti.*'      => ['integer'],
        ]);

        $akar = $investigasi->akar()->create([
            'uraian'       => $data['uraian'],
            'metode'       => $data['metode'] ?? '5why',
            'taksonomi_id' => $data['taksonomi_id'] ?? null,
            'urutan'       => (int) $investigasi->akar()->max('urutan') + 1,
        ]);

        /* Bukti yang ditaut disaring pada bukti MILIK investigasi ini.
           Tanpa penyaring itu, id bukti dari berkas lain dapat ditaut
           lewat kiriman yang disusun tangan — dan akar masalah yang
           menunjuk bukti berkas lain adalah persis jenis kekeliruan yang
           tidak akan pernah ada yang menyadarinya. */
        $akar->bukti()->sync(
            $investigasi->bukti()->whereIn('id', $data['bukti'] ?? [])->pluck('id')->all()
        );

        return back()->with('ok', 'Akar masalah dicatat.');
    }

    public function akarHapus(Investigasi $investigasi, AkarMasalah $akar)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($akar->investigasi_id === $investigasi->id, 404);

        $akar->delete();

        return back()->with('ok', 'Akar masalah dihapus.');
    }

    /* ── temuan dan tindakan ── */

    public function temuanTambah(Request $request, Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $data = $request->validate([
            'uraian'      => ['required', 'string', 'max:2000'],
            'rekomendasi' => ['nullable', 'string', 'max:2000'],
            'tingkat'     => ['nullable', Rule::in(array_keys(Temuan::TINGKAT))],
            'akar_id'     => ['nullable', 'integer'],
        ]);

        $investigasi->temuan()->create([
            'no_temuan'   => NomorInvestigasi::terbitkan(NomorInvestigasi::TEMUAN),
            'uraian'      => $data['uraian'],
            'rekomendasi' => $data['rekomendasi'] ?? null,
            'tingkat'     => $data['tingkat'] ?? 'sedang',

            /* Akar yang dirujuk harus milik investigasi ini. */
            'akar_id'     => $investigasi->akar()->whereKey($data['akar_id'] ?? null)->value('id'),
            'urutan'      => (int) $investigasi->temuan()->max('urutan') + 1,
        ]);

        return back()->with('ok', 'Temuan dicatat.');
    }

    public function temuanHapus(Investigasi $investigasi, Temuan $temuan)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($temuan->investigasi_id === $investigasi->id, 404);

        $temuan->delete();

        return back()->with('ok', 'Temuan dihapus.');
    }

    public function tindakanTambah(Request $request, Investigasi $investigasi, Temuan $temuan)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($temuan->investigasi_id === $investigasi->id, 404);

        $data = $request->validate([
            'uraian'      => ['required', 'string', 'max:2000'],
            'hierarki_id' => ['nullable', 'exists:inv_hierarki_kendali,id'],
            'pic_id'      => ['nullable', 'exists:users,id'],
            'pic_nama'    => ['nullable', 'string', 'max:120'],
            'tenggat'     => ['nullable', 'date'],
        ]);

        $temuan->tindakan()->create($data + [
            'no_tindakan' => NomorInvestigasi::terbitkan(NomorInvestigasi::TINDAKAN),
            'status'      => 'terbuka',
        ]);

        return back()->with('ok', 'Tindakan perbaikan dicatat.');
    }

    /**
     * Ubah status satu tindakan perbaikan.
     *
     * "selesai" dinyatakan pelaksananya; "diverifikasi" dinyatakan orang
     * lain. Yang menegakkan pembedaan itu di sini bukan sekadar nama
     * statusnya melainkan penolakan di bawah: pelaksana tidak boleh
     * memverifikasi pekerjaannya sendiri, dan verifikasi semacam itu
     * tidak pernah menemukan apa pun.
     */
    public function tindakanStatus(Request $request, Investigasi $investigasi, Tindakan $tindakan)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($tindakan->temuan?->investigasi_id === $investigasi->id, 404);

        $data = $request->validate([
            'status'             => ['required', Rule::in(array_keys(Tindakan::STATUS))],
            'catatan_verifikasi' => ['nullable', 'string', 'max:2000'],
            'efektif'            => ['nullable', 'boolean'],
        ]);

        $memverifikasi = in_array($data['status'], ['diverifikasi', 'ditutup'], true);

        if ($memverifikasi && $tindakan->pic_id && $tindakan->pic_id === auth()->id()) {
            return back()->withErrors([
                'tindakan' => 'Pelaksana tidak dapat memverifikasi tindakannya sendiri. '
                    .'Mintalah orang lain di tim yang memeriksanya di lapangan.',
            ]);
        }

        $tindakan->update([
            'status'             => $data['status'],
            'selesai_pada'       => in_array($data['status'], ['selesai', 'diverifikasi', 'ditutup'], true)
                ? ($tindakan->selesai_pada ?? now()->toDateString()) : null,
            'diverifikasi_oleh'  => $memverifikasi ? auth()->id() : null,
            'diverifikasi_pada'  => $memverifikasi ? now()->toDateString() : null,
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
            'efektif'            => $memverifikasi ? ($data['efektif'] ?? null) : null,
        ]);

        return back()->with('ok', 'Status tindakan '.$tindakan->no_tindakan.' diperbarui.');
    }

    public function tindakanHapus(Investigasi $investigasi, Tindakan $tindakan)
    {
        $this->pastikanBerjalan($investigasi);
        abort_unless($tindakan->temuan?->investigasi_id === $investigasi->id, 404);

        $tindakan->delete();

        return back()->with('ok', 'Tindakan dihapus.');
    }

    /* ── tahap dan penutupan ── */

    public function tahapMaju(Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        /* Syaratnya diperiksa DI SINI, bukan hanya di layar. Tombolnya
           memang sudah disembunyikan ketika syaratnya kurang, tetapi
           tombol yang tersembunyi bukan penjagaan — kiriman POST yang
           disusun tangan tidak pernah melihat layarnya. */
        if ($kurang = TahapInvestigasi::yangKurang($investigasi)) {
            return back()->withErrors(['tahap' => implode(' ', $kurang)]);
        }

        $berikutnya = TahapInvestigasi::berikutnya($investigasi->tahap, $investigasi->level());

        if (! $berikutnya) return back();

        $investigasi->update(['tahap' => $berikutnya]);

        Jejak::catat('Tahap dimajukan',
            TahapInvestigasi::URUTAN[$investigasi->tahap] ?? $berikutnya,
            $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Maju ke tahap '.(TahapInvestigasi::URUTAN[$berikutnya] ?? $berikutnya).'.');
    }

    /**
     * Mundur satu tahap.
     *
     * TIDAK menuntut syarat apa pun, dan itu disengaja. Mundur dipakai
     * justru ketika ada yang keliru — bukti salah, akar masalah
     * dirumuskan terlalu cepat — dan menuntut kelengkapan untuk mundur
     * berarti berkas yang terlanjur maju tidak dapat diperbaiki sama
     * sekali.
     */
    public function tahapMundur(Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        $sebelumnya = TahapInvestigasi::sebelumnya($investigasi->tahap, $investigasi->level());

        if (! $sebelumnya) return back();

        $investigasi->update(['tahap' => $sebelumnya]);

        Jejak::catat('Tahap dimundurkan',
            TahapInvestigasi::URUTAN[$sebelumnya] ?? $sebelumnya,
            $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Kembali ke tahap '.(TahapInvestigasi::URUTAN[$sebelumnya] ?? $sebelumnya).'.');
    }

    public function tutup(Investigasi $investigasi)
    {
        $this->pastikanBerjalan($investigasi);

        if ($kurang = TahapInvestigasi::yangKurangUntukTutup($investigasi)) {
            return back()->withErrors(['tutup' => implode(' ', $kurang)]);
        }

        $investigasi->update(['status' => 'ditutup', 'ditutup_pada' => now()]);
        $investigasi->insiden?->update(['status' => 'ditutup']);

        Jejak::catat('Investigasi ditutup', $investigasi->no_investigasi,
            $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Investigasi '.$investigasi->no_investigasi.' ditutup.');
    }

    public function bukaLagi(Investigasi $investigasi)
    {
        abort_unless($investigasi->sudahDitutup(), 422, 'Investigasi ini belum ditutup.');

        $investigasi->update(['status' => 'berjalan', 'ditutup_pada' => null]);
        $investigasi->insiden?->update(['status' => 'diselidiki']);

        Jejak::catat('Investigasi dibuka kembali', $investigasi->no_investigasi,
            $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Investigasi dibuka kembali.');
    }

    public function pembelajaranTambah(Request $request, Investigasi $investigasi)
    {
        $data = $request->validate([
            'judul'       => ['required', 'string', 'max:200'],
            'ringkasan'   => ['required', 'string', 'max:3000'],
            'pesan_kunci' => ['nullable', 'string', 'max:1000'],
        ]);

        /* Pembelajaran BOLEH diterbitkan pada berkas yang sudah ditutup,
           dan hanya ini yang boleh. Yang dibaca site lain adalah
           paragraf ini, dan menutup berkasnya lebih dahulu adalah urutan
           yang wajar — memaksa pembelajaran terbit sebelum penutupan
           berarti ia ditulis sebelum kesimpulannya matang. */
        $investigasi->pembelajaran()->create($data + [
            'diterbitkan_pada' => now()->toDateString(),
            'diterbitkan_oleh' => auth()->id(),
        ]);

        Jejak::catat('Pembelajaran diterbitkan', $data['judul'],
            $investigasi->id, $investigasi->insiden_id);

        return back()->with('ok', 'Pembelajaran diterbitkan.');
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
