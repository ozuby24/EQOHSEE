<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, EnergyBaseline, EnergyEquipment, EnergyFuelLog,
                EnergyFuelRecon, EnergyOpportunity, EnergyOtherLog, EnergyPowerLog, EnergyProduction};
use App\Models\Energi\{Periode, Site};
use App\Rules\DalamPerusahaan;
use App\Support\{Energi, KopDokumen};
use App\Support\Energi\{Katalog, RegistriFaktor};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Energy Performance Center.
 *
 * Catatan harian disimpan mentah; seluruh angka di halaman ini diturunkan
 * saat dibaca. Itu membuat satu perubahan faktor emisi atau harga bahan
 * bakar langsung tercermin di seluruh riwayat, tanpa perlu menghitung
 * ulang data lama.
 */
class EnergyController extends Controller
{
    /* ================= dashboard ================= */

    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $r = $this->ringkasan($dari, $sampai);
        $b = $this->baselineBerlaku();

        return Inertia::render('Energi/Halaman', [
            'mode'     => 'index',
            'r'        => $r,
            'baseline' => $b,
            'tren'     => $this->trenHarian($dari, $sampai),
            'dari'     => $dari,
            'sampai'   => $sampai,
            'teratas'  => $this->peringkatUnit($dari, $sampai, 5),
            'peluang'  => EnergyOpportunity::whereIn('status', ['disetujui','berjalan'])
                            ->orderByDesc('hemat_liter')->limit(4)->get(),
        ]);
    }

    /* ================= input lapangan ================= */

    public function input(Request $request)
    {
        $lain = [];
        foreach (Katalog::LAIN as $k) $lain[$k] = ['label' => Katalog::label($k), 'satuan' => Katalog::satuan($k)];

        return Inertia::render('Energi/Halaman', [
            'mode' => 'input',
            'units' => EnergyEquipment::where('aktif', true)->orderBy('kode')->get(['id', 'kode', 'nama', 'kategori']),
            'areas' => Energi::AREA,
            'sources' => Energi::SUMBER_LISTRIK,
            'sites' => Site::where('aktif', true)->orderBy('nama')->get(['id', 'kode', 'nama']),
            'lain' => $lain,
            'sumberData' => EnergyFuelLog::$SUMBER_DATA,
            /* Campuran FAME nasional yang berlaku hari ini — bawaan isian;
               penginput menimpanya hanya bila nota pengiriman menyebut lain. */
            'fameBawaan' => RegistriFaktor::nilai('solar', 'persen_fame', now(), ['company_id' => $request->user()->company_id]),
            'boleh' => ['input' => $request->user()->can('energi.input')],
            'recent' => [
                'production' => EnergyProduction::latest('tanggal')->limit(8)->get(),
                'fuel' => EnergyFuelLog::with('equipment')->latest('tanggal')->limit(8)->get(),
                'power' => EnergyPowerLog::latest('tanggal')->limit(8)->get(),
                'recon' => EnergyFuelRecon::latest('tanggal')->limit(8)->get(),
                'other' => EnergyOtherLog::latest('tanggal')->limit(8)->get(),
            ],
            'tautan' => [
                'production' => route('energi.input.production'),
                'fuel' => route('energi.input.fuel'),
                'power' => route('energi.input.power'),
                'recon' => route('energi.input.recon'),
                'other' => route('energi.input.other'),
            ],
        ]);
    }

    public function simpanProduksi(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'ton' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'bcm' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'stripping_ratio' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'jarak_angkut_km' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'sumber_data' => ['nullable', Rule::in(array_keys(EnergyProduction::$SUMBER_DATA))],
        ]);

        if ($galat = $this->periodeTerkunci($data['tanggal'], $request->user()->company_id)) return $galat;

        EnergyProduction::updateOrCreate(['tanggal' => $data['tanggal']], $data + $this->diajukan());

        return back()->with('ok', 'Produksi harian tersimpan dan langsung masuk ke KPI energi.');
    }

    public function simpanFuel(Request $request)
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'exists:energy_equipment,id'],
            'tanggal' => ['required', 'date'],
            'hm' => ['required', 'numeric', 'min:0', 'max:10000'],
            'liter' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'idle_jam' => ['required', 'numeric', 'min:0', 'max:10000'],
            'jarak_km' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ton' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'bcm' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'cycle_menit' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'persen_fame' => ['nullable', 'numeric', 'between:0,100'],
            'sumber_data' => ['nullable', Rule::in(array_keys(EnergyFuelLog::$SUMBER_DATA))],
        ]);

        // Model unit membawa scope perusahaan; ID lintas perusahaan berhenti di sini.
        $unit = EnergyEquipment::findOrFail($data['equipment_id']);
        if ($galat = $this->periodeTerkunci($data['tanggal'], $unit->company_id)) return $galat;

        $log = EnergyFuelLog::updateOrCreate(
            ['equipment_id' => $unit->id, 'tanggal' => $data['tanggal']],
            $data + $this->diajukan()
        );

        ActivityLog::write('Input bahan bakar alat', $unit->kode.' - '.$log->tanggal->format('Y-m-d'), 'energi');

        return back()->with('ok', 'Catatan bahan bakar alat tersimpan.');
    }

    public function simpanListrik(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'area' => ['required', Rule::in(array_keys(Energi::AREA))],
            'sumber' => ['required', Rule::in(array_keys(Energi::SUMBER_LISTRIK))],
            'kwh' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'puncak_kw' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'jam_operasi' => ['required', 'numeric', 'min:0', 'max:24'],
            'liter_genset' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'site_id' => ['nullable', new DalamPerusahaan('energi_sites')],
            'sumber_data' => ['nullable', Rule::in(array_keys(EnergyPowerLog::$SUMBER_DATA))],
        ]);

        if ($galat = $this->periodeTerkunci($data['tanggal'], $request->user()->company_id)) return $galat;

        EnergyPowerLog::updateOrCreate(
            ['tanggal' => $data['tanggal'], 'area' => $data['area'], 'sumber' => $data['sumber']],
            $data + $this->diajukan()
        );

        return back()->with('ok', 'Catatan listrik tersimpan.');
    }

    public function simpanRekonsiliasi(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'stok_awal_liter' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'disalurkan_liter' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'stok_akhir_liter' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['stok_awal_liter'] + $data['disalurkan_liter'] < $data['stok_akhir_liter']) {
            return back()->withInput()->withErrors(['stok_akhir_liter' => 'Stok akhir tidak boleh melebihi stok awal ditambah penyaluran.']);
        }

        if ($galat = $this->periodeTerkunci($data['tanggal'], $request->user()->company_id)) return $galat;

        EnergyFuelRecon::updateOrCreate(['tanggal' => $data['tanggal']], $data + $this->diajukan());

        return back()->with('ok', 'Rekonsiliasi bahan bakar tersimpan.');
    }

    /**
     * Gas & energi lain (cacat D3 — sebelumnya tidak ada formulirnya):
     * bensin, LPG, gas alam, bahan peledak, refrigeran, SF₆.
     */
    public function simpanLain(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'site_id' => ['nullable', new DalamPerusahaan('energi_sites')],
            'jenis' => ['required', Rule::in(Katalog::LAIN)],
            'jumlah' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'sumber_data' => ['required', Rule::in(array_keys(EnergyOtherLog::$SUMBER_DATA))],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        if ($galat = $this->periodeTerkunci($data['tanggal'], $request->user()->company_id)) return $galat;

        $data['satuan'] = Katalog::satuan($data['jenis']);
        EnergyOtherLog::create($this->pemilik($data) + $this->diajukan());

        return back()->with('ok', Katalog::label($data['jenis']).' tercatat.');
    }

    /** Status baris yang baru disimpan atau dibetulkan sesudah ditolak. */
    private function diajukan(): array
    {
        return ['status' => 'diajukan', 'alasan_tolak' => null];
    }

    /** Tolak penyimpanan ke periode terkunci dengan pesan yang dapat dibaca, bukan 423 mentah. */
    private function periodeTerkunci(string $tanggal, ?int $companyId)
    {
        if (!Periode::terkunciPada($companyId, $tanggal)) return null;

        return back()->withInput()->withErrors(['tanggal' => 'Periode '.Carbon::parse($tanggal)->translatedFormat('F Y')
            .' sudah terkunci. Perubahan hanya lewat pengajuan koreksi di halaman Verifikasi & Periode.']);
    }

    /* ================= konsumsi energi ================= */

    public function konsumsi(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $r = $this->ringkasan($dari, $sampai);

        return Inertia::render('Energi/Halaman', [
            'mode' => 'konsumsi',
            'r' => $r, 'dari' => $dari, 'sampai' => $sampai,
            'baseline' => $this->baselineBerlaku(),
            'tren'     => $this->trenHarian($dari, $sampai),
        ]);
    }

    /* ================= bahan bakar ================= */

    public function fuel(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $recon = EnergyFuelRecon::whereBetween('tanggal', [$dari, $sampai])->get();

        /* Selisih dihitung atas tanggal yang SAMA dengan rekonsiliasinya —
           sama persis dengan kartu rekonsiliasi di Control Tower. */
        $r = \App\Support\Energi\KendaliDireksi::rekonsiliasi($dari, $sampai, []);
        $tercatat = $r['tercatat'];

        $disalurkan = $recon->sum(fn ($x) => $x->terpakaiMenurutStok());

        return Inertia::render('Energi/Halaman', [
            'mode'      => 'fuel',
            'dari' => $dari, 'sampai' => $sampai,
            'r'        => $this->ringkasan($dari, $sampai),
            'peringkat'=> $this->peringkatUnit($dari, $sampai, 10),
            'perKategori' => $this->perKategori($dari, $sampai),
            'recon'    => [
                'disalurkan' => $disalurkan,
                'tercatat'   => $tercatat,
                'selisih'    => $disalurkan - $tercatat,
                'persen'     => Energi::selisihRekonsiliasi($disalurkan, $tercatat),
                'baris'      => $recon,
            ],
        ]);
    }

    /* ================= listrik ================= */

    public function listrik(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $log = EnergyPowerLog::whereBetween('tanggal', [$dari, $sampai])->get();
        $ton = (float) EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->sum('ton');

        $perArea = [];
        foreach (Energi::AREA as $kode => $nama) {
            $baris = $log->where('area', $kode);
            if ($baris->isEmpty()) continue;

            $kwh = (float) $baris->sum('kwh');
            $perArea[$kode] = [
                'nama'    => $nama,
                'kwh'     => $kwh,
                'gj'      => Energi::kwhKeGj($kwh),
                'rupiah'  => Energi::kwhKeRp($kwh),
                'puncak'  => (float) $baris->max('puncak_kw'),
                'jam'     => (float) $baris->sum('jam_operasi'),
                'faktor'  => Energi::faktorBeban($kwh, (float) $baris->max('puncak_kw'), (float) $baris->sum('jam_operasi')),
            ];
        }
        uasort($perArea, fn ($a, $b) => $b['kwh'] <=> $a['kwh']);

        $genset = $log->where('sumber', 'genset');
        $pln    = $log->where('sumber', 'pln');
        $kwhTot = (float) $log->sum('kwh');

        return Inertia::render('Energi/Halaman', [
            'mode' => 'listrik',
            'dari' => $dari, 'sampai' => $sampai,
            'perArea' => $perArea,
            'total'   => [
                'kwh'    => $kwhTot,
                'gj'     => Energi::kwhKeGj($kwhTot),
                'rupiah' => Energi::kwhKeRp($kwhTot),
                'tco2e'  => Energi::kwhKeCo2($kwhTot),
                'perTon' => $ton > 0 ? $kwhTot / $ton : 0.0,
            ],
            'pln'    => ['kwh' => (float) $pln->sum('kwh')],
            'genset' => [
                'kwh'       => (float) $genset->sum('kwh'),
                'liter'     => (float) $genset->sum('liter_genset'),
                'efisiensi' => Energi::efisiensiGenset((float) $genset->sum('kwh'), (float) $genset->sum('liter_genset')),
                'jam'       => (float) $genset->sum('jam_operasi'),
            ],
        ]);
    }

    /* ================= kinerja alat ================= */

    public function equipment(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $kategori = $request->get('kategori');

        $peringkat = collect($this->peringkatUnit($dari, $sampai, 200))
            ->when($kategori, fn ($c) => $c->where('kategori', $kategori))
            ->values();

        return Inertia::render('Energi/Halaman', [
            'mode'      => 'equipment',
            'dari' => $dari, 'sampai' => $sampai,
            'kategori'  => $kategori,
            'peringkat' => $peringkat,
            'perKategori' => $this->perKategori($dari, $sampai),
        ]);
    }

    public function equipmentShow(Request $request, EnergyEquipment $unit)
    {
        [$dari, $sampai] = $this->rentang($request);

        $log = $unit->fuelLogs()->whereBetween('tanggal', [$dari, $sampai])->orderBy('tanggal')->get();

        $hm    = (float) $log->sum('hm');
        $liter = (float) $log->sum('liter');
        $ton   = (float) $log->sum('ton');
        $bcm   = (float) $log->sum('bcm');
        $idle  = (float) $log->sum('idle_jam');
        $jarak = (float) $log->sum('jarak_km');

        $acuan = $this->acuanKategori($unit->kategori, $dari, $sampai);
        $lpj   = Energi::rasio($liter, $hm);

        return Inertia::render('Energi/Halaman', [
            'mode' => 'equipment-show',
            'unit' => $unit, 'log' => $log, 'dari' => $dari, 'sampai' => $sampai,
            'acuan' => $acuan,
            'status' => Energi::statusEfisiensi($lpj, $acuan),
            'm' => [
                'hm' => $hm, 'liter' => $liter, 'ton' => $ton, 'bcm' => $bcm,
                'idle' => $idle, 'jarak' => $jarak,
                'l_hm'   => $lpj,
                'l_ton'  => Energi::rasio($liter, $ton),
                'l_bcm'  => Energi::rasio($liter, $bcm),
                'gj'     => Energi::literKeGj($liter),
                'tco2e'  => Energi::literKeCo2($liter),
                'rupiah' => Energi::literKeRp($liter),
                'idle_persen' => $hm > 0 ? ($idle / $hm) * 100 : 0.0,
                'cycle'  => (float) $log->avg('cycle_menit'),
                'kecepatan' => $hm > 0 ? $jarak / $hm : 0.0,
            ],
        ]);
    }

    /* ================= KPI, baseline, hemat, karbon ================= */

    public function kpi(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $r = $this->ringkasan($dari, $sampai);
        $b = $this->baselineBerlaku();

        return Inertia::render('Energi/Halaman', [
            'mode' => 'kpi',
            'r' => $r, 'baseline' => $b, 'dari' => $dari, 'sampai' => $sampai,
            'hemat' => $this->penghematanTerwujud(),
        ]);
    }

    public function baseline(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        return Inertia::render('Energi/Halaman', [
            'mode'      => 'baseline',
            'daftar'   => EnergyBaseline::orderByDesc('tahun')->get(),
            'baseline' => $this->baselineBerlaku(),
            'r'        => $this->ringkasan($dari, $sampai),
            'dari'     => $dari, 'sampai' => $sampai,
            'companies'=> Company::orderBy('name')->get(),
        ]);
    }

    public function simpanBaseline(Request $request)
    {
        $d = $this->pemilik($request->validate([
            'company_id'      => ['nullable','exists:companies,id'],
            'tahun'           => ['required','integer','min:2000','max:2100'],
            'baseline_gj_ton' => ['required','numeric','min:0','max:100'],
            'target_gj_ton'   => ['required','numeric','min:0','max:100'],
            'catatan'         => ['nullable','string','max:1000'],
        ]));

        // Sasaran yang lebih boros daripada garis dasar bukan sasaran.
        if ($d['target_gj_ton'] > $d['baseline_gj_ton']) {
            return back()->withInput()->withErrors([
                'target_gj_ton' => 'Target harus lebih rendah daripada baseline — sasaran energi berarti turun, bukan naik.',
            ]);
        }

        EnergyBaseline::updateOrCreate(
            ['company_id' => $d['company_id'] ?? null, 'tahun' => $d['tahun']],
            $d
        );

        ActivityLog::write('Tetapkan baseline energi', 'Tahun '.$d['tahun'], 'energi');

        return back()->with('ok', 'Baseline dan target tahun '.$d['tahun'].' tersimpan.');
    }

    public function hemat()
    {
        $semua = EnergyOpportunity::orderByDesc('hemat_liter')->orderByDesc('hemat_kwh')->get();

        return Inertia::render('Energi/Halaman', [
            'mode'   => 'hemat',
            'daftar'  => $semua,
            'terwujud'=> $this->penghematanTerwujud(),
            'potensi' => [
                'gj'     => $semua->sum(fn ($o) => $o->gj()),
                'tco2e'  => $semua->sum(fn ($o) => $o->tco2e()),
                'rupiah' => $semua->sum(fn ($o) => $o->rupiah()),
            ],
        ]);
    }

    public function simpanPeluang(Request $request)
    {
        $d = $request->validate([
            'judul'            => ['required','string','max:200'],
            'area'             => ['nullable','string','max:100'],
            'status'           => ['required', Rule::in(Energi::STATUS_PELUANG)],
            'uraian'           => ['nullable','string','max:2000'],
            'hemat_liter'      => ['nullable','numeric','min:0','max:10000000'],
            'hemat_kwh'        => ['nullable','numeric','min:0','max:10000000'],
            'penanggung_jawab' => ['nullable','string','max:150'],
            'target_selesai'   => ['nullable','date'],
            'capex_rp'         => ['nullable','numeric','min:0','max:100000000000000'],
        ]);

        if ($d['status'] === 'selesai') {
            return back()->withErrors(['status' => 'Peluang baru tidak dapat langsung "selesai" — catat dulu, lalu isi hasil M&V-nya.']);
        }

        $d['hemat_liter'] = $d['hemat_liter'] ?? 0;
        $d['hemat_kwh']   = $d['hemat_kwh']   ?? 0;
        $d['user_id']     = auth()->id();

        EnergyOpportunity::create($d);

        return back()->with('ok', 'Peluang penghematan dicatat.');
    }

    public function ubahPeluang(Request $request, EnergyOpportunity $peluang)
    {
        $d = $request->validate([
            'status' => ['required', Rule::in(Energi::STATUS_PELUANG)],
            'capex_rp' => ['nullable', 'numeric', 'min:0', 'max:100000000000000'],
            'metode_mv' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'hemat_terverifikasi_liter' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'hemat_terverifikasi_kwh' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'tanggal_mulai_hemat' => ['nullable', 'date'],
            'mv_catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        /* "Selesai" berarti penghematannya sudah diukur (ISO 50015/IPMVP).
           Tanpa M&V, yang ada hanya perkiraan — dan perkiraan tidak boleh
           masuk KPI "terwujud" di laporan regulator (F4.6). */
        $mv = $d + $peluang->only(['metode_mv', 'hemat_terverifikasi_liter', 'hemat_terverifikasi_kwh']);
        if ($d['status'] === 'selesai' && (blank($mv['metode_mv'] ?? null)
                || ((float) ($mv['hemat_terverifikasi_liter'] ?? 0) <= 0 && (float) ($mv['hemat_terverifikasi_kwh'] ?? 0) <= 0))) {
            return back()->withErrors(['status' => 'Status "selesai" mensyaratkan metode M&V (IPMVP A–D) dan penghematan terverifikasi.']);
        }

        $peluang->update(array_filter($d, fn ($v) => $v !== null) + ['status' => $d['status']]);

        return back()->with('ok', 'Peluang diperbarui.');
    }

    public function hapusPeluang(EnergyOpportunity $peluang)
    {
        $peluang->delete();
        return back()->with('ok', 'Peluang dihapus.');
    }

    /**
     * Halaman Karbon lama kini menampilkan inventarisasi GRK (FSD §8.4).
     * Alamatnya dipertahankan — tautan dan tangkapan layar lama tetap berlaku.
     */
    /** Kalkulator menghitung di server (cacat D2): angkanya identik dengan laporan. */
    public function kalkulator(Request $request)
    {
        $kode = [];
        foreach (Katalog::ENERGI as $k => $v) $kode[$k] = ['label' => $v['label'], 'satuan' => $v['satuan']];

        return Inertia::render('Energi/Halaman', [
            'mode' => 'kalkulator',
            'kode' => $kode,
            'kategori' => Katalog::KATEGORI_IPCC,
            'terpasang' => RegistriFaktor::terpasang(),
            'tautan' => ['hitung' => route('energi.api.kalkulator')],
        ]);
    }

    /* ================= laporan ================= */

    /**
     * Laporan kinerja energi, terbit sebagai dokumen terkendali.
     *
     * Dipenggal menjadi lembar di sini, bukan diserahkan ke peramban:
     * peramban tidak dapat menghitung halaman cetaknya sendiri, sehingga
     * "Halaman 2 dari 3" pada kop hanya benar bila kitalah yang menentukan
     * pemenggalannya.
     */
    public function laporan(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        return Inertia::render('Energi/Halaman', [
            'mode'      => 'laporan',
            'dok'      => KopDokumen::untuk('laporan-energi', $this->perusahaanKop($request)),
            'r'        => $this->ringkasan($dari, $sampai),
            'baseline' => $this->baselineBerlaku(),
            'dari'     => $dari, 'sampai' => $sampai,
            'perKategori' => $this->perKategori($dari, $sampai),
            'peringkat'   => $this->peringkatUnit($dari, $sampai, 12),
            'peluang'  => EnergyOpportunity::orderByDesc('hemat_liter')->get(),
            'hemat'    => $this->penghematanTerwujud(),
        ]);
    }

    /* ================= data induk ================= */

    public function master()
    {
        return Inertia::render('Energi/Halaman', [
            'mode'      => 'master',
            'units'     => EnergyEquipment::with('site:id,nama')->withCount('fuelLogs')->orderBy('kategori')->orderBy('kode')->get(),
            'companies' => Company::orderBy('name')->get(),
            'sites'     => Site::orderBy('nama')->get(),
            'pilihan'   => ['kategori' => Energi::KATEGORI, 'kategoriIpcc' => Katalog::KATEGORI_IPCC,
                            'jenisSite' => Site::JENIS, 'grid' => Site::SISTEM_GRID, 'zona' => Site::ZONA],
            'boleh'     => ['kelola' => auth()->user()->can('energi.kelola'), 'admin' => auth()->user()->isAdmin()],
        ]);
    }

    public function simpanUnit(Request $request)
    {
        $d = $this->pemilik($request->validate([
            'company_id'  => ['nullable','exists:companies,id'],
            'kode'        => ['required','string','max:50','unique:energy_equipment,kode'],
            'nama'        => ['required','string','max:150'],
            'kategori'    => ['required', Rule::in(array_keys(Energi::KATEGORI))],
            'merek'       => ['nullable','string','max:100'],
            'daya_hp'     => ['nullable','integer','min:0','max:100000'],
            'payload_ton' => ['nullable','numeric','min:0','max:1000'],
            'site_id'     => ['nullable', new DalamPerusahaan('energi_sites')],
            'kategori_ipcc' => ['nullable', Rule::in(['bergerak_offroad', 'bergerak_jalan', 'stasioner'])],
        ]));
        $d['kategori_ipcc'] ??= 'bergerak_offroad';

        EnergyEquipment::create($d + ['aktif' => true]);

        return back()->with('ok', 'Unit '.$d['kode'].' terdaftar.');
    }

    public function ubahUnit(Request $request, EnergyEquipment $unit)
    {
        $unit->update($request->validate([
            'nama'          => ['required', 'string', 'max:150'],
            'kategori'      => ['required', Rule::in(array_keys(Energi::KATEGORI))],
            'site_id'       => ['nullable', new DalamPerusahaan('energi_sites')],
            'kategori_ipcc' => ['required', Rule::in(['bergerak_offroad', 'bergerak_jalan', 'stasioner'])],
            'aktif'         => ['boolean'],
        ]));

        return back()->with('ok', 'Unit '.$unit->kode.' diperbarui.');
    }

    /**
     * Hapus unit (cacat D5). Unit yang sudah punya catatan BBM tidak dihapus
     * — catatannya adalah data aktivitas laporan GRK — melainkan
     * dinonaktifkan; yang catatannya sudah terkunci tidak disentuh sama sekali.
     */
    public function hapusUnit(EnergyEquipment $unit)
    {
        $kode = $unit->kode;

        if ($unit->fuelLogs()->where('status', 'terkunci')->exists()) {
            $unit->update(['aktif' => false]);

            return back()->with('ok', "Unit {$kode} memiliki catatan pada periode terkunci — dinonaktifkan, tidak dihapus.");
        }

        if ($unit->fuelLogs()->exists()) {
            $unit->update(['aktif' => false]);

            return back()->with('ok', "Unit {$kode} sudah memiliki catatan bahan bakar — dinonaktifkan supaya catatannya tetap terhitung.");
        }

        $unit->delete();

        return back()->with('ok', "Unit {$kode} dihapus.");
    }

    public function simpanSite(Request $request)
    {
        $d = $this->validasiSite($request);
        Site::create($this->pemilik($d) + ['aktif' => true]);

        return back()->with('ok', 'Site '.$d['nama'].' terdaftar.');
    }

    public function ubahSite(Request $request, Site $site)
    {
        $site->update($this->validasiSite($request, $site) + ['aktif' => $request->boolean('aktif', true)]);

        return back()->with('ok', 'Site '.$site->nama.' diperbarui.');
    }

    private function validasiSite(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'kode'        => ['required', 'string', 'max:30', Rule::unique('energi_sites', 'kode')
                                ->where('company_id', $site?->company_id ?? $request->user()->company_id)->ignore($site?->id)],
            'nama'        => ['required', 'string', 'max:150'],
            'jenis'       => ['required', Rule::in(array_keys(Site::JENIS))],
            'provinsi'    => ['nullable', 'string', 'max:60'],
            'sistem_grid' => ['nullable', Rule::in(array_keys(Site::SISTEM_GRID))],
            'zona_waktu'  => ['required', Rule::in(array_keys(Site::ZONA))],
        ]);
    }

    /* ================= bantu ================= */

    /** Rentang tanggal yang diminta; bawaannya bulan berjalan. */
    private function rentang(Request $request): array
    {
        $dari   = $request->date('dari')   ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();

        // Rentang terbalik dibetulkan diam-diam; menolaknya hanya membuat
        // halaman kosong tanpa memberi tahu apa yang salah.
        return $dari->lessThanOrEqualTo($sampai)
            ? [$dari->startOfDay(), $sampai->endOfDay()]
            : [$sampai->startOfDay(), $dari->endOfDay()];
    }

    /**
     * Ringkasan seluruh sumber energi pada satu rentang.
     *
     * @return array<string,mixed>
     */
    private function ringkasan(Carbon $dari, Carbon $sampai): array
    {
        $liter  = (float) EnergyFuelLog::whereBetween('tanggal', [$dari, $sampai])->sum('liter');
        $genset = (float) EnergyPowerLog::whereBetween('tanggal', [$dari, $sampai])->sum('liter_genset');
        $kwh    = (float) EnergyPowerLog::whereBetween('tanggal', [$dari, $sampai])->sum('kwh');
        $m3     = (float) EnergyOtherLog::whereBetween('tanggal', [$dari, $sampai])->where('jenis','gas')->sum('jumlah');

        $ton = (float) EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->sum('ton');
        $bcm = (float) EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->sum('bcm');

        // Solar genset ikut dihitung sebagai solar: ia dibakar di lokasi
        // yang sama dan masuk ke tangki yang sama.
        $solarTotal = $liter + $genset;
        $k = Energi::konsolidasi($solarTotal, $kwh, $m3);

        /* Kedua ujungnya dijepit ke AWAL hari lebih dulu, lalu +1 untuk
           membuat rentangnya inklusif.

           Versi sebelumnya membulatkan pecahannya, dan itu menghitung
           dua kali: `sampai` adalah akhir hari, jadi 1–31 Agustus
           menghasilkan 30,99999… hari; round() menaikkannya menjadi 31,
           lalu +1 menjadikannya 32. Rentang satu bulan penuh terbaca
           sebagai 32 hari, dan setiap angka "per hari" yang dibagi
           dengannya ikut meleset. */
        $hari = max(1, (int) $dari->copy()->startOfDay()
            ->diffInDays($sampai->copy()->startOfDay()) + 1);

        return $k + [
            'toe'        => \App\Support\Energi\Kalkulator::toe((float) ($k['gj'] ?? 0), $sampai),
            'liter'      => $solarTotal,
            'liter_alat' => $liter,
            'liter_genset' => $genset,
            'kwh'        => $kwh,
            'm3'         => $m3,
            'ton'        => $ton,
            'bcm'        => $bcm,
            'hari'       => $hari,
            'liter_hari' => $solarTotal / $hari,
            'intensitas' => Energi::intensitas($k['gj'], $ton),
            'l_ton'      => Energi::rasio($solarTotal, $ton),
            'l_bcm'      => Energi::rasio($solarTotal, $bcm),
            'kwh_ton'    => Energi::rasio($kwh, $ton),
        ];
    }

    /** Tren harian intensitas dan pemakaian, untuk grafik. */
    private function trenHarian(Carbon $dari, Carbon $sampai): array
    {
        /* Kunci harian SELALU 'Y-m-d', dijumlah di PHP.

           Sebelumnya tiap sumber dikunci dengan caranya sendiri: listrik
           lewat keyBy pada model bercast tanggal ("2026-11-02 00:00:00"),
           solar dan produksi dengan teks mentah kolomnya ("2026-11-02").
           Satu hari terbelah menjadi dua titik — satu hanya berisi solar,
           satu hanya listrik — dan grafiknya bergerigi. Produksi pun
           diambil dengan pluck per tanggal tanpa dijumlah, sehingga pada
           tampilan gabungan beberapa perusahaan hanya satu perusahaan
           yang menjadi pembagi intensitas. */
        $hari = fn ($v) => substr((string) $v, 0, 10);
        $jumlah = function ($q, string $pilih) use ($hari, $dari, $sampai) {
            $out = [];
            foreach ($q->whereBetween('tanggal', Energi\Inventaris::sela($dari, $sampai))->toBase()->selectRaw($pilih)->groupBy('tanggal')->get() as $r) {
                $k = $hari($r->tanggal);
                foreach ((array) $r as $kol => $v) {
                    if ($kol !== 'tanggal') $out[$k][$kol] = ($out[$k][$kol] ?? 0.0) + (float) $v;
                }
            }
            return $out;
        };
        $fuel  = $jumlah(EnergyFuelLog::query(), 'tanggal, SUM(liter) as liter');
        $power = $jumlah(EnergyPowerLog::query(), 'tanggal, SUM(kwh) as kwh, SUM(liter_genset) as genset');
        $prod  = $jumlah(EnergyProduction::query(), 'tanggal, SUM(ton) as ton');

        $kunciSemua = array_unique(array_merge(array_keys($prod), array_keys($fuel), array_keys($power)));
        sort($kunciSemua);

        $out = [];
        foreach ($kunciSemua as $kunci) {
            $liter  = (float) ($fuel[$kunci]['liter'] ?? 0) + (float) ($power[$kunci]['genset'] ?? 0);
            $kwh    = (float) ($power[$kunci]['kwh'] ?? 0);
            $ton    = (float) ($prod[$kunci]['ton'] ?? 0);
            $gj     = Energi::literKeGj($liter) + Energi::kwhKeGj($kwh);

            $out[] = [
                'tanggal'    => Carbon::parse($kunci),
                'liter'      => $liter,
                'kwh'        => $kwh,
                'ton'        => $ton,
                'gj'         => $gj,
                // GJ per sumber dari server — grafik tidak lagi menyimpan faktornya sendiri.
                'gj_solar'   => Energi::literKeGj($liter),
                'gj_listrik' => Energi::kwhKeGj($kwh),
                'intensitas' => Energi::intensitas($gj, $ton),
            ];
        }
        return $out;
    }

    /**
     * Peringkat unit menurut keborosan, liter per jam operasi.
     *
     * Dibandingkan terhadap rata-rata kategorinya sendiri: excavator dan
     * dump truck memang berbeda haus, dan menyandingkannya langsung akan
     * selalu menempatkan yang bertenaga besar di puncak daftar boros.
     */
    private function peringkatUnit(Carbon $dari, Carbon $sampai, int $batas): array
    {
        $agg = EnergyFuelLog::whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('equipment_id, SUM(liter) as liter, SUM(hm) as hm, SUM(ton) as ton, SUM(bcm) as bcm, SUM(idle_jam) as idle')
            ->groupBy('equipment_id')->get()->keyBy('equipment_id');

        if ($agg->isEmpty()) return [];

        $units = EnergyEquipment::whereIn('id', $agg->keys())->get()->keyBy('id');
        $acuan = [];

        $baris = [];
        foreach ($agg as $id => $a) {
            $u = $units[$id] ?? null;
            if (!$u) continue;

            $baris[] = [
                'unit'     => $u,
                'kategori' => $u->kategori,
                'liter'    => (float) $a->liter,
                'hm'       => (float) $a->hm,
                'ton'      => (float) $a->ton,
                'l_hm'     => Energi::rasio((float) $a->liter, (float) $a->hm),
                'l_ton'    => Energi::rasio((float) $a->liter, (float) $a->ton),
                'idle'     => (float) $a->idle,
                'gj'       => Energi::literKeGj((float) $a->liter),
                'rupiah'   => Energi::literKeRp((float) $a->liter),
            ];
        }

        // Rata-rata per kategori sebagai acuan status.
        foreach ($baris as $b) {
            $acuan[$b['kategori']][] = $b['l_hm'];
        }
        foreach ($acuan as $k => $nilai) {
            $sah = array_filter($nilai, fn ($n) => $n > 0);
            $acuan[$k] = $sah ? array_sum($sah) / count($sah) : 0.0;
        }

        foreach ($baris as &$b) {
            $b['acuan']  = $acuan[$b['kategori']] ?? 0.0;
            $b['status'] = Energi::statusEfisiensi($b['l_hm'], $b['acuan']);
        }
        unset($b);

        usort($baris, fn ($x, $y) => $y['l_hm'] <=> $x['l_hm']);

        return array_slice($baris, 0, $batas);
    }

    /** Rata-rata liter per jam tiap kategori — acuan status efisiensi. */
    private function acuanKategori(string $kategori, Carbon $dari, Carbon $sampai): float
    {
        $a = EnergyFuelLog::whereBetween('tanggal', [$dari, $sampai])
            ->whereHas('equipment', fn ($q) => $q->where('kategori', $kategori))
            ->selectRaw('SUM(liter) as liter, SUM(hm) as hm')->first();

        return Energi::rasio((float) ($a->liter ?? 0), (float) ($a->hm ?? 0));
    }

    private function perKategori(Carbon $dari, Carbon $sampai): array
    {
        $out = [];
        foreach (Energi::KATEGORI as $kode => $nama) {
            $a = EnergyFuelLog::whereBetween('tanggal', [$dari, $sampai])
                ->whereHas('equipment', fn ($q) => $q->where('kategori', $kode))
                ->selectRaw('SUM(liter) as liter, SUM(hm) as hm, SUM(ton) as ton')->first();

            $liter = (float) ($a->liter ?? 0);
            if ($liter <= 0) continue;

            $out[$kode] = [
                'nama'  => $nama,
                'liter' => $liter,
                'hm'    => (float) ($a->hm ?? 0),
                'l_hm'  => Energi::rasio($liter, (float) ($a->hm ?? 0)),
                'gj'    => Energi::literKeGj($liter),
            ];
        }
        return $out;
    }

    /** Baseline tahun berjalan, atau yang terbaru bila belum ditetapkan. */
    private function baselineBerlaku(): ?EnergyBaseline
    {
        return EnergyBaseline::where('tahun', now()->year)->first()
            ?? EnergyBaseline::orderByDesc('tahun')->first();
    }

    /** Penghematan dari peluang yang benar-benar berjalan atau selesai. */
    private function penghematanTerwujud(): array
    {
        $o = EnergyOpportunity::whereIn('status', ['berjalan','selesai'])->get();

        return [
            'jumlah' => $o->count(),
            'gj'     => $o->sum(fn ($x) => $x->gj()),
            'tco2e'  => $o->sum(fn ($x) => $x->tco2e()),
            'rupiah' => $o->sum(fn ($x) => $x->rupiah()),
        ];
    }
}
