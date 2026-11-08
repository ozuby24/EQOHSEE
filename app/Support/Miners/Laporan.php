<?php

namespace App\Support\Miners;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan Excel Miners — Report_MCU, Report_Induction_Adm, dan
 * Report_MinePermit_Adm Safe Track (maatwebsite/excel), di sini ditulis
 * langsung dengan PhpSpreadsheet seperti register EQOHSEE lainnya.
 *
 * Satu baris judul tebal lalu barisnya apa adanya, dengan lebar kolom
 * menyesuaikan isi — bentuk yang sama dengan unduhan Safe Track, supaya
 * pengolahan lanjutan yang sudah dipakai orang di lapangan tetap jalan.
 */
final class Laporan
{
    /**
     * @param  list<string>  $judul
     * @param  iterable<array<int,mixed>>  $baris
     */
    public static function unduh(string $namaBerkas, array $judul, iterable $baris): StreamedResponse
    {
        $buku  = new Spreadsheet();
        $lembar = $buku->getActiveSheet();
        $lembar->setTitle('Laporan');

        $lembar->fromArray($judul, null, 'A1');
        $lembar->getStyle('A1:'.$lembar->getHighestColumn().'1')->getFont()->setBold(true);

        $r = 2;
        foreach ($baris as $b) {
            $lembar->fromArray(array_map(fn ($v) => $v === null ? '' : (string) $v, array_values($b)), null, 'A'.$r);
            $r++;
        }

        foreach (range(1, count($judul)) as $k) {
            $lembar->getColumnDimensionByColumn($k)->setAutoSize(true);
        }

        $lembar->freezePane('A2');

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
            $buku->disconnectWorksheets();
        }, $namaBerkas.'.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
