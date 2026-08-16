<?php

namespace App\Http\Controllers;

use App\Rules\DalamPerusahaan;

use App\Models\{ActivityLog, Company, Document, Procedure};
use App\Support\{Db, Dokumen, Iso, KopDokumen};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * ISO & Dokumen — register dokumen terkendali.
 *
 * Alur: daftarkan dokumen → terbitkan revisi baru saat berubah → pantau
 * jatuh tempo peninjauan berkala. Berkas revisi lama tidak dihapus supaya
 * jejaknya tetap dapat ditelusuri saat audit.
 */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        /* Seluruhnya dicor menjadi teks, termasuk yang kosong.
           `get()` mengembalikan null ketika parameternya tidak ada, dan
           null itu terkirim ke <select> yang pilihan pertamanya bernilai
           "" — v-model tidak pernah mencocokkan keduanya, sehingga
           selectedIndex menjadi −1 dan kotaknya tampil KOSONG, bukan
           "Semua jenis". Tidak ada galat yang muncul; saringannya hanya
           terlihat seperti belum jadi. */
        $f = [
            'q'          => trim((string) $request->get('q')),
            'jenis'      => (string) $request->get('jenis'),
            'status'     => (string) $request->get('status'),
            'departemen' => (string) $request->get('departemen'),
            'tinjau'     => (string) $request->get('tinjau'),   // 'lewat' | 'segera'
        ];

        $like = Db::like();

        $documents = Document::with('company')
            ->when($f['q'], fn($b) => $b->where(fn($w) => $w
                ->where('kode', $like, "%{$f['q']}%")
                ->orWhere('judul', $like, "%{$f['q']}%")
                ->orWhere('ringkasan', $like, "%{$f['q']}%")))
            ->when($f['jenis'],      fn($b) => $b->where('jenis', $f['jenis']))
            ->when($f['status'],     fn($b) => $b->where('status', $f['status']))
            ->when($f['departemen'], fn($b) => $b->where('departemen', $f['departemen']))
            ->when($f['tinjau'] === 'lewat', fn($b) => $b->where('status','berlaku')
                ->whereNotNull('tanggal_tinjau')->whereDate('tanggal_tinjau','<',now()))
            ->when($f['tinjau'] === 'segera', fn($b) => $b->where('status','berlaku')
                ->whereNotNull('tanggal_tinjau')
                ->whereDate('tanggal_tinjau','>=',now())
                ->whereDate('tanggal_tinjau','<=',now()->addDays(Dokumen::AMBANG_PERINGATAN)))
            ->orderByRaw(Dokumen::urutJenisSql())
            ->orderBy('kode')
            ->paginate(20)->withQueryString();

        $semua = Document::query();

        return Inertia::render('Dokumen/Index', [
            'judul'    => 'Register Dokumen',
            'subjudul' => 'Dokumen terkendali beserta revisi dan masa tinjaunya',

            'dokumen' => array_map(fn (Document $d) => $this->baris($d), $documents->items()),
            'halaman' => $this->halaman($documents),

            'f'    => $f,
            'opsi' => [
                'jenis'  => Dokumen::JENIS,
                'status' => Dokumen::STATUS,
                'tinjau' => [
                    ['nilai' => 'lewat',  'label' => 'Lewat jatuh tempo'],
                    ['nilai' => 'segera', 'label' => 'Segera ('.Dokumen::AMBANG_PERINGATAN.' hari)'],
                ],
                'departemen' => Document::whereNotNull('departemen')->distinct()
                    ->orderBy('departemen')->pluck('departemen')->all(),
            ],

            'stat' => [
                'total'   => (clone $semua)->count(),
                'berlaku' => (clone $semua)->where('status','berlaku')->count(),
                'draft'   => (clone $semua)->where('status','draft')->count(),
                'lewat'   => (clone $semua)->where('status','berlaku')->whereNotNull('tanggal_tinjau')
                                ->whereDate('tanggal_tinjau','<',now())->count(),
            ],

            'tautan' => [
                'daftar'      => route('dokumen.index'),
                'buat'        => route('dokumen.create'),
                'piramida'    => route('dokumen.piramida'),
                'daftarInduk' => route('dokumen.daftar-induk'),
            ],
        ]);
    }

    /**
     * Piramida dokumen: enam tingkat dari Kebijakan sampai Rekaman.
     *
     * Register mendaftar dokumen secara mendatar; piramida menunjukkan
     * bentuk sistemnya. Tingkat yang kosong justru yang paling berguna
     * dilihat — sistem tanpa Prosedur, misalnya, terbaca seketika.
     */
    public function piramida()
    {
        // Satu kueri untuk seluruh tingkat; menghitung per jenis di dalam
        // perulangan berarti enam kueri untuk pertanyaan yang sama.
        $hitung = Document::query()
            ->selectRaw('jenis, status, COUNT(*) as jumlah')
            ->groupBy('jenis', 'status')
            ->get()
            ->groupBy('jenis');

        $tingkat = [];
        foreach (Dokumen::JENIS as $i => $jenis) {
            $baris = $hitung[$jenis] ?? collect();
            $tingkat[] = [
                'jenis'   => $jenis,
                'urutan'  => $i + 1,
                'total'   => (int) $baris->sum('jumlah'),
                'berlaku' => (int) $baris->firstWhere('status', 'berlaku')?->jumlah,
                'draft'   => (int) $baris->firstWhere('status', 'draft')?->jumlah,
                'ket'     => Dokumen::KETERANGAN[$jenis] ?? '',
            ];
        }

        return Inertia::render('Dokumen/Piramida', [
            'judul'    => 'Piramida Dokumen',
            'subjudul' => 'Bentuk sistem dokumentasi pada enam tingkat',

            'tingkat' => array_map(fn ($t) => $t + ['url' => route('dokumen.index', ['jenis' => $t['jenis']])], $tingkat),
            'total'   => array_sum(array_column($tingkat, 'total')),
        ]);
    }

    /** Daftar Induk Dokumen — berkas wajib sistem manajemen, siap cetak. */
    public function daftarInduk()
    {
        return Inertia::render('Print/Dokumen', [
            'documents' => Document::with('company')
                ->orderByRaw(Dokumen::urutJenisSql())->orderBy('kode')->get(),
            'dok'       => KopDokumen::untuk('daftar-induk', Company::first()),
            'kembali'   => route('dokumen.index'),
        ]);
    }

    public function create()
    {
        return $this->formulir(new Document(['status' => 'draft', 'revisi' => 0]));
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $d['user_id'] = auth()->id();

        // Nilai bawaan kolom hanya berlaku di sisi basis data; tanpa ini
        // $doc->revisi masih null saat dipakai membuat baris riwayat.
        $d['revisi'] = $d['revisi'] ?? 0;

        if ($berkas = $this->simpanBerkas($request)) $d['berkas'] = $berkas;

        $doc = Document::create($d);
        $this->simpanKlausul($request, $doc);

        // Revisi awal ikut tercatat supaya riwayat tidak berlubang.
        $doc->revisions()->create([
            'revisi'              => $doc->revisi,
            'ringkasan_perubahan' => 'Penerbitan awal.',
            'berkas'              => $doc->berkas,
            'tanggal'             => $doc->tanggal_terbit ?? now(),
            'oleh'                => auth()->user()?->name,
        ]);

        ActivityLog::write('Daftarkan dokumen', $doc->kode.' — '.$doc->judul, 'dokumen');

        return redirect()->route('dokumen.show', $doc)->with('ok','Dokumen terdaftar.');
    }

    public function show(Document $dokumen)
    {
        $dokumen->load(['company','user','revisions','isoMap']);

        return Inertia::render('Dokumen/Detail', [
            'judul'    => $dokumen->kode,
            'subjudul' => $dokumen->judul,

            'd' => $this->baris($dokumen) + [
                'ringkasan'      => $dokumen->ringkasan ?: null,
                'departemen'     => $dokumen->departemen ?: null,
                'perusahaan'     => $dokumen->company?->name,
                'klasifikasi'    => $dokumen->klasifikasi ?: null,
                'disetujui'      => $dokumen->disetujui_oleh ?: null,
                'acuan'          => $dokumen->acuan ?: null,
                'tanggalTerbit'  => $dokumen->tanggal_terbit?->format('d M Y'),
                'tanggalBerlaku' => $dokumen->tanggal_berlaku?->format('d M Y'),
                'revisiBerikut'  => 'Rev. '.str_pad((string) ($dokumen->revisi + 1), 2, '0', STR_PAD_LEFT),
            ],

            'riwayat' => $dokumen->revisions->map(fn ($r) => [
                'id'        => $r->id,
                'label'     => $r->labelRevisi(),
                'tanggal'   => $r->tanggal?->format('d M Y'),
                'oleh'      => $r->oleh,
                'ringkasan' => $r->ringkasan_perubahan,
            ])->all(),

            // Klausul dibawa lengkap dengan nama standarnya: kode mentah
            // seperti '9001' tidak memberi tahu pembaca standar mana yang
            // dipenuhi tanpa membuka halaman lain.
            'klausul' => array_map(fn ($kode) => [
                'kode'   => (string) $kode,
                'nama'   => Iso::get((string) $kode)['nama'] ?? (string) $kode,
                'warna'  => Iso::warna((string) $kode),
                'url'    => route('iso.show', $kode),
                'butir'  => $dokumen->klausul()[$kode],
            ], array_keys($dokumen->klausul())),

            'bolehHapus' => Gate::allows('admin'),

            'tautan' => [
                'ubah'    => route('dokumen.edit', $dokumen),
                'unduh'   => $dokumen->berkas ? route('dokumen.unduh', $dokumen) : null,
                'revisi'  => route('dokumen.revisi', $dokumen),
                'hapus'   => route('dokumen.destroy', $dokumen),
                'daftar'  => route('dokumen.index'),
            ],
        ]);
    }

    public function edit(Document $dokumen)
    {
        return $this->formulir($dokumen);
    }

    public function update(Request $request, Document $dokumen)
    {
        $d = $this->validasi($request, $dokumen);
        if ($berkas = $this->simpanBerkas($request)) $d['berkas'] = $berkas;

        $dokumen->update($d);
        $this->simpanKlausul($request, $dokumen);

        return redirect()->route('dokumen.show', $dokumen)->with('ok','Dokumen diperbarui.');
    }

    public function destroy(Document $dokumen)
    {
        $kode = $dokumen->kode;
        $dokumen->delete();
        ActivityLog::write('Hapus dokumen', $kode, 'dokumen');
        return redirect()->route('dokumen.index')->with('ok','Dokumen dihapus.');
    }

    /**
     * Terbitkan revisi baru: nomor revisi naik satu, berkas lama tetap
     * tersimpan pada baris riwayatnya sendiri.
     */
    public function revisi(Request $request, Document $dokumen)
    {
        $d = $request->validate([
            'ringkasan_perubahan' => ['required','string','max:2000'],
            'tanggal'             => ['nullable','date'],
            'tanggal_tinjau'      => ['nullable','date'],
            'berkas'              => ['nullable','file','max:20480'],
        ]);

        $berkas = $this->simpanBerkas($request) ?: $dokumen->berkas;

        $dokumen->revisions()->create([
            'revisi'              => $dokumen->revisi + 1,
            'ringkasan_perubahan' => $d['ringkasan_perubahan'],
            'berkas'              => $berkas,
            'tanggal'             => $d['tanggal'] ?? now(),
            'oleh'                => auth()->user()?->name,
        ]);

        $dokumen->update([
            'revisi'         => $dokumen->revisi + 1,
            'berkas'         => $berkas,
            'tanggal_terbit' => $d['tanggal'] ?? now(),
            'tanggal_tinjau' => $d['tanggal_tinjau'] ?? $dokumen->tanggal_tinjau,
            'status'         => 'berlaku',
        ]);

        ActivityLog::write('Terbitkan revisi dokumen', $dokumen->kode.' → '.$dokumen->labelRevisi(), 'dokumen');

        return redirect()->route('dokumen.show', $dokumen)
            ->with('ok', 'Revisi baru terbit: '.$dokumen->labelRevisi().'.');
    }

    /* ---------- penyaji ---------- */

    /** Satu dokumen dalam bentuk yang digambar daftar maupun halaman rincinya. */
    private function baris(Document $d): array
    {
        return [
            'id'            => $d->id,
            'kode'          => $d->kode,
            'judul'         => $d->judul,
            'jenis'         => $d->jenis,
            'status'        => $d->status,
            'warnaStatus'   => Dokumen::warna($d->status),
            'revisi'        => (int) $d->revisi,
            'labelRevisi'   => $d->labelRevisi(),
            'departemen'    => $d->departemen ?: null,
            'tanggalTinjau' => $d->tanggal_tinjau?->format('d M Y'),
            'perluTinjau'   => $d->perluTinjau(),
            'segeraTinjau'  => $d->segeraTinjau(),
            'adaBerkas'     => (bool) $d->berkas,
            'url'           => route('dokumen.show', $d),
        ];
    }

    /** Bentuk penomoran halaman yang sama untuk seluruh daftar Inertia. */
    private function halaman($paginator): array
    {
        return [
            'kini'   => $paginator->currentPage(),
            'akhir'  => $paginator->lastPage(),
            'total'  => $paginator->total(),
            'tautan' => array_map(fn ($t) => [
                'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
            ], $paginator->linkCollection()->all()),
        ];
    }

    /**
     * Formulir dokumen, dipakai bersama oleh create dan edit.
     *
     * Daftar klausul ISO dibawa utuh beserta yang sudah tercentang. Ia
     * memang besar, tetapi memuatnya belakangan lewat permintaan kedua
     * berarti isian yang sudah diketik hilang saat pengguna menunggu.
     */
    private function formulir(Document $d)
    {
        $terpilih = $d->exists ? $d->klausul() : [];

        return Inertia::render('Dokumen/Form', [
            'judul'    => $d->exists ? 'Ubah Dokumen' : 'Dokumen Baru',
            'subjudul' => $d->exists ? $d->kode.' — '.$d->judul : 'Daftarkan dokumen terkendali baru',

            'tersimpan' => $d->exists,

            'awal' => [
                'kode'            => (string) ($d->kode ?? ''),
                'judul'           => (string) ($d->judul ?? ''),
                'jenis'           => $d->jenis ?: Dokumen::JENIS[0],
                'status'          => $d->status ?: 'draft',
                'revisi'          => (string) ($d->revisi ?? 0),
                'departemen'      => (string) ($d->departemen ?? ''),
                'klasifikasi'     => (string) ($d->klasifikasi ?? ''),
                'company_id'      => $d->company_id ? (string) $d->company_id : '',
                'procedure_id'    => $d->procedure_id ? (string) $d->procedure_id : '',
                'tanggal_terbit'  => $d->tanggal_terbit?->format('Y-m-d') ?? '',
                'tanggal_berlaku' => $d->tanggal_berlaku?->format('Y-m-d') ?? '',
                'tanggal_tinjau'  => $d->tanggal_tinjau?->format('Y-m-d') ?? '',
                'acuan'           => (string) ($d->acuan ?? ''),
                'disetujui_oleh'  => (string) ($d->disetujui_oleh ?? ''),
                'ringkasan'       => (string) ($d->ringkasan ?? ''),
            ],

            // Kuncinya dipaksa teks; lihat catatan pada 'standar' di bawah.
            'isoAwal'   => (object) array_combine(
                array_map('strval', array_keys($terpilih)),
                array_values($terpilih),
            ),
            'adaBerkas' => (bool) $d->berkas,

            'opsi' => [
                'jenis'       => Dokumen::JENIS,
                'status'      => Dokumen::STATUS,
                'klasifikasi' => Dokumen::KLASIFIKASI,
                'perusahaan'  => Company::orderBy('name')->get()
                    ->map(fn ($c) => ['nilai' => (string) $c->id, 'label' => $c->name])->all(),
                'prosedur'    => Procedure::orderBy('title')->get()
                    ->map(fn ($p) => ['nilai' => (string) $p->id,
                                      'label' => ($p->code ? $p->code.' — ' : '').$p->title])->all(),
            ],

            // Lewat kodeSah(), bukan array_keys(): kode ISO seluruhnya
            // angka, dan PHP mengubah kunci array numerik menjadi integer.
            // Kode yang sampai ke Vue sebagai angka membuat pencocokannya
            // dengan kunci isoAwal bergantung pada pemaksaan jenis.
            'standar' => array_map(fn ($kode) => [
                'kode'   => $kode,
                'nama'   => Iso::get($kode)['nama'],
                'judul'  => Iso::get($kode)['judul'],
                'warna'  => Iso::warna($kode),
                'butir'  => Iso::butir($kode),
            ], Iso::kodeSah()),

            'tautan' => [
                'simpan' => $d->exists ? route('dokumen.update', $d) : route('dokumen.store'),
                'batal'  => $d->exists ? route('dokumen.show', $d) : route('dokumen.index'),
            ],
        ]);
    }

    /* ---------- bantu ---------- */

    /**
     * Simpan pemetaan dokumen ke klausul ISO.
     *
     * Ditulis ulang seluruhnya tiap kali disimpan: formulir mengirim keadaan
     * lengkap, jadi menambah tanpa membuang akan meninggalkan klausul yang
     * sudah dicabut centangnya tetap terpetakan.
     */
    private function simpanKlausul(Request $request, Document $doc): void
    {
        $masuk = (array) $request->input('iso', []);

        $sah = [];
        foreach (Iso::kodeSah() as $kode) {
            $butir = array_column(Iso::butir($kode), 'no');
            foreach ((array) ($masuk[$kode] ?? []) as $klausul) {
                if (in_array($klausul, $butir, true)) {
                    $sah[] = ['standar' => $kode, 'klausul' => $klausul];
                }
            }
        }

        $doc->isoMap()->delete();
        foreach ($sah as $baris) $doc->isoMap()->create($baris);
    }

    private function validasi(Request $r, ?Document $abaikan = null): array
    {
        $d = $this->pemilik($r->validate([
            'kode'            => ['required','string','max:60', Rule::unique('documents','kode')->ignore($abaikan?->id)],
            'judul'           => ['required','string','max:200'],
            'jenis'           => ['required', Rule::in(Dokumen::JENIS)],
            'klasifikasi'     => ['nullable', Rule::in(Dokumen::KLASIFIKASI)],
            'departemen'      => ['nullable','string','max:100'],
            'company_id'      => ['nullable','exists:companies,id'],
            'procedure_id'    => ['nullable', new DalamPerusahaan('procedures')],
            'revisi'          => ['nullable','integer','min:0','max:999'],
            'status'          => ['required', Rule::in(Dokumen::STATUS)],
            'tanggal_terbit'  => ['nullable','date'],
            'tanggal_berlaku' => ['nullable','date'],
            'tanggal_tinjau'  => ['nullable','date'],
            'ringkasan'       => ['nullable','string','max:3000'],
            'acuan'           => ['nullable','string','max:150'],
            'disetujui_oleh'  => ['nullable','string','max:150'],
            'berkas'          => ['nullable','file','max:20480'],
        ]));

        // Hasil validasi memuat objek unggahan, bukan path. Buang di sini agar
        // tidak ikut mass-assignment; pemanggil menyetel path hasil simpanBerkas().
        unset($d['berkas']);

        return $d;
    }

    /** Simpan berkas unggahan; kembalikan path, atau null bila tidak ada. */
    private function simpanBerkas(Request $r): ?string
    {
        $f = $r->file('berkas');
        return ($f && $f->isValid()) ? $f->store('dokumen', 'public') : null;
    }

    /** Unduh berkas revisi berjalan. */
    public function unduh(Document $dokumen)
    {
        abort_if(!$dokumen->berkas || !Storage::disk('public')->exists($dokumen->berkas), 404, 'Berkas tidak ditemukan.');
        return Storage::disk('public')->download($dokumen->berkas);
    }
}
