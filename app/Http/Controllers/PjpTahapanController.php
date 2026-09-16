<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use App\Models\PjpEvaluasi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PjpTahapanController extends Controller
{
    public function persyaratanSeleksiPenetapan(Request $request): Response
    {
        return $this->renderTahapan('Pjp/PersyaratanSeleksiPenetapan', 'smkp', $request);
    }

    public function tanggungJawabPemantauanPelaporan(Request $request): Response
    {
        return $this->renderTahapan('Pjp/TanggungJawabPemantauanPelaporan', 'pelaporan', $request);
    }

    public function evaluasi(Request $request): Response
    {
        return $this->renderTahapan('Pjp/Evaluasi', 'evaluasi', $request);
    }

    /**
     * Setiap PJP berjalan di ketiga tahap (Persyaratan, Pelaporan, Evaluasi)
     * secara bersamaan, jadi ketiga halaman ini menampilkan SEMUA PJP —
     * bedanya hanya skor mana yang ditonjolkan sebagai `achievement` untuk
     * AchievementBarChart/PjpMiniList di halaman itu.
     */
    private function renderTahapan(string $component, string $metrik, Request $request): Response
    {
        $pjps = $this->allPjps($request);
        $extra = [];

        if ($metrik === 'smkp') {
            $pjps->each(function (Pjp $pjp) {
                $skor = $pjp->smkpScore();
                $pjp->smkpScore = $skor;
                $pjp->achievement = $skor['persentase'];
            });
        }

        if ($metrik === 'pelaporan') {
            $pjps->each(fn (Pjp $pjp) => $pjp->achievement = $pjp->pelaporanScore());
        }

        if ($metrik === 'evaluasi') {
            $pjps->each(function (Pjp $pjp) {
                $pjp->latestEvaluasi = $pjp->evaluasis()->first();
                $pjp->evaluasiHistory = $pjp->evaluasis()->get()
                    ->sortBy([['tahun', 'asc'], ['semester', 'asc']])
                    ->values();
                $pjp->achievement = $pjp->latestEvaluasi?->skor_rata_rata;
            });
            $extra['evaluasiTrendGabungan'] = PjpEvaluasi::averageTrend();
        }

        return Inertia::render($component, [
            'pjps' => $pjps,
            'filters' => $this->filtersFromRequest($request),
            'statusCounts' => Pjp::statusCountsFor(),
            ...$extra,
        ]);
    }

    private function allPjps(Request $request): Collection
    {
        return Pjp::query()
            ->filter($request->query('search'), $request->query('status'))
            ->latest()
            ->get(['id', 'nama_perusahaan', 'status']);
    }

    private function filtersFromRequest(Request $request): array
    {
        return [
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
        ];
    }
}
