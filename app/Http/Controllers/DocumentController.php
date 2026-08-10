<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, Document, Procedure};
use App\Support\{Db, Dokumen, Iso, KopDokumen};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        $f = [
            'q'          => trim((string) $request->get('q')),
            'jenis'      => $request->get('jenis'),
            'status'     => $request->get('status'),
            'departemen' => $request->get('departemen'),
            'tinjau'     => $request->get('tinjau'),   // 'lewat' | 'segera'
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

        return view('dokumen.index', [
            'documents' => $documents,
            'f'         => $f,
            'stat'      => [
                'total'   => (clone $semua)->count(),
                'berlaku' => (clone $semua)->where('status','berlaku')->count(),
                'draft'   => (clone $semua)->where('status','draft')->count(),
                'lewat'   => (clone $semua)->where('status','berlaku')->whereNotNull('tanggal_tinjau')
                                ->whereDate('tanggal_tinjau','<',now())->count(),
            ],
            'departemenOpsi' => Document::whereNotNull('departemen')->distinct()->orderBy('departemen')->pluck('departemen'),
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

        return view('dokumen.piramida', [
            'tingkat' => $tingkat,
            'total'   => array_sum(array_column($tingkat, 'total')),
        ]);
    }

    /** Daftar Induk Dokumen — berkas wajib sistem manajemen, siap cetak. */
    public function daftarInduk()
    {
        return view('dokumen.daftar-induk', [
            'documents' => Document::with('company')
                ->orderByRaw(Dokumen::urutJenisSql())->orderBy('kode')->get(),
            'dok'       => KopDokumen::untuk('daftar-induk', Company::first()),
            'kembali'   => route('dokumen.index'),
        ]);
    }

    public function create()
    {
        return view('dokumen.form', [
            'document'   => new Document(['status' => 'draft', 'revisi' => 0]),
            'companies'  => Company::orderBy('name')->get(),
            'procedures' => Procedure::orderBy('title')->get(),
        ]);
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
        $dokumen->load(['company','user','revisions']);
        return view('dokumen.show', ['d' => $dokumen]);
    }

    public function edit(Document $dokumen)
    {
        return view('dokumen.form', [
            'document'   => $dokumen,
            'companies'  => Company::orderBy('name')->get(),
            'procedures' => Procedure::orderBy('title')->get(),
        ]);
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
        $d = $r->validate([
            'kode'            => ['required','string','max:60', Rule::unique('documents','kode')->ignore($abaikan?->id)],
            'judul'           => ['required','string','max:200'],
            'jenis'           => ['required', Rule::in(Dokumen::JENIS)],
            'klasifikasi'     => ['nullable', Rule::in(Dokumen::KLASIFIKASI)],
            'departemen'      => ['nullable','string','max:100'],
            'company_id'      => ['nullable','exists:companies,id'],
            'procedure_id'    => ['nullable','exists:procedures,id'],
            'revisi'          => ['nullable','integer','min:0','max:999'],
            'status'          => ['required', Rule::in(Dokumen::STATUS)],
            'tanggal_terbit'  => ['nullable','date'],
            'tanggal_berlaku' => ['nullable','date'],
            'tanggal_tinjau'  => ['nullable','date'],
            'ringkasan'       => ['nullable','string','max:3000'],
            'acuan'           => ['nullable','string','max:150'],
            'disetujui_oleh'  => ['nullable','string','max:150'],
            'berkas'          => ['nullable','file','max:20480'],
        ]);

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
