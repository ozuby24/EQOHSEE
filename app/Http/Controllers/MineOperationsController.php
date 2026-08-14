<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, EnergyFuelLog, MineMapLayer, MineOperationalRecord, MineOperationalTarget};
use App\Support\{Alur, KelengkapanShift, PeringatanOperasi, Ramalan};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MineOperationsController extends Controller
{
    public const SHIFT = ['siang', 'malam'];
    public const TIPE_LAYER = ['pit', 'disposal', 'rom', 'stockpile', 'haul_road', 'area_kerja', 'drainase'];
    public const STATUS_LAYER = ['draft', 'aktif', 'arsip'];

    public function index(Request $request) { return $this->halaman($request, 'dashboard'); }
    public function data(Request $request) { return $this->halaman($request, 'data'); }
    public function target(Request $request) { return $this->halaman($request, 'target'); }
    public function gis(Request $request) { return $this->halaman($request, 'gis'); }

    public function simpanRecord(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'tanggal' => ['required', 'date'],
            'shift' => ['required', Rule::in(self::SHIFT)],
            'pit' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'material' => ['required', 'string', 'max:100'],
            'produksi_ton' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'overburden_bcm' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'jarak_angkut_km' => ['required', 'numeric', 'min:0', 'max:10000'],
            'jumlah_truk' => ['required', 'integer', 'min:0', 'max:10000'],
            'jumlah_excavator' => ['required', 'integer', 'min:0', 'max:1000'],
            'jam_operasi' => ['required', 'numeric', 'min:0', 'max:24'],
            'jam_delay' => ['required', 'numeric', 'min:0', 'max:24'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]));

        if ($data['jam_operasi'] + $data['jam_delay'] > 24) {
            return back()->withInput()->withErrors(['jam_delay' => 'Jam operasi dan delay tidak boleh melebihi 24 jam.']);
        }

        $data['user_id'] = auth()->id();
        $record = MineOperationalRecord::create($data);
        ActivityLog::write('Input operasi tambang', $record->tanggal->format('Y-m-d').' · '.$record->material, 'operasi');

        return back()->with('ok', 'Data operasi tambang tersimpan.');
    }

    public function hapusRecord(MineOperationalRecord $record)
    {
        if ($record->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Data yang sudah disetujui tidak dapat dihapus.']);
        }

        ActivityLog::write('Hapus operasi tambang', $record->tanggal->format('Y-m-d').' · '.$record->material, 'operasi');
        $record->delete();
        return back()->with('ok', 'Data operasi dihapus.');
    }

    /* ---------- alur tinjauan ---------- */

    public function ajukanRecord(MineOperationalRecord $record)
    {
        return $this->jalankan($record, fn () => $record->ajukan(),
            'Ajukan data operasi', 'Data diajukan untuk ditinjau.');
    }

    public function setujuiRecord(MineOperationalRecord $record)
    {
        return $this->jalankan($record, fn () => $record->setujui(),
            'Setujui data operasi', 'Data disetujui dan kini terhitung pada KPI.');
    }

    public function tolakRecord(Request $request, MineOperationalRecord $record)
    {
        $alasan = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:1000'],
        ])['alasan_tolak'];

        return $this->jalankan($record, fn () => $record->tolak($alasan),
            'Tolak data operasi', 'Data ditolak dan dikembalikan kepada pengaju.');
    }

    /**
     * Penolakan oleh alur dijawab sebagai galat pada bidang, bukan 403.
     *
     * Halaman ini dibuka lewat Inertia: 403 memunculkan layar galat dan
     * membuang isian yang sedang diketik, sementara pesan pada bidang
     * tampil di tempat pengguna sedang bekerja.
     */
    private function jalankan(MineOperationalRecord $record, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, $record->tanggal->format('Y-m-d').' · '.$record->material, 'operasi');

        return back()->with('ok', $pesan);
    }

    public function simpanTarget(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'target_produksi_ton' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'target_overburden_bcm' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'target_strip_ratio' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'target_jarak_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]));

        $data['user_id'] = auth()->id();
        MineOperationalTarget::updateOrCreate(
            ['company_id' => $data['company_id'] ?? null, 'tahun' => $data['tahun'], 'bulan' => $data['bulan']],
            $data
        );
        ActivityLog::write('Tetapkan target operasi', $data['bulan'].'/'.$data['tahun'], 'operasi');

        return back()->with('ok', 'Target operasi bulanan tersimpan.');
    }

    public function simpanLayer(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'nama' => ['required', 'string', 'max:150'],
            'tipe' => ['required', Rule::in(self::TIPE_LAYER)],
            'geojson' => ['required', 'json', 'max:5000000'],
            'warna' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'status' => ['required', Rule::in(self::STATUS_LAYER)],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]));

        $data['user_id'] = auth()->id();
        $layer = MineMapLayer::create($data);
        ActivityLog::write('Tambah layer peta', $layer->nama.' ('.$layer->tipe.')', 'operasi');

        return back()->with('ok', 'Layer GeoJSON tersimpan dan siap dipakai pada peta operasional.');
    }

    public function hapusLayer(MineMapLayer $layer)
    {
        ActivityLog::write('Hapus layer peta', $layer->nama.' ('.$layer->tipe.')', 'operasi');
        $layer->delete();
        return back()->with('ok', 'Layer peta dihapus.');
    }

    private function halaman(Request $request, string $mode)
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();
        if ($dari->greaterThan($sampai)) [$dari, $sampai] = [$sampai, $dari];

        $records = MineOperationalRecord::with('company')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->latest('tanggal')->latest('id')->get();

        // Daftar menampilkan semuanya supaya pengaju melihat drafnya
        // sendiri; hitungan hanya memakai yang sudah ditinjau. Angka yang
        // belum disetujui yang ikut terhitung tidak menimbulkan galat —
        // ia hanya membuat KPI dan laporan salah tanpa ada yang tahu.
        $sah = $records->whereIn('status', Alur::terhitung());
        $targets = MineOperationalTarget::with('company')
            ->whereRaw('(tahun * 100 + bulan) between ? and ?', [
                $dari->year * 100 + $dari->month,
                $sampai->year * 100 + $sampai->month,
            ])
            ->get();

        /* Kolom `geojson` bertipe longText dan boleh sampai lima juta karakter.
           Hanya halaman peta yang benar-benar membacanya; mode lain cukup
           metadata layer. Menariknya di setiap mode pernah membuat muatan
           Inertia satu halaman dasbor membengkak sampai puluhan megabita. */
        $layers = MineMapLayer::with('company')->latest()->get(
            $mode === 'gis' ? ['*'] : ['id', 'company_id', 'nama', 'tipe', 'warna', 'status', 'catatan', 'created_at']
        );

        $produksi = (float) $sah->sum('produksi_ton');
        $ob = (float) $sah->sum('overburden_bcm');
        $operasi = (float) $sah->sum('jam_operasi');
        $delay = (float) $sah->sum('jam_delay');
        $targetProduksi = (float) $targets->sum('target_produksi_ton');
        $targetOb = (float) $targets->sum('target_overburden_bcm');
        $stripRatio = $produksi > 0 ? $ob / $produksi : 0;
        $targetStrip = $targets->filter(fn ($x) => $x->target_strip_ratio !== null)->avg('target_strip_ratio');
        $jarak = $produksi > 0 ? $sah->sum(fn ($x) => $x->jarak_angkut_km * $x->produksi_ton) / $produksi : 0;
        $targetJarak = $targets->filter(fn ($x) => $x->target_jarak_km !== null)->avg('target_jarak_km');

        $ringkas = [
            'produksi' => $produksi,
            'ob' => $ob,
            'capaian_produksi' => $targetProduksi > 0 ? $produksi / $targetProduksi * 100 : 0,
            'strip_ratio' => $stripRatio,
            'capaian_ob' => $targetOb > 0 ? $ob / $targetOb * 100 : 0,
            'jarak_rata' => $jarak,
            'efisiensi_waktu' => ($operasi + $delay) > 0 ? $operasi / ($operasi + $delay) * 100 : 0,
            'delay_jam' => $delay,
            'hari_aktif' => $sah->pluck('tanggal')->unique()->count(),
            'jumlah_record' => $sah->count(),
            'layer_aktif' => $layers->where('status', 'aktif')->count(),
        ];

        $ramalan = new Ramalan($produksi, $targetProduksi, $dari->copy(), $sampai->copy());
        $kelengkapan = new KelengkapanShift($records, $dari->copy(), $sampai->copy(), self::SHIFT);
        $fuelBeda = $this->kenaikanFuelPerTon($dari, $sampai, $produksi);

        $alerts = PeringatanOperasi::susun(
            $ringkas,
            ['produksi' => $targetProduksi, 'ob' => $targetOb, 'strip_ratio' => $targetStrip, 'jarak' => $targetJarak],
            $ramalan,
            $kelengkapan,
            $fuelBeda,
        );

        $perPit = $sah->groupBy(fn ($x) => $x->pit ?: ($x->area ?: 'Belum ditentukan'))->map(function ($rows, $nama) {
            $ton = (float) $rows->sum('produksi_ton');
            $ob = (float) $rows->sum('overburden_bcm');
            $operasi = (float) $rows->sum('jam_operasi');
            $delay = (float) $rows->sum('jam_delay');
            return ['nama' => $nama, 'produksi' => $ton, 'ob' => $ob, 'strip_ratio' => $ton > 0 ? $ob / $ton : 0, 'delay_persen' => ($operasi + $delay) > 0 ? $delay / ($operasi + $delay) * 100 : 0, 'record' => $rows->count()];
        })->sortByDesc('produksi')->values()->all();

        $tanggal = $sah->pluck('tanggal')->map(fn ($x) => Carbon::parse($x)->toDateString())->unique()->sort()->values();
        $tren = $tanggal->map(function (string $date) use ($sah) {
            $rows = $sah->filter(fn ($x) => $x->tanggal->toDateString() === $date);
            return ['tanggal' => $date, 'produksi' => (float) $rows->sum('produksi_ton'), 'ob' => (float) $rows->sum('overburden_bcm'), 'delay' => (float) $rows->sum('jam_delay')];
        })->all();

        $companies = Company::query()->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Operasi/Halaman', [
            'mode' => $mode, 'dari' => $dari, 'sampai' => $sampai, 'ringkas' => $ringkas,
            'target' => ['produksi' => $targetProduksi, 'ob' => $targetOb, 'strip_ratio' => $targetStrip, 'jarak' => $targetJarak],
            'records' => $records->map(fn (MineOperationalRecord $x) => $this->recordView($x))->values(),
            'targets' => $targets->sortByDesc(fn ($x) => $x->tahun * 100 + $x->bulan)
                ->map(fn (MineOperationalTarget $x) => $this->targetView($x))->values(),
            'layers' => $layers->map(fn (MineMapLayer $x) => $this->layerView($x, $mode === 'gis'))->values(),
            'perPit' => $perPit, 'tren' => $tren, 'alerts' => $alerts, 'companies' => $companies,
            'ramalan' => $ramalan->toArray(), 'kelengkapan' => $kelengkapan->toArray(),
            'fuelPerTonBeda' => $fuelBeda,
            'opsi' => ['shift' => self::SHIFT, 'statusRecord' => Alur::LABEL, 'tipeLayer' => self::TIPE_LAYER, 'statusLayer' => self::STATUS_LAYER],
            /*
             * Tautan yang memerlukan id memakai penanda __ID__, bukan angka
             * 0 yang lalu disambung di sisi browser. Cara lama menghasilkan
             * /records/0/5 — id-nya menempel di belakang nol, bukan
             * menggantikannya — sehingga tombol hapus tidak pernah bekerja
             * sejak awal. Kegagalannya luput dari pengujian karena tes
             * menembak rute backend langsung dan tidak pernah memakai
             * tautan yang benar-benar dibangun halaman.
             */
            'tautan' => [
                'dashboard' => route('operasi.index'), 'data' => route('operasi.data'),
                'target' => route('operasi.target'), 'gis' => route('operasi.gis'),

                'recordSimpan'  => route('operasi.record.simpan'),
                'recordHapus'   => route('operasi.record.hapus',   ['record' => '__ID__']),
                'recordAjukan'  => route('operasi.record.ajukan',  ['record' => '__ID__']),
                'recordSetujui' => route('operasi.record.setujui', ['record' => '__ID__']),
                'recordTolak'   => route('operasi.record.tolak',   ['record' => '__ID__']),

                'targetSimpan' => route('operasi.target.simpan'),
                'layerSimpan'  => route('operasi.layer.simpan'),
                'layerHapus'   => route('operasi.layer.hapus', ['layer' => '__ID__']),
            ],
        ]);
    }

    private function pemilik(array $data): array
    {
        if (!auth()->user()?->isAdmin()) $data['company_id'] = auth()->user()?->company_id;
        return $data;
    }

    /* Muatan halaman disusun kolom demi kolom, bukan lewat toArray(). Model
       yang dikirim utuh ikut membawa user_id dan stempel waktu ke browser,
       dan setiap kolom baru yang ditambahkan nanti akan ikut terbawa tanpa
       ada yang memutuskannya. */

    /**
     * Kenaikan liter bahan bakar per ton terhadap periode sebelumnya.
     *
     * Ditarik dari modul Energi, bukan dari data operasi, sebab di sanalah
     * liter tercatat. Perbandingannya memakai periode sebelumnya yang
     * panjangnya sama persis — membandingkan bulan berjalan yang baru
     * sepuluh hari dengan bulan penuh sebelumnya akan selalu menunjukkan
     * penurunan, dan peringatan yang selalu diam sama tidak bergunanya
     * dengan yang selalu menyala.
     *
     * Mengembalikan null bila salah satu periode tidak punya cukup data;
     * angka nol akan terbaca sebagai "tidak ada kenaikan", padahal yang
     * benar adalah "belum dapat dibandingkan".
     */
    private function kenaikanFuelPerTon(Carbon $dari, Carbon $sampai, float $produksiKini): ?float
    {
        if ($produksiKini <= 0) return null;

        $panjang = $dari->diffInDays($sampai) + 1;
        $dariLalu = $dari->copy()->subDays($panjang);
        $sampaiLalu = $dari->copy()->subDay();

        $liter = fn (Carbon $a, Carbon $b) => (float) EnergyFuelLog::query()
            ->whereBetween('tanggal', [$a, $b])->sum('liter');

        $literKini = $liter($dari, $sampai);
        $literLalu = $liter($dariLalu, $sampaiLalu);

        if ($literKini <= 0 || $literLalu <= 0) return null;

        $produksiLalu = (float) MineOperationalRecord::query()
            ->whereIn('status', Alur::terhitung())
            ->whereBetween('tanggal', [$dariLalu, $sampaiLalu])
            ->sum('produksi_ton');

        if ($produksiLalu <= 0) return null;

        $kini = $literKini / $produksiKini;
        $lalu = $literLalu / $produksiLalu;

        return $lalu > 0 ? ($kini - $lalu) / $lalu * 100 : null;
    }

    private function recordView(MineOperationalRecord $x): array
    {
        return [
            'id' => $x->id,
            'tanggal' => $x->tanggal?->toDateString(),
            'tanggalLabel' => $x->tanggal?->format('d M Y'),
            'shift' => $x->shift,
            'pit' => $x->pit,
            'area' => $x->area,
            'material' => $x->material,
            'produksi_ton' => $x->produksi_ton,
            'overburden_bcm' => $x->overburden_bcm,
            'jarak_angkut_km' => $x->jarak_angkut_km,
            'jumlah_truk' => $x->jumlah_truk,
            'jumlah_excavator' => $x->jumlah_excavator,
            'jam_operasi' => $x->jam_operasi,
            'jam_delay' => $x->jam_delay,
            'status' => $x->status,
            'statusLabel' => Alur::LABEL[$x->status] ?? $x->status,
            'catatan' => $x->catatan,
            'companyName' => $x->company?->name,

            // Keadaan alur ikut dikirim supaya antarmuka tidak menyusun
            // ulang aturannya sendiri; aturan yang ditulis dua kali akan
            // berbeda cepat atau lambat.
            'alur' => [
                'dapatDiubah'   => $x->dapatDiubah(),
                'dapatDiajukan' => $x->dapatDiubah(),
                'dapatDitinjau' => $x->dapatDitinjauOleh(auth()->user()),
                'pengaju'       => $x->pengaju?->name,
                'diajukanPada'  => $x->diajukan_pada?->format('Y-m-d H:i'),
                'peninjau'      => $x->peninjau?->name,
                'ditinjauPada'  => $x->ditinjau_pada?->format('Y-m-d H:i'),
                'alasanTolak'   => $x->alasan_tolak,
            ],
        ];
    }

    private function targetView(MineOperationalTarget $x): array
    {
        return [
            'id' => $x->id,
            'tahun' => $x->tahun,
            'bulan' => $x->bulan,
            'target_produksi_ton' => $x->target_produksi_ton,
            'target_overburden_bcm' => $x->target_overburden_bcm,
            'target_strip_ratio' => $x->target_strip_ratio,
            'target_jarak_km' => $x->target_jarak_km,
            'catatan' => $x->catatan,
            'companyName' => $x->company?->name,
        ];
    }

    private function layerView(MineMapLayer $x, bool $denganGeojson): array
    {
        return [
            'id' => $x->id,
            'nama' => $x->nama,
            'tipe' => $x->tipe,
            'warna' => $x->warna,
            'status' => $x->status,
            'catatan' => $x->catatan,
            'companyName' => $x->company?->name,
            'geojson' => $denganGeojson ? $x->geojson : null,
        ];
    }
}
