<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen: JENIS/PERUSAHAAN/DEPARTEMEN/URUT.
 *
 *     FRM/CAM/OHSE/001
 *      │   │    │    └── urutan, tiga angka, per perusahaan per jenis
 *      │   │    └─────── singkatan departemen penerbit
 *      │   └──────────── singkatan perusahaan
 *      └──────────────── jenis dokumen
 *
 * Satu tempat, dipakai seluruh modul. Sebelumnya tiap modul menyusun
 * nomornya sendiri dengan bentuk yang berbeda-beda — GDM/2026-08/0001
 * di gudang, CAM-OHSE-IV.067h pada kop laporan, HZ-2608-001 pada
 * laporan bahaya — dan tidak satu pun dapat dicocokkan dengan yang
 * lain pada daftar induk dokumen. Bentuk yang berbeda-beda bukan
 * sekadar tidak rapi: daftar induk adalah yang diperiksa auditor, dan
 * nomor yang tidak sepola membuat dokumen yang sama tampak berasal
 * dari dua sistem.
 *
 * URUTANNYA PER PERUSAHAAN, bukan se-pemasangan. FRM/CAM/OHSE/001 dan
 * FRM/GBU/OHSE/001 keduanya sah dan menunjuk dokumen berbeda; itulah
 * sebabnya indeks uniknya juga menyebut company_id.
 */
final class Nomor
{
    /**
     * Jenis dokumen dan singkatannya.
     *
     * Enam yang pertama mengikuti piramida dokumen terkendali
     * (Dokumen::JENIS); sisanya bentuk yang diterbitkan modul lain
     * tetapi tetap merupakan formulir atau laporan.
     *
     * @var array<string,string> nama panjang => singkatan
     */
    public const JENIS = [
        'Kebijakan'       => 'KEB',
        'Manual'          => 'MAN',
        'Prosedur'        => 'SOP',
        'Instruksi Kerja' => 'IK',
        'Formulir'        => 'FRM',
        'Rekaman'         => 'REK',
        'Laporan'         => 'LAP',
        'Sertifikat'      => 'SRT',
        'Berita Acara'    => 'BA',
    ];

    /** Singkatan departemen bawaan bila perusahaan belum menetapkannya. */
    public const DEPT_BAWAAN = 'OHSE';

    /**
     * Singkatan untuk sebuah jenis.
     *
     * Yang tidak dikenal dipakai apa adanya setelah dibesarkan
     * hurufnya, bukan diganti sesuatu yang generik: nomor yang salah
     * masih dapat ditelusuri asalnya, nomor yang diseragamkan menjadi
     * "DOC" tidak.
     */
    public static function jenis(string $nama): string
    {
        return self::JENIS[$nama] ?? strtoupper(preg_replace('/[^A-Za-z]/', '', $nama) ?: 'DOK');
    }

    /**
     * Singkatan perusahaan.
     *
     * Kosong bila perusahaan belum menetapkan prefiksnya — dan itu
     * disengaja. Nomor yang dikarang dari inisial nama perusahaan
     * terbaca persis seperti nomor sungguhan lalu bertabrakan dengan
     * penomoran mereka sendiri di daftar induk.
     */
    public static function perusahaan(?Company $c): string
    {
        return trim((string) ($c?->doc_no_prefix ?? ''));
    }

    public static function departemen(?Company $c): string
    {
        return trim((string) ($c?->dept_kode ?? '')) ?: self::DEPT_BAWAAN;
    }

    /**
     * Susun nomor dari bagian-bagiannya.
     *
     * Memulangkan STRING KOSONG bila perusahaannya belum punya
     * singkatan. Kop yang kolom nomornya kosong terlihat sebagai
     * pekerjaan yang belum selesai, dan memang begitulah keadaannya;
     * nomor yang setengah jadi — "FRM//OHSE/001" — terlihat seperti
     * kerusakan sistem.
     */
    public static function susun(string $jenis, ?Company $c, int $urut): string
    {
        $pt = self::perusahaan($c);

        if ($pt === '') return '';

        return sprintf('%s/%s/%s/%03d', self::jenis($jenis), $pt, self::departemen($c), $urut);
    }

    /**
     * Nomor berikutnya yang belum terpakai pada satu tabel.
     *
     * Urutannya dibaca dari nomor yang SUDAH ADA, bukan dari jumlah
     * baris. Menghitung baris membuat nomor terpakai ulang begitu ada
     * yang dihapus, dan dokumen terkendali yang bernomor sama dengan
     * dokumen yang pernah ditarik adalah persis yang dicari auditor.
     *
     * @param  string  $tabel   tabel tempat nomornya disimpan
     * @param  string  $kolom   kolom nomornya
     */
    public static function berikut(
        string $tabel,
        string $kolom,
        string $jenis,
        ?Company $c,
    ): string {
        $pt = self::perusahaan($c);

        if ($pt === '') return '';

        $awalan = sprintf('%s/%s/%s/', self::jenis($jenis), $pt, self::departemen($c));

        $terpakai = DB::table($tabel)
            ->where($kolom, 'like', $awalan.'%')
            ->pluck($kolom);

        $tertinggi = 0;
        foreach ($terpakai as $n) {
            if (preg_match('#/(\d+)$#', (string) $n, $cocok)) {
                $tertinggi = max($tertinggi, (int) $cocok[1]);
            }
        }

        return $awalan.sprintf('%03d', $tertinggi + 1);
    }

    /**
     * Uraikan nomor kembali menjadi bagian-bagiannya.
     *
     * Dipakai daftar induk untuk mengelompokkan menurut jenis tanpa
     * menyimpan jenisnya dua kali.
     *
     * @return array{jenis:string,perusahaan:string,departemen:string,urut:int}|null
     */
    public static function urai(?string $nomor): ?array
    {
        if (!preg_match('#^([A-Z]+)/([A-Z0-9]+)/([A-Z0-9]+)/(\d+)$#', (string) $nomor, $c)) {
            return null;
        }

        return [
            'jenis'      => $c[1],
            'perusahaan' => $c[2],
            'departemen' => $c[3],
            'urut'       => (int) $c[4],
        ];
    }
}
