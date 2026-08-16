<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, MinerbaConservationRecord, TindakLanjut};
use App\Support\{Alur, KopDokumen, PeringatanKonservasi};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KonservasiController extends Controller
{
    public const KATEGORI_ACTION = ['recovery', 'kehilangan', 'dilusi', 'stockpile', 'mineral_ikutan', 'reklamasi'];
    public const PRIORITAS_ACTION = ['rendah', 'sedang', 'tinggi', 'kritis'];
    /** Keterlambatan dihitung dari tanggal, bukan disimpan sebagai status. */
    public const STATUS_ACTION = TindakLanjut::STATUS;

    public function index(Request $request)
    {
        return $this->halaman($request, 'dashboard');
    }

    public function data(Request $request)
    {
        return $this->halaman($request, 'data');
    }

    public function laporan(Request $request)
    {
        return $this->halaman($request, 'laporan');
    }

    public function simpanRecord(Request $request)
    {
        $data = $this->pemilik($this->validasiRecord($request));
        $data['user_id'] = auth()->id();

        $record = MinerbaConservationRecord::create($data);
        ActivityLog::write('Catat konservasi minerba', $record->komoditas.' - '.$record->periode->format('F Y'), 'konservasi');

        return back()->with('ok', 'Data konservasi minerba tersimpan.');
    }

    public function ubahRecord(Request $request, MinerbaConservationRecord $record)
    {
        $record->update($this->pemilik($this->validasiRecord($request)));
        ActivityLog::write('Ubah konservasi minerba', $record->komoditas.' - '.$record->periode->format('F Y'), 'konservasi');

        return back()->with('ok', 'Data konservasi diperbarui.');
    }

    public function hapusRecord(MinerbaConservationRecord $record)
    {
        if ($record->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Data yang sudah disetujui tidak dapat dihapus.']);
        }

        ActivityLog::write('Hapus konservasi minerba', $record->komoditas.' - '.$record->periode->format('F Y'), 'konservasi');
        $record->delete();

        return back()->with('ok', 'Data konservasi dihapus.');
    }

    /* ---------- alur tinjauan ---------- */

    public function ajukanRecord(MinerbaConservationRecord $record)
    {
        return $this->jalankan($record, fn () => $record->ajukan(),
            'Ajukan konservasi minerba', 'Data diajukan untuk ditinjau.');
    }

    public function setujuiRecord(MinerbaConservationRecord $record)
    {
        return $this->jalankan($record, fn () => $record->setujui(),
            'Setujui konservasi minerba', 'Data disetujui dan kini terhitung pada KPI.');
    }

    public function tolakRecord(Request $request, MinerbaConservationRecord $record)
    {
        $alasan = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:1000'],
        ])['alasan_tolak'];

        return $this->jalankan($record, fn () => $record->tolak($alasan),
            'Tolak konservasi minerba', 'Data ditolak dan dikembalikan kepada pengaju.');
    }

    /** Penolakan oleh alur dijawab sebagai galat pada bidang, bukan 403. */
    private function jalankan(MinerbaConservationRecord $record, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, $record->komoditas.' - '.$record->periode->format('F Y'), 'konservasi');

        return back()->with('ok', $pesan);
    }

    /**
     * Laporan siap cetak.
     *
     * Hanya memuat catatan yang sudah ditinjau. Laporan konservasi
     * dikutip pada rapat dan diedarkan keluar; yang memuat draf akan
     * berubah setelah beredar, dan yang beredar tidak lagi cocok dengan
     * yang tersimpan tanpa seorang pun tahu mana yang benar.
     *
     * Jumlah yang belum ditinjau tetap disebutkan pada lembarnya. Laporan
     * yang memuat empat dari sembilan catatan tetap sah dibaca asalkan
     * pembacanya tahu; yang menyembunyikannya membuat pembacanya mengira
     * sudah melihat seluruh tahun.
     */
    public function cetak(Request $request)
    {
        $tahun = max(2000, min(2100, (int) $request->integer('tahun', now()->year)));

        $semua = MinerbaConservationRecord::with(['company', 'peninjau'])
            ->whereBetween('periode', ["{$tahun}-01-01", "{$tahun}-12-31"])
            ->orderBy('periode')->get();

        $sah = $semua->whereIn('status', Alur::terhitung());

        $material = (float) $sah->sum('material_digali');
        $aktual = (float) $sah->sum('produksi_aktual');
        $target = (float) $sah->sum('target_produksi');
        $hilang = (float) $sah->sum('kehilangan_material');
        $dilusi = (float) $sah->sum('dilusi');

        $perusahaan = $this->perusahaanKop();

        return Inertia::render('Print/Konservasi', [
            'dok'   => KopDokumen::untuk('laporan-konservasi', $perusahaan),
            'tahun' => $tahun,

            'ringkas' => [
                'target_produksi'     => $target,
                'produksi_aktual'     => $aktual,
                'capaian_target'      => $target > 0 ? $aktual / $target * 100 : 0,
                'material_digali'     => $material,
                'recovery'            => $material > 0 ? $aktual / $material * 100 : 0,
                'kehilangan_material' => $hilang,
                'porsi_kehilangan'    => $material > 0 ? $hilang / $material * 100 : 0,
                'dilusi'              => $dilusi,
                'porsi_dilusi'        => $aktual > 0 ? $dilusi / $aktual * 100 : 0,
                'stok_akhir'          => (float) $sah->sum('stok_akhir'),
                'jumlah_record'       => $sah->count(),
                'belum_ditinjau'      => $semua->count() - $sah->count(),
            ],

            'perKomoditas' => $sah->groupBy('komoditas')->map(function ($rows, $komoditas) {
                $bahan = (float) $rows->sum('material_digali');
                $keluar = (float) $rows->sum('produksi_aktual');

                return [
                    'komoditas'  => $komoditas,
                    'target'     => (float) $rows->sum('target_produksi'),
                    'aktual'     => $keluar,
                    'digali'     => $bahan,
                    'recovery'   => $bahan > 0 ? $keluar / $bahan * 100 : 0,
                    'kehilangan' => (float) $rows->sum('kehilangan_material'),
                    'record'     => $rows->count(),
                ];
            })->values()->all(),

            'records' => $sah->map(fn (MinerbaConservationRecord $r) => [
                'periode'             => $r->periode?->format('M Y'),
                'lokasi'              => $r->lokasi,
                'komoditas'           => $r->komoditas,
                'satuan'              => $r->satuan,
                'target_produksi'     => $r->target_produksi,
                'produksi_aktual'     => $r->produksi_aktual,
                'material_digali'     => $r->material_digali,
                'recovery'            => $r->recoveryTerhitung(),
                'kehilangan_material' => $r->kehilangan_material,
                'dilusi'              => $r->dilusi,
                'mineral_ikutan'      => $r->mineral_ikutan,
                'peninjau'            => $r->peninjau?->name,
                'ditinjauPada'        => $r->ditinjau_pada?->format('d/m/Y'),
            ])->values()->all(),

            'tindak' => TindakLanjut::with('sumber')->modul('konservasi')->terbukaSaja()
                ->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values()->all(),

            'kembali' => route('konservasi.laporan', ['tahun' => $tahun]),
        ]);
    }

    public function simpanAction(Request $request)
    {
        $data = $this->pemilik($this->validasiAction($request));

        if (!empty($data['record_id'])) {
            $linked = MinerbaConservationRecord::findOrFail($data['record_id']);
            $data['company_id'] ??= $linked->company_id;
        }

        $data['user_id'] = auth()->id();
        $data['modul'] = 'konservasi';

        if (!empty($data['record_id'])) {
            $data['sumber_type'] = MinerbaConservationRecord::class;
            $data['sumber_id'] = $data['record_id'];
        }
        unset($data['record_id']);

        TindakLanjut::create($data);
        ActivityLog::write('Tambah tindak lanjut konservasi', $data['judul'], 'konservasi');

        return back()->with('ok', 'Tindak lanjut konservasi ditambahkan.');
    }

    public function ubahAction(Request $request, TindakLanjut $action)
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(self::STATUS_ACTION)],
        ])['status'];

        // Tanggal penyelesaian dicatat saat status berpindah, bukan
        // diketik terpisah: yang diketik terpisah akan kosong pada
        // sebagian besar baris, dan laporan penutupan menjadi mustahil.
        $action->update([
            'status' => $status,
            'selesai_pada' => $status === 'selesai' ? now()->toDateString() : null,
        ]);

        return back()->with('ok', 'Status tindak lanjut diperbarui.');
    }

    /** Pengguna biasa tidak boleh menulis data ke perusahaan lain lewat payload. */

    public function hapusAction(TindakLanjut $action)
    {
        ActivityLog::write('Hapus tindak lanjut konservasi', $action->judul, 'konservasi');
        $action->delete();

        return back()->with('ok', 'Tindak lanjut dihapus.');
    }

    private function halaman(Request $request, string $mode)
    {
        $tahun = max(2000, min(2100, (int) $request->integer('tahun', now()->year)));
        $query = MinerbaConservationRecord::with('company')
            ->whereBetween('periode', ["{$tahun}-01-01", "{$tahun}-12-31"])
            ->latest('periode')->latest('id');

        if ($request->filled('perusahaan')) {
            $query->where('company_id', $request->integer('perusahaan'));
        }

        $records = $query->get();

        // Daftar menampilkan semuanya supaya pengaju melihat drafnya
        // sendiri; hitungan hanya memakai yang sudah ditinjau. Memisahkan
        // keduanya di sini, sekali, mencegah angka yang belum disetujui
        // ikut terbawa ke KPI dan ke laporan — kesalahan yang tidak
        // menimbulkan galat apa pun, hanya angka yang salah.
        $sah = $records->whereIn('status', Alur::terhitung());

        $actions = TindakLanjut::with(['sumber', 'company'])
            ->modul('konservasi')
            ->when($request->filled('perusahaan'), fn ($q) => $q->where('company_id', $request->integer('perusahaan')))
            ->urutMendesak()->get();

        $material = (float) $sah->sum('material_digali');
        $aktual = (float) $sah->sum('produksi_aktual');
        $target = (float) $sah->sum('target_produksi');
        $recovery = $material > 0 ? ($aktual / $material) * 100 : (float) $sah->avg('recovery_percent');

        $ringkas = [
            'jumlah_record' => $sah->count(),
            'target_produksi' => $target,
            'produksi_aktual' => $aktual,
            'capaian_target' => $target > 0 ? ($aktual / $target) * 100 : 0,
            'material_digali' => $material,
            'recovery' => min(100, max(0, $recovery ?: 0)),
            'kehilangan_material' => (float) $sah->sum('kehilangan_material'),
            'dilusi' => (float) $sah->sum('dilusi'),
            'stok_akhir' => (float) $sah->sum('stok_akhir'),
            'action_terbuka' => $actions->filter(fn (TindakLanjut $a) => $a->terbuka())->count(),
        ];

        $perKomoditas = $sah->groupBy('komoditas')->map(function ($rows, $komoditas) {
            $material = (float) $rows->sum('material_digali');
            $aktual = (float) $rows->sum('produksi_aktual');
            $target = (float) $rows->sum('target_produksi');

            return [
                'komoditas' => $komoditas,
                'record' => $rows->count(),
                'target' => $target,
                'aktual' => $aktual,
                'capaian' => $target > 0 ? ($aktual / $target) * 100 : 0,
                'recovery' => $material > 0 ? ($aktual / $material) * 100 : (float) $rows->avg('recovery_percent'),
                'kehilangan' => (float) $rows->sum('kehilangan_material'),
            ];
        })->values()->all();

        $alerts = PeringatanKonservasi::susun($ringkas, $records, $actions);

        return Inertia::render('Konservasi/Halaman', [
            'judul' => 'Konservasi Minerba',
            'subjudul' => 'Pengendalian pemanfaatan mineral, recovery, kehilangan, dan mineral ikutan',
            'mode' => $mode,
            'tahun' => $tahun,
            'tahunOpsi' => range(now()->year - 3, now()->year + 1),
            'ringkas' => $ringkas,
            'perKomoditas' => $perKomoditas,
            'alerts' => $alerts,
            'records' => $records->map(fn (MinerbaConservationRecord $record) => $this->recordView($record))->values()->all(),
            'actions' => $actions->map(fn (TindakLanjut $a) => $this->actionView($a))->values()->all(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'opsi' => [
                'statusRecord' => Alur::LABEL,
                'kategoriAction' => self::KATEGORI_ACTION,
                'prioritasAction' => self::PRIORITAS_ACTION,
                'statusAction' => self::STATUS_ACTION,
            ],
            /*
             * Tautan ber-id memakai penanda __ID__, sejajar dengan modul
             * Operasi. Sebelumnya halaman menuliskan jalurnya sendiri
             * ('/konservasi/records/' + id); jalur itu kebetulan benar,
             * tetapi ia tidak ikut berubah ketika rutenya berubah — dan
             * yang rusak karenanya adalah tombol, tanpa galat apa pun.
             */
            'tautan' => [
                'dashboard' => route('konservasi.index'),
                'data' => route('konservasi.data'),
                'laporan' => route('konservasi.laporan'),
                'cetak' => route('konservasi.cetak', ['tahun' => $tahun]),

                'recordSimpan'  => route('konservasi.record.simpan'),
                'recordUbah'    => route('konservasi.record.ubah',    ['record' => '__ID__']),
                'recordHapus'   => route('konservasi.record.hapus',   ['record' => '__ID__']),
                'recordAjukan'  => route('konservasi.record.ajukan',  ['record' => '__ID__']),
                'recordSetujui' => route('konservasi.record.setujui', ['record' => '__ID__']),
                'recordTolak'   => route('konservasi.record.tolak',   ['record' => '__ID__']),

                'actionSimpan' => route('konservasi.action.simpan'),
                'actionUbah'   => route('konservasi.action.ubah',  ['action' => '__ID__']),
                'actionHapus'  => route('konservasi.action.hapus', ['action' => '__ID__']),
            ],
        ]);
    }

    private function recordView(MinerbaConservationRecord $record): array
    {
        return [
            'id' => $record->id,
            'periode' => $record->periode?->format('Y-m-d'),
            'periodeLabel' => $record->periode?->translatedFormat('M Y'),
            'lokasi' => $record->lokasi,
            'komoditas' => $record->komoditas,
            'satuan' => $record->satuan,
            'target_produksi' => $record->target_produksi,
            'produksi_aktual' => $record->produksi_aktual,
            'material_digali' => $record->material_digali,
            'recovery_percent' => $record->recovery_percent,
            'recovery_terhitung' => $record->recoveryTerhitung(),
            'capaian_target' => $record->capaianTarget(),
            'kehilangan_material' => $record->kehilangan_material,
            'dilusi' => $record->dilusi,
            'stok_akhir' => $record->stok_akhir,
            'mineral_ikutan' => $record->mineral_ikutan,
            'status' => $record->status,
            'statusLabel' => Alur::LABEL[$record->status] ?? $record->status,
            'catatan' => $record->catatan,
            'perusahaan' => $record->company?->name,

            // Keadaan alur ikut dikirim supaya antarmuka tidak perlu
            // menyusun ulang aturannya sendiri. Aturan yang ditulis dua
            // kali akan berbeda cepat atau lambat, dan yang di sisi
            // browser adalah yang paling mudah dilewati.
            'alur' => [
                'dapatDiubah'  => $record->dapatDiubah(),
                'dapatDiajukan' => $record->dapatDiubah(),
                'dapatDitinjau' => $record->dapatDitinjauOleh(auth()->user()),
                'pengaju'      => $record->pengaju?->name,
                'diajukanPada' => $record->diajukan_pada?->format('Y-m-d H:i'),
                'peninjau'     => $record->peninjau?->name,
                'ditinjauPada' => $record->ditinjau_pada?->format('Y-m-d H:i'),
                'alasanTolak'  => $record->alasan_tolak,
            ],
        ];
    }

    private function actionView(TindakLanjut $a): array
    {
        return $a->toView() + [
            'record' => $a->sumber instanceof MinerbaConservationRecord
                ? $a->sumber->komoditas.' - '.$a->sumber->periode?->format('M Y')
                : null,
        ];
    }

    private function validasiRecord(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'periode' => ['required', 'date'],
            'lokasi' => ['required', 'string', 'max:150'],
            'komoditas' => ['required', 'string', 'max:100'],
            'satuan' => ['required', 'string', 'max:20'],
            'target_produksi' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'produksi_aktual' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'material_digali' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'recovery_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'kehilangan_material' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'dilusi' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'stok_akhir' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'mineral_ikutan' => ['nullable', 'string', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    private function validasiAction(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'record_id' => ['nullable', 'exists:konservasi_minerba_records,id'],
            'judul' => ['required', 'string', 'max:200'],
            'kategori' => ['required', Rule::in(self::KATEGORI_ACTION)],
            'prioritas' => ['required', Rule::in(self::PRIORITAS_ACTION)],
            'status' => ['required', Rule::in(self::STATUS_ACTION)],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'target_selesai' => ['nullable', 'date'],
            'uraian' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
