<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Ekspor data ke CSV (dibuka Excel) — tanpa pustaka luar. */
class Ekspor
{
    public static function csv(string $namaBerkas, array $judul, iterable $baris): StreamedResponse
    {
        return response()->stream(function () use ($judul, $baris) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");             // BOM agar Excel membaca UTF-8
            fputcsv($out, $judul, ';');
            foreach ($baris as $b) fputcsv($out, $b, ';');
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'.csv"',
        ]);
    }

    /** Tautan berbagi WhatsApp. Nomor kosong = buka pemilih kontak/grup. */
    public static function waLink(string $teks, ?string $nomor = null): string
    {
        return $nomor
            ? 'https://wa.me/'.$nomor.'?text='.rawurlencode($teks)
            : 'https://wa.me/?text='.rawurlencode($teks);
    }

    /** Tautan email (mailto) — jalan tanpa perlu server SMTP. */
    public static function mailLink(string $ke, string $judul, string $isi): string
    {
        return 'mailto:'.rawurlencode($ke).'?subject='.rawurlencode($judul).'&body='.rawurlencode($isi);
    }
}
