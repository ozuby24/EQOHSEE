<?php

namespace App\Support;

/**
 * ISO & Dokumen — daftar acuan dan aturan status dokumen terkendali.
 *
 * Hierarki dokumen mengikuti piramida mutu yang lazim dipakai pada sistem
 * manajemen: Kebijakan di puncak, lalu Manual, Prosedur, Instruksi Kerja,
 * dan Formulir/Rekaman sebagai bukti pelaksanaan di dasar.
 */
class Dokumen
{
    public const JENIS = [
        'Kebijakan',
        'Manual',
        'Prosedur',
        'Instruksi Kerja',
        'Formulir',
        'Rekaman',
    ];

    public const KLASIFIKASI = ['Umum', 'Internal', 'Rahasia'];

    public const STATUS = ['draft', 'berlaku', 'kadaluarsa', 'ditarik'];

    /** Warna per status untuk lencana dan bilah ringkasan. */
    public const WARNA = [
        'draft'      => '#9AA3AE',
        'berlaku'    => '#4FA82E',
        'kadaluarsa' => '#F0921E',
        'ditarik'    => '#E5484D',
    ];

    /** Berapa hari sebelum jatuh tempo sebuah dokumen dianggap "segera ditinjau". */
    public const AMBANG_PERINGATAN = 30;

    public static function warna(?string $status): string
    {
        return self::WARNA[$status] ?? '#9AA3AE';
    }

    /**
     * Urutan jenis dokumen dari yang tertinggi di piramida.
     * Dipakai untuk mengurutkan register agar terbaca sebagai hierarki.
     */
    public static function urutJenisSql(string $kolom = 'jenis'): string
    {
        $bagian = [];
        foreach (self::JENIS as $i => $j) {
            $bagian[] = "WHEN " . self::kutip($j) . " THEN " . ($i + 1);
        }

        // CASE WHEN adalah SQL baku sehingga berlaku di SQLite, MySQL,
        // maupun PostgreSQL — berbeda dengan FIELD() yang khusus MySQL.
        return "CASE {$kolom} " . implode(' ', $bagian) . ' ELSE ' . (count(self::JENIS) + 1) . ' END';
    }

    /** Kutip nilai literal untuk disisipkan ke ekspresi SQL. */
    private static function kutip(string $nilai): string
    {
        return "'" . str_replace("'", "''", $nilai) . "'";
    }
}
