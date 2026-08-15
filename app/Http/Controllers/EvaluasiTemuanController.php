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

        /* Sebaran dibentuk seragam di sini: judul, nilai terbesar untuk
           menskalakan batang, dan barisnya. Tampilan yang menghitung
           sendiri nilai terbesarnya harus mengulang perhitungan itu di
           setiap blok, dan blok yang terlewat menggambar batang yang
           panjangnya tidak berarti apa-apa. */
        $sebaran = fn (string $judul, $koleksi) => [
            'judul' => $judul,
            'maks'  => $koleksi->max() ?: 1,
            'baris' => $koleksi->map(fn ($v, $k) => ['label' => (string) ($k ?: '—'), 'nilai' => $v])
                ->values()->all(),
        ];

        $maksTren = max(1, collect($tren)->flatMap(fn ($t) => array_values($t))->max() ?: 1);

        return \Inertia\Inertia::render('Hazard/Evaluasi', [
            'judul'    => 'Evaluasi Temuan',
            'subjudul' => 'Hazard report dan temuan inspeksi dalam satu pandangan',

            'saring' => ['bulan' => $bulan, 'perusahaan' => $perusahaan],
            'opsi'   => [
                'bulan' => HazardReport::selectRaw(Db::ym('tanggal') . ' as b')
                    ->whereNotNull('tanggal')->distinct()->orderByDesc('b')->pluck('b')
                    ->map(fn ($b) => [
                        'nilai' => $b,
                        'label' => \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y'),
                    ])->all(),
                'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
            ],

            'ringkas'    => $ringkas,
            'rerataHari' => $rerataHari,

            'sebaran' => [
                $sebaran('Lokasi terbanyak',            $perLokasi->take(8)),
                $sebaran('Tindakan tidak aman',         $topUA),
                $sebaran('Kondisi tidak aman',          $topUC),
                $sebaran('Parameter sering tidak sesuai', $paramSering),
                $sebaran('Kategori',                    $perKategori),
                $sebaran('Risiko',                      $perRisiko),
                $sebaran('Status',                      $perStatus),
                $sebaran('Hirarki pengendalian',        $perHirarki),
            ],

            'perPerusahaan' => $perPerusahaan->map(fn ($v, $k) => [
                'nama'   => (string) $k,
                'total'  => $v['total'],
                'tutup'  => $v['tutup'],
                'tinggi' => $v['tinggi'],
            ])->values()->all(),

            'tren' => collect($tren)->map(fn ($t, $k) => [
                'label'    => \Carbon\Carbon::parse($k.'-01')->translatedFormat('M'),
                'hazard'   => $t['hazard'],
                'inspeksi' => $t['inspeksi'],
                'maks'     => $maksTren,
            ])->values()->all(),
        ]);
    }
}
