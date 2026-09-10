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
                'urut'  => 1,
],
            'rencana-audit' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'RENCANA AUDIT INTERNAL SMKP MINERBA',
                'kode'  => 'OHSE-IV.059',
                'urut'  => 2,
],
            'daftar-hadir' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'DAFTAR HADIR AUDIT INTERNAL SMKP MINERBA',
                'kode'  => 'OHSE-IV.067g',
                'urut'  => 3,
],
            'kriteria' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'FORMULIR KRITERIA AUDIT SMKP MINERBA',
                'kode'  => 'OHSE-IV.067j',
                'urut'  => 4,
],
            'tindak-lanjut' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'RENCANA TINDAK LANJUT AUDIT SMKP MINERBA',
                'kode'  => 'OHSE-IV.067f',
                'urut'  => 5,
],
            'iso-matriks' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'MATRIKS PEMENUHAN KLAUSUL STANDAR',
                'kode'  => 'OHSE-II.012',
                'urut'  => 6,
],
            'daftar-induk' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'DAFTAR INDUK DOKUMEN TERKENDALI',
                'kode'  => 'OHSE-II.001',
                'urut'  => 7,
],
            /* Lima keluaran audit yang sebelumnya belum punya kop.
               Nomornya mengikuti urutan berkas audit, bukan urutan
               pembuatannya di sini.

               `urut` WAJIB unik dalam satu awalan nomor: ia yang
               menyusun FRM/CAM/OHSE/0NN. Lima formulir bernomor sama
               tidak menimbulkan galat — hanya lima lembar berbeda yang
               masuk daftar induk dokumen dengan satu nomor, dan
               daftar induk itulah yang diperiksa auditor eksternal.
               Slot 1–9 sudah terpakai formulir lain dan 10 oleh Mine
               Permit, jadi kelimanya mulai dari 11. */
            'formulir-kriteria' => [
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR KRITERIA AUDIT SMKP',
                'kode'  => 'OHSE-IV.141',
                'urut'  => 11,
            ],
            'rekap-ketidaksesuaian' => [
                'jenis' => 'FORMULIR',
                'judul' => 'REKAPITULASI KETIDAKSESUAIAN',
                'kode'  => 'OHSE-IV.142',
                'urut'  => 12,
            ],
            'respon-manajemen' => [
                'jenis' => 'FORMULIR',
                'judul' => 'RESPON MANAJEMEN ATAS KETIDAKSESUAIAN',
                'kode'  => 'OHSE-IV.143',
                'urut'  => 13,
            ],
            'rencana-tindak-lanjut' => [
                'jenis' => 'FORMULIR',
                'judul' => 'RENCANA TINDAK LANJUT AUDIT SMKP',
                'kode'  => 'OHSE-IV.144',
                'urut'  => 14,
            ],
            'ketidaksesuaian-tindak-lanjut' => [
                'jenis' => 'FORMULIR',
                'judul' => 'KETIDAKSESUAIAN DAN TINDAK LANJUTNYA',
                'kode'  => 'OHSE-IV.145',
                'urut'  => 15,
            ],

            'laporan-audit' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN AUDIT INTERNAL PENERAPAN SMKP MINERBA',
                'kode'  => 'OHSE-IV.067',
                'urut'  => 1,
],
            'laporan-energi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KINERJA ENERGI DAN EMISI KARBON',
                'kode'  => 'OHSE-V.021',
                'urut'  => 2,
],
            'laporan-operasi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KINERJA OPERASI PENAMBANGAN',
                'kode'  => 'OHSE-V.031',
                'urut'  => 3,
],
            'laporan-keandalan' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KEANDALAN DAN PEMELIHARAAN ARMADA',
                'kode'  => 'OHSE-V.051',
                'urut'  => 4,
],
            'laporan-air' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PENGELOLAAN AIR DAN PENIRISAN TAMBANG',
                'kode'  => 'OHSE-V.061',
                'urut'  => 5,
],
            'laporan-lingkungan' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PENGELOLAAN LINGKUNGAN DAN REKLAMASI',
                'kode'  => 'OHSE-V.081',
                'urut'  => 6,
],
            'laporan-peledakan' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PENGEBORAN DAN PELEDAKAN',
                'kode'  => 'OHSE-V.091',
                'urut'  => 7,
],
            'laporan-angkutan' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PENGANGKUTAN DAN PENGATURAN ARMADA',
                'kode'  => 'OHSE-V.101',
                'urut'  => 8,
],
            'laporan-biaya' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PENGENDALIAN BIAYA OPERASI PENAMBANGAN',
                'kode'  => 'OHSE-V.111',
                'urut'  => 9,
],
            /* Mine Permit bukan LAPORAN melainkan IZIN: ia dibawa
               orangnya, bukan diarsipkan bagiannya. Jenisnya ikut
               menentukan bunyi kop dan tempat tanda tangannya. */
            'mine-permit' => [
                'jenis' => 'IZIN',
                'judul' => 'MINE PERMIT — IZIN MASUK AREA TAMBANG',
                'kode'  => 'OHSE-IV.131',
                'urut'  => 10,
            ],

            'laporan-izin-kerja' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN IZIN KERJA AMAN',
                'kode'  => 'OHSE-IV.121',
                'urut'  => 10,
],
            'laporan-geoteknik' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PEMANTAUAN KESTABILAN LERENG',
                'kode'  => 'OHSE-V.071',
                'urut'  => 11,
],
            'register-hazard' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'REGISTER LAPORAN BAHAYA',
                'kode'  => 'OHSE-IV.031',
                'urut'  => 8,
],
            'register-inspeksi' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'REGISTER INSPEKSI KESELAMATAN',
                'kode'  => 'OHSE-IV.041',
                'urut'  => 9,
],
            /* urut 16, bukan 10. Prefiks nomornya FRM, dan FRM 001–015
               sudah dipakai — termasuk oleh 'mine-permit' yang berjenis
               IZIN tetapi tetap bernomor FRM. Dua formulir bernomor sama
               tidak menimbulkan galat apa pun; yang menemukannya
               auditor. */
            'register-pjp' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'REGISTER PEMANTAUAN PERUSAHAAN JASA PERTAMBANGAN',
                'kode'  => 'OHSE-IV.051',
                'urut'  => 16,
],
            'laporan-konservasi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KONSERVASI MINERAL DAN BATUBARA',
                'kode'  => 'OHSE-V.041',
                'urut'  => 12,
],
        ];
    }

    public const DIVISI     = 'Occupational Health, Safety and Environment, External';
    public const DEPARTEMEN = 'Occupational Health, Safety and Environment';

    /**
     * Data kop untuk satu formulir.
     *
     * Perusahaan tanpa prefiks nomor dokumen menghasilkan nomor KOSONG,
     * bukan nomor yang dikarang dari inisial namanya.
     *
     * Sebelumnya dikarang, dan itu keliru justru karena hasilnya
     * meyakinkan: "PT Gunung Bara Utama" menjadi GBU-OHSE-IV.067, yang
     * terbaca persis seperti nomor sungguhan. Lembar itu lalu keluar
     * sebagai dokumen terkendali dan diserahkan kepada auditor,
     * membawa nomor yang tidak ada di daftar induk perusahaan itu —
     * dan bertabrakan dengan penomoran mereka sendiri.
     *
     * Kolom yang kosong terlihat sebagai pekerjaan yang belum selesai,
     * dan memang begitulah keadaannya. Nomor yang salah terlihat
     * sebagai pekerjaan yang sudah selesai.
     *
     * Berkas cetaknya tetap tidak boleh gagal karena ini — yang kosong
     * hanya nomornya, bukan halamannya.
     *
     * @return array{jenis:string,judul:string,nomor:string,terbit:?string,setuju:?string,revisi:string,divisi:string,departemen:string,logo:?string,perusahaan:string}
     */
    public static function untuk(string $kunci, ?Company $c = null): array
    {
        $d = self::daftar()[$kunci] ?? null;

        if (!$d) {
            $d = ['jenis' => 'DOKUMEN', 'judul' => strtoupper(str_replace('-', ' ', $kunci)),
                  'kode' => 'OHSE', 'urut' => 0];
        }

        /* Bentuknya JENIS/PERUSAHAAN/DEPARTEMEN/URUT — lihat Support\Nomor.
           Urutannya TETAP per formulir, bukan berjalan: ini formulir baku
           yang diterbitkan berulang kali, dan nomor yang berubah tiap kali
           dicetak bukan nomor dokumen terkendali. */
        $jenisNomor = $d['jenis'] === 'LAPORAN' ? 'Laporan' : 'Formulir';

        return [
            'jenis'      => $d['jenis'],
            'judul'      => $d['judul'],
            'nomor'      => Nomor::susun($jenisNomor, $c, (int) ($d['urut'] ?? 0)),
            'terbit'     => $c?->doc_terbit,
            'setuju'     => $c?->doc_setuju,
            'revisi'     => str_pad((string) (int) ($c?->doc_revisi ?? 0), 2, '0', STR_PAD_LEFT),
            'divisi'     => trim((string) ($c?->divisi ?? '')) ?: self::DIVISI,
            'departemen' => trim((string) ($c?->departemen ?? '')) ?: self::DEPARTEMEN,
            /* Alamat penuh, bukan jalur simpanan. Sebelumnya jalur
               mentah — dan halaman cetak yang menerimanya tidak akan
               pernah dapat menggambarnya, sebab <img src="logos/x.png">
               menunjuk ke tempat yang tidak ada. */
            'logo'       => ($l = $c?->effectiveLogo()) ? Berkas::terbuka($l) : null,
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
