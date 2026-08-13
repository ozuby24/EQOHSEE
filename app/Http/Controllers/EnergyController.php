<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, EnergyBaseline, EnergyEquipment, EnergyFuelLog,
                EnergyFuelRecon, EnergyOpportunity, EnergyOtherLog, EnergyPowerLog, EnergyProduction};
use App\Support\{Energi, KopDokumen};
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
    /* ================= penyaji bersama ================= */

    /** Rentang tanggal dalam bentuk siap dikirim ke Vue. */
    private function rentangProp(Carbon $dari, Carbon $sampai): array
    {
        return ['dari' => $dari->format('Y-m-d'), 'sampai' => $sampai->format('Y-m-d')];
    }

    /** Satu status efisiensi, dipakai berulang di seluruh halaman. */
    private function statusProp(array $s): array
    {
        return ['kode' => $s['kode'], 'label' => $s['label'], 'warna' => $s['warna']];
    }

    /** Potensi hemat bila sebuah unit berjalan pada acuan kategorinya, atau null bila sudah di bawahnya. */
    private function potensiHematProp(float $lHm, float $acuan, float $hm): ?array
    {
        if ($acuan <= 0 || $lHm <= $acuan) return null;

        $liter = ($lHm - $acuan) * $hm;

        return ['liter' => $liter, 'rupiah' => Energi::literKeRp($liter), 'tco2e' => Energi::literKeCo2($liter)];
    }

    private function baselineProp(?EnergyBaseline $b, float $intensitasKini): ?array
    {
        if (!$b) return null;

        return [
            'tahun'          => $b->tahun,
            'baselineGjTon'  => $b->baseline_gj_ton,
            'targetGjTon'    => $b->target_gj_ton,
            'catatan'        => $b->catatan,
            'penurunanTarget'=> $b->penurunanTarget(),
            'penurunanTercapai' => $b->penurunanTercapai($intensitasKini),
            'kemajuan'       => $b->kemajuan($intensitasKini),
        ];
    }

    /* ================= dashboard ================= */

    public function index(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $r = $this->ringkasan($dari, $sampai);
        $b = $this->baselineBerlaku();

        return Inertia::render('Energi/Index', [
            'judul'    => 'Energy Performance Center',
            'subjudul' => 'Seluruh sumber energi dalam satu satuan',

            'r' => $r,
            'baseline' => $this->baselineProp($b, $r['intensitas']),
            'tren'     => $this->trenProp($this->trenHarian($dari, $sampai)),
            ...$this->rentangProp($dari, $sampai),

            'teratas' => array_map(fn ($x) => $this->unitBarisProp($x, $dari, $sampai),
                                   $this->peringkatUnit($dari, $sampai, 5)),

            'peluang' => EnergyOpportunity::whereIn('status', ['disetujui', 'berjalan'])
                ->orderByDesc('hemat_liter')->limit(4)->get()
                ->map(fn ($o) => $this->peluangRingkasProp($o))->all(),

            'tautan' => [
                'equipment' => route('energi.equipment', $this->rentangProp($dari, $sampai)),
                'hemat'     => route('energi.hemat'),
            ],
        ]);
    }

    /* ================= konsumsi energi ================= */

    public function konsumsi(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $r = $this->ringkasan($dari, $sampai);

        return Inertia::render('Energi/Konsumsi', [
            'judul'    => 'Energy Consumption',
            'subjudul' => 'Seluruh sumber energi disandingkan setelah disamakan ke gigajoule',

            'r' => $r,
            ...$this->rentangProp($dari, $sampai),
            'baseline' => $this->baselineProp($this->baselineBerlaku(), $r['intensitas']),
            'tren'     => $this->trenProp($this->trenHarian($dari, $sampai)),
            'tautan'   => ['konsumsi' => route('energi.konsumsi')],
        ]);
    }

    /* ================= bahan bakar ================= */

    public function fuel(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $recon = EnergyFuelRecon::whereBetween('tanggal', [$dari, $sampai])->get();
        $tercatat = (float) EnergyFuelLog::whereBetween('tanggal', [$dari, $sampai])->sum('liter');
        $disalurkan = $recon->sum(fn ($x) => $x->terpakaiMenurutStok());
        $r = $this->ringkasan($dari, $sampai);

        return Inertia::render('Energi/Fuel', [
            'judul'    => 'Fuel Management',
            'subjudul' => 'Pemakaian menurut kelompok alat, ditutup dengan rekonsiliasi stok',

            ...$this->rentangProp($dari, $sampai),
            'r'           => $r,
            'biayaSolar'  => Energi::literKeRp($r['liter']),
            'peringkat'   => array_map(fn ($x) => $this->unitBarisProp($x, $dari, $sampai), $this->peringkatUnit($dari, $sampai, 10)),
            'perKategori' => $this->perKategori($dari, $sampai),

            'recon' => [
                'disalurkan' => $disalurkan,
                'tercatat'   => $tercatat,
                'selisih'    => $disalurkan - $tercatat,
                'persen'     => Energi::selisihRekonsiliasi($disalurkan, $tercatat),
                'baris'      => $recon->map(fn ($x) => [
                    'tanggal'      => $x->tanggal->translatedFormat('d M Y'),
                    'stokAwal'     => (float) $x->stok_awal_liter,
                    'disalurkan'   => (float) $x->disalurkan_liter,
                    'stokAkhir'    => (float) $x->stok_akhir_liter,
                    'terpakai'     => (float) $x->terpakaiMenurutStok(),
                    'catatan'      => $x->catatan ?: null,
                ])->all(),
            ],

            'tautan' => ['fuel' => route('energi.fuel'), 'equipment' => route('energi.equipment')],
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

        return Inertia::render('Energi/Listrik', [
            'judul'    => 'Electricity Management',
            'subjudul' => 'Listrik dipisah menurut area dan sumbernya',

            ...$this->rentangProp($dari, $sampai),
            'perArea' => array_values($perArea),
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
            'tautan' => ['listrik' => route('energi.listrik')],
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

        return Inertia::render('Energi/Equipment', [
            'judul'    => 'Equipment Energy Performance',
            'subjudul' => 'Peringkat keborosan tiap unit terhadap rata-rata kelompoknya',

            ...$this->rentangProp($dari, $sampai),
            'kategori'    => $kategori,
            'peringkat'   => $peringkat->map(fn ($x) => $this->unitBarisProp($x, $dari, $sampai))->all(),
            'perKategori' => $this->perKategori($dari, $sampai),
            'opsi'        => ['kategori' => Energi::KATEGORI],
            'tautan'      => ['equipment' => route('energi.equipment')],
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

        $m = [
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
        ];

        return Inertia::render('Energi/EquipmentDetail', [
            'judul'    => $unit->kode.' — Kinerja Energi',
            'subjudul' => $unit->nama,

            'unit' => [
                'kode' => $unit->kode, 'nama' => $unit->nama, 'kategori' => $unit->labelKategori(),
                'merek' => $unit->merek ?: null, 'dayaHp' => $unit->daya_hp ?: null,
                'payloadTon' => $unit->payload_ton ?: null,
            ],
            ...$this->rentangProp($dari, $sampai),
            'acuan'  => $acuan,
            'status' => $this->statusProp(Energi::statusEfisiensi($lpj, $acuan)),
            'm'      => $m,
            'hariBeroperasi' => $log->count(),

            'trenLiter' => $this->trenProp($log->map(fn ($x) => [
                'tanggal' => $x->tanggal, 'nilai' => (float) $x->liter,
            ])->all(), 'nilai'),

            'potensiHemat' => $this->potensiHematProp($lpj, $acuan, $hm),

            'log' => $log->map(fn ($x) => [
                'tanggal' => $x->tanggal->translatedFormat('d M Y'),
                'hm' => (float) $x->hm, 'liter' => (float) $x->liter,
                'lHm' => Energi::rasio((float) $x->liter, (float) $x->hm),
                'idle' => (float) $x->idle_jam, 'ton' => (float) $x->ton, 'bcm' => (float) $x->bcm,
            ])->all(),

            'tautan' => [
                'equipment' => route('energi.equipment'),
                'diriSendiri' => route('energi.equipment.show', $unit),
            ],
        ]);
    }

    /* ================= KPI, baseline, hemat, karbon ================= */

    public function kpi(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $r = $this->ringkasan($dari, $sampai);

        return Inertia::render('Energi/Kpi', [
            'judul'    => 'Energy KPI',
            'subjudul' => 'Enam angka yang dipakai menilai kinerja energi',

            'r' => $r,
            'baseline' => $this->baselineProp($this->baselineBerlaku(), $r['intensitas']),
            ...$this->rentangProp($dari, $sampai),
            'hemat' => $this->penghematanTerwujud(),
            'tautan' => ['kpi' => route('energi.kpi'), 'baseline' => route('energi.baseline'), 'hemat' => route('energi.hemat')],
        ]);
    }

    public function baseline(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $r = $this->ringkasan($dari, $sampai);
        $b = $this->baselineBerlaku();

        return Inertia::render('Energi/Baseline', [
            'judul'    => 'Energy Baseline & Target',
            'subjudul' => 'Garis dasar pembanding seluruh capaian tahun berjalan',

            'daftar' => EnergyBaseline::orderByDesc('tahun')->get()->map(fn ($x) => [
                'id' => $x->id, 'tahun' => $x->tahun,
                'perusahaan' => $x->company?->name ?: null,
                'baselineGjTon' => $x->baseline_gj_ton, 'targetGjTon' => $x->target_gj_ton,
                'penurunanTarget' => $x->penurunanTarget(), 'catatan' => $x->catatan ?: null,
                'berlaku' => $b?->is($x) ?? false,
            ])->all(),

            'baseline' => $this->baselineProp($b, $r['intensitas']),
            'r' => $r,
            ...$this->rentangProp($dari, $sampai),

            'opsi' => ['perusahaan' => Company::orderBy('name')->get()
                ->map(fn ($c) => ['nilai' => (string) $c->id, 'label' => $c->name])->all()],

            'tautan' => ['baseline' => route('energi.baseline'), 'simpan' => route('energi.baseline.simpan')],
        ]);
    }

    public function simpanBaseline(Request $request)
    {
        $d = $request->validate([
            'company_id'      => ['nullable', 'exists:companies,id'],
            'tahun'           => ['required', 'integer', 'min:2000', 'max:2100'],
            'baseline_gj_ton' => ['required', 'numeric', 'min:0', 'max:100'],
            'target_gj_ton'   => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan'         => ['nullable', 'string', 'max:1000'],
        ]);

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

        return back()->with('sukses', 'Baseline dan target tahun '.$d['tahun'].' tersimpan.');
    }

    public function hemat()
    {
        $semua = EnergyOpportunity::orderByDesc('hemat_liter')->orderByDesc('hemat_kwh')->get();

        return Inertia::render('Energi/Hemat', [
            'judul'    => 'Energy Saving Opportunities',
            'subjudul' => 'Peluang penghematan dicatat pada satuan asalnya',

            'daftar'   => $semua->map(fn ($o) => $this->peluangProp($o))->all(),
            'terwujud' => $this->penghematanTerwujud(),
            'potensi'  => [
                'gj'     => $semua->sum(fn ($o) => $o->gj()),
                'tco2e'  => $semua->sum(fn ($o) => $o->tco2e()),
                'rupiah' => $semua->sum(fn ($o) => $o->rupiah()),
            ],

            'opsi' => ['status' => Energi::STATUS_PELUANG],
            'tautan' => [
                'hemat' => route('energi.hemat'), 'simpan' => route('energi.hemat.simpan'),
            ],
        ]);
    }

    public function simpanPeluang(Request $request)
    {
        $d = $request->validate([
            'judul'            => ['required', 'string', 'max:200'],
            'area'             => ['nullable', 'string', 'max:100'],
            'status'           => ['required', Rule::in(Energi::STATUS_PELUANG)],
            'uraian'           => ['nullable', 'string', 'max:2000'],
            'hemat_liter'      => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'hemat_kwh'        => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'target_selesai'   => ['nullable', 'date'],
        ]);

        $d['hemat_liter'] = $d['hemat_liter'] ?? 0;
        $d['hemat_kwh']   = $d['hemat_kwh']   ?? 0;
        $d['user_id']     = auth()->id();

        EnergyOpportunity::create($d);

        return back()->with('sukses', 'Peluang penghematan dicatat.');
    }

    public function ubahPeluang(Request $request, EnergyOpportunity $peluang)
    {
        $peluang->update($request->validate([
            'status' => ['required', Rule::in(Energi::STATUS_PELUANG)],
        ]));

        return back()->with('sukses', 'Status peluang diperbarui.');
    }

    public function hapusPeluang(EnergyOpportunity $peluang)
    {
        $peluang->delete();

        return back()->with('sukses', 'Peluang dihapus.');
    }

    public function karbon(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);
        $r = $this->ringkasan($dari, $sampai);

        // Setara pohon: satu pohon tropis menyerap ±22 kg CO₂ per tahun.
        $pohon = $r['tco2e'] > 0 ? $r['tco2e'] * 1000 / 22 : 0.0;

        $s1 = Energi::literKeCo2($r['liter']) + Energi::m3KeCo2($r['m3']);
        $s2 = Energi::kwhKeCo2($r['kwh']);
        $st = max(1e-9, $s1 + $s2);

        return Inertia::render('Energi/Karbon', [
            'judul'    => 'Carbon & Emission',
            'subjudul' => 'Emisi dihitung dari catatan energi yang sama',

            'r' => $r,
            ...$this->rentangProp($dari, $sampai),
            'hemat' => $this->penghematanTerwujud(),
            'pohon' => $pohon,

            'faktor' => [
                'literKgCo2' => Energi::TCO2E_PER_LITER * 1000,
                'kwhKgCo2'   => Energi::TCO2E_PER_KWH * 1000,
                'm3KgCo2'    => Energi::TCO2E_PER_M3_GAS * 1000,
            ],

            'lingkup' => [
                's1' => $s1, 's2' => $s2, 'total' => $st,
                'porsiS1' => $s1 / $st, 'porsiS2' => $s2 / $st,
            ],

            'tautan' => ['karbon' => route('energi.karbon'), 'hemat' => route('energi.hemat')],
        ]);
    }

    public function kalkulator()
    {
        return Inertia::render('Energi/Kalkulator', [
            'judul'    => 'Energy Calculator',
            'subjudul' => 'Alat hitung cepat memakai faktor konversi yang sama persis',

            'faktor' => [
                'gjLiter' => Energi::GJ_PER_LITER, 'co2Liter' => Energi::TCO2E_PER_LITER, 'rpLiter' => Energi::RP_PER_LITER,
                'gjKwh'   => Energi::GJ_PER_KWH,   'co2Kwh'   => Energi::TCO2E_PER_KWH,   'rpKwh'   => Energi::RP_PER_KWH,
                'gjM3'    => Energi::GJ_PER_M3_GAS,'co2M3'    => Energi::TCO2E_PER_M3_GAS,'rpM3'    => Energi::RP_PER_M3_GAS,
            ],

            'tautan' => ['hemat' => route('energi.hemat')],
        ]);
    }

    /* ================= laporan ================= */

    /**
     * Laporan kinerja energi, terbit sebagai dokumen terkendali.
     *
     * Tetap Blade — ini tata letak kertas berkop, bukan halaman aplikasi.
     * Dipenggal menjadi lembar di server, bukan diserahkan ke peramban:
     * peramban tidak dapat menghitung halaman cetaknya sendiri, sehingga
     * "Halaman 2 dari 3" pada kop hanya benar bila kitalah yang menentukan
     * pemenggalannya.
     */
    public function laporan(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        return view('energi.laporan', [
            'dok'      => KopDokumen::untuk('laporan-energi', Company::first()),
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
        $units = EnergyEquipment::orderBy('kategori')->orderBy('kode')->get();

        return Inertia::render('Energi/Master', [
            'judul'    => 'Master Data — Unit Alat',
            'subjudul' => 'Daftar alat yang catatan bahan bakarnya diikuti',

            'hitungKategori' => array_map(fn ($kode) => [
                'kode' => $kode, 'nama' => Energi::KATEGORI[$kode],
                'jumlah' => $units->where('kategori', $kode)->count(),
            ], array_keys(Energi::KATEGORI)),

            'units' => $units->map(fn ($u) => [
                'id' => $u->id, 'kode' => $u->kode, 'nama' => $u->nama,
                'kategori' => $u->labelKategori(), 'merek' => $u->merek ?: null,
                'dayaHp' => $u->daya_hp ?: null, 'payloadTon' => $u->payload_ton ?: null,
                'urlDetail' => route('energi.equipment.show', $u),
                'urlHapus'  => route('energi.master.hapus', $u),
            ])->all(),

            'opsi' => [
                'kategori'   => Energi::KATEGORI,
                'perusahaan' => Company::orderBy('name')->get()
                    ->map(fn ($c) => ['nilai' => (string) $c->id, 'label' => $c->name])->all(),
            ],

            'tautan' => ['simpan' => route('energi.master.simpan')],
        ]);
    }

    public function simpanUnit(Request $request)
    {
        $d = $request->validate([
            'company_id'  => ['nullable', 'exists:companies,id'],
            'kode'        => ['required', 'string', 'max:50', 'unique:energy_equipment,kode'],
            'nama'        => ['required', 'string', 'max:150'],
            'kategori'    => ['required', Rule::in(array_keys(Energi::KATEGORI))],
            'merek'       => ['nullable', 'string', 'max:100'],
            'daya_hp'     => ['nullable', 'integer', 'min:0', 'max:100000'],
            'payload_ton' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);

        EnergyEquipment::create($d + ['aktif' => true]);

        return back()->with('sukses', 'Unit '.$d['kode'].' terdaftar.');
    }

    public function hapusUnit(EnergyEquipment $unit)
    {
        $kode = $unit->kode;
        $unit->delete();

        return back()->with('sukses', "Unit {$kode} dihapus beserta catatan bahan bakarnya.");
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
        $m3     = (float) EnergyOtherLog::whereBetween('tanggal', [$dari, $sampai])->where('jenis', 'gas')->sum('jumlah');

        $ton = (float) EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->sum('ton');
        $bcm = (float) EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->sum('bcm');

        // Solar genset ikut dihitung sebagai solar: ia dibakar di lokasi
        // yang sama dan masuk ke tangki yang sama.
        $solarTotal = $liter + $genset;
        $k = Energi::konsolidasi($solarTotal, $kwh, $m3);

        // Dibulatkan: selisih hari Carbon berupa pecahan, dan rentang yang
        // dijepit ke awal/akhir hari menghasilkan 30,99999… hari — angka yang
        // benar tetapi tidak untuk dibaca orang.
        $hari = max(1, (int) round($dari->diffInDays($sampai)) + 1);

        return $k + [
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
        $fuel = EnergyFuelLog::whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('tanggal, SUM(liter) as liter')->groupBy('tanggal')->pluck('liter', 'tanggal');
        $power = EnergyPowerLog::whereBetween('tanggal', [$dari, $sampai])
            ->selectRaw('tanggal, SUM(kwh) as kwh, SUM(liter_genset) as genset')->groupBy('tanggal')->get()->keyBy('tanggal');
        $prod = EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->pluck('ton', 'tanggal');

        $out = [];
        foreach ($prod->keys()->merge($fuel->keys())->merge($power->keys())->unique()->sort() as $tgl) {
            $kunci  = (string) $tgl;
            $liter  = (float) ($fuel[$kunci] ?? 0) + (float) ($power[$kunci]->genset ?? 0);
            $kwh    = (float) ($power[$kunci]->kwh ?? 0);
            $ton    = (float) ($prod[$kunci] ?? 0);
            $gj     = Energi::literKeGj($liter) + Energi::kwhKeGj($kwh);

            $out[] = [
                'tanggal'    => Carbon::parse($kunci),
                'liter'      => $liter,
                'kwh'        => $kwh,
                'ton'        => $ton,
                'gj'         => $gj,
                'intensitas' => Energi::intensitas($gj, $ton),
            ];
        }

        return $out;
    }

    /**
     * Tren dalam bentuk siap dikirim ke Vue: tanggalnya sudah berupa teks.
     *
     * @param  array<int,array<string,mixed>>  $baris
     */
    private function trenProp(array $baris, ?string $satuKunci = null): array
    {
        if ($satuKunci !== null) {
            // Dipakai untuk tren satu deret saja (mis. liter per hari unit).
            return array_map(fn ($x) => [
                'tanggal' => $x['tanggal']->translatedFormat('d M Y'),
                'nilai'   => $x[$satuKunci],
            ], $baris);
        }

        return array_map(fn ($x) => array_merge($x, ['tanggal' => $x['tanggal']->translatedFormat('d M Y')]), $baris);
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

    /**
     * Satu baris peringkat unit, dalam bentuk yang cukup untuk seluruh
     * pemakainya — dashboard, fuel, dan equipment membaca kunci yang sama.
     */
    private function unitBarisProp(array $b, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        $rentang = ($dari && $sampai) ? $this->rentangProp($dari, $sampai) : [];

        return [
            'id' => $b['unit']->id, 'kode' => $b['unit']->kode, 'nama' => $b['unit']->nama,
            'kategori' => $b['unit']->labelKategori(),
            'liter' => $b['liter'], 'hm' => $b['hm'], 'lHm' => $b['l_hm'], 'acuan' => $b['acuan'],
            'status' => $this->statusProp($b['status']),
            'url' => route('energi.equipment.show', [$b['unit'], ...$rentang]),
        ];
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

            $out[] = [
                'kode'  => $kode,
                'nama'  => $nama,
                'liter' => $liter,
                'hm'    => (float) ($a->hm ?? 0),
                'l_hm'  => Energi::rasio($liter, (float) ($a->hm ?? 0)),
                'gj'    => Energi::literKeGj($liter),
                'url'   => route('energi.equipment',
                    ['kategori' => $kode, ...$this->rentangProp($dari, $sampai)]),
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
        $o = EnergyOpportunity::whereIn('status', ['berjalan', 'selesai'])->get();

        return [
            'jumlah' => $o->count(),
            'gj'     => $o->sum(fn ($x) => $x->gj()),
            'tco2e'  => $o->sum(fn ($x) => $x->tco2e()),
            'rupiah' => $o->sum(fn ($x) => $x->rupiah()),
        ];
    }

    private function peluangRingkasProp(EnergyOpportunity $o): array
    {
        return [
            'judul' => $o->judul, 'status' => $o->status,
            'rupiah' => $o->rupiah(), 'tco2e' => $o->tco2e(),
        ];
    }

    private function peluangProp(EnergyOpportunity $o): array
    {
        return [
            'id' => $o->id, 'judul' => $o->judul, 'area' => $o->area ?: null,
            'status' => $o->status, 'uraian' => $o->uraian ?: null,
            'hematLiter' => (float) $o->hemat_liter, 'hematKwh' => (float) $o->hemat_kwh,
            'penanggungJawab' => $o->penanggung_jawab ?: null,
            'targetSelesai' => $o->target_selesai?->translatedFormat('d M Y'),
            'gj' => $o->gj(), 'tco2e' => $o->tco2e(), 'rupiah' => $o->rupiah(),
            'terwujud' => $o->terwujud(),
            'urlUbah' => route('energi.hemat.ubah', $o),
            'urlHapus' => route('energi.hemat.hapus', $o),
        ];
    }
}
