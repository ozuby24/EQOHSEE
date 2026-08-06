<?php

namespace App\Http\Controllers;

use App\Models\{Company, HazardReport, Inspection, InspectionItem};
use App\Support\{Db, Hazard};
use Illuminate\Http\Request;

/**
 * Dashboard Evaluasi Temuan — menyatukan hasil Hazard Report & Inspeksi
 * untuk menjawab: di mana temuan terbanyak, penyebabnya apa, dan
 * seberapa cepat ditindaklanjuti.
 */
class EvaluasiTemuanController extends Controller
{
    public function index(Request $request)
    {
        $bulan     = $request->get('bulan');
        $perusahaan= $request->get('perusahaan');

        /* ---------- Hazard Report ---------- */
        $hz = HazardReport::with('company')
            ->when($bulan,      fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$bulan]))
            ->when($perusahaan, fn($b) => $b->where('company_id', $perusahaan))
            ->get();

        /* ---------- Inspeksi ---------- */
        $insQ = Inspection::when($bulan,      fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$bulan]))
                          ->when($perusahaan, fn($b) => $b->where('company_id', $perusahaan));
        $insIds = $insQ->pluck('id');
        $items  = InspectionItem::with('inspection')->whereIn('inspection_id', $insIds)->get();
        $tidakSesuai = $items->where('kondisi', 'Tidak Sesuai');

        /* ---------- Ringkasan ---------- */
        $ringkas = [
            'hazard'        => $hz->count(),
            'inspeksi'      => $insIds->count(),
            'itemDiperiksa' => $items->count(),
            'temuanInspeksi'=> $tidakSesuai->count(),
            'totalTemuan'   => $hz->count() + $tidakSesuai->count(),
            'belumTutup'    => $hz->where('status','<>','Closed')->count(),
            'risikoTinggi'  => $hz->where('risiko','Tinggi')->count() + $tidakSesuai->where('risiko','Tinggi')->count(),
            'naikJadiHazard'=> $items->whereNotNull('hazard_report_id')->count(),
        ];

        /* ---------- Lokasi (gabungan) ---------- */
        $lokasi = collect();
        foreach ($hz as $h)          if ($h->lokasi) $lokasi->push($h->lokasi);
        foreach ($tidakSesuai as $t) if ($t->inspection?->lokasi) $lokasi->push($t->inspection->lokasi);
        $perLokasi = $lokasi->countBy()->sortDesc();

        /* ---------- Penyebab: bentuk unsafe action & condition ---------- */
        $bentukUA = collect(); $bentukUC = collect();
        foreach ($hz as $h) {
            foreach ($h->unsafe_action_list as $x)    $bentukUA->push($x);
            foreach ($h->unsafe_condition_list as $x) $bentukUC->push($x);
        }
        $topUA = $bentukUA->countBy()->sortDesc()->take(8);
        $topUC = $bentukUC->countBy()->sortDesc()->take(8);

        /* ---------- Parameter inspeksi paling sering tidak sesuai ---------- */
        $paramSering = $tidakSesuai->groupBy('uraian')->map->count()->sortDesc()->take(8);

        /* ---------- Distribusi lain ---------- */
        $perKategori = $hz->groupBy('kategori')->map->count()->sortDesc();
        $perRisiko   = $hz->groupBy('risiko')->map->count();
        $perStatus   = $hz->groupBy('status')->map->count();
        $perHirarki  = $hz->whereNotNull('hirarki')->groupBy('hirarki')->map->count()->sortDesc();

        /* ---------- Per perusahaan terlapor ---------- */
        $perPerusahaan = $hz->groupBy(fn($h) => $h->company?->name ?: ($h->terlapor ?: '—'))
            ->map(fn($g) => [
                'total'  => $g->count(),
                'tutup'  => $g->where('status','Closed')->count(),
                'tinggi' => $g->where('risiko','Tinggi')->count(),
            ])->sortByDesc('total');

        /* ---------- Kecepatan penutupan ---------- */
        $ditutup = $hz->whereNotNull('closed_at')->filter(fn($h) => $h->tanggal);
        $rerataHari = $ditutup->count()
            ? round($ditutup->avg(fn($h) => $h->tanggal->diffInDays($h->closed_at)), 1)
            : null;

        /* ---------- Tren 12 bulan (hazard vs temuan inspeksi) ---------- */
        $tren = [];
        for ($i = 11; $i >= 0; $i--) {
            $k = now()->subMonths($i)->format('Y-m');
            $tren[$k] = [
                'hazard'   => HazardReport::whereRaw(Db::ym('tanggal') . ' = ?', [$k])->count(),
                'inspeksi' => InspectionItem::where('kondisi','Tidak Sesuai')
                                ->whereHas('inspection', fn($q) => $q->whereRaw(Db::ym('tanggal') . ' = ?', [$k]))
                                ->count(),
            ];
        }

        return view('hazard.evaluasi', compact(
            'bulan','perusahaan','ringkas','perLokasi','topUA','topUC','paramSering',
            'perKategori','perRisiko','perStatus','perHirarki','perPerusahaan','rerataHari','tren'
        ) + [
            'companies' => Company::orderBy('name')->get(),
            'bulanOpsi' => HazardReport::selectRaw(Db::ym('tanggal') . ' as b')
                            ->whereNotNull('tanggal')->distinct()->orderByDesc('b')->pluck('b'),
        ]);
    }
}
