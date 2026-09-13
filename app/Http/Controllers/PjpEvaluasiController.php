<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Pjp;
use App\Models\PjpEvaluasi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Penilaian kinerja PJP per semester.
 */
class PjpEvaluasiController extends Controller
{
    /**
     * Menyimpan penilaian satu semester.
     *
     * updateOrCreate berkunci (PJP, tahun, semester): mengisi ulang
     * semester yang sama MENIMPA nilainya, bukan menambah baris kedua.
     * Karena itu tidak ada titik akhir "ubah" tersendiri — memisahkannya
     * berarti ada dua jalan menulis satu baris yang sama, dan yang kedua
     * pasti berbeda aturannya cepat atau lambat.
     */
    public function simpan(Request $request, Pjp $pjp)
    {
        $data = $request->validate([
            'tahun'                      => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester'                   => ['required', 'integer', Rule::in(array_keys(PjpEvaluasi::SEMESTER))],
            'skor_teknis'                => ['required', 'integer', 'min:0', 'max:100'],
            'skor_keselamatan_kesehatan' => ['required', 'integer', 'min:0', 'max:100'],
            'skor_lingkungan'            => ['required', 'integer', 'min:0', 'max:100'],
            'catatan'                    => ['nullable', 'string', 'max:3000'],
        ]);

        $pjp->evaluasis()->updateOrCreate(
            ['tahun' => $data['tahun'], 'semester' => $data['semester']],
            [
                'skor_teknis'                => $data['skor_teknis'],
                'skor_keselamatan_kesehatan' => $data['skor_keselamatan_kesehatan'],
                'skor_lingkungan'            => $data['skor_lingkungan'],
                'catatan'                    => $data['catatan'] ?? null,
            ],
        );

        ActivityLog::write(
            'Simpan evaluasi PJP',
            $pjp->nama_perusahaan." — S{$data['semester']} {$data['tahun']}",
            'pjp',
        );

        return back()->with('ok', 'Evaluasi kinerja tersimpan.');
    }

    public function hapus(Pjp $pjp, PjpEvaluasi $evaluasi)
    {
        // Lihat catatan yang sama di PjpLaporanController.
        abort_unless($evaluasi->pjp_id === $pjp->id, 404);

        ActivityLog::write(
            'Hapus evaluasi PJP',
            $pjp->nama_perusahaan.' — '.$evaluasi->periodeSingkat(),
            'pjp',
        );

        $evaluasi->delete();

        return back()->with('ok', 'Evaluasi kinerja dihapus.');
    }
}
