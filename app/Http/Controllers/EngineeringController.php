<?php

namespace App\Http\Controllers;

use App\Models\{EnergyBaseline, EnergyFuelLog, EnergyPowerLog, EnergyProduction};
use App\Support\Engineering as E;
use App\Support\Energi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Mining Engineering Hub.
 *
 * Halaman acuan rekayasa: indikator armada, energi, pemeliharaan, dan
 * keselamatan beserta rumusnya, ditambah alat hitung yang dipakai
 * sehari-hari. Angkanya data contoh dari App\Support\Engineering — modul
 * Energy Performance yang menyimpan catatan lapangan sungguhan.
 *
 * Seluruh angka turunan dihitung di kelas pendukung itu, bukan di sini
 * maupun di view: satu rumus, satu tempat, dan tidak mungkin dua halaman
 * menampilkan angka berbeda untuk hal yang sama.
 */
class EngineeringController extends Controller
{
    public function index()
    {
        return Inertia::render('Engineering/Halaman', [
            'mode' => 'index',
            'p' => E::ringkasProduksi(),
            'a' => E::ringkasArmada(),
            'e' => E::ringkasEnergi(),
            'm' => E::ringkasPemeliharaan(),
            'tren' => E::trenProduksi(),
        ]);
    }

    /** Control Tower: data operasi nyata yang menjadi dasar keputusan engineering. */
    public function monitor(Request $request)
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();
        if ($dari->greaterThan($sampai)) [$dari, $sampai] = [$sampai, $dari];

        $fuel = EnergyFuelLog::with('equipment')->whereBetween('tanggal', [$dari, $sampai])->get();
        $production = EnergyProduction::whereBetween('tanggal', [$dari, $sampai])->get();
        $power = EnergyPowerLog::whereBetween('tanggal', [$dari, $sampai])->get();

        $ton = (float) $production->sum('ton');
        $liter = (float) $fuel->sum('liter');
        $kwh = (float) $power->sum('kwh');
        $gj = Energi::literKeGj($liter) + Energi::kwhKeGj($kwh);
        $intensitas = Energi::intensitas($gj, $ton);
        $baseline = EnergyBaseline::where('tahun', $dari->year)->first()
            ?? EnergyBaseline::orderByDesc('tahun')->first();

        $acuan = [];
        foreach ($fuel->groupBy(fn ($row) => $row->equipment?->kategori ?: 'lainnya') as $kategori => $rows) {
            $hm = (float) $rows->sum('hm');
            $acuan[$kategori] = Energi::rasio((float) $rows->sum('liter'), $hm);
        }

        $unit = $fuel->groupBy('equipment_id')->map(function ($rows) use ($acuan) {
            $equipment = $rows->first()->equipment;
            $hm = (float) $rows->sum('hm');
            $liter = (float) $rows->sum('liter');
            $idle = (float) $rows->sum('idle_jam');
            $rate = Energi::rasio($liter, $hm);
            $category = $equipment?->kategori ?: 'lainnya';
            $reference = $acuan[$category] ?? 0;

            return [
                'kode' => $equipment?->kode ?: 'Unit '.$rows->first()->equipment_id,
                'nama' => $equipment?->nama ?: 'Unit tidak dikenal',
                'kategori' => $category,
                'hm' => $hm,
                'liter' => $liter,
                'ton' => (float) $rows->sum('ton'),
                'idle' => $idle,
                'idle_persen' => $hm > 0 ? ($idle / $hm) * 100 : 0,
                'fuel_rate' => $rate,
                'acuan' => $reference,
                'status' => $reference > 0 && $rate > ($reference * 1.2) ? 'Anomali konsumsi' : ($idle > 0 && $hm > 0 && ($idle / $hm) > .2 ? 'Idle tinggi' : 'Normal'),
            ];
        })->sortByDesc('fuel_rate')->values()->all();

        $alerts = [];
        if (!$production->count()) $alerts[] = ['level' => 'tinggi', 'judul' => 'Belum ada input produksi', 'ket' => 'Intensitas energi belum dapat dipercaya sebelum tonase harian masuk.'];
        if (!$fuel->count()) $alerts[] = ['level' => 'tinggi', 'judul' => 'Belum ada input bahan bakar', 'ket' => 'Catatan fuel alat belum tersedia pada rentang ini.'];
        if ($baseline && $intensitas > $baseline->target_gj_ton) $alerts[] = ['level' => 'sedang', 'judul' => 'Intensitas di atas target', 'ket' => number_format($intensitas, 4).' GJ/ton vs target '.number_format($baseline->target_gj_ton, 4).' GJ/ton.'];
        foreach (array_slice(array_filter($unit, fn ($row) => $row['status'] !== 'Normal'), 0, 5) as $row) {
            $alerts[] = ['level' => $row['status'] === 'Anomali konsumsi' ? 'tinggi' : 'sedang', 'judul' => $row['kode'].' - '.$row['status'], 'ket' => number_format($row['fuel_rate'], 2).' L/HM, idle '.number_format($row['idle_persen'], 1).'%.'];
        }

        $dates = $production->pluck('tanggal')->merge($fuel->pluck('tanggal'))->merge($power->pluck('tanggal'))->map(fn ($date) => Carbon::parse($date)->toDateString())->unique()->sort()->values();
        $tren = $dates->map(function (string $date) use ($production, $fuel, $power) {
            $ton = (float) $production->filter(fn ($row) => $row->tanggal?->toDateString() === $date)->sum('ton');
            $liter = (float) $fuel->filter(fn ($row) => $row->tanggal?->toDateString() === $date)->sum('liter');
            $kwh = (float) $power->filter(fn ($row) => $row->tanggal?->toDateString() === $date)->sum('kwh');
            $gj = Energi::literKeGj($liter) + Energi::kwhKeGj($kwh);

            return ['tanggal' => $date, 'ton' => $ton, 'liter' => $liter, 'kwh' => $kwh, 'intensitas' => Energi::intensitas($gj, $ton)];
        })->all();

        return Inertia::render('Engineering/Halaman', [
            'mode' => 'monitor',
            'dari' => $dari,
            'sampai' => $sampai,
            'monitor' => [
                'ton' => $ton,
                'liter' => $liter,
                'kwh' => $kwh,
                'gj' => $gj,
                'intensitas' => $intensitas,
                'hari_produksi' => $production->count(),
                'hari_fuel' => $fuel->pluck('tanggal')->unique()->count(),
                'hari_listrik' => $power->pluck('tanggal')->unique()->count(),
            ],
            'baseline' => $baseline,
            'alerts' => $alerts,
            'unit' => $unit,
            'trenMonitor' => $tren,
            'tautan' => ['input' => route('energi.input')],
        ]);
    }

    public function energy()
    {
        $e = E::ringkasEnergi();

        $program = array_map(fn ($x) => $x + E::nilaiProgram($x), E::programHemat());

        return Inertia::render('Engineering/Halaman', [
            'mode' => 'energy',
            'e' => $e,
            'program' => $program,
            'terwujud' => array_reduce(
                array_filter($program, fn ($x) => in_array($x['status'], ['Berjalan', 'Selesai'], true)),
                fn ($t, $x) => [
                    'gj' => $t['gj'] + $x['gj'],
                    'tco2e' => $t['tco2e'] + $x['tco2e'],
                    'rupiah' => $t['rupiah'] + $x['rupiah'],
                    'jumlah' => $t['jumlah'] + 1,
                ],
                ['gj' => 0, 'tco2e' => 0, 'rupiah' => 0, 'jumlah' => 0]
            ),
        ]);
    }

    public function fleet(Request $request)
    {
        $status = $request->get('status');
        $unit = $this->armadaData();

        if ($status && $status !== 'semua') {
            $unit = array_values(array_filter($unit, fn ($u) => $u['status'] === $status));
        }

        // Diurutkan menurut keborosan; unit paling haus yang paling perlu dilihat.
        usort($unit, fn ($x, $y) => E::fuelRate($y) <=> E::fuelRate($x));

        return Inertia::render('Engineering/Halaman', [
            'mode' => 'fleet',
            'unit' => $unit,
            'status' => $status ?: 'semua',
            'a' => E::ringkasArmada(),
            'p' => E::ringkasProduksi(),
            'acuan' => E::acuanKelas(),
        ]);
    }

    public function equipment()
    {
        return Inertia::render('Engineering/Halaman', [
            'mode' => 'equipment',
            'unit' => $this->armadaData(),
            'a' => E::ringkasArmada(),
            'acuan' => E::acuanKelas(),
        ]);
    }

    public function maintenance(Request $request)
    {
        $prioritas = $request->get('prioritas');
        $kerja = E::pekerjaan();

        if ($prioritas && $prioritas !== 'semua') {
            $kerja = array_values(array_filter($kerja, fn ($p) => $p['prioritas'] === $prioritas));
        }

        $urutan = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
        usort($kerja, fn ($a, $b) => $urutan[$a['prioritas']] <=> $urutan[$b['prioritas']]);

        return Inertia::render('Engineering/Halaman', [
            'mode' => 'maintenance',
            'm' => E::ringkasPemeliharaan(),
            'kerja' => $kerja,
            'prioritas' => $prioritas ?: 'semua',
        ]);
    }

    public function hse()
    {
        return Inertia::render('Engineering/Halaman', [
            'mode' => 'hse',
            'h' => E::ringkasHse(),
            'smkp' => E::smkp(),
        ]);
    }

    public function kpi()
    {
        return Inertia::render('Engineering/Halaman', [
            'mode' => 'kpi',
            'a' => E::ringkasArmada(),
            'p' => E::ringkasProduksi(),
            'e' => E::ringkasEnergi(),
            'rumus' => [
                'Jam kerja ÷ (Jam kerja + Jam rusak) × 100',
                'Overburden dipindahkan ÷ Batu bara terangkut',
                'Energi total ÷ Produksi',
            ],
        ]);
    }

    public function tools()
    {
        return Inertia::render('Engineering/Halaman', [
            'mode' => 'tools',
            'catatan' => [
                'Engineering reference only.',
                'Permenaker No. 5 Tahun 2018',
            ],
        ]);
    }

    public function regulations(Request $request)
    {
        $kategori = $request->get('kategori');
        $daftar = E::regulasi();

        if ($kategori && $kategori !== 'Semua') {
            $daftar = array_values(array_filter($daftar, fn ($r) => $r['kategori'] === $kategori));
        }

        return Inertia::render('Engineering/Halaman', [
            'mode' => 'regulations',
            'daftar' => $daftar,
            'kategori' => $kategori ?: 'Semua',
            'semuaKategori' => array_merge(['Semua'], array_values(array_unique(array_column(E::regulasi(), 'kategori')))),
        ]);
    }

    /** Armada siap tampil: seluruh indikator turunan tetap dihitung oleh Support. */
    private function armadaData(): array
    {
        $acuan = E::acuanKelas();

        return array_map(function (array $unit) use ($acuan): array {
            $fuel = E::fuelRate($unit);

            return $unit + [
                'pa' => E::pa($unit),
                'ma' => E::ma($unit),
                'ua' => E::ua($unit),
                'utilisasi' => E::utilisasi($unit),
                'fuelRate' => $fuel,
                'statusBoros' => E::statusBoros($fuel, $acuan[$unit['kelas']] ?? 0),
            ];
        }, E::armada());
    }
}
