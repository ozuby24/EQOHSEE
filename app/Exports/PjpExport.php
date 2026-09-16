<?php

namespace App\Exports;

use App\Models\Pjp;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PjpExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly ?string $search = null,
        private readonly ?string $status = null,
    ) {}

    public function collection(): Collection
    {
        return Pjp::query()
            ->filter($this->search, $this->status)
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nama Perusahaan',
            'NIB',
            'Penanggung Jawab',
            'Alamat',
            'Status',
            'Skor Persyaratan PJP (%)',
            'Skor Kepatuhan Pelaporan (%)',
            'Skor Evaluasi Terakhir',
            'Catatan',
            'Terdaftar Sejak',
        ];
    }

    public function map(mixed $row): array
    {
        $pelaporanScore = $row->pelaporanScore();
        $latestEvaluasi = $row->evaluasis()->first();

        return [
            $row->nama_perusahaan,
            $row->nib,
            $row->penanggung_jawab,
            $row->alamat,
            Pjp::STATUS[$row->status] ?? $row->status,
            // String, bukan angka mentah — PhpSpreadsheet menulis float 0
            // sebagai sel numerik kosong, jadi 0% bisa salah terbaca "belum ada data".
            $row->smkpScore()['persentase'].'%',
            $pelaporanScore !== null ? $pelaporanScore.'%' : 'Belum ada laporan',
            $latestEvaluasi !== null ? $latestEvaluasi->skor_rata_rata.'' : 'Belum dievaluasi',
            $row->catatan,
            $row->created_at->format('d-m-Y'),
        ];
    }
}
