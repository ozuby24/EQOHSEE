<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use Inertia\Inertia;
use Inertia\Response;

class PjpBerandaController extends Controller
{
    public function __invoke(): Response
    {
        $statusCounts = Pjp::statusCountsFor();

        $perluPerhatian = Pjp::query()
            ->get(['id', 'nama_perusahaan'])
            ->map(fn (Pjp $pjp) => [
                'id' => $pjp->id,
                'nama_perusahaan' => $pjp->nama_perusahaan,
                'achievement' => $pjp->achievement(),
            ])
            ->filter(fn (array $row) => $row['achievement'] !== null && $row['achievement'] < 80)
            ->sortBy('achievement')
            ->take(5)
            ->values();

        return Inertia::render('Pjp/Beranda', [
            'stats' => [
                'total' => array_sum($statusCounts),
                'aktifDipantau' => $statusCounts['aktif'],
                'perluTindakLanjut' => $statusCounts['perlu_tindak_lanjut'],
                'tidakAktif' => $statusCounts['tidak_aktif'],
            ],
            'statusCounts' => $statusCounts,
            'pjpBelumLaporanBulanan' => Pjp::belumLaporanBulananBulanIni(),
            'perluPerhatian' => $perluPerhatian,
        ]);
    }
}
