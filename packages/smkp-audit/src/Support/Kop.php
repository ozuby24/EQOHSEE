<?php

namespace Eqohsee\SmkpAudit\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Kop dokumen terkendali untuk berkas cetak audit.
 *
 * Berkas audit SMKP terbit sebagai dokumen terkendali: tiap lembar membawa
 * nomor dokumen, tanggal penerbitan, tanggal persetujuan, nomor revisi, dan
 * nomor halaman. Bentuknya diambil dari berkas audit nyata (PT Cemerlang Asa
 * Mandiri 2025 dan PT Gunung Bara Utama 2023).
 *
 * Nomor dokumen tersusun dari prefiks perusahaan dan kode formulir —
 * CAM-OHSE-IV.067h, GBU-OHSE-IV.059 — sehingga satu perusahaan cukup
 * menetapkan prefiksnya sekali dan seluruh berkasnya ikut bernomor benar.
 *
 * Kelas ini sengaja TIDAK menyebut satu pun model aplikasi induk. Perusahaan
 * masuk sebagai model apa pun (atau null), dan kolomnya dibaca lewat pemetaan
 * pada config('smkp.kop.atribut'). Aplikasi yang tidak punya kolom itu tetap
 * menghasilkan kop yang terbaca — berkas cetak tidak boleh gagal hanya karena
 * data induk belum lengkap.
 */
final class Kop
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
            'laporan-audit' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN AUDIT INTERNAL PENERAPAN SMKP MINERBA',
                'kode'  => 'OHSE-IV.067',
            ],
        ];
    }

    /**
     * Data kop untuk satu formulir.
     *
     * @return array{jenis:string,judul:string,nomor:string,terbit:?string,
     *               setuju:?string,revisi:string,divisi:string,departemen:string,
     *               logo:?string,perusahaan:string}
     */
    public static function untuk(string $kunci, ?Model $perusahaan = null): array
    {
        $d = self::daftar()[$kunci] ?? [
            'jenis' => 'DOKUMEN',
            'judul' => strtoupper(str_replace('-', ' ', $kunci)),
            'kode'  => 'OHSE',
        ];

        $nama    = (string) (self::atribut($perusahaan, 'nama') ?? '');
        $prefiks = trim((string) (
            self::atribut($perusahaan, 'prefiks')
            ?? config('smkp.kop.prefiks')
            ?? ''
        )) ?: self::prefiksDari($nama);

        return [
            'jenis'      => $d['jenis'],
            'judul'      => $d['judul'],
            'nomor'      => $prefiks.'-'.$d['kode'],
            'terbit'     => self::tanggal(self::atribut($perusahaan, 'terbit')),
            'setuju'     => self::tanggal(self::atribut($perusahaan, 'setuju')),
            'revisi'     => str_pad((string) (int) (self::atribut($perusahaan, 'revisi') ?? 0), 2, '0', STR_PAD_LEFT),
            'divisi'     => trim((string) (self::atribut($perusahaan, 'divisi') ?? '')) ?: (string) config('smkp.kop.divisi'),
            'departemen' => trim((string) (self::atribut($perusahaan, 'departemen') ?? '')) ?: (string) config('smkp.kop.departemen'),
            'logo'       => self::logo($perusahaan),
            'perusahaan' => $nama !== '' ? $nama : (string) config('smkp.kop.perusahaan', 'Perusahaan'),
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

    /**
     * Satu atribut perusahaan menurut pemetaan config.
     *
     * Dibaca lewat getAttribute, bukan properti langsung, supaya kolom yang
     * tidak ada menjawab null alih-alih melempar galat pada saat mencetak.
     */
    private static function atribut(?Model $c, string $peran): mixed
    {
        if (!$c) return null;

        $kolom = config("smkp.kop.atribut.{$peran}");

        return $kolom ? $c->getAttribute($kolom) : null;
    }

    /**
     * Logo perusahaan sebagai URL siap pakai.
     *
     * Model aplikasi induk yang punya cara sendiri menentukan logo efektifnya
     * (mis. `effectiveLogo()`) dihormati lebih dulu; sisanya dibaca sebagai
     * kolom biasa lalu diberi awalan disk.
     */
    private static function logo(?Model $c): ?string
    {
        if (!$c) return null;

        $berkas = method_exists($c, 'effectiveLogo')
            ? $c->effectiveLogo()
            : self::atribut($c, 'logo');

        $berkas = trim((string) $berkas);
        if ($berkas === '') return null;

        if (str_starts_with($berkas, 'http') || str_starts_with($berkas, '/')) return $berkas;

        return '/'.ltrim((string) config('smkp.kop.logo_disk', 'storage/'), '/').$berkas;
    }

    /** Tanggal apa pun bentuknya menjadi Y-m-d; Vue yang menatanya. */
    private static function tanggal(mixed $nilai): ?string
    {
        if (!$nilai) return null;
        if ($nilai instanceof \DateTimeInterface) return $nilai->format('Y-m-d');

        $waktu = strtotime((string) $nilai);

        return $waktu ? date('Y-m-d', $waktu) : null;
    }
}
