<?php

namespace App\Http\Controllers;

use App\Models\Pjp;
use App\Models\PjpLaporan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PjpLaporanController extends Controller
{
    public function store(Request $request, Pjp $pjp): RedirectResponse
    {
        $data = $request->validate([
            'jenis' => ['required', 'string', 'in:'.implode(',', array_keys(PjpLaporan::JENIS))],
            'periode' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($data['jenis'] === 'laporan_triwulan' && ! PjpLaporan::triwulanSedangDibuka()) {
            $bulanDibuka = implode(', ', PjpLaporan::BULAN_TRIWULAN_DIBUKA);

            return back()->withErrors([
                'file' => "Laporan Triwulan hanya bisa diunggah pada bulan {$bulanDibuka}.",
            ]);
        }

        $file = $request->file('file');
        $path = $file->store("pjp-laporan/{$pjp->id}", 'public');

        $pjp->laporans()->create([
            'jenis' => $data['jenis'],
            'periode' => $data['periode'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function update(Request $request, Pjp $pjp, PjpLaporan $laporan): RedirectResponse
    {
        abort_unless($laporan->pjp_id === $pjp->id, 404);

        $data = $request->validate([
            'kesesuaian_isi' => ['nullable', 'string', 'in:'.implode(',', array_keys(PjpLaporan::KESESUAIAN))],
        ]);

        $laporan->update($data);

        return back()->with('success', 'Evaluasi dokumen berhasil disimpan.');
    }

    public function destroy(Pjp $pjp, PjpLaporan $laporan): RedirectResponse
    {
        abort_unless($laporan->pjp_id === $pjp->id, 404);

        Storage::disk('public')->delete($laporan->file_path);
        $laporan->delete();

        return back()->with('success', 'Dokumen berhasil dihapus.');
    }
}
