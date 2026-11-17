<?php

namespace App\Http\Controllers;

use App\Rules\DalamPerusahaan;

use App\Models\{ActivityLog, Company, KoObject, MineOperationalRecord, TindakLanjut, WorkOrder, WorkOrderPart};
use App\Support\{Alur, Keandalan, KopDokumen, PeringatanMaintenance};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pusat Pemeliharaan dan Keandalan.
 *
 * Menyatukan yang sudah tersebar: registri alat beserta kritikalitas dan
 * jadwal perawatan ada di modul Keselamatan Operasi, konsumsi bahan
 * bakar per unit ada di modul Energi, dan suku cadang ada di Gudang.
 * Yang belum ada di mana pun — dan tanpanya MTBF, MTTR, ketersediaan,
 * serta tunggakan pekerjaan mustahil dihitung — adalah catatan gangguan
 * dan perbaikannya. Itulah yang ditambahkan modul ini.
 */
class MaintenanceController extends Controller
{
    /** Jam kerja alat dalam sehari; dasar hitungan ketersediaan. */
    private const JAM_PER_HARI = 24;

    public function index(Request $r)   { return $this->halaman($r, 'dashboard'); }
    public function order(Request $r)   { return $this->halaman($r, 'order'); }
    public function armada(Request $r)  { return $this->halaman($r, 'armada'); }

    /* ---------- perintah kerja ---------- */

    public function simpan(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'      => ['nullable', 'exists:companies,id'],
            'ko_object_id'    => ['nullable', new DalamPerusahaan('ko_objects')],
            'nomor'           => ['nullable', 'string', 'max:60'],
            'jenis'           => ['required', Rule::in(WorkOrder::JENIS)],
            'prioritas'       => ['required', Rule::in(WorkOrder::PRIORITAS)],
            'gejala'          => ['required', 'string', 'max:300'],
            'penyebab'        => ['nullable', 'string', 'max:2000'],
            'dilaporkan_pada' => ['required', 'date'],
            'hm_saat_rusak'   => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]));

        $data['user_id'] = auth()->id();
        $wo = WorkOrder::create($data);

        ActivityLog::write('Buka perintah kerja', ($wo->nomor ?: "WO-{$wo->id}").' · '.$wo->gejala, 'maintenance');

        return back()->with('ok', 'Perintah kerja dibuka.');
    }

    /**
     * Memindahkan status sekaligus mencatat waktunya.
     *
     * Stempel waktu diisi oleh perpindahan, bukan diketik terpisah.
     * Yang diketik terpisah akan kosong pada sebagian besar baris, dan
     * begitu ia kosong seluruh hitungan MTTR dan ketersediaan ikut
     * kehilangan dasarnya — diam-diam, sebab kolom kosong hanya membuat
     * angkanya lebih kecil, bukan menimbulkan galat.
     */
    public function ubahStatus(Request $request, WorkOrder $order)
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(WorkOrder::STATUS)],
            'tindakan' => ['nullable', 'string', 'max:2000'],
            'biaya' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);

        $ubah = ['status' => $status['status']];

        if ($status['status'] === 'dikerjakan' && !$order->mulai_pada) {
            $ubah['mulai_pada'] = now();
        }

        if ($status['status'] === 'selesai') {
            $ubah['mulai_pada'] = $order->mulai_pada ?: now();
            $ubah['selesai_pada'] = now();
            $ubah['ditutup_oleh'] = auth()->id();
        }

        // Dibuka kembali: jejak penyelesaian dihapus, kalau tidak alatnya
        // terbaca sudah jalan padahal masih di bengkel.
        if (in_array($status['status'], ['dibuka', 'batal'], true)) {
            $ubah['selesai_pada'] = null;
        }

        foreach (['tindakan', 'biaya'] as $k) {
            if (array_key_exists($k, $status) && $status[$k] !== null) $ubah[$k] = $status[$k];
        }

        try {
            $order->update($ubah);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        return back()->with('ok', 'Perintah kerja diperbarui.');
    }

    public function simpanPart(Request $request, WorkOrder $order)
    {
        $data = $request->validate([
            'gudang_barang_id' => ['nullable', new DalamPerusahaan('gudang_barang')],
            'nama'             => ['required', 'string', 'max:150'],
            'jumlah'           => ['required', 'numeric', 'min:0', 'max:1000000'],
            'satuan'           => ['nullable', 'string', 'max:20'],
            'harga_satuan'     => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);

        $order->parts()->create($data);

        return back()->with('ok', 'Suku cadang dicatat.');
    }

    /* ---------- verifikasi ---------- */

    public function verifikasi(WorkOrder $order)
    {
        try {
            $order->verifikasi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write('Verifikasi perintah kerja', $order->nomor ?: "WO-{$order->id}", 'maintenance');

        return back()->with('ok', 'Penutupan perintah kerja diverifikasi.');
    }

    public function batalVerifikasi(WorkOrder $order)
    {
        $order->batalkanVerifikasi();
        ActivityLog::write('Batalkan verifikasi perintah kerja', $order->nomor ?: "WO-{$order->id}", 'maintenance');

        return back()->with('ok', 'Verifikasi dibatalkan; perintah kerja dapat disunting kembali.');
    }

    /* ---------- tindak lanjut ---------- */

    public function simpanTindakLanjut(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'       => ['nullable', 'exists:companies,id'],
            'work_order_id'    => ['nullable', new DalamPerusahaan('work_orders')],
            'kode_pemicu'      => ['nullable', 'string', 'max:60'],
            'judul'            => ['required', 'string', 'max:200'],
            'prioritas'        => ['required', Rule::in(TindakLanjut::PRIORITAS)],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'target_selesai'   => ['nullable', 'date'],
            'uraian'           => ['nullable', 'string', 'max:3000'],
        ]));

        if (!empty($data['work_order_id'])) {
            $data['sumber_type'] = WorkOrder::class;
            $data['sumber_id'] = $data['work_order_id'];
        }
        unset($data['work_order_id']);

        $data['modul'] = 'maintenance';
        $data['user_id'] = auth()->id();

        TindakLanjut::create($data);
        ActivityLog::write('Tambah tindak lanjut pemeliharaan', $data['judul'], 'maintenance');

        return back()->with('ok', 'Tindak lanjut ditambahkan.');
    }

    public function ubahTindakLanjut(Request $request, TindakLanjut $tindak)
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(TindakLanjut::STATUS)],
        ])['status'];

        $tindak->update([
            'status' => $status,
            'selesai_pada' => $status === 'selesai' ? now()->toDateString() : null,
        ]);

        return back()->with('ok', 'Status tindak lanjut diperbarui.');
    }

    public function hapus(WorkOrder $order)
    {
        ActivityLog::write('Hapus perintah kerja', $order->nomor ?: "WO-{$order->id}", 'maintenance');
        $order->delete();

        return back()->with('ok', 'Perintah kerja dihapus.');
    }

    /**
     * Laporan keandalan siap cetak.
     *
     * Berbeda dari laporan Operasi dan Konservasi, laporan ini TIDAK
     * mengeluarkan perintah kerja yang belum diverifikasi. Pada data
     * produksi, mengeluarkan yang belum disetujui membuat capaian
     * terlihat lebih kecil, dan itu benar. Di sini akibatnya terbalik:
     * mengeluarkan waktu henti yang belum diverifikasi membuat
     * ketersediaan terlihat lebih baik daripada kenyataannya, dan
     * laporan yang menyanjung dirinya sendiri lebih berbahaya daripada
     * laporan yang mengaku belum lengkap.
     *
     * Yang dilakukan adalah menyebutkan berapa yang belum diverifikasi,
     * di bagian dasar laporan sebelum angka mana pun muncul.
     */
    public function cetak(Request $request)
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();
        if ($dari->greaterThan($sampai)) [$dari, $sampai] = [$sampai, $dari];

        $objek = KoObject::query()->get();
        $orders = WorkOrder::with(['objek', 'parts', 'pemverifikasi'])
            ->periode($dari, $sampai)->urutMendesak()->get();
        $terbuka = WorkOrder::with(['objek', 'parts'])->terbukaSaja()->urutMendesak()->get();

        $keandalan = $this->hitungKeandalan($objek, $orders, $dari, $sampai);
        $perusahaan = $this->perusahaanKop();

        return Inertia::render('Print/Maintenance', [
            'dok'    => KopDokumen::untuk('laporan-keandalan', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'keandalan' => $keandalan->toArray(),
            'pm'        => Keandalan::kepatuhanPm($objek, $sampai),
            'biaya'     => $this->biaya($orders, $keandalan, $dari, $sampai),
            'tunggakan' => $this->tunggakan($terbuka),

            'dasar' => [
                'order'             => $orders->count(),
                'diverifikasi'      => $orders->filter(fn (WorkOrder $w) => $w->sudahDiverifikasi())->count(),
                'belumDiverifikasi' => $orders->filter(fn (WorkOrder $w) => $w->menungguVerifikasi())->count(),
                'masihTerbuka'      => $orders->filter(fn (WorkOrder $w) => $w->terbuka())->count(),
                'unit'              => $objek->count(),
            ],

            'perAlat' => $this->perAlat($orders),

            'orders' => $orders->map(fn (WorkOrder $w) => $w->toView() + [
                'pemverifikasi' => $w->pemverifikasi?->name,
            ])->values(),

            'tindak' => TindakLanjut::modul('maintenance')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('maintenance.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();
        if ($dari->greaterThan($sampai)) [$dari, $sampai] = [$sampai, $dari];

        $objek = KoObject::query()->get();

        $orders = WorkOrder::with(['objek', 'parts'])
            ->periode($dari, $sampai)->urutMendesak()->get();

        $terbuka = WorkOrder::with(['objek', 'parts'])->terbukaSaja()->urutMendesak()->get();

        $keandalan = $this->hitungKeandalan($objek, $orders, $dari, $sampai);
        $pm = Keandalan::kepatuhanPm($objek, $sampai);
        $biaya = $this->biaya($orders, $keandalan, $dari, $sampai);
        $tunggakan = $this->tunggakan($terbuka);

        $tindak = TindakLanjut::with('sumber')->modul('maintenance')->urutMendesak()->get();

        return Inertia::render('Maintenance/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'keandalan' => $keandalan->toArray(),
            'pm'        => $pm,
            'biaya'     => $biaya,
            'tunggakan' => $tunggakan,

            'alerts' => PeringatanMaintenance::susun($keandalan, $pm, $tunggakan, $orders, $biaya),

            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'ringkas' => [
                'unit'          => $objek->count(),
                'order'         => $orders->count(),
                'orderTerbuka'  => $terbuka->count(),
                'kegagalan'     => $orders->filter(fn (WorkOrder $w) => $w->kegagalan())->count(),
                'belumDiverifikasi' => $orders->filter(fn (WorkOrder $w) => $w->menungguVerifikasi())->count(),
                'tindakTerbuka' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())->count(),
            ],

            'perAlat' => $this->perAlat($orders),
            'orders'  => $orders->map(fn (WorkOrder $w) => $w->toView())->values(),
            'terbuka' => $terbuka->map(fn (WorkOrder $w) => $w->toView())->values(),

            'objekOpsi' => $objek->map(fn (KoObject $o) => [
                'id' => $o->id, 'kode' => $o->kode, 'nama' => $o->nama,
                'kritikalitas' => $o->kritikalitas,
            ])->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'prioritasTindak' => TindakLanjut::PRIORITAS,
                'statusTindak' => TindakLanjut::STATUS,
                'jenis' => WorkOrder::JENIS,
                'status' => WorkOrder::STATUS,
                'prioritas' => WorkOrder::PRIORITAS,
            ],

            'tautan' => [
                'dashboard'  => route('maintenance.index'),
                'order'      => route('maintenance.order'),
                'armada'     => route('maintenance.armada'),
                'simpan'     => route('maintenance.simpan'),
                'ubahStatus' => route('maintenance.status', ['order' => '__ID__']),
                'simpanPart' => route('maintenance.part', ['order' => '__ID__']),
                'hapus'      => route('maintenance.hapus', ['order' => '__ID__']),
                'verifikasi' => route('maintenance.verifikasi', ['order' => '__ID__']),
                'batalVerifikasi' => route('maintenance.batalVerifikasi', ['order' => '__ID__']),
                'tindakSimpan' => route('maintenance.tindak.simpan'),
                'tindakUbah'   => route('maintenance.tindak.ubah', ['tindak' => '__ID__']),
                'cetak'        => route('maintenance.cetak'),
            ],
        ]);
    }

    /**
     * @param Collection<int,KoObject>  $objek
     * @param Collection<int,WorkOrder> $orders
     */
    private function hitungKeandalan(Collection $objek, Collection $orders, Carbon $dari, Carbon $sampai): Keandalan
    {
        $hari = $dari->diffInDays($sampai) + 1;
        $jamTersedia = $objek->count() * $hari * self::JAM_PER_HARI;

        $gagal = $orders->filter(fn (WorkOrder $w) => $w->kegagalan());

        return new Keandalan(
            $jamTersedia,
            (float) $orders->sum(fn (WorkOrder $w) => $w->jamHenti()),
            $gagal->count(),
            (float) $gagal->sum(fn (WorkOrder $w) => $w->jamPerbaikan()),
        );
    }

    /**
     * Biaya pemeliharaan per jam jalan dan per ton produksi.
     *
     * Produksi diambil hanya dari catatan operasi yang sudah disetujui.
     * Memakai draf akan membuat biaya per ton turun setiap kali ada
     * laporan shift baru masuk — sebelum seorang pun memeriksanya — dan
     * naik lagi bila laporan itu kemudian ditolak.
     */
    private function biaya(Collection $orders, Keandalan $k, Carbon $dari, Carbon $sampai): array
    {
        $total = (float) $orders->sum(fn (WorkOrder $w) => $w->totalBiaya());

        $ton = (float) MineOperationalRecord::query()
            ->whereIn('status', Alur::terhitung())
            ->whereBetween('tanggal', [$dari, $sampai])
            ->sum('produksi_ton');

        $jamJalan = $k->jamJalan();

        return [
            'total'    => $total,
            'perJam'   => $jamJalan > 0 ? round($total / $jamJalan, 2) : 0.0,
            'perTon'   => $ton > 0 ? round($total / $ton, 2) : 0.0,
            'tonDasar' => $ton,
            'sukuCadang' => (float) $orders->sum(fn (WorkOrder $w) => $w->parts->sum(fn (WorkOrderPart $p) => $p->subtotal())),
        ];
    }

    /**
     * Tunggakan pekerjaan, dibaca menurut kritikalitas alatnya.
     *
     * Sepuluh perintah kerja pada alat pendukung tidak sama gawatnya
     * dengan satu pada alat kritis, dan jumlah tunggakan saja tidak
     * membedakan keduanya.
     *
     * @param Collection<int,WorkOrder> $terbuka
     */
    private function tunggakan(Collection $terbuka): array
    {
        $jamTertahan = (float) $terbuka->sum(fn (WorkOrder $w) => $w->jamHenti());

        return [
            'jumlah'      => $terbuka->count(),
            'kritis'      => $terbuka->filter(fn (WorkOrder $w) => $w->prioritas === 'kritis')->count(),
            'belumMulai'  => $terbuka->filter(fn (WorkOrder $w) => $w->mulai_pada === null)->count(),
            'jamTertahan' => round($jamTertahan, 1),
            'terlamaJam'  => round((float) $terbuka->max(fn (WorkOrder $w) => $w->jamHenti()), 1),
        ];
    }

    /** @param Collection<int,WorkOrder> $orders */
    private function perAlat(Collection $orders): array
    {
        return $orders->filter(fn (WorkOrder $w) => $w->ko_object_id !== null)
            ->groupBy('ko_object_id')
            ->map(function (Collection $rows) {
                $o = $rows->first()->objek;
                $gagal = $rows->filter(fn (WorkOrder $w) => $w->kegagalan());

                return [
                    'kode'         => $o?->kode ?? '—',
                    'nama'         => $o?->nama ?? '—',
                    'kritikalitas' => $o?->kritikalitas,
                    'order'        => $rows->count(),
                    'kegagalan'    => $gagal->count(),
                    'jamHenti'     => round((float) $rows->sum(fn (WorkOrder $w) => $w->jamHenti()), 1),
                    'jamMenunggu'  => round((float) $rows->sum(fn (WorkOrder $w) => $w->jamMenunggu()), 1),
                    'biaya'        => (float) $rows->sum(fn (WorkOrder $w) => $w->totalBiaya()),
                ];
            })
            ->sortByDesc('jamHenti')->values()->all();
    }

    /** Pengguna biasa tidak boleh menulis data ke perusahaan lain lewat payload. */
}
