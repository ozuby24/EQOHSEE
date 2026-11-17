<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Pjp;
use App\Models\PjpLaporan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Unggahan dan penilaian dokumen kepatuhan PJP.
 */
class PjpLaporanController extends Controller
{
    public function simpan(Request $request, Pjp $pjp)
    {
        $data = $request->validate([
            'jenis'   => ['required', Rule::in(array_keys(PjpLaporan::JENIS))],
            'periode' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'file'    => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ]);

        /*
         * Jendela triwulan diperiksa di server, bukan hanya dengan
         * menyembunyikan formulirnya. Formulir yang disembunyikan tetap
         * dapat dilewati, dan yang masuk lewat situ akan tercatat sebagai
         * laporan triwulan pada bulan yang tidak pernah membukanya —
         * tanpa ada yang menandainya.
         */
        if ($data['jenis'] === 'laporan_triwulan' && !PjpLaporan::triwulanSedangDibuka()) {
            return back()->withErrors([
                'file' => 'Laporan Triwulan hanya bisa diunggah pada bulan '
                    .PjpLaporan::bulanTriwulanDibuka().'.',
            ]);
        }

        $file = $request->file('file');
        $path = $file->store("pjp-laporan/{$pjp->id}", 'public');

        $pjp->laporans()->create([
            'jenis'     => $data['jenis'],
            'periode'   => $data['periode'] ?? null,
            'catatan'   => $data['catatan'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        ActivityLog::write(
            'Unggah dokumen PJP',
            $pjp->nama_perusahaan.' — '.(PjpLaporan::JENIS[$data['jenis']] ?? $data['jenis']),
            'pjp',
        );

        return back()->with('ok', 'Dokumen berhasil diunggah.');
    }

    /** Menetapkan kesesuaian isi — penilaian manusia, terpisah dari ketepatan waktu. */
    public function nilai(Request $request, Pjp $pjp, PjpLaporan $laporan)
    {
        $this->pastikanMilik($pjp, $laporan);

        $data = $request->validate([
            'kesesuaian_isi' => ['nullable', Rule::in(array_keys(PjpLaporan::KESESUAIAN))],
        ]);

        $laporan->update(['kesesuaian_isi' => $data['kesesuaian_isi'] ?? null]);

        return back()->with('ok', 'Penilaian dokumen tersimpan.');
    }

    public function hapus(Pjp $pjp, PjpLaporan $laporan)
    {
        $this->pastikanMilik($pjp, $laporan);

        Storage::disk('public')->delete($laporan->file_path);
        $laporan->delete();

        ActivityLog::write('Hapus dokumen PJP', $pjp->nama_perusahaan.' — '.$laporan->file_name, 'pjp');

        return back()->with('ok', 'Dokumen dihapus.');
    }

    /**
     * Dokumen harus benar-benar milik PJP pada alamatnya.
     *
     * Kedua model diikat terpisah dari URL, jadi tanpa pemeriksaan ini
     * `/pjp/7/laporan/99` akan menghapus dokumen milik PJP lain — dan
     * pada pemasangan multi-perusahaan, milik perusahaan lain.
     */
    private function pastikanMilik(Pjp $pjp, PjpLaporan $laporan): void
    {
        abort_unless($laporan->pjp_id === $pjp->id, 404);
    }
}
