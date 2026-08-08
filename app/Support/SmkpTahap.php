<?php

namespace App\Support;

/**
 * Dua tahap audit SMKP dan berkas resmi yang menyertainya.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019, dibaca lewat berkas audit
 * nyata (PT Gunung Bara Utama 2023 dan PT CAM 2025) agar istilah dan urutan
 * isiannya sama persis dengan yang dipakai auditor di lapangan.
 *
 * Kelas ini hanya berisi daftar acuan dan hitungan. Ia tidak menyentuh basis
 * data — bentuk isiannya disimpan sebagai JSON pada model SmkpAudit.
 */
final class SmkpTahap
{
    public const PERMULAAN = 1;   // permulaan audit, peninjauan dokumen, persiapan lapangan
    public const LAPANGAN  = 2;   // rapat pembukaan s.d. rapat penutupan
    public const PELAPORAN = 3;   // laporan selesai

    public const LENGKAP       = 'lengkap';
    public const TIDAK_LENGKAP = 'tidak_lengkap';

    /* ================= tahap ================= */

    /** @return array<int,array{label:string,ket:string}> */
    public static function tahap(): array
    {
        return [
            self::PERMULAAN => [
                'label' => 'Tahap I — Permulaan Audit',
                'ket'   => 'Kontak awal, penentuan kelayakan, peninjauan kecukupan dokumentasi, dan penyiapan Rencana Audit.',
            ],
            self::LAPANGAN => [
                'label' => 'Tahap II — Audit Lapangan',
                'ket'   => 'Rapat pembukaan, pengumpulan dan verifikasi informasi, perumusan temuan, kesimpulan, rapat penutupan.',
            ],
            self::PELAPORAN => [
                'label' => 'Pelaporan',
                'ket'   => 'Laporan audit, rencana tindak lanjut, dan respon manajemen.',
            ],
        ];
    }

    public static function labelTahap(int $tahap): string
    {
        return self::tahap()[$tahap]['label'] ?? 'Tahap '.$tahap;
    }

    /** Lima kegiatan yang dicakup Tahap II — dasar penyusunan susunan kegiatan. */
    public static function kegiatanLapangan(): array
    {
        return [
            'Pelaksanaan Rapat Pembukaan',
            'Pengumpulan dan Verifikasi Informasi',
            'Perumusan Temuan Audit',
            'Penyiapan Kesimpulan Audit',
            'Pelaksanaan Rapat Penutupan',
        ];
    }

    /* ================= Tahap I ================= */

    /** Tujuh indikator penentuan kelayakan audit. */
    public static function indikatorKelayakan(): array
    {
        return [
            'profil_organisasi' => 'Informasi untuk pengembangan program audit: Profil Organisasi',
            'profil_risiko'     => 'Informasi untuk pengembangan program audit: Profil Risiko',
            'kinerja'           => 'Informasi untuk pengembangan program audit: Data Kinerja Keselamatan Pertambangan pada periode audit',
            'kerjasama'         => 'Kerja sama dari auditi',
            'waktu'             => 'Ketersediaan waktu',
            'sumberdaya'        => 'Ketersediaan sumber daya lainnya',
            'keselamatan'       => 'Pemenuhan persyaratan keselamatan dan keamanan',
        ];
    }

    /**
     * Tujuh kondisi dasar faktor penyesuaian hari kerja audit.
     * Tiap kondisi yang terpenuhi menambah hari audit.
     */
    public static function faktorPenyesuaian(): array
    {
        return [
            'jarak'      => 'Jarak antar objek audit yang saling berjauhan, dengan waktu tempuh ≥ 4 jam',
            'metode'     => 'Perusahaan pertambangan menggunakan lebih dari satu metode penambangan',
            'pengolahan' => 'Perusahaan pertambangan memiliki fasilitas pengolahan dan pemurnian',
            'kecelakaan' => 'Severity rate dan frequency rate kecelakaan tahun terakhir lebih tinggi dari rata-rata nasional',
            'penyakit'   => 'Absence severity rate dan morbidity frequency rate tahun terakhir lebih tinggi dari rata-rata nasional',
            'berbahaya'  => 'Terjadi kejadian berbahaya serupa dan berulang dalam satu tahun terakhir',
            'pak'        => 'Terjadi kejadian akibat penyakit tenaga kerja dan/atau penyakit akibat kerja dalam satu tahun terakhir',
        ];
    }

    /** Data kinerja Keselamatan Pertambangan pada periode audit. */
    public static function butirKinerja(): array
    {
        return [
            'cidera_ringan' => ['label' => 'Kecelakaan tambang berakibat cidera ringan', 'satuan' => 'kecelakaan'],
            'cidera_berat'  => ['label' => 'Kecelakaan tambang berakibat cidera berat',  'satuan' => 'kecelakaan'],
            'mati'          => ['label' => 'Kecelakaan tambang berakibat mati',           'satuan' => 'kecelakaan'],
            'fr'            => ['label' => 'Frequency rate kecelakaan tambang',           'satuan' => ''],
            'sr'            => ['label' => 'Severity rate kecelakaan tambang',            'satuan' => ''],
            'berbahaya'     => ['label' => 'Jumlah kejadian berbahaya',                   'satuan' => 'kejadian'],
            'asr'           => ['label' => 'Absence severity rate',                       'satuan' => ''],
            'mfr'           => ['label' => 'Morbidity frequency rate',                    'satuan' => ''],
            'kptk'          => ['label' => 'Kejadian akibat penyakit tenaga kerja',       'satuan' => 'kejadian'],
            'pak'           => ['label' => 'Frekuensi penyakit akibat kerja',             'satuan' => ''],
        ];
    }

    public static function kelasRisiko(): array
    {
        return ['Rendah', 'Sedang', 'Tinggi'];
    }

    /**
     * Hari kerja audit.
     *
     * Mandays dasar dibagi jumlah auditor, lalu ditambah faktor penyesuaian.
     * Tahap I mendapat maksimal 10% dari total, sisanya untuk Tahap II —
     * itulah sebabnya alokasi diturunkan, bukan diisi terpisah.
     *
     * @return array{dasar:float,auditor:int,per_auditor:float,penyesuaian:float,total:float,tahap1:float,tahap2:float,usul_penyesuaian:int}
     */
    public static function mandays(array $p): array
    {
        $dasar   = max(0.0, (float) ($p['mandays_dasar'] ?? 0));
        $auditor = max(1, (int) ($p['jumlah_auditor'] ?? 1));

        // Tiap kondisi yang dijawab "ya" mengusulkan tambahan satu hari.
        $usul = 0;
        foreach (array_keys(self::faktorPenyesuaian()) as $k) {
            if (!empty($p['faktor'][$k])) $usul++;
        }

        // Auditor boleh menetapkan angka lain; usulan hanya jadi bawaan.
        $tambah = array_key_exists('penyesuaian', $p) && $p['penyesuaian'] !== ''
            ? max(0.0, (float) $p['penyesuaian'])
            : (float) $usul;

        $per   = $dasar / $auditor;
        $total = $per + $tambah;

        return [
            'dasar'            => round($dasar, 2),
            'auditor'          => $auditor,
            'per_auditor'      => round($per, 2),
            'penyesuaian'      => round($tambah, 2),
            'usul_penyesuaian' => $usul,
            'total'            => round($total, 2),
            'tahap1'           => round($total * 0.10, 2),
            'tahap2'           => round($total * 0.90, 2),
        ];
    }

    /**
     * Kecukupan dokumentasi tujuh elemen.
     *
     * Elemen yang belum ditinjau dihitung belum lengkap — audit lapangan tidak
     * boleh terlihat siap hanya karena peninjauannya dilewati.
     *
     * @return array{lengkap:int,tidak:int,belum:int,total:int,siap:bool}
     */
    public static function rekapKecukupan(array $kecukupan): array
    {
        $lengkap = $tidak = $belum = 0;

        foreach (Smkp::elemen() as $e) {
            $s = $kecukupan[$e['kode']]['status'] ?? null;
            if ($s === self::LENGKAP)            $lengkap++;
            elseif ($s === self::TIDAK_LENGKAP)  $tidak++;
            else                                 $belum++;
        }

        $total = $lengkap + $tidak + $belum;

        return [
            'lengkap' => $lengkap,
            'tidak'   => $tidak,
            'belum'   => $belum,
            'total'   => $total,
            'siap'    => $belum === 0,
        ];
    }

    public static function labelKecukupan(?string $status): string
    {
        return match ($status) {
            self::LENGKAP       => 'Lengkap',
            self::TIDAK_LENGKAP => 'Tidak Lengkap',
            default             => 'Belum ditinjau',
        };
    }

    /* ================= Rencana Audit ================= */

    /**
     * Sembilan komponen wajib Rencana Audit Tahap II.
     *
     * Kunci di sini dipakai apa adanya sebagai nama ruas formulir dan sebagai
     * urutan bagian pada laporan, supaya keduanya tidak pernah berbeda.
     */
    public static function komponenRencana(): array
    {
        return [
            'tujuan' => [
                'judul' => 'Penetapan Tujuan Audit',
                'ket'   => 'Apa yang hendak dicapai audit ini bagi auditi.',
            ],
            'kriteria' => [
                'judul' => 'Penetapan Kriteria Audit',
                'ket'   => 'Acuan penilaian — Kepdirjen 185.K/37.04/DJB/2019 beserta dokumen internal auditi.',
            ],
            'ruang_lingkup' => [
                'judul' => 'Penetapan Ruang Lingkup Audit',
                'ket'   => 'Batas wilayah, kegiatan, dan periode yang diaudit.',
            ],
            'tanggal' => [
                'judul' => 'Penetapan Tanggal Pelaksanaan Audit',
                'ket'   => 'Rentang tanggal audit lapangan.',
            ],
            'susunan' => [
                'judul' => 'Penetapan Susunan Kegiatan Audit',
                'ket'   => 'Jadwal kegiatan dari rapat pembukaan sampai rapat penutupan.',
            ],
            'tugas' => [
                'judul' => 'Pembagian Tugas dan Tanggung Jawab Anggota Tim Audit',
                'ket'   => 'Siapa mengaudit elemen apa, lengkap dengan nomor registrasi auditor.',
            ],
            'sumberdaya' => [
                'judul' => 'Penetapan Alokasi Sumber Daya',
                'ket'   => 'Sarana, akomodasi, transportasi, dan alat pelindung diri yang disiapkan.',
            ],
            'metode' => [
                'judul' => 'Penetapan Metode dan Sampel Audit',
                'ket'   => 'Cara pembuktian dan dasar pengambilan sampel, mengacu pada top risks auditi.',
            ],
            'pengesahan' => [
                'judul' => 'Pengesahan Rencana Audit',
                'ket'   => 'Kepala Teknik Tambang, Penanggung Jawab Operasional (bila auditi perusahaan jasa), dan Ketua Tim Audit.',
            ],
        ];
    }

    /** Pihak yang mengesahkan Rencana Audit. */
    public static function pengesah(): array
    {
        return [
            'ktt'   => ['peran' => 'Kepala Teknik Tambang', 'wajib' => true],
            'pjo'   => ['peran' => 'Penanggung Jawab Operasional', 'wajib' => false],
            'ketua' => ['peran' => 'Ketua Tim Audit', 'wajib' => true],
        ];
    }

    /**
     * Kelengkapan Rencana Audit terhadap sembilan komponen wajib.
     *
     * Sebuah komponen dianggap terisi bila punya isi yang bermakna: untuk yang
     * berupa tabel, minimal satu baris; untuk tanggal, keduanya terisi; untuk
     * pengesahan, pihak yang wajib sudah bernama.
     *
     * @return array{terisi:array<string,bool>,jumlah:int,total:int,lengkap:bool,kurang:array<int,string>}
     */
    public static function rekapRencana(?array $rencana): array
    {
        $r = $rencana ?? [];
        $terisi = [];

        foreach (array_keys(self::komponenRencana()) as $k) {
            $terisi[$k] = match ($k) {
                'tanggal'    => !empty($r['tanggal_mulai']) && !empty($r['tanggal_selesai']),
                'susunan'    => self::adaBaris($r['susunan'] ?? [], ['kegiatan']),
                'tugas'      => self::adaBaris($r['tugas'] ?? [], ['nama']),
                'metode'     => trim((string) ($r['metode'] ?? '')) !== '',
                'pengesahan' => self::pengesahanLengkap($r['pengesahan'] ?? []),
                default      => trim((string) ($r[$k] ?? '')) !== '',
            };
        }

        $kurang = [];
        foreach (self::komponenRencana() as $k => $c) {
            if (!$terisi[$k]) $kurang[] = $c['judul'];
        }

        $jumlah = count(array_filter($terisi));

        return [
            'terisi'  => $terisi,
            'jumlah'  => $jumlah,
            'total'   => count($terisi),
            'lengkap' => $jumlah === count($terisi),
            'kurang'  => $kurang,
        ];
    }

    /** Baris tabel dianggap ada bila salah satu kolom wajibnya terisi. */
    private static function adaBaris($baris, array $kolom): bool
    {
        foreach ((array) $baris as $b) {
            foreach ($kolom as $k) {
                if (trim((string) (($b[$k] ?? ''))) !== '') return true;
            }
        }
        return false;
    }

    private static function pengesahanLengkap($p): bool
    {
        foreach (self::pengesah() as $k => $def) {
            if (!$def['wajib']) continue;
            if (trim((string) (((array) $p)[$k]['nama'] ?? '')) === '') return false;
        }
        return true;
    }

    /* ================= rapat Tahap II ================= */

    public static function rapat(): array
    {
        return [
            'pembukaan' => 'Rapat Pembukaan',
            'penutupan' => 'Rapat Penutupan',
        ];
    }

    public static function labelRapat(string $rapat): string
    {
        return self::rapat()[$rapat] ?? ucfirst($rapat);
    }
}
