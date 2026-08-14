<?php

namespace App\Support;

use App\Models\Company;

/**
 * Kop dokumen terkendali.
 *
 * Berkas audit SMKP terbit sebagai dokumen terkendali: tiap lembar membawa
 * nomor dokumen, tanggal penerbitan, tanggal persetujuan, nomor revisi, dan
 * nomor halaman. Bentuknya diambil dari berkas audit PT Cemerlang Asa Mandiri
 * 2025 dan PT Gunung Bara Utama 2023.
 *
 * Nomor dokumen tersusun dari prefiks perusahaan dan kode formulir —
 * CAM-OHSE-IV.067h, GBU-OHSE-IV.059 — sehingga satu perusahaan cukup menetapkan
 * prefiksnya sekali dan seluruh berkasnya ikut bernomor benar.
 */
final class KopDokumen
{
    /**
     * Formulir yang diterbitkan modul audit.
     *
     * `kode` mengikuti penomoran formulir pada dokumen acuan. `jenis` adalah
     * baris atas kop — dokumen acuan memakai "FORM & CHECKLIST" untuk formulir
     * dan "LAPORAN" untuk laporan naratif.
     */
    public static function daftar(): array
    {
        return [
            'berita-acara' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'BERITA ACARA HASIL PELAKSANAAN TAHAPAN AWAL AUDIT INTERNAL SMKP',
                'kode'  => 'OHSE-IV.067h',
            ],
            'rencana-audit' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'RENCANA AUDIT INTERNAL SMKP MINERBA',
                'kode'  => 'OHSE-IV.059',
            ],
            'daftar-hadir' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'DAFTAR HADIR AUDIT INTERNAL SMKP MINERBA',
                'kode'  => 'OHSE-IV.067g',
            ],
            'kriteria' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'FORMULIR KRITERIA AUDIT SMKP MINERBA',
                'kode'  => 'OHSE-IV.067j',
            ],
            'tindak-lanjut' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'RENCANA TINDAK LANJUT AUDIT SMKP MINERBA',
                'kode'  => 'OHSE-IV.067f',
            ],
            'iso-matriks' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'MATRIKS PEMENUHAN KLAUSUL STANDAR',
                'kode'  => 'OHSE-II.012',
            ],
            'daftar-induk' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'DAFTAR INDUK DOKUMEN TERKENDALI',
                'kode'  => 'OHSE-II.001',
            ],
            'laporan-audit' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN AUDIT INTERNAL PENERAPAN SMKP MINERBA',
                'kode'  => 'OHSE-IV.067',
            ],
            'laporan-energi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KINERJA ENERGI DAN EMISI KARBON',
                'kode'  => 'OHSE-V.021',
            ],
            'laporan-operasi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KINERJA OPERASI PENAMBANGAN',
                'kode'  => 'OHSE-V.031',
            ],
            'laporan-keandalan' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KEANDALAN DAN PEMELIHARAAN ARMADA',
                'kode'  => 'OHSE-V.051',
            ],
            'laporan-konservasi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KONSERVASI MINERAL DAN BATUBARA',
                'kode'  => 'OHSE-V.041',
            ],
        ];
    }

    public const DIVISI     = 'Occupational Health, Safety and Environment, External';
    public const DEPARTEMEN = 'Occupational Health, Safety and Environment';

    /**
     * Data kop untuk satu formulir.
     *
     * Perusahaan tanpa prefiks nomor dokumen tetap menghasilkan nomor yang
     * terbaca — berkas cetak tidak boleh gagal hanya karena data induk belum
     * lengkap.
     *
     * @return array{jenis:string,judul:string,nomor:string,terbit:?string,setuju:?string,revisi:string,divisi:string,departemen:string,logo:?string,perusahaan:string}
     */
    public static function untuk(string $kunci, ?Company $c = null): array
    {
        $d = self::daftar()[$kunci] ?? null;

        if (!$d) {
            $d = ['jenis' => 'DOKUMEN', 'judul' => strtoupper(str_replace('-', ' ', $kunci)), 'kode' => 'OHSE'];
        }

        $prefiks = trim((string) ($c?->doc_no_prefix ?? '')) ?: self::prefiksDari($c?->name);

        return [
            'jenis'      => $d['jenis'],
            'judul'      => $d['judul'],
            'nomor'      => $prefiks.'-'.$d['kode'],
            'terbit'     => $c?->doc_terbit,
            'setuju'     => $c?->doc_setuju,
            'revisi'     => str_pad((string) (int) ($c?->doc_revisi ?? 0), 2, '0', STR_PAD_LEFT),
            'divisi'     => trim((string) ($c?->divisi ?? '')) ?: self::DIVISI,
            'departemen' => trim((string) ($c?->departemen ?? '')) ?: self::DEPARTEMEN,
            'logo'       => $c?->effectiveLogo(),
            'perusahaan' => $c?->name ?: 'Perusahaan',
        ];
    }

    /**
     * Prefiks cadangan dari nama perusahaan: huruf awal tiap kata penting.
     * "PT Cemerlang Asa Mandiri" menjadi "CAM", sama seperti dokumen acuan.
     */
    public static function prefiksDari(?string $nama): string
    {
        $abaikan = ['pt', 'cv', 'tbk', 'persero', 'dan', 'the'];

        $huruf = '';
        foreach (preg_split('/\s+/', trim((string) $nama)) as $kata) {
            $bersih = preg_replace('/[^A-Za-z]/', '', $kata);
            if ($bersih === '' || in_array(strtolower($bersih), $abaikan, true)) continue;
            $huruf .= strtoupper($bersih[0]);
        }

        return $huruf !== '' ? substr($huruf, 0, 4) : 'EQ';
    }
}
