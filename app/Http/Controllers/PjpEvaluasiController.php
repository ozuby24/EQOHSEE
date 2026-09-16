<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use App\Models\PjpEvaluasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PjpEvaluasiController extends Controller
{
    public function store(Request $request, Pjp $pjp): RedirectResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => ['required', 'integer', 'in:'.implode(',', array_keys(PjpEvaluasi::SEMESTER))],
            'skor_teknis' => ['required', 'integer', 'min:0', 'max:100'],
            'skor_keselamatan_kesehatan' => ['required', 'integer', 'min:0', 'max:100'],
            'skor_lingkungan' => ['required', 'integer', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string'],
        ]);

        $pjp->evaluasis()->updateOrCreate(
            ['tahun' => $data['tahun'], 'semester' => $data['semester']],
            [
                'skor_teknis' => $data['skor_teknis'],
                'skor_keselamatan_kesehatan' => $data['skor_keselamatan_kesehatan'],
                'skor_lingkungan' => $data['skor_lingkungan'],
                'catatan' => $data['catatan'] ?? null,
            ],
        );

        return back()->with('success', 'Evaluasi kinerja berhasil disimpan.');
    }

    public function destroy(Pjp $pjp, PjpEvaluasi $evaluasi): RedirectResponse
    {
        abort_unless($evaluasi->pjp_id === $pjp->id, 404);

        $evaluasi->delete();

        return back()->with('success', 'Evaluasi kinerja berhasil dihapus.');
    }
}
