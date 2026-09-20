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

    /**
     * Nomor Indonesia menjadi bentuk yang dimengerti wa.me.
     *
     * ── SATU ATURAN, BUKAN DUA ──
     *
     * Aturannya sudah ada di Company::waNumber() dan kini dipakai juga
     * oleh halaman depan serta etalase. Disalin, cepat atau lambat
     * salah satunya akan lupa mengubah 0 di depan menjadi 62 — dan
     * wa.me/081214407991 bukan galat yang terlihat: ia membuka WhatsApp
     * dengan pesan "nomor tidak valid", pada tombol paling menonjol di
     * halaman, kepada orang yang baru saja memutuskan untuk bertanya.
     *
     * Mengembalikan null untuk nomor kosong, supaya pemanggilnya dapat
     * memilih tidak menggambar tombolnya sama sekali.
     */
    public static function nomorWa(?string $nomor): ?string
    {
        $n = preg_replace('/\D/', '', (string) $nomor);
        if (!$n) return null;
        if (str_starts_with($n, '0'))   $n = '62'.substr($n, 1);
        if (!str_starts_with($n, '62')) $n = '62'.$n;

        return $n;
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
