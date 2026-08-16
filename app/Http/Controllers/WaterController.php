<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, KoObject, TindakLanjut, WaterLog, WaterSump, WaterSumpPump};
use App\Support\{Alur, KelengkapanShift, KopDokumen, NeracaAir, PeringatanAir};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pengelolaan Air dan Penirisan Tambang.
 *
 * Pompanya memakai registri Keselamatan Operasi, sama seperti modul
 * Pemeliharaan; yang didaftarkan di sini adalah kolamnya beserta daerah
 * tangkapan air yang mengalir ke sana. Tanpa luas tangkapan dan
 * koefisien limpasan, hujan tidak dapat diterjemahkan menjadi meter
 * kubik, dan seluruh perkiraan luapan mustahil dihitung.
 */
class WaterController extends Controller
{
    /** Kejadian hujan yang dipakai sebagai acuan perkiraan. */
    private const HUJAN_RENCANA_MM = 50.0;

    public function index(Request $r)   { return $this->halaman($r, 'dashboard'); }
    public function catatan(Request $r) { return $this->halaman($r, 'catatan'); }
    public function kolam(Request $r)   { return $this->halaman($r, 'kolam'); }

    /* ---------- kolam ---------- */

    public function simpanKolam(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'           => ['nullable', 'exists:companies,id'],
            'kode'                 => ['required', 'string', 'max:40'],
            'nama'                 => ['required', 'string', 'max:150'],
            'jenis'                => ['required', Rule::in(WaterSump::JENIS)],
            'lokasi'               => ['nullable', 'string', 'max:150'],
            'kapasitas_m3'         => ['required', 'numeric', 'min:0', 'max:100000000'],
            'luas_tangkapan_ha'    => ['required', 'numeric', 'min:0', 'max:1000000'],
            'koefisien_limpasan'   => ['required', 'numeric', 'min:0.01', 'max:1'],
            'elevasi_luapan_m'     => ['nullable', 'numeric', 'min:-1000', 'max:10000'],
            'status'               => ['required', Rule::in(WaterSump::STATUS)],
            'pembersihan_terakhir' => ['nullable', 'date'],
            'interval_bersih_hari' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'catatan'              => ['nullable', 'string', 'max:2000'],
        ]));

        $data['user_id'] = auth()->id();
        $sump = WaterSump::create($data);
        ActivityLog::write('Daftarkan kolam penirisan', $sump->kode.' — '.$sump->nama, 'air');

        return back()->with('ok', 'Kolam tersimpan.');
    }

    public function simpanPompa(Request $request, WaterSump $sump)
    {
        $data = $request->validate([
            'ko_object_id'     => ['nullable', 'exists:ko_objects,id'],
            'nama'             => ['nullable', 'string', 'max:150'],
            'kapasitas_m3_jam' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'status'           => ['required', Rule::in(WaterSumpPump::STATUS)],
        ]);

        if (empty($data['ko_object_id']) && empty($data['nama'])) {
            return back()->withErrors(['nama' => 'Pilih alat dari registri, atau isi nama pompanya.']);
        }

        $sump->pumps()->create($data);

        return back()->with('ok', 'Pompa ditetapkan pada kolam ini.');
    }

    public function ubahPompa(Request $request, WaterSumpPump $pompa)
    {
        $pompa->update($request->validate([
            'status' => ['required', Rule::in(WaterSumpPump::STATUS)],
        ]));

        return back()->with('ok', 'Status pompa diperbarui.');
    }

    public function hapusKolam(WaterSump $sump)
    {
        ActivityLog::write('Hapus kolam penirisan', $sump->kode, 'air');
        $sump->delete();

        return back()->with('ok', 'Kolam dihapus.');
    }

    /* ---------- catatan harian ---------- */

    public function simpanCatatan(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'      => ['nullable', 'exists:companies,id'],
            'water_sump_id'   => ['required', 'exists:water_sumps,id'],
            'tanggal'         => ['required', 'date'],
            'curah_hujan_mm'  => ['required', 'numeric', 'min:0', 'max:2000'],
            'level_m'         => ['nullable', 'numeric', 'min:-1000', 'max:10000'],
            'volume_m3'       => ['required', 'numeric', 'min:0', 'max:100000000'],
            'debit_masuk_m3'  => ['required', 'numeric', 'min:0', 'max:100000000'],
            'debit_keluar_m3' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'jam_pompa'       => ['required', 'numeric', 'min:0', 'max:24'],
            'energi_kwh'      => ['nullable', 'numeric', 'min:0', 'max:10000000'],

            // Dibiarkan kosong pada hari yang tidak diambil sampelnya.
            'ph'      => ['nullable', 'numeric', 'min:0', 'max:14'],
            'tss_mgl' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'fe_mgl'  => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'mn_mgl'  => ['nullable', 'numeric', 'min:0', 'max:10000'],

            'catatan' => ['nullable', 'string', 'max:2000'],
        ]));

        $data['user_id'] = auth()->id();

        // Satu kolam satu catatan per hari; menyimpan yang kedua akan
        // menggandakan debit dan curah hujan pada seluruh hitungan.
        $ada = WaterLog::where('water_sump_id', $data['water_sump_id'])
            ->whereDate('tanggal', $data['tanggal'])->first();

        if ($ada) {
            return back()->withErrors([
                'tanggal' => 'Catatan untuk kolam dan tanggal ini sudah ada. Ubah catatan yang sudah tersimpan.',
            ]);
        }

        $log = WaterLog::create($data);
        ActivityLog::write('Catat penirisan harian', $log->sump?->kode.' · '.$log->tanggal->format('Y-m-d'), 'air');

        return back()->with('ok', 'Catatan harian tersimpan sebagai draf.');
    }

    public function hapusCatatan(WaterLog $catatan)
    {
        if ($catatan->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Catatan yang sudah disetujui tidak dapat dihapus.']);
        }

        ActivityLog::write('Hapus catatan penirisan', $catatan->tanggal->format('Y-m-d'), 'air');
        $catatan->delete();

        return back()->with('ok', 'Catatan dihapus.');
    }

    /* ---------- alur tinjauan ---------- */

    public function ajukan(WaterLog $catatan)  { return $this->jalankan($catatan, fn () => $catatan->ajukan(), 'Ajukan catatan penirisan', 'Catatan diajukan untuk ditinjau.'); }
    public function setujui(WaterLog $catatan) { return $this->jalankan($catatan, fn () => $catatan->setujui(), 'Setujui catatan penirisan', 'Catatan disetujui dan kini masuk laporan.'); }

    public function tolak(Request $request, WaterLog $catatan)
    {
        $alasan = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:1000'],
        ])['alasan_tolak'];

        return $this->jalankan($catatan, fn () => $catatan->tolak($alasan),
            'Tolak catatan penirisan', 'Catatan ditolak dan dikembalikan kepada pengaju.');
    }

    private function jalankan(WaterLog $log, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, $log->sump?->kode.' · '.$log->tanggal?->format('Y-m-d'), 'air');

        return back()->with('ok', $pesan);
    }

    /* ---------- tindak lanjut ---------- */

    public function simpanTindakLanjut(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'       => ['nullable', 'exists:companies,id'],
            'kode_pemicu'      => ['nullable', 'string', 'max:60'],
            'judul'            => ['required', 'string', 'max:200'],
            'prioritas'        => ['required', Rule::in(TindakLanjut::PRIORITAS)],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'target_selesai'   => ['nullable', 'date'],
            'uraian'           => ['nullable', 'string', 'max:3000'],
        ]));

        $data['modul'] = 'air';
        $data['user_id'] = auth()->id();

        TindakLanjut::create($data);
        ActivityLog::write('Tambah tindak lanjut penirisan', $data['judul'], 'air');

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

    /* ---------- laporan ---------- */

    public function cetak(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $sumps = WaterSump::with(['pumps.objek', 'logs'])->where('status', 'aktif')->orderBy('kode')->get();
        $semua = WaterLog::with('sump')->whereBetween('tanggal', [$dari, $sampai])->orderBy('tanggal')->get();
        $sah = $semua->whereIn('status', Alur::terhitung());

        $perusahaan = auth()->user()?->company ?: Company::first();

        return Inertia::render('Print/Air', [
            'dok'    => KopDokumen::untuk('laporan-air', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'dasar' => [
                'catatan'        => $semua->count(),
                'disetujui'      => $sah->count(),
                'belumDitinjau'  => $semua->count() - $sah->count(),
                'kolam'          => $sumps->count(),
            ],

            'ringkas'  => $this->ringkas($sah, $sumps),
            'kolam'    => $sumps->map(fn (WaterSump $s) => $s->toView())->values(),
            'mutu'     => $this->mutuAir($sah),
            'catatan'  => $sah->map(fn (WaterLog $l) => $l->toView())->values(),
            'tindak'   => TindakLanjut::modul('air')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('air.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        [$dari, $sampai] = $this->rentang($request);

        $sumps = WaterSump::with(['pumps.objek', 'logs'])->orderBy('kode')->get();
        $aktif = $sumps->where('status', 'aktif');

        $semua = WaterLog::with('sump')->whereBetween('tanggal', [$dari, $sampai])
            ->orderByDesc('tanggal')->get();
        $sah = $semua->whereIn('status', Alur::terhitung());

        // Satu catatan per kolam per hari; kelengkapannya diukur dengan
        // alat yang sama seperti shift produksi, dengan "shift" berupa
        // daftar kode kolam aktif.
        $kelengkapan = (new KelengkapanShift(
            $semua->map(fn (WaterLog $l) => (object) [
                'tanggal' => $l->tanggal, 'shift' => (string) $l->water_sump_id, 'status' => $l->status,
            ]),
            $dari->copy(), $sampai->copy(),
            $aktif->pluck('id')->map(fn ($i) => (string) $i)->all() ?: ['0'],
        ))->toArray();

        $tindak = TindakLanjut::with('sumber')->modul('air')->urutMendesak()->get();

        return Inertia::render('Air/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas'     => $this->ringkas($sah, $aktif),
            'kelengkapan' => $kelengkapan,
            'alerts'      => PeringatanAir::susun($aktif, $sah, $kelengkapan),
            'mutu'        => $this->mutuAir($sah),

            'kolam'   => $sumps->map(fn (WaterSump $s) => $s->toView() + [
                'pumps' => $s->pumps->map(fn (WaterSumpPump $p) => [
                    'id' => $p->id, 'label' => $p->label(),
                    'kapasitas' => $p->kapasitas_m3_jam, 'status' => $p->status,
                ])->values(),
                'simulasi' => $this->simulasi($s),
            ])->values(),

            'catatan' => $semua->map(fn (WaterLog $l) => $l->toView())->values(),
            'tindak'  => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'objekOpsi' => KoObject::query()->orderBy('kode')->get()
                ->map(fn (KoObject $o) => ['id' => $o->id, 'kode' => $o->kode, 'nama' => $o->nama])->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'jenis' => WaterSump::JENIS, 'statusKolam' => WaterSump::STATUS,
                'statusPompa' => WaterSumpPump::STATUS,
                'statusTindak' => TindakLanjut::STATUS, 'prioritasTindak' => TindakLanjut::PRIORITAS,
                'hujanRencana' => self::HUJAN_RENCANA_MM,
            ],

            'tautan' => [
                'dashboard' => route('air.index'), 'catatan' => route('air.catatan'), 'kolam' => route('air.kolam'),
                'cetak'     => route('air.cetak'),
                'kolamSimpan'   => route('air.kolam.simpan'),
                'kolamHapus'    => route('air.kolam.hapus', ['sump' => '__ID__']),
                'pompaSimpan'   => route('air.pompa.simpan', ['sump' => '__ID__']),
                'pompaUbah'     => route('air.pompa.ubah', ['pompa' => '__ID__']),
                'catatanSimpan' => route('air.catatan.simpan'),
                'catatanHapus'  => route('air.catatan.hapus', ['catatan' => '__ID__']),
                'ajukan'        => route('air.ajukan', ['catatan' => '__ID__']),
                'setujui'       => route('air.setujui', ['catatan' => '__ID__']),
                'tolak'         => route('air.tolak', ['catatan' => '__ID__']),
                'tindakSimpan'  => route('air.tindak.simpan'),
                'tindakUbah'    => route('air.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    /**
     * Perkiraan yang dapat langsung dibaca di lapangan.
     *
     * Dinyatakan terhadap kejadian hujan acuan, dan memakai kapasitas
     * pompa yang benar-benar siap — bukan yang terpasang. Memakai yang
     * terpasang membuat kolam yang separuh pompanya rusak tampak aman.
     */
    private function simulasi(WaterSump $s): array
    {
        $n = $s->neraca();
        $siap = $s->kapasitasPompaSiap();
        $limpasan = $n->limpasan(self::HUJAN_RENCANA_MM);

        // Hujan acuan dianggap turun merata selama enam jam; laju
        // masuknya itulah yang dilawan pompa.
        $masukPerJam = $limpasan / 6;

        return [
            'hujanRencana'    => self::HUJAN_RENCANA_MM,
            'limpasan'        => round($limpasan, 2),
            'hujanTertampung' => $n->hujanTertampungMm(),
            'akanLimpah'      => $n->akanLimpah(self::HUJAN_RENCANA_MM),
            'jamSampaiLimpah' => $n->jamSampaiLimpah($masukPerJam, $siap),
            'jamSampaiKosong' => $n->jamSampaiKosong(0, $siap),
            'pompaDibutuhkan' => $n->pompaDibutuhkan(self::HUJAN_RENCANA_MM, 6),
            'pompaSiap'       => $siap,
        ];
    }

    /** @param Collection<int,WaterLog> $sah */
    private function ringkas(Collection $sah, Collection $sumps): array
    {
        $keluar = (float) $sah->sum('debit_keluar_m3');
        $jam = (float) $sah->sum('jam_pompa');
        $energi = (float) $sah->sum('energi_kwh');

        return [
            'hujanTotal'   => round((float) $sah->sum('curah_hujan_mm'), 1),
            'hujanMaks'    => round((float) $sah->max('curah_hujan_mm'), 1),
            'masuk'        => round((float) $sah->sum('debit_masuk_m3'), 2),
            'keluar'       => round($keluar, 2),
            'jamPompa'     => round($jam, 1),
            'debitPerJam'  => $jam > 0 ? round($keluar / $jam, 2) : 0.0,
            'energiPerM3'  => $keluar > 0 ? round($energi / $keluar, 4) : 0.0,
            'kolamAktif'   => $sumps->count(),
            'pompaRusak'   => (int) $sumps->sum(fn (WaterSump $s) => $s->pompaRusak()),
            'catatan'      => $sah->count(),
        ];
    }

    /** @param Collection<int,WaterLog> $sah */
    private function mutuAir(Collection $sah): array
    {
        $sampel = $sah->filter(fn (WaterLog $l) => $l->adaSampelAir());

        $rata = fn (string $k) => $sampel->whereNotNull($k)->isEmpty()
            ? null : round((float) $sampel->whereNotNull($k)->avg($k), 3);

        return [
            'sampel' => $sampel->count(),
            'ph'     => $rata('ph'),
            'tss'    => $rata('tss_mgl'),
            'fe'     => $rata('fe_mgl'),
            'mn'     => $rata('mn_mgl'),
        ];
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $request): array
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();

        return $dari->greaterThan($sampai) ? [$sampai, $dari] : [$dari, $sampai];
    }

}
