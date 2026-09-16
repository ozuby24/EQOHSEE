<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template kosong (dengan satu baris contoh) untuk fitur "Import Excel" di
 * Pjp/Index.tsx — kolomnya sengaja sama persis dengan field yang bisa diisi
 * lewat form Tambah PJP (bukan kolom skor di PjpExport, yang dihitung dari
 * checklist/laporan/evaluasi, bukan sesuatu yang bisa diimpor mentah).
 */
class PjpImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            [
                'PT Contoh Tambang Sejahtera',
                '1234567890123',
                'Nama Penanggung Jawab',
                'Jl. Contoh No. 1, Kota',
                'Aktif Dipantau',
                'Opsional',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'Nama Perusahaan',
            'NIB',
            'Penanggung Jawab',
            'Alamat',
            'Status',
            'Catatan',
        ];
    }
}
