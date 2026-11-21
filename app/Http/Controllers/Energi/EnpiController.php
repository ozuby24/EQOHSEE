<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, EnergyBaseline, EnergyEquipment, EnergyProduction};
use App\Support\Db;
use App\Support\Energi\{Enpi, Inventaris, Seu};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * EnPI & Energy Baseline ternormalisasi (ISO 50006) dan tinjauan SEU (FSD §8.5).
 */
class EnpiController extends Controller
{
    use BantuEnergi;

    public const VARIABEL = ['ton' => 'Produksi (ton)', 'bcm' => 'Overburden (BCM)',
                             'jarak_angkut_km' => 'Jarak angkut rata-rata (km)', 'stripping_ratio' => 'Stripping ratio'];

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $mulai = $request->date('mulai') ?: now()->subMonths(12)->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->subMonth()->endOfMonth()->startOfDay();
        $var = array_values(array_intersect((array) $request->input('variabel', ['ton', 'bcm']), array_keys(self::VARIABEL))) ?: ['ton'];

        $data = $this->dataBulanan($c, $mulai, $sampai, $var);
        $model = Enpi::regresi(array_column($data, 'gj'), array_map(fn ($d) => array_intersect_key($d, array_flip($var)), $data));

        foreach ($data as &$d) $d['prediksi'] = Enpi::prediksi($model, $d);
        unset($d);

        return Inertia::render('Energi/Enpi', [
            'mulai' => $mulai->toDateString(), 'sampai' => $sampai->toDateString(), 'variabel' => $var,
            'pilihanVariabel' => self::VARIABEL,
            'data' => $data, 'model' => $model,
            'ambang' => ['r2' => Enpi::R2_MIN, 'cv' => Enpi::CVRMSE_MAKS],
            'baseline' => EnergyBaseline::orderByDesc('tahun')->get()->map(fn ($b) => [
                'id' => $b->id, 'tahun' => $b->tahun, 'status' => $b->status, 'baseline_gj_ton' => $b->baseline_gj_ton,
                'target_gj_ton' => $b->target_gj_ton, 'punya_model' => !empty($b->model_enb)])->values(),
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    /** Simpan model EnB ke usulan baseline tahun tertentu — diputus BOD (BOD-17). */
    public function simpan(Request $request)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $d = $request->validate([
            'tahun'   => ['required', 'integer', 'min:2000', 'max:2100'],
            'mulai'   => ['required', 'date'], 'sampai' => ['required', 'date', 'after:mulai'],
            'variabel' => ['required', 'array', 'min:1'], 'variabel.*' => [Rule::in(array_keys(self::VARIABEL))],
            'target_gj_ton' => ['required', 'numeric', 'min:0', 'max:100'],
            'target_tco2e_ton' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $c = $this->perusahaanAktif($request);
        $data = $this->dataBulanan($c, Carbon::parse($d['mulai']), Carbon::parse($d['sampai']), $d['variabel']);
        $model = Enpi::regresi(array_column($data, 'gj'), array_map(fn ($x) => array_intersect_key($x, array_flip($d['variabel'])), $data));

        $gj = array_sum(array_column($data, 'gj'));
        $ton = array_sum(array_column($data, 'ton'));
        $baseline = $ton > 0 ? $gj / $ton : 0;

        if ($d['target_gj_ton'] > $baseline && $baseline > 0) {
            return back()->withErrors(['target_gj_ton' => 'Target harus lebih rendah daripada baseline ('.number_format($baseline, 4, ',', '.').' GJ/ton).']);
        }

        EnergyBaseline::updateOrCreate(['company_id' => $c, 'tahun' => $d['tahun']], [
            'baseline_gj_ton' => $baseline, 'target_gj_ton' => $d['target_gj_ton'], 'target_tco2e_ton' => $d['target_tco2e_ton'] ?? null,
            'model_enb' => $model + ['periode_dasar' => [$d['mulai'], $d['sampai']]],
            'status' => 'usulan', 'diusulkan_oleh' => $request->user()->id, 'disetujui_oleh' => null, 'disetujui_pada' => null,
            'catatan' => 'EnB '.($model['diterima'] ? 'regresi diterima' : 'regresi tidak memenuhi ambang — EnPI rasio dipakai'),
        ]);
        ActivityLog::write('Usulkan baseline energi', 'Tahun '.$d['tahun'], 'energi');

        return back()->with('ok', 'Baseline & target '.$d['tahun'].' diusulkan — menunggu persetujuan BOD.');
    }

    public function seu(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        [$dari, $sampai] = $this->rentangTanggal($request, now()->subYear()->startOfDay(), now()->startOfDay());

        return Inertia::render('Energi/Seu', [
            'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(),
            'pareto' => Seu::pareto($dari, $sampai, $c ? ['company_id' => $c] : []),
            'unit' => EnergyEquipment::orderBy('kode')->get(['id', 'kode', 'nama', 'kategori', 'seu']),
            'boleh' => ['kelola' => $request->user()->can('energi.kelola')],
        ]);
    }

    public function tandaiSeu(Request $request, EnergyEquipment $unit)
    {
        abort_unless($request->user()->can('energi.kelola'), 403);
        $unit->update(['seu' => $request->boolean('seu')]);

        return back()->with('ok', $unit->kode.($unit->seu ? ' ditetapkan sebagai SEU.' : ' tidak lagi SEU.'));
    }

    /** Energi (GJ) dan variabel relevan per bulan. */
    private function dataBulanan(?int $c, Carbon $mulai, Carbon $sampai, array $var): array
    {
        $saring = $c ? ['company_id' => $c] : [];
        $per = Inventaris::kelompok(Inventaris::hitung($mulai, $sampai, $saring)['baris'], fn ($b) => $b['bulan']);

        $ym = Db::ym('tanggal');
        $prod = EnergyProduction::query()->whereBetween('tanggal', Inventaris::sela($mulai, $sampai))
            ->when($c, fn ($q) => $q->where('company_id', $c))
            ->selectRaw("{$ym} as bulan, SUM(ton) as ton, SUM(bcm) as bcm, AVG(jarak_angkut_km) as jarak, AVG(stripping_ratio) as sr")
            ->groupByRaw($ym)->get()->keyBy('bulan');

        $out = [];
        for ($m = $mulai->copy()->startOfMonth(); $m->lte($sampai); $m->addMonth()) {
            $k = $m->format('Y-m');
            $p = $prod[$k] ?? null;
            $gj = $per[$k]['gj'] ?? 0.0;
            if ($gj <= 0 && !$p) continue;

            $out[] = ['bulan' => $k, 'label' => $m->translatedFormat('M y'), 'gj' => $gj,
                      'ton' => (float) ($p->ton ?? 0), 'bcm' => (float) ($p->bcm ?? 0),
                      'jarak_angkut_km' => (float) ($p->jarak ?? 0), 'stripping_ratio' => (float) ($p->sr ?? 0)];
        }

        return $out;
    }
}
