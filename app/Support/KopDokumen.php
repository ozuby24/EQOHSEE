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
            /* ── Audit SMKP (porting Aurorasys) ──
               Kunci, jenis, dan judul mengikuti paket formulir Aurorasys
               (Lampiran Kepdirjen 185.K/37.04/DJB/2019); nomornya tetap
               disusun Support\Nomor dari `urut`.

               Formulir yang menggantikan formulir SMKP lama MEWARISI
               nomor urutnya — FRM/…/011 tetap formulir kriteria/penilaian,
               012 rekapitulasi ketidaksesuaian, 014 rencana tindak lanjut,
               015 ketidaksesuaian & tindak lanjut, 017 rekomendasi & OFI —
               sehingga daftar induk dokumen tidak berubah arti. Formulir
               Kesesuaian belum punya padanan dan memakai slot baru 22.

               Slot 4 (formulir kriteria lama), 5 (rencana tindak lanjut
               lama), dan 13 (respon manajemen) DIPENSIUNKAN: formulirnya
               sudah tidak diterbitkan, dan nomor yang pernah beredar
               tidak boleh dipakai ulang dokumen lain. */
            'berita-acara' => [
                'nama'  => 'Berita Acara Tahapan Awal',
                'jenis' => 'FORMULIR',
                'judul' => 'BERITA ACARA HASIL PELAKSANAAN TAHAPAN AWAL AUDIT INTERNAL SMKP',
                'kode'  => 'OHSE-IV.067h',
                'urut'  => 1,
            ],
            'rencana-audit' => [
                'nama'  => 'Rencana Audit',
                'jenis' => 'FORMULIR',
                'judul' => 'RENCANA AUDIT INTERNAL SMKP MINERBA',
                'kode'  => 'OHSE-IV.059',
                'urut'  => 2,
            ],
            'daftar-hadir' => [
                'nama'  => 'Daftar Hadir Rapat',
                'jenis' => 'FORMULIR',
                'judul' => 'DAFTAR HADIR AUDIT INTERNAL SMKP MINERBA',
                'kode'  => 'OHSE-IV.067g',
                'urut'  => 3,
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
            'penilaian-smkp' => [
                'nama'  => 'Formulir Kriteria Audit (Penilaian)',
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR KRITERIA AUDIT SMKP MINERBA',
                'kode'  => 'OHSE-IV.067a',
                'urut'  => 11,
            ],
            'formulir-kesesuaian' => [
                'nama'  => 'Formulir Kesesuaian',
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR KESESUAIAN AUDIT PENERAPAN SMKP',
                'kode'  => 'OHSE-IV.067b',
                'urut'  => 22,
            ],
            'formulir-ketidaksesuaian' => [
                'nama'  => 'Formulir Ketidaksesuaian & Tindak Lanjut',
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR KETIDAKSESUAIAN DAN TINDAK LANJUT KETIDAKSESUAIAN AUDIT',
                'kode'  => 'OHSE-IV.067c',
                'urut'  => 15,
            ],
            'rekapitulasi-ketidaksesuaian' => [
                'nama'  => 'Rekapitulasi Ketidaksesuaian',
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR REKAPITULASI KETIDAKSESUAIAN',
                'kode'  => 'OHSE-IV.067d',
                'urut'  => 12,
            ],
            'rekomendasi-ofi' => [
                'nama'  => 'Rekomendasi & OFI',
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR REKOMENDASI DAN PELUANG UNTUK PERBAIKAN',
                'kode'  => 'OHSE-IV.067e',
                'urut'  => 17,
            ],
            'rencana-tindak-lanjut' => [
                'nama'  => 'Rencana Tindak Lanjut',
                'jenis' => 'FORMULIR',
                'judul' => 'FORMULIR RENCANA TINDAK LANJUT KETIDAKSESUAIAN AUDIT',
                'kode'  => 'OHSE-IV.067f',
                'urut'  => 14,
            ],

            'laporan-audit' => [
                'nama'  => 'Laporan Audit',
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
            /* Lembar yang diserahkan kepada perusahaan terlapor dan
               dibawa ke rapat bulanan. BERBEDA dari register laporan
               bahaya di atas: yang itu mendaftar temuannya, yang ini
               mendaftar apa yang harus dikerjakan atas temuan itu —
               beserta hirarki pengendalian, penanggung jawab, dan
               tenggatnya. */
            'register-perbaikan' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'REGISTER TINDAKAN PERBAIKAN',
                'kode'  => 'OHSE-IV.054',
                'urut'  => 24,
],
            'register-inspeksi' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'REGISTER INSPEKSI KESELAMATAN',
                'kode'  => 'OHSE-IV.041',
                'urut'  => 9,
],
            /* Lembar SATU inspeksi — yang ditandatangani dan diarsipkan,
               berbeda dari register di atas yang mendaftar seluruhnya.
               Urut 18, bukan 9: keduanya berprefiks FRM dan dipakai
               berdampingan pada rapat yang sama, jadi nomor yang kembar
               di antara keduanya justru yang paling cepat ketahuan. */
            'lembar-inspeksi' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'LEMBAR INSPEKSI KESELAMATAN',
                'kode'  => 'OHSE-IV.042',
                'urut'  => 18,
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
            /* Lembar pemantauan SATU perusahaan jasa, berbeda dari
               register di atas yang mendaftar semuanya sekaligus.
               Keduanya memang sekeluarga, karena itu kodenya bersubhuruf
               seperti OHSE-IV.067h dan 067g pada berkas acuan.

               Tanpa entri ini kop lembarnya tetap tergambar — justru itu
               persoalannya: `untuk()` jatuh ke bentuk cadangan bernomor
               urut 0, sehingga tiap PJP tercetak membawa nomor dokumen
               yang sama dengan setiap dokumen tak terdaftar lainnya.
               Lembar yang diserahkan kepada Inspektur Tambang tidak dapat
               ditelusuri di daftar induk, dan tidak ada galat yang
               memberitahukannya. */
            'laporan-pjp' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN PEMANTAUAN PERUSAHAAN JASA PERTAMBANGAN',
                'kode'  => 'OHSE-IV.051a',
                'urut'  => 13,
],
            'laporan-konservasi' => [
                'jenis' => 'LAPORAN',
                'judul' => 'LAPORAN KONSERVASI MINERAL DAN BATUBARA',
                'kode'  => 'OHSE-V.041',
                'urut'  => 12,
],
            /* Evaluasi pemenuhan satu peraturan, standar, atau dokumen.
               Berjenis FORM & CHECKLIST karena itulah bentuknya di atas
               kertas: satu baris per pasal dengan kolom penilaian yang
               diisi dan ditandatangani, bukan uraian naratif.

               Urut 19, menyusul 'lembar-inspeksi' yang 18: keduanya
               berprefiks FRM dan sama-sama dilampirkan ke berkas audit,
               jadi nomor yang kembar di antara keduanya adalah yang
               paling cepat ditemukan auditor. */
            'evaluasi-pemenuhan' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'IDENTIFIKASI DAN EVALUASI PEMENUHAN PERATURAN PERUNDANGAN',
                'kode'  => 'OHSE-II.021',
                'urut'  => 19,
],
            'audit-lingkungan' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'DAFTAR PERIKSA AUDIT INTERNAL KINERJA PENGELOLAAN DAN PEMANTAUAN LINGKUNGAN',
                'kode'  => 'OHSE-V.061',
                'urut'  => 21,
],
            'rekap-pemenuhan' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'REKAPITULASI BULANAN EVALUASI PEMENUHAN',
                'kode'  => 'OHSE-II.021a',
                'urut'  => 20,
],
            /* Dua lembar Miners yang di Safe Track berkop formulir
               terkendali. Nomor, tanggal terbit, dan revisinya ikut
               pengaturan IUP penerbitnya — bukan nomor dokumen
               perusahaan asal Safe Track yang tertulis mati di
               berkas Blade-nya. Sertifikat induksi TIDAK di sini:
               ia sertifikat berbingkai, bukan formulir. */
            'sertifikat-mcu' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'MEDICAL CHECK UP CERTIFICATE',
                'kode'  => 'OHSE-MCU.01',
                'urut'  => 25,
],
            'surat-rujukan' => [
                'jenis' => 'FORM & CHECKLIST',
                'judul' => 'PENGANTAR RUJUKAN DOKTER PERUSAHAAN (INHOUSE CLINIC)',
                'kode'  => 'OHSE-MCU.02',
                'urut'  => 26,
],
        ];
    }

    /**
     * Formulir audit SMKP yang kopnya dapat disesuaikan per perusahaan
     * (`companies.kop_formulir`, porting Aurorasys). Formulir modul lain
     * tidak ikut: penomorannya milik daftar induk EQOHSEE.
     */
    public const FORMULIR_SMKP = [
        'berita-acara', 'rencana-audit', 'daftar-hadir', 'penilaian-smkp', 'formulir-kesesuaian',
        'formulir-ketidaksesuaian', 'rekapitulasi-ketidaksesuaian', 'rekomendasi-ofi',
        'rencana-tindak-lanjut', 'laporan-audit',
    ];

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
        $nomor = Nomor::susun($jenisNomor, $c, (int) ($d['urut'] ?? 0));

        /* Penyesuaian kop per perusahaan untuk formulir audit SMKP
           (porting Aurorasys — Admin → Perusahaan → Kop formulir audit
           SMKP). Jenis dan judul yang diisi menggantikan bawaan; KODE yang
           diisi mengganti nomornya menjadi bentuk Aurorasys,
           {kode singkat}-{kode}, mis. CAM-HSE-IV.067h. Tanpa isian,
           penomoran EQOHSEE di atas tetap berlaku. */
        $ubah = in_array($kunci, self::FORMULIR_SMKP, true)
            ? (array) (((array) ($c?->kop_formulir ?? []))[$kunci] ?? [])
            : [];
        foreach (['jenis', 'judul'] as $k) {
            $v = trim((string) ($ubah[$k] ?? ''));
            if ($v !== '') $d[$k] = $v;
        }
        if (($kode = trim((string) ($ubah['kode'] ?? ''))) !== '') {
            $nomor = self::prefiksAurorasys($c).'-'.$kode;
        }

        return [
            'jenis'      => $d['jenis'],
            'judul'      => $d['judul'],
            'nomor'      => $nomor,
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
     * Kop untuk lembar cetak audit SMKP (porting Aurorasys).
     *
     * Isinya sama dengan untuk(), tetapi tanggal terbit dan persetujuan
     * sudah ditulis ("01 September 2023"): lembar cetak SMKP menampilkan
     * kop apa adanya, sedangkan untuk() memulangkan tanggal sebagai objek
     * yang di peramban terbaca sebagai cap waktu ISO.
     *
     * @return array{jenis:string,judul:string,nomor:string,terbit:?string,setuju:?string,revisi:string,divisi:string,departemen:string,logo:?string,perusahaan:string}
     */
    public static function cetak(string $kunci, ?Company $c = null): array
    {
        $d = self::untuk($kunci, $c);

        foreach (['terbit', 'setuju'] as $k) {
            $d[$k] = $d[$k] instanceof \DateTimeInterface
                ? \Illuminate\Support\Carbon::instance($d[$k])->translatedFormat('d F Y')
                : (filled($d[$k]) ? (string) $d[$k] : null);
        }

        return $d;
    }

    /**
     * Awalan nomor kop bergaya Aurorasys: Kode singkat perusahaan — sumber
     * yang sama dengan nomor temuan/CAR (SmkpAudit::kodeNomorTemuan) —
     * atau, bila kosong, huruf awal namanya.
     */
    public static function prefiksAurorasys(?Company $c): string
    {
        return trim((string) ($c?->code ?? '')) ?: self::prefiksDari($c?->name);
    }

    /**
     * Daftar formulir SMKP untuk penyesuaian kop di Admin → Perusahaan:
     * nama, jenis & judul bawaan, dan nomor bawaan EQOHSEE bagi
     * perusahaan itu.
     *
     * @return array<string, array{nama:string,jenis:string,judul:string,kode:string,nomor:string,jenisNomor:string,urut:int}>
     */
    public static function formulirSmkp(?Company $c = null): array
    {
        $daftar = self::daftar();
        $hasil = [];

        foreach (self::FORMULIR_SMKP as $k) {
            $d = $daftar[$k];
            $jenisNomor = $d['jenis'] === 'LAPORAN' ? 'Laporan' : 'Formulir';
            $hasil[$k] = [
                'nama'  => $d['nama'] ?? $d['judul'],
                'jenis' => $d['jenis'],
                'judul' => $d['judul'],
                /* Kode formulir Aurorasys (HSE-IV.067h): contoh isian bagi
                   yang hendak menyamakan nomornya dengan berkas Aurorasys. */
                'kode'  => preg_replace('/^OHSE-/', 'HSE-', $d['kode']),
                'nomor' => Nomor::susun($jenisNomor, $c, (int) ($d['urut'] ?? 0)),
                'jenisNomor' => Nomor::jenis($jenisNomor),
                'urut'  => (int) ($d['urut'] ?? 0),
            ];
        }

        return $hasil;
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
