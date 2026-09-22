<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, CompliancePoint, ComplianceRecap, ComplianceSubject, Company, Document};
use App\Support\{Berkas, Iso, Kepatuhan, KopDokumen, PemecahPeraturan, PustakaKepatuhan};
use Illuminate\Http\Request;
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
            'subjudul' => 'Baca naskah peraturan, pecah jadi butir, periksa, lalu simpan',

            'opsi' => $this->opsi(),

            /* Diberi tahu dari awal, bukan sesudah menunggu.
               Tanpa kunci AI, pemecahan pasalnya tetap berjalan penuh —
               yang tidak ada hanya usulan rangkuman dan penerapannya. */
            'ai' => \App\Support\Ai::aktif(),

            'tautan' => $this->tautan() + [
                'rangkum' => route('kepatuhan.rangkum'),
                'simpan'  => route('kepatuhan.rangkum.simpan'),
            ],
        ]);
    }

    /**
     * Baca naskahnya dan usulkan butir-butirnya — TANPA menyimpan.
     *
     * Hasilnya dipulangkan ke layar untuk diperiksa, disunting, dan
     * dicentang. Yang menyimpan langsung akan memasukkan pasal yang
     * belum pernah dibaca siapa pun ke dalam angka pemenuhan.
     */
    public function rangkum(Request $request)
    {
        $d = $request->validate([
            'teks'     => ['nullable', 'string', 'max:400000'],
            'kegiatan' => ['nullable', 'string', 'max:500'],
            'berkas'   => ['nullable', 'file', 'mimes:pdf,docx,txt,md', 'max:20480'],
        ]);

        $catatan = null;
        $naskah  = trim((string) ($d['teks'] ?? ''));

        /* Kotak teks MENANG atas berkasnya, tidak digabung.
           Yang menempelkan naskah sudah melakukannya justru karena
           berkasnya tidak terbaca; menambahkan hasil bacaan berkas ke
           bawahnya menghasilkan naskah rangkap dengan pasal berulang. */
        if ($naskah === '' && $request->hasFile('berkas')) {
            $hasil   = PemecahPeraturan::dariBerkas($request->file('berkas'));
            $naskah  = $hasil['teks'];
            $catatan = $hasil['catatan'];
        }

        if ($naskah === '') {
            return back()->with('rangkuman', [
                'butir'   => [],
                'catatan' => $catatan ?: 'Tidak ada naskah yang dapat dibaca. '
                                        .'Unggah berkasnya atau tempelkan teksnya.',
                'ai'      => false,
            ]);
        }

        $butir = PemecahPeraturan::pecah($naskah);

        if (!$butir) {
            return back()->with('rangkuman', [
                'butir'   => [],
                'catatan' => 'Naskahnya terbaca tetapi tidak ditemukan penanda "Pasal". '
                            .'Tambahkan butirnya satu per satu di halaman penilaian.',
                'ai'      => false,
            ]);
        }

        $pakaiAi = \App\Support\Ai::aktif();
        $usul    = PemecahPeraturan::usul($butir, $d['kegiatan'] ?? null);

        return back()->with('rangkuman', [
            'butir'   => $usul,
            'catatan' => $catatan,
            'ai'      => $pakaiAi,
        ]);
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
            'butir'               => ['required', 'array', 'min:1', 'max:200'],
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
