<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Support\Miners\AksesMiners;
use App\Support\Miners\DataLama;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Upload Data Lama — DataLamaController Safe Track (Master Data).
 *
 * Safe Track membuka layar ini bagi setiap akun yang masuk dan membatasi
 * lingkupnya di importir. Di sini hanya admin dan HSE perusahaan IUP
 * (AksesMiners::kelola): impor ini menerbitkan Mine Permit dan SIMPER
 * tanpa alur persetujuan, jadi ia hak yang sama dengan menyunting kartu
 * yang sudah terbit. Lingkup perusahaannya tetap AksesMiners::perusahaan.
 */
class DataLamaController extends Controller
{
    public function index(Request $r)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Upload Data Lama hanya untuk admin dan HSE perusahaan IUP.');

        return Inertia::render('Miners/DataLama', [
            'judul'  => 'Upload Data Lama',
            'JENIS'  => DataLama::JENIS,
            'KOLOM'  => DataLama::KOLOM,
            'hasil'  => $r->session()->get('dataLama'),
        ]);
    }

    public function templat(Request $r, string $jenis)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()));
        abort_unless(isset(DataLama::JENIS[$jenis]), 404);
        $buku = DataLama::templat($jenis);

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
            $buku->disconnectWorksheets();
        }, 'templat-data-lama-'.($jenis === 'permit' ? 'mine-permit' : 'simper').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function unggah(Request $r, string $jenis, DataLama $dataLama)
    {
        AksesMiners::tegakkan(AksesMiners::kelola($r->user()), 'Upload Data Lama hanya untuk admin dan HSE perusahaan IUP.');
        abort_unless(isset(DataLama::JENIS[$jenis]), 404);
        $r->validate([
            'berkas'    => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv'],
            'pratinjau' => ['nullable', 'boolean'],
        ], [], ['berkas' => 'berkas Excel']);

        $hasil = $dataLama->proses($jenis, $r->file('berkas')->getRealPath(), $r->user(), $r->boolean('pratinjau'));

        return back()->with('dataLama', $hasil + ['jenis' => $jenis, 'namaBerkas' => $r->file('berkas')->getClientOriginalName()]);
    }
}
