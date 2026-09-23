<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, CompliancePoint, ComplianceRecap, ComplianceSubject, Company, Document};
use App\Support\{Ai, AnalisisPeraturan, Berkas, Iso, Kepatuhan, KopDokumen, PemecahPeraturan, PustakaKepatuhan, RegisterKepatuhan};
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Identifikasi dan evaluasi pemenuhan.
 *
 * Tiga sumber kewajiban — peraturan perundangan, klausul standar ISO,
 * dan dokumen terkendali — dilayani satu controller, karena
 * pertanyaannya sama untuk ketiganya: kewajiban apa yang mengikat kita,
 * sudah dipenuhi atau belum, dan kalau belum siapa yang mengerjakan
 * sampai kapan.
 */
class KepatuhanController extends Controller
{
    /* ══════════════════ dasbor ══════════════════ */

    public function dasbor(Request $request)
    {
        [$perusahaan, $tahun, $sumber, $aspek] = $this->saringan($request);

        $subjek = $this->kueri($perusahaan, $tahun, $sumber, $aspek)->with('points')->get();
        $ringkas = Kepatuhan::ringkas($subjek);

        $menunggu = Kepatuhan::menunggu($perusahaan, $tahun, $sumber)->take(12);

        return Inertia::render('Kepatuhan/Dasbor', [
            'judul'    => 'Dasbor Pemenuhan',
            'subjudul' => 'Seberapa jauh kewajiban yang mengikat sudah dipenuhi',

            'saring' => compact('perusahaan', 'tahun', 'sumber', 'aspek'),
            'opsi'   => $this->opsi(),

            'ringkas' => [
                'total'     => $ringkas['total'],
                'comply'    => $ringkas['comply'],
                'notComply' => $ringkas['notComply'],
                'na'        => $ringkas['na'],
                'belum'     => $ringkas['belum'],
                'dinilai'   => $ringkas['dinilai'],
                'persen'    => $ringkas['persen'],
                'draf'      => $ringkas['draf'],
                'subjek'    => $subjek->where('status', 'Tetap')->count(),
            ],

            'aspek' => $ringkas['aspek'],

            /* Subjek yang terdaftar tetapi belum punya satu butir pun.
               Ia tidak ikut menentukan persentase apa pun — dan karena
               itu justru harus disebut, sebab yang membaca angka 100%
               berhak tahu tujuh peraturan tidak ikut dihitung. */
            'kosong' => array_map(fn (ComplianceSubject $s) => [
                'id' => $s->id, 'nomor' => $s->nomor, 'judul' => $s->judul,
                'url' => route('kepatuhan.show', $s),
            ], $ringkas['kosong']),

            'menunggu' => $menunggu->map(fn (CompliancePoint $p) => [
                'id'       => $p->id,
                'penunjuk' => $p->penunjuk,
                'rangkuman'=> $p->rangkuman,
                'tindak'   => $p->tindak_lanjut,
                'pic'      => $p->pic,
                'target'   => $p->target?->format('d-m-Y'),
                'lewat'    => $p->lewatTarget(),
                'nomor'    => $p->subject?->nomor,
                'judul'    => $p->subject?->judul,
                'url'      => $p->subject ? route('kepatuhan.show', $p->subject) : null,
            ])->all(),

            'tren' => ComplianceRecap::query()
                ->where('tahun', $tahun)
                ->when($perusahaan, fn ($q) => $q->where('company_id', $perusahaan))
                ->when($sumber, fn ($q) => $q->where('sumber', $sumber))
                ->orderBy('bulan')->get()
                ->map(fn (ComplianceRecap $r) => [
                    'bulan'     => $r->namaBulan(),
                    'comply'    => $r->comply,
                    'notComply' => $r->not_comply,
                    'persen'    => $r->persen(),
                    'evaluasi'  => $r->evaluasi,
                ])->all(),

            'tautan' => $this->tautan(),
        ]);
    }

    /* ══════════════════ register ══════════════════ */

    public function index(Request $request)
    {
        [$perusahaan, $tahun, $sumber, $aspek] = $this->saringan($request);

        $cari   = trim((string) $request->get('cari'));
        $punya  = $request->get('punya');   // Comply | Not Comply | N/A | belum

        $daftar = $this->kueri($perusahaan, $tahun, $sumber, $aspek)
            ->with('points')
            ->when($cari, fn ($q) => $q->where(function ($b) use ($cari) {
                $b->where('nomor', 'like', "%{$cari}%")
                  ->orWhere('judul', 'like', "%{$cari}%")
                  ->orWhere('instansi', 'like', "%{$cari}%");
            }))
            ->when($punya === 'belum', fn ($q) => $q->whereHas('points', fn ($b) => $b->whereNull('status')))
            ->when($punya && $punya !== 'belum',
                   fn ($q) => $q->whereHas('points', fn ($b) => $b->where('status', $punya)))
            ->orderBy('aspek')->orderBy('kode')
            ->paginate(20)->withQueryString();

        return Inertia::render('Kepatuhan/Register', [
            'judul'    => 'Register Pemenuhan',
            'subjudul' => 'Kewajiban yang sudah diidentifikasi beserta capaiannya',

            'saring' => compact('perusahaan', 'tahun', 'sumber', 'aspek', 'cari', 'punya'),
            'opsi'   => $this->opsi(),

            'daftar' => array_map(fn (ComplianceSubject $s) => $this->baris($s), $daftar->items()),

            'halaman' => [
                'kini'  => $daftar->currentPage(),
                'akhir' => $daftar->lastPage(),
                'total' => $daftar->total(),
                'tautan'=> array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $daftar->linkCollection()->all()),
            ],

            'tautan' => $this->tautan(),
        ]);
    }

    /* ══════════════════ penilaian per butir ══════════════════ */

    public function show(ComplianceSubject $kepatuhan)
    {
        $kepatuhan->load(['points', 'company', 'document']);

        return Inertia::render('Kepatuhan/Penilaian', [
            'judul'    => $kepatuhan->nomor,
            'subjudul' => $kepatuhan->judul,

            's' => $this->baris($kepatuhan) + [
                'jenis'         => $kepatuhan->jenis,
                'instansi'      => $kepatuhan->instansi,
                'tanggalTerbit' => $kepatuhan->tanggal_terbit?->format('d-m-Y'),
                'ruangLingkup'  => $kepatuhan->ruang_lingkup,
                'rangkuman'     => $kepatuhan->rangkuman,
                'perusahaan'    => $kepatuhan->company?->name,
                'dokumen'       => $kepatuhan->document?->kode,
                'urlDokumen'    => $kepatuhan->document ? route('dokumen.show', $kepatuhan->document) : null,
                'isoKode'       => $kepatuhan->iso_kode,
                'urlIso'        => $kepatuhan->iso_kode ? route('iso.show', $kepatuhan->iso_kode) : null,
                'berkas'        => Berkas::daftarUrl($kepatuhan, 'kpt'),
            ],

            'butir' => $kepatuhan->points->map(fn (CompliancePoint $p) => [
                'id'        => $p->id,
                'penunjuk'  => $p->penunjuk,
                'rangkuman' => $p->rangkuman,
                'penerapan' => $p->penerapan,
                'status'    => $p->status,
                'keterangan'=> $p->keterangan,
                'tindak'    => $p->tindak_lanjut,
                'pic'       => $p->pic,
                'target'    => $p->target?->toDateString(),
                'lewat'     => $p->lewatTarget(),
                'urlUbah'   => route('kepatuhan.butir.update', $p),
                'urlHapus'  => route('kepatuhan.butir.destroy', $p),
            ])->all(),

            'opsi' => $this->opsi(),

            'tautan' => $this->tautan() + [
                'tambahButir' => route('kepatuhan.butir.store', $kepatuhan),
                'ubah'        => route('kepatuhan.edit', $kepatuhan),
                'hapus'       => route('kepatuhan.destroy', $kepatuhan),
                'salin'       => route('kepatuhan.salin', $kepatuhan),
                'lembar'      => route('kepatuhan.lembar', $kepatuhan),
            ],
        ]);
    }

    /* ══════════════════ identitas subjek ══════════════════ */

    public function create(Request $request)
    {
        return Inertia::render('Kepatuhan/Form', [
            'judul'    => 'Tambah Kewajiban',
            'subjudul' => 'Identitas peraturan, standar, atau dokumen yang dinilai',

            'awal' => [
                'sumber' => $request->get('sumber', 'Peraturan'),
                'jenis' => '', 'nomor' => '', 'judul' => '',
                'tanggal_terbit' => '', 'instansi' => '', 'aspek' => '',
                'ruang_lingkup' => '', 'rangkuman' => '',
                'company_id' => '', 'tahun' => (string) now()->year,
                'iso_kode' => '', 'document_id' => '',
            ],
            'sunting' => false,
            'opsi'    => $this->opsi(),
            'tautan'  => ['simpan' => route('kepatuhan.store'), 'batal' => route('kepatuhan.index')],
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->v($request);

        $d['user_id'] = auth()->id();
        $d['kode']    = Kepatuhan::kodeBaru($d['aspek'] ?? null, $d['company_id'] ?? null, $d['tahun']);

        if ($request->hasFile('berkas')) {
            $request->validate(['berkas.*' => Berkas::ATURAN_DOKUMEN], [], ['berkas.*' => 'berkas']);
            if ($f = Berkas::simpanBanyak($request->file('berkas'), 'kepatuhan')) $d['berkas'] = $f;
        }

        $s = ComplianceSubject::create($d);

        ActivityLog::write('Tambah kewajiban', $s->nomor, 'iso');

        return redirect()->route('kepatuhan.show', $s)->with('ok', $s->nomor.' masuk register.');
    }

    public function edit(ComplianceSubject $kepatuhan)
    {
        return Inertia::render('Kepatuhan/Form', [
            'judul'    => 'Ubah '.$kepatuhan->nomor,
            'subjudul' => $kepatuhan->judul,

            'awal' => [
                'sumber'         => $kepatuhan->sumber,
                'jenis'          => (string) $kepatuhan->jenis,
                'nomor'          => $kepatuhan->nomor,
                'judul'          => $kepatuhan->judul,
                'tanggal_terbit' => $kepatuhan->tanggal_terbit?->toDateString() ?? '',
                'instansi'       => (string) $kepatuhan->instansi,
                'aspek'          => (string) $kepatuhan->aspek,
                'ruang_lingkup'  => (string) $kepatuhan->ruang_lingkup,
                'rangkuman'      => (string) $kepatuhan->rangkuman,
                'company_id'     => (string) $kepatuhan->company_id,
                'tahun'          => (string) $kepatuhan->tahun,
                'iso_kode'       => (string) $kepatuhan->iso_kode,
                'document_id'    => (string) $kepatuhan->document_id,
                'status'         => $kepatuhan->status,
            ],
            'sunting' => true,
            'opsi'    => $this->opsi(),
            'tautan'  => [
                'simpan' => route('kepatuhan.update', $kepatuhan),
                'batal'  => route('kepatuhan.show', $kepatuhan),
            ],
        ]);
    }

    public function update(Request $request, ComplianceSubject $kepatuhan)
    {
        $d = $this->v($request);

        /* Menaikkan draf menjadi tetap adalah keputusan tersendiri:
           sejak saat itu isinya ikut menentukan angka pemenuhan. */
        if ($request->filled('status')) {
            $request->validate(['status' => [Rule::in(ComplianceSubject::STATUS)]]);
            $d['status'] = $request->input('status');
            if ($d['status'] === 'Tetap') $d['dari_ai'] = false;
        }

        $kepatuhan->update($d);

        return redirect()->route('kepatuhan.show', $kepatuhan)->with('ok', 'Identitas diperbarui.');
    }

    public function destroy(ComplianceSubject $kepatuhan)
    {
        $nomor = $kepatuhan->nomor;
        $kepatuhan->delete();

        ActivityLog::write('Hapus kewajiban', $nomor, 'iso');

        return redirect()->route('kepatuhan.index')->with('ok', $nomor.' dihapus dari register.');
    }

    /**
     * Salin ke tahun evaluasi lain.
     *
     * Peraturan yang mengikat tahun ini hampir selalu mengikat tahun
     * depan juga, tetapi PENILAIANNYA tidak boleh ikut tersalin: yang
     * comply tahun lalu belum tentu comply tahun ini, dan register baru
     * yang lahir sudah seratus persen comply adalah register yang tidak
     * akan pernah diperiksa ulang.
     */
    public function salin(Request $request, ComplianceSubject $kepatuhan)
    {
        $tahun = (int) $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])['tahun'];

        abort_if($tahun === $kepatuhan->tahun, 422, 'Tahunnya sama dengan yang sekarang.');

        $baru = $kepatuhan->replicate(['kode', 'status', 'dari_ai']);
        $baru->tahun   = $tahun;
        $baru->status  = 'Tetap';
        $baru->dari_ai = false;
        $baru->kode    = Kepatuhan::kodeBaru($kepatuhan->aspek, $kepatuhan->company_id, $tahun);
        $baru->save();

        foreach ($kepatuhan->points as $p) {
            $baru->points()->create([
                'penunjuk'   => $p->penunjuk,
                'rangkuman'  => $p->rangkuman,
                'order_index'=> $p->order_index,
                // penerapan, status, keterangan, tindak lanjut, PIC, dan
                // target sengaja TIDAK disalin.
            ]);
        }

        return redirect()->route('kepatuhan.show', $baru)
            ->with('ok', 'Disalin ke tahun '.$tahun.'; penilaiannya dikosongkan.');
    }

    /* ══════════════════ butir ══════════════════ */

    public function storeButir(Request $request, ComplianceSubject $kepatuhan)
    {
        $d = $this->vButir($request);
        $d['order_index'] = (int) $kepatuhan->points()->max('order_index') + 1;

        $kepatuhan->points()->create($d);

        return back()->with('ok', 'Butir ditambahkan.');
    }

    public function updateButir(Request $request, CompliancePoint $butir)
    {
        /* Lewat subjeknya, bukan langsung dari $butir: butir milik
           perusahaan lain harus tidak dapat ditemukan, dan itu hanya
           terjadi bila pencariannya melewati baris yang ber-scope. */
        ComplianceSubject::findOrFail($butir->subject_id);

        $butir->update($this->vButir($request));

        return back()->with('ok', 'Penilaian butir tersimpan.');
    }

    public function destroyButir(CompliancePoint $butir)
    {
        ComplianceSubject::findOrFail($butir->subject_id);
        $butir->delete();

        return back()->with('ok', 'Butir dihapus.');
    }

    /* ══════════════════ rekap bulanan ══════════════════ */

    public function rekap(Request $request)
    {
        [$perusahaan, $tahun, $sumber] = $this->saringan($request);

        $tersimpan = ComplianceRecap::query()
            ->where('tahun', $tahun)
            ->when($perusahaan, fn ($q) => $q->where('company_id', $perusahaan))
            ->when($sumber, fn ($q) => $q->where('sumber', $sumber))
            ->get()->keyBy('bulan');

        /* Angka BERJALAN, untuk diusulkan saat merekap bulan ini.
           Ditawarkan, bukan disimpan diam-diam: rekap adalah potret yang
           ditandatangani, dan potret yang berubah sendiri setiap kali
           halamannya dibuka bukan potret. */
        $kini = Kepatuhan::ringkas(
            $this->kueri($perusahaan, $tahun, $sumber, null)->with('points')->get(),
        );

        return Inertia::render('Kepatuhan/Rekap', [
            'judul'    => 'Rekap Bulanan',
            'subjudul' => 'Lembar PICA — dua belas bulan beserta evaluasinya',

            'saring' => compact('perusahaan', 'tahun', 'sumber'),
            'opsi'   => $this->opsi(),

            /* `->get($b)`, bukan `$tersimpan[$b]`.

               Collection::offsetGet pada kunci yang tidak ada memicu
               "Undefined array key" SEBELUM `??` sempat menangkapnya —
               dan bulan yang belum direkap adalah keadaan yang paling
               lazim di halaman ini, bukan keadaan luar biasa. Halaman
               rekap karena itu gagal 500 selama belum ada satu bulan
               pun yang terisi: persis keadaan yang dilihat orang
               pertama yang membukanya. */
            'bulan' => array_map(function (int $b) use ($tersimpan) {
                $r = $tersimpan->get($b);

                return [
                    'bulan'     => $b,
                    'nama'      => ComplianceRecap::BULAN[$b],
                    'terekap'   => $r !== null,
                    'comply'    => $r?->comply,
                    'notComply' => $r?->not_comply,
                    'na'        => $r?->na,
                    'belum'     => $r?->belum,
                    'persen'    => $r?->persen(),
                    'evaluasi'  => $r?->evaluasi ?? '',
                    'rencana'   => $r?->rencana ?? '',
                ];
            }, range(1, 12)),

            'kini' => [
                'comply' => $kini['comply'], 'notComply' => $kini['notComply'],
                'na' => $kini['na'], 'belum' => $kini['belum'], 'persen' => $kini['persen'],
            ],

            'tautan' => $this->tautan() + ['simpan' => route('kepatuhan.rekap.simpan')],
        ]);
    }

    public function simpanRekap(Request $request)
    {
        $d = $request->validate([
            'bulan'      => ['required', 'integer', 'min:1', 'max:12'],
            'tahun'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'sumber'     => ['required', Rule::in(ComplianceSubject::SUMBER)],
            'comply'     => ['required', 'integer', 'min:0'],
            'not_comply' => ['required', 'integer', 'min:0'],
            'na'         => ['required', 'integer', 'min:0'],
            'belum'      => ['required', 'integer', 'min:0'],
            'evaluasi'   => ['nullable', 'string', 'max:2000'],
            'rencana'    => ['nullable', 'string', 'max:2000'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $d['company_id'] ??= auth()->user()?->company_id;

        ComplianceRecap::updateOrCreate(
            array_intersect_key($d, array_flip(['company_id', 'sumber', 'tahun', 'bulan'])),
            $d,
        );

        return back()->with('ok', 'Rekap '.ComplianceRecap::BULAN[$d['bulan']].' tersimpan.');
    }

    /* ══════════════════ pustaka daftar periksa ══════════════════ */

    public function pustaka(Request $request)
    {
        [$perusahaan, $tahun] = $this->saringan($request);

        return Inertia::render('Kepatuhan/Pustaka', [
            'judul'    => 'Pustaka Daftar Periksa',
            'subjudul' => 'Daftar periksa yang sudah jadi — terbitkan, lalu tinggal dinilai',

            'saring' => compact('perusahaan', 'tahun'),
            'opsi'   => $this->opsi(),

            'pustaka' => array_map(fn (string $kunci) => [
                'kunci'  => $kunci,
                'nama'   => PustakaKepatuhan::semua()[$kunci]['nama'],
                'nomor'  => PustakaKepatuhan::semua()[$kunci]['nomor'],
                'judul'  => PustakaKepatuhan::semua()[$kunci]['judul'],
                'sumber' => PustakaKepatuhan::semua()[$kunci]['sumber'],
                'aspek'  => Kepatuhan::namaAspek(PustakaKepatuhan::semua()[$kunci]['aspek']),
                'warna'  => Kepatuhan::warnaAspek(PustakaKepatuhan::semua()[$kunci]['aspek']),
                'ket'    => PustakaKepatuhan::semua()[$kunci]['ket'],
                'acuan'  => PustakaKepatuhan::semua()[$kunci]['sumberAcuan'],
                'jumlah' => count(PustakaKepatuhan::semua()[$kunci]['butir']),

                /* Sudah pernah diterbitkan untuk tahun dan perusahaan
                   ini? Menerbitkannya dua kali tidak menimbulkan galat
                   apa pun — ia hanya menggandakan tiga puluh butir yang
                   dinilai dua orang berbeda dengan jawaban berbeda. */
                'sudah'  => ComplianceSubject::query()
                    ->where('tahun', $tahun)
                    ->where('nomor', PustakaKepatuhan::semua()[$kunci]['nomor'])
                    ->when($perusahaan, fn ($q) => $q->where('company_id', $perusahaan))
                    ->value('id'),

                'contoh' => array_map(
                    fn ($b) => ['penunjuk' => $b[0], 'uraian' => $b[1]],
                    array_slice(PustakaKepatuhan::semua()[$kunci]['butir'], 0, 4),
                ),
            ], array_keys(PustakaKepatuhan::semua())),

            'tautan' => $this->tautan() + ['terbitkan' => route('kepatuhan.pustaka.terbitkan')],
        ]);
    }

    public function terbitkanPustaka(Request $request)
    {
        $d = $request->validate([
            'kunci'      => ['required', Rule::in(array_keys(PustakaKepatuhan::semua()))],
            'tahun'      => ['required', 'integer', 'min:2000', 'max:2100'],
            'company_id' => ['nullable', 'exists:companies,id'],
        ]);

        $companyId = $d['company_id'] ?? auth()->user()?->company_id;

        $s = PustakaKepatuhan::terbitkan($d['kunci'], $companyId, $d['tahun'], auth()->id());

        ActivityLog::write('Terbitkan daftar periksa', $s->nomor, 'iso');

        return redirect()->route('kepatuhan.show', $s)
            ->with('ok', $s->points()->count().' butir siap dinilai.');
    }

    /* ══════════════════ unggah & rangkum ══════════════════ */

    public function unggah()
    {
        return Inertia::render('Kepatuhan/Unggah', [
            'judul'    => 'Unggah & Rangkum',
            'subjudul' => 'Baca naskah peraturan, pecah jadi butir, analisis dengan AI, periksa, lalu simpan',

            'opsi' => $this->opsi(),

            /* Diberi tahu dari awal, bukan sesudah menunggu.
               Tanpa kunci AI, pemecahan pasalnya tetap berjalan penuh —
               yang tidak ada hanya usulan rangkuman dan penerapannya. */
            'ai'      => Ai::aktif(),
            'aiLabel' => Ai::aktif() ? Ai::label() : null,
            'batas'   => [
                'perGiliran'     => AnalisisPeraturan::PER_GILIRAN,
                'halamanPerBaca' => AnalisisPeraturan::HALAMAN_PER_BACA,
                'maksButir'      => PemecahPeraturan::MAKS_BUTIR,
            ],

            'tautan' => $this->tautan() + [
                'rangkum'   => route('kepatuhan.rangkum'),
                'aiButir'   => route('kepatuhan.rangkum.ai'),
                'identitas' => route('kepatuhan.rangkum.identitas'),
                'baca'      => route('kepatuhan.rangkum.baca'),
                'simpan'    => route('kepatuhan.rangkum.simpan'),
            ],
        ]);
    }

    /**
     * Baca naskahnya dan pecah menjadi butir — TANPA menyimpan.
     *
     * Memulangkan JSON, bukan pengalihan. Sebelumnya hasilnya dititipkan
     * lewat flash 'rangkuman', yang tidak pernah dibagikan ke halaman:
     * tombol "Baca & Rangkum" berputar sebentar, lalu kolom hasil tetap
     * kosong — tanpa galat di mana pun. Dengan JSON, yang dipulangkan
     * server langsung sampai ke halaman yang memintanya.
     *
     * AI TIDAK dipanggil di sini. Analisisnya dijalankan halaman per
     * selusin butir lewat rangkumAi(), supaya peraturan panjang tidak
     * menjadi satu permintaan yang melampaui batas waktu server.
     */
    public function rangkum(Request $request)
    {
        [$d, $tolak] = $this->validasiJson($request, [
            'teks'     => ['nullable', 'string', 'max:'.PemecahPeraturan::MAKS_AKSARA],
            'berkas'   => ['nullable', 'file', 'mimes:pdf,docx,txt,md', 'max:20480'],
        ], ['teks' => 'teks peraturan', 'berkas' => 'berkas']);
        if ($tolak) return $tolak;

        $catatan = [];
        $naskah  = trim((string) ($d['teks'] ?? ''));
        $baca    = null;

        /* Kotak teks MENANG atas berkasnya, tidak digabung.
           Yang menempelkan naskah sudah melakukannya justru karena
           berkasnya tidak terbaca; menambahkan hasil bacaan berkas ke
           bawahnya menghasilkan naskah rangkap dengan pasal berulang. */
        if ($naskah === '' && $request->hasFile('berkas')) {
            $baca   = PemecahPeraturan::dariBerkas($request->file('berkas'));
            $naskah = $baca['teks'];
            if ($baca['catatan']) $catatan[] = $baca['catatan'];
        }

        if ($naskah === '' && !$baca) {
            return response()->json(['errors' => ['teks' => ['Unggah berkasnya atau tempelkan teks peraturannya.']],
                                     'message' => 'Naskah kosong.'], 422);
        }

        /* Halaman gambar dapat dibaca AI: berkasnya disimpan sebentar
           supaya halaman itu dapat diminta satu per satu. */
        $token = null;
        if ($baca && $baca['halamanGambar']) {
            $berkas = $request->file('berkas');

            if (!Ai::aktif()) {
                $catatan[] = 'Pasang kunci AI di Pusat Kendali agar halaman gambar dapat dibaca otomatis, '
                            .'atau tempelkan teks halaman itu di kotak teks.';
            } elseif ($berkas->getSize() > AnalisisPeraturan::MAKS_PDF_BYTE) {
                $catatan[] = 'PDF-nya lebih besar dari '.(AnalisisPeraturan::MAKS_PDF_BYTE / 1048576).' MB, terlalu besar '
                            .'untuk dibaca AI. Tempelkan teks halaman itu di kotak teks.';
            } else {
                $token = $this->simpanSementara($berkas);
                $catatan[] = 'Halaman gambar itu dibaca dengan AI.';
            }
        }

        $semua = PemecahPeraturan::pecah($naskah);
        $total = count($semua);
        $butir = array_slice($semua, 0, PemecahPeraturan::MAKS_BUTIR);

        if ($naskah !== '' && !$semua) {
            $catatan[] = 'Naskahnya terbaca tetapi tidak ditemukan penanda "Pasal" yang berdiri di barisnya sendiri. '
                        .'Periksa teksnya di kotak kiri, atau tambahkan butirnya satu per satu di halaman penilaian.';
        }
        if ($total > count($butir)) {
            $catatan[] = "Naskah ini memuat {$total} butir; yang ditampilkan {$this->angka(count($butir))} pertama. "
                        .'Sisanya — '.($total - count($butir)).' butir — simpan sebagai peraturan terpisah '
                        .'dengan menempelkan bagian naskah berikutnya.';
        }

        return response()->json([
            'butir' => array_map(fn ($b, $i) => ['no' => $i + 1] + $b, $butir, array_keys($butir)),
            'total' => $total,
            'identitas' => $naskah === '' ? null : PemecahPeraturan::identitas($naskah),
            'catatan' => $catatan,
            'halaman' => $baca['halaman'] ?? null,
            'halamanGambar' => $baca['halamanGambar'] ?? [],
            /* Teks per halaman hanya dikirim bila ada halaman yang akan
               dibaca AI: halaman hasil bacaan itu disisipkan pada
               tempatnya, bukan ditempel di ujung naskah. */
            'perHalaman' => $token ? $baca['perHalaman'] : null,
            'token' => $token,
            'naskah' => $naskah,
            'ai' => Ai::aktif(),
        ]);
    }

    /** Analisis AI untuk satu giliran butir. */
    public function rangkumAi(Request $request)
    {
        [$d, $tolak] = $this->validasiJson($request, [
            'kegiatan'         => ['nullable', 'string', 'max:500'],
            'butir'            => ['required', 'array', 'min:1', 'max:'.AnalisisPeraturan::PER_GILIRAN],
            'butir.*.no'       => ['required', 'integer', 'min:1'],
            'butir.*.penunjuk' => ['required', 'string', 'max:200'],
            'butir.*.isi'      => ['required', 'string', 'max:3000'],
        ]);
        if ($tolak) return $tolak;

        if (!Ai::aktif()) return $this->aiMati();

        @set_time_limit(115);

        return response()->json($this->saring(AnalisisPeraturan::butir($d['butir'], $d['kegiatan'] ?? null)));
    }

    /** Identitas peraturan dianalisis AI dari kepala dan penutup naskahnya. */
    public function rangkumIdentitas(Request $request)
    {
        [$d, $tolak] = $this->validasiJson($request, [
            'naskah' => ['required', 'string', 'max:'.PemecahPeraturan::MAKS_AKSARA],
        ]);
        if ($tolak) return $tolak;

        if (!Ai::aktif()) return $this->aiMati();

        @set_time_limit(115);

        return response()->json($this->saring(
            AnalisisPeraturan::identitas($d['naskah'], PemecahPeraturan::identitas($d['naskah'])),
        ));
    }

    /** Halaman PDF yang berupa gambar dibaca AI, beberapa sekali jalan. */
    public function rangkumBaca(Request $request)
    {
        [$d, $tolak] = $this->validasiJson($request, [
            'token'   => ['required', 'uuid'],
            'dari'    => ['required', 'integer', 'min:1', 'max:2000'],
            'sampai'  => ['required', 'integer', 'gte:dari', 'max:2000'],
        ]);
        if ($tolak) return $tolak;

        if (!Ai::aktif()) return $this->aiMati();

        /* Berkasnya hanya dapat diminta pengunggahnya sendiri: jalurnya
           memuat id pengguna, dan token orang lain tidak menemukan apa-apa. */
        $jalur = $this->jalurSementara($d['token']);
        if (!is_file($jalur)) {
            return response()->json(['ok' => false, 'halaman' => [],
                'pesan' => 'Berkas sementara sudah tidak ada — unggah ulang berkasnya.'], 404);
        }

        @set_time_limit(115);

        $sampai = min($d['sampai'], $d['dari'] + AnalisisPeraturan::HALAMAN_PER_BACA - 1);

        return response()->json($this->saring(AnalisisPeraturan::bacaHalaman($jalur, $d['dari'], $sampai)));
    }

    /**
     * Validasi yang SELALU memulangkan JSON bila gagal.
     *
     * bootstrap/app.php merender galat sebagai JSON hanya untuk api/*.
     * Pada rute web, `$request->validate()` yang gagal memulangkan
     * pengalihan ke halaman sebelumnya — dan fetch mengikutinya lalu
     * menerima HTML ber-status 200, yang terbaca sebagai "berhasil tetapi
     * kosong". Berkas .jpg yang diunggah ke sini berakhir begitu.
     *
     * @return array{0:?array,1:?\Illuminate\Http\JsonResponse}
     */
    private function validasiJson(Request $request, array $aturan, array $nama = []): array
    {
        $v = \Illuminate\Support\Facades\Validator::make($request->all(), $aturan, [], $nama);

        if ($v->fails()) {
            return [null, response()->json([
                'message' => $v->errors()->first(),
                'errors'  => $v->errors()->toArray(),
            ], 422)];
        }

        return [$v->validated(), null];
    }

    /**
     * Rincian galat penyedia hanya untuk administrator.
     *
     * Pesan penyedia kadang memuat nama proyek atau potongan konfigurasi;
     * pengguna biasa cukup membaca bahwa AI gagal dan dapat diulang.
     */
    private function saring(array $hasil): array
    {
        if (!auth()->user()?->isAdmin()) unset($hasil['galat']);

        return $hasil;
    }

    private function aiMati()
    {
        return response()->json(['ok' => false,
            'pesan' => 'AI belum diaktifkan. Masukkan kunci API di Pusat Kendali → Integrasi AI.'], 409);
    }

    private function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    private function jalurSementara(string $token): string
    {
        return storage_path('app/private/rangkum/'.auth()->id().'/'.$token.'.pdf');
    }

    /** Simpan PDF sebentar untuk dibaca AI per halaman; yang lewat sehari dibuang. */
    private function simpanSementara(\Illuminate\Http\UploadedFile $berkas): string
    {
        $akar = storage_path('app/private/rangkum');
        foreach (glob($akar.'/*/*.pdf') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) @unlink($f);
        }

        $token = (string) \Illuminate\Support\Str::uuid();
        $jalur = $this->jalurSementara($token);
        @mkdir(dirname($jalur), 0770, true);
        copy($berkas->getRealPath(), $jalur);

        return $token;
    }

    /**
     * Simpan butir yang dicentang sebagai subjek baru berstatus Draf.
     *
     * Draf, bukan Tetap: isinya belum ikut dihitung sampai seseorang
     * membacanya dan menaikkan statusnya. Butirnya pun tersimpan tanpa
     * status — belum dinilai, bukan N/A.
     */
    public function simpanRangkuman(Request $request)
    {
        $d = $this->v($request);

        $butir = $request->validate([
            'butir'               => ['required', 'array', 'min:1', 'max:'.PemecahPeraturan::MAKS_BUTIR],
            'butir.*.penunjuk'    => ['required', 'string', 'max:200'],
            'butir.*.rangkuman'   => ['nullable', 'string', 'max:3000'],
            'butir.*.penerapan'   => ['nullable', 'string', 'max:3000'],
        ])['butir'];

        $d['user_id'] = auth()->id();
        $d['status']  = 'Draf';
        $d['dari_ai'] = (bool) $request->boolean('dari_ai');
        $d['kode']    = Kepatuhan::kodeBaru($d['aspek'] ?? null, $d['company_id'] ?? null, $d['tahun']);

        $s = ComplianceSubject::create($d);

        foreach (array_values($butir) as $i => $b) {
            $s->points()->create([
                'penunjuk'    => $b['penunjuk'],
                'rangkuman'   => $b['rangkuman'] ?? null,
                'penerapan'   => $b['penerapan'] ?? null,
                'order_index' => $i + 1,
            ]);
        }

        ActivityLog::write('Rangkum peraturan', $s->nomor.' — '.count($butir).' butir', 'iso');

        return redirect()->route('kepatuhan.show', $s)
            ->with('ok', count($butir).' butir tersimpan sebagai draf. '
                        .'Periksa isinya, lalu ubah statusnya menjadi Tetap.');
    }

    /* ══════════════════ lembar cetak ══════════════════ */

    public function lembar(ComplianceSubject $kepatuhan)
    {
        $kepatuhan->load(['points', 'company']);
        $rekap = $kepatuhan->rekap();

        return Inertia::render('Print/KepatuhanLembar', [
            'judul' => 'Evaluasi Pemenuhan '.$kepatuhan->nomor,

            'dok' => KopDokumen::untuk('evaluasi-pemenuhan', $kepatuhan->company),

            's' => [
                'kode'          => $kepatuhan->kode,
                'sumber'        => $kepatuhan->sumber,
                'jenis'         => $kepatuhan->jenis,
                'nomor'         => $kepatuhan->nomor,
                'judul'         => $kepatuhan->judul,
                'instansi'      => $kepatuhan->instansi,
                'tanggalTerbit' => $kepatuhan->tanggal_terbit?->format('d-m-Y'),
                'aspek'         => Kepatuhan::namaAspek($kepatuhan->aspek),
                'tahun'         => $kepatuhan->tahun,
                'ruangLingkup'  => $kepatuhan->ruang_lingkup,
                'rangkuman'     => $kepatuhan->rangkuman,
                'perusahaan'    => $kepatuhan->company?->name,
            ],

            'rekap' => $rekap,

            'butir' => $kepatuhan->points->values()->map(fn (CompliancePoint $p, $i) => [
                'no'        => $i + 1,
                'penunjuk'  => $p->penunjuk,
                'rangkuman' => $p->rangkuman,
                'penerapan' => $p->penerapan,
                'status'    => $p->status,
                'keterangan'=> $p->keterangan,
                'tindak'    => $p->tindak_lanjut,
                'pic'       => $p->pic,
                'target'    => $p->target?->format('d-m-Y'),
            ])->all(),

            'kembali' => route('kepatuhan.show', $kepatuhan),
        ]);
    }

    /* ══════════════════ unduh register ══════════════════ */

    /**
     * Seluruh register satu tahun sebagai berkas Excel.
     *
     * Lembar cetak yang sudah ada memuat SATU peraturan beserta
     * pasalnya — yang ditandatangani dan diarsipkan sebagai bukti
     * evaluasi naskah itu. Yang dibawa ke rapat dan diminta auditor
     * eksternal adalah kebalikannya: seluruh kewajiban berdampingan,
     * dapat disaring dan diurutkan sendiri oleh yang menerimanya.
     *
     * Dikirim sebagai ALIRAN, bukan disimpan dulu ke berkas sementara:
     * permintaan yang putus di tengah meninggalkan berkas yatim di
     * diska server yang tidak ada yang membersihkannya.
     */
    public function ekspor(Request $request): StreamedResponse
    {
        [$perusahaan, $tahun, $sumber, $aspek] = $this->saringan($request);

        /* Penyaringnya SAMA dengan penyaring register di layar, TANPA
           kotak cari dan tanpa saringan status. Keduanya menjawab
           pertanyaan yang berbeda: layar menjawab "apa yang sedang saya
           lihat", berkas ini menjawab "apa yang saya serahkan". Kotak
           cari yang kebetulan masih terisi akan diam-diam memotong
           lembar yang diserahkan — dan yang menerimanya tidak punya cara
           mengetahui bahwa ada yang hilang. */
        $data = $this->kueri($perusahaan, $tahun, $sumber, $aspek)
            ->with('points')
            ->orderBy('aspek')->orderBy('kode')
            ->get();

        $penyusun = new RegisterKepatuhan(
            $data,
            $perusahaan ? Company::find($perusahaan) : null,
            compact('tahun', 'sumber', 'aspek'),
        );

        $nama = $penyusun->namaBerkas();
        $buku = $penyusun->spreadsheet();

        return response()->streamDownload(function () use ($buku) {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($buku))->save('php://output');

            $buku->disconnectWorksheets();
        }, $nama, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /* ══════════════════ dalaman ══════════════════ */

    /** @return array{0:?int,1:int,2:?string,3:?string} */
    private function saringan(Request $request): array
    {
        $perusahaan = $request->filled('perusahaan') ? (int) $request->get('perusahaan') : null;
        $perusahaan ??= auth()->user()?->company_id;

        $tahun  = (int) ($request->get('tahun') ?: now()->year);
        $sumber = in_array($request->get('sumber'), ComplianceSubject::SUMBER, true)
            ? $request->get('sumber') : null;
        $aspek  = in_array($request->get('aspek'), Kepatuhan::ASPEK, true)
            ? $request->get('aspek') : null;

        return [$perusahaan, $tahun, $sumber, $aspek];
    }

    private function kueri(?int $perusahaan, int $tahun, ?string $sumber, ?string $aspek)
    {
        return ComplianceSubject::query()
            ->where('tahun', $tahun)
            ->when($perusahaan, fn ($q) => $q->where(fn ($b) =>
                $b->where('company_id', $perusahaan)->orWhereNull('company_id')))
            ->when($sumber, fn ($q) => $q->where('sumber', $sumber))
            ->when($aspek,  fn ($q) => $q->where('aspek', $aspek));
    }

    private function baris(ComplianceSubject $s): array
    {
        $r = $s->rekap();

        return [
            'id'      => $s->id,
            'kode'    => $s->kode,
            'sumber'  => $s->sumber,
            'nomor'   => $s->nomor,
            'judul'   => $s->judul,
            'jenis'   => $s->jenis,
            'instansi'=> $s->instansi,
            'terbit'  => $s->tanggal_terbit?->format('d-m-Y'),
            'aspek'   => $s->aspek,
            'aspekNama'  => Kepatuhan::namaAspek($s->aspek),
            'aspekWarna' => Kepatuhan::warnaAspek($s->aspek),
            'tahun'   => $s->tahun,
            'status'  => $s->status,
            'dariAi'  => $s->dari_ai,
            'rekap'   => $r,
            'url'     => route('kepatuhan.show', $s),
        ];
    }

    private function opsi(): array
    {
        return [
            'sumber'     => ComplianceSubject::SUMBER,
            'sumberNama' => Kepatuhan::SUMBER,
            'jenis'      => Kepatuhan::JENIS,
            'status'     => CompliancePoint::STATUS,
            'aspek'      => Kepatuhan::daftarAspek(),
            'tahun'      => range(now()->year + 1, now()->year - 4),
            'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
            'iso'        => array_map(fn ($kode) => [
                'kode' => $kode, 'nama' => Iso::get($kode)['nama'] ?? $kode,
            ], array_keys(Iso::semua())),
            'dokumen'    => Document::orderBy('kode')->limit(300)->get(['id', 'kode', 'judul'])
                ->map(fn ($d) => ['id' => $d->id, 'nama' => $d->kode.' — '.$d->judul])->all(),
        ];
    }

    private function tautan(): array
    {
        return [
            'dasbor'   => route('kepatuhan.dasbor'),
            'register' => route('kepatuhan.index'),
            'rekap'    => route('kepatuhan.rekap'),
            'buat'     => route('kepatuhan.create'),
            'unggah'   => route('kepatuhan.unggah'),
            'pustaka'  => route('kepatuhan.pustaka'),
            'ekspor'   => route('kepatuhan.ekspor'),
        ];
    }

    private function v(Request $request): array
    {
        return $request->validate([
            'sumber'         => ['required', Rule::in(ComplianceSubject::SUMBER)],
            'jenis'          => ['nullable', 'string', 'max:120'],
            'nomor'          => ['required', 'string', 'max:300'],
            'judul'          => ['required', 'string', 'max:500'],
            'tanggal_terbit' => ['nullable', 'date'],
            'instansi'       => ['nullable', 'string', 'max:200'],
            'aspek'          => ['nullable', Rule::in(Kepatuhan::ASPEK)],
            'ruang_lingkup'  => ['nullable', 'string', 'max:2000'],
            'rangkuman'      => ['nullable', 'string', 'max:5000'],
            'tahun'          => ['required', 'integer', 'min:2000', 'max:2100'],
            'company_id'     => ['nullable', 'exists:companies,id'],
            'document_id'    => ['nullable', 'exists:documents,id'],
            'iso_kode'       => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function vButir(Request $request): array
    {
        return $request->validate([
            'penunjuk'      => ['required', 'string', 'max:200'],
            'rangkuman'     => ['nullable', 'string', 'max:3000'],
            'penerapan'     => ['nullable', 'string', 'max:3000'],
            'status'        => ['nullable', Rule::in(CompliancePoint::STATUS)],
            'keterangan'    => ['nullable', 'string', 'max:2000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:2000'],
            'pic'           => ['nullable', 'string', 'max:150'],
            'target'        => ['nullable', 'date'],
        ]);
    }
}
