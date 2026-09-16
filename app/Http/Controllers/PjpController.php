<?php

namespace App\Http\Controllers;

use App\Exports\PjpExport;
use App\Exports\PjpImportTemplateExport;
use App\Imports\PjpImport;
use App\Models\Pjp;
use App\Models\PjpLaporan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PjpController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $pjps = Pjp::query()
            ->filter($search, $status)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Pjp/Index', [
            'pjps' => $pjps,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? '',
            ],
            'statusCounts' => Pjp::statusCountsFor(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $export = new PjpExport(
            $request->query('search'),
            $request->query('status'),
        );

        return Excel::download($export, 'data-pjp.xlsx');
    }

    public function importTemplate(): BinaryFileResponse
    {
        return Excel::download(new PjpImportTemplateExport(), 'template-import-pjp.xlsx');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $import = new PjpImport();
        Excel::import($import, $request->file('file'));

        $pesan = "{$import->berhasil} PJP berhasil diimpor.";

        if ($import->gagal !== []) {
            $jumlahGagal = count($import->gagal);
            $pesan .= " {$jumlahGagal} baris dilewati (baris ".
                collect($import->gagal)->pluck('baris')->implode(', ').
                ' — cek kembali data pada baris tersebut).';
        }

        return to_route('pjp.index')->with('success', $pesan);
    }

    public function exportPdf(Pjp $pjp): HttpResponse
    {
        $laporans = $pjp->laporans()->get();

        $pdf = Pdf::loadView('pdf.pjp-report', [
            'pjp' => $pjp,
            'laporans' => $laporans,
            'statusLabel' => Pjp::STATUS[$pjp->status] ?? $pjp->status,
            'jenisOptions' => PjpLaporan::JENIS,
        ]);

        $fileName = 'laporan-'.str($pjp->nama_perusahaan)->slug().'.pdf';

        return $pdf->stream($fileName);
    }

    public function create(): Response
    {
        return Inertia::render('Pjp/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $pjp = Pjp::create($data);

        return to_route('pjp.show', $pjp)->with('success', 'Data PJP berhasil ditambahkan.');
    }

    public function show(Pjp $pjp): Response
    {
        return Inertia::render('Pjp/Show', [
            'pjp' => $pjp,
            'laporans' => $pjp->laporans()->get(),
            'evaluasis' => $pjp->evaluasis()->get(),
            'catatans' => $pjp->catatans()->get(),
            'smkpScore' => $pjp->smkpScore(),
            'legalitasStatus' => $pjp->smkpLegalitasStatus(),
            'pelaporanScore' => $pjp->pelaporanScore(),
            'triwulanTerbuka' => PjpLaporan::triwulanSedangDibuka(),
            'bulanTriwulanDibuka' => implode(', ', PjpLaporan::BULAN_TRIWULAN_DIBUKA),
        ]);
    }

    public function edit(Pjp $pjp): Response
    {
        return Inertia::render('Pjp/Edit', [
            'pjp' => $pjp,
        ]);
    }

    public function update(Request $request, Pjp $pjp): RedirectResponse
    {
        $data = $this->validateData($request);

        $pjp->update($data);

        return to_route('pjp.show', $pjp)->with('success', 'Data PJP berhasil diperbarui.');
    }

    public function destroy(Pjp $pjp): RedirectResponse
    {
        Storage::disk('public')->deleteDirectory("pjp-laporan/{$pjp->id}");
        $pjp->delete();

        return to_route('pjp.index')->with('success', 'Data PJP berhasil dihapus.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'nama_perusahaan' => ['required', 'string', 'max:255'],
            'nib' => ['nullable', 'digits:13'],
            'penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(Pjp::STATUS))],
            'catatan' => ['nullable', 'string'],
        ], [
            'nib.digits' => 'NIB harus terdiri dari 13 digit angka.',
        ]);
    }
}
