<?php

namespace App\Support;

/**
 * Dua tahap audit SMKP dan berkas resmi yang menyertainya.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019. Istilah dan urutan isian
 * mengikuti formulir audit yang dipakai auditor di lapangan.
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

    /* ================= alur kerja ================= */

    /**
     * Alur audit sebagai empat babak, masing-masing berisi langkah tersendiri.
     *
     * Ini yang menggerakkan menu modul. Tiap langkah punya rutenya sendiri
     * supaya auditor melihat pekerjaan sebagai rangkaian yang jelas, bukan
     * satu daftar tautan yang rata.
     *
     * `jenis`: 'kerja' = formulir isian, 'cetak' = berkas resmi siap cetak.
     * `kunci` dipakai model untuk menentukan status tiap langkah.
     */
    public static function alur(): array
    {
        return [
            'permulaan' => [
                'nomor' => 'Tahap I',
                'judul' => 'Permulaan Audit',
                'ket'   => 'Menentukan apakah audit layak dijalankan dan apakah dokumentasi auditi cukup untuk diaudit.',
                'warna' => '#0F766E',
                'langkah' => [
                    ['kunci'=>'kontak',    'judul'=>'Kontak Awal & Penugasan Tim',   'jenis'=>'kerja', 'rute'=>'smkp.tahap1',
                     'ket'=>'Pertemuan pertama dengan auditi dan surat pengangkatan tim audit.'],
                    ['kunci'=>'kinerja',   'judul'=>'Kinerja Keselamatan Pertambangan','jenis'=>'kerja','rute'=>'smkp.tahap1',
                     'ket'=>'Sepuluh angka kinerja pada periode audit — frequency rate, severity rate, kejadian berbahaya, penyakit akibat kerja.'],
                    ['kunci'=>'kelayakan', 'judul'=>'Penentuan Kelayakan Audit',     'jenis'=>'kerja', 'rute'=>'smkp.tahap1',
                     'ket'=>'Tujuh indikator yang memutuskan audit boleh berjalan: profil organisasi, profil risiko, kerja sama auditi, sumber daya.'],
                    ['kunci'=>'mandays',   'judul'=>'Perhitungan Hari Kerja Audit',  'jenis'=>'kerja', 'rute'=>'smkp.tahap1',
                     'ket'=>'Mandays dibagi jumlah auditor; satu kondisi penyesuaian atau lebih menambah paling banyak 10%. Tahap I paling banyak 10% dari total.'],
                    ['kunci'=>'kecukupan', 'judul'=>'Kecukupan Dokumentasi 7 Elemen','jenis'=>'kerja', 'rute'=>'smkp.tahap1',
                     'ket'=>'Peninjauan dokumen dan rekaman tiap elemen — lengkap atau tidak lengkap — sebelum tim turun ke lapangan.'],
                    ['kunci'=>'berita',    'judul'=>'Berita Acara Tahapan Awal',     'jenis'=>'cetak', 'rute'=>'smkp.berita-acara',
                     'ket'=>'Berkas resmi hasil Tahap I, berkop dokumen terkendali dan siap ditandatangani KTT.'],
                ],
            ],

            'rencana' => [
                'nomor' => 'Tahap I·B',
                'judul' => 'Rencana Audit',
                'ket'   => 'Kesepakatan antara klien audit, tim audit, dan auditi mengenai pelaksanaan audit lapangan.',
                'warna' => '#2A9D8F',
                'langkah' => [
                    ['kunci'=>'lingkup',  'judul'=>'Tujuan, Kriteria & Ruang Lingkup','jenis'=>'kerja','rute'=>'smkp.rencana',
                     'ket'=>'Tiga komponen pertama: apa yang hendak dicapai, acuan penilaiannya, dan batas wilayah serta kegiatan yang diaudit.'],
                    ['kunci'=>'jadwal',   'judul'=>'Tanggal & Susunan Kegiatan',      'jenis'=>'kerja','rute'=>'smkp.rencana',
                     'ket'=>'Jadwal dari rapat pembukaan sampai rapat penutupan, dengan auditi dan auditor pada tiap sesi.'],
                    ['kunci'=>'tim',      'judul'=>'Pembagian Tugas Tim Audit',       'jenis'=>'kerja','rute'=>'smkp.rencana',
                     'ket'=>'Siapa mengaudit elemen apa, lengkap dengan nomor registrasi auditor DBT.'],
                    ['kunci'=>'sampel',   'judul'=>'Metode, Sampel & Top Risks',      'jenis'=>'kerja','rute'=>'smkp.rencana',
                     'ket'=>'Cara pembuktian dan dasar pengambilan sampel, mengacu risiko tertinggi periode berjalan dan rencana kegiatan berikutnya.'],
                    ['kunci'=>'sah',      'judul'=>'Pengesahan KTT & Ketua Tim',      'jenis'=>'kerja','rute'=>'smkp.rencana',
                     'ket'=>'Tanda tangan Kepala Teknik Tambang, Penanggung Jawab Operasional bila auditi perusahaan jasa, dan Ketua Tim Audit.'],
                    ['kunci'=>'rencana-cetak','judul'=>'Laporan Rencana Audit',       'jenis'=>'cetak','rute'=>'smkp.rencana.cetak',
                     'ket'=>'Sembilan komponen wajib dalam satu berkas bernomor, siap dibagikan ke auditi.'],
                ],
            ],

            'lapangan' => [
                'nomor' => 'Tahap II',
                'judul' => 'Audit Lapangan',
                'ket'   => 'Pengumpulan dan verifikasi informasi di lokasi, dari rapat pembukaan sampai rapat penutupan.',
                'warna' => '#D9993A',
                'langkah' => [
                    ['kunci'=>'pembukaan','judul'=>'Rapat Pembukaan',               'jenis'=>'kerja','rute'=>'smkp.rapat',
                     'ket'=>'Menyampaikan rencana audit kepada auditi dan mencatat daftar hadir yang menjadi lampiran laporan.'],
                    ['kunci'=>'nilai',    'judul'=>'Penilaian Tujuh Elemen',         'jenis'=>'kerja','rute'=>'smkp.show',
                     'ket'=>'Pemberian nilai tiap butir kriteria beserta keterangan dan bukti yang diperiksa di lapangan.'],
                    ['kunci'=>'temuan',   'judul'=>'Perumusan Temuan & Tindakan',    'jenis'=>'kerja','rute'=>'smkp.temuan',
                     'ket'=>'Ketidaksesuaian mayor dan minor diangkat menjadi tindakan perbaikan dengan akar masalah dan target selesai.'],
                    ['kunci'=>'penutupan','judul'=>'Rapat Penutupan',               'jenis'=>'kerja','rute'=>'smkp.rapat',
                     'ket'=>'Menyampaikan kesimpulan audit dan temuan kepada manajemen auditi, dengan daftar hadir tersendiri.'],
                ],
            ],

            'pelaporan' => [
                'nomor' => 'Tahap III',
                'judul' => 'Pelaporan',
                'ket'   => 'Berkas akhir audit dan tindak lanjut yang menjadi tanggung jawab auditi.',
                'warna' => '#FF7F50',
                'langkah' => [
                    ['kunci'=>'laporan',   'judul'=>'Laporan Audit Internal',        'jenis'=>'cetak','rute'=>'smkp.laporan',
                     'ket'=>'Nilai akhir, tingkat penerapan, rekapitulasi tujuh elemen, dan seluruh temuan dalam satu berkas bernomor.'],
                    ['kunci'=>'hadir-buka','judul'=>'Daftar Hadir Rapat Pembukaan',  'jenis'=>'cetak','rute'=>'smkp.hadir.cetak','arg'=>'pembukaan',
                     'ket'=>'Lampiran daftar hadir, berpindah halaman sendiri bila pesertanya banyak.'],
                    ['kunci'=>'hadir-tutup','judul'=>'Daftar Hadir Rapat Penutupan', 'jenis'=>'cetak','rute'=>'smkp.hadir.cetak','arg'=>'penutupan',
                     'ket'=>'Lampiran daftar hadir rapat penutupan.'],
                ],
            ],
        ];
    }

    /* ================= Tahap I ================= */

    /** Tujuh indikator penentuan kelayakan audit. */
    public static function indikatorKelayakan(): array
    {
        return [
            'profil_organisasi' => 'Informasi untuk Pengembangan Program Audit: Profil Organisasi',
            'profil_risiko'     => 'Informasi untuk Pengembangan Program Audit: Profil Risiko',
            'kinerja'           => 'Informasi untuk Pengembangan Program Audit: Data Kinerja Keselamatan Pertambangan pada Periode Audit',
            'kerjasama'         => 'Kerjasama dari Auditi',
            'waktu'             => 'Ketersediaan Waktu',
            'sumberdaya'        => 'Ketersediaan Sumberdaya lainnya',
            'keselamatan'       => 'Pemenuhan Persyaratan Keselamatan dan Keamanan',
        ];
    }

    /**
     * Tujuh kondisi dasar faktor penyesuaian hari kerja audit. Satu kondisi
     * atau lebih yang terpenuhi membuka tambahan hari kerja paling banyak
     * PENYESUAIAN_MAKS — bukan satu hari per kondisi.
     */
    /** Tambahan hari kerja audit dari faktor penyesuaian: paling banyak 10%. */
    public const PENYESUAIAN_MAKS = 0.10;

    public static function faktorPenyesuaian(): array
    {
        return [
            'jarak'      => 'Jarak antar objek audit yang saling berjauhan, dengan waktu tempuh ≥ 4 jam',
            'metode'     => 'Perusahaan pertambangan menggunakan lebih dari satu metode penambangan',
            'pengolahan' => 'Perusahaan pertambangan memiliki fasilitas pengolahan dan pemurnian',
            'kecelakaan' => 'Tingkat keparahan (severity rate) dan tingkat kekerapan (frequency rate) kecelakaan perusahaan tahun terakhir lebih tinggi dari tingkat rata-rata nasional',
            'penyakit'   => 'Tingkat keparahan penyakit berdasarkan absensi (absence severity rate) dan tingkat kekerapan kesakitan (morbidity frequency rate) perusahaan tahun terakhir lebih tinggi dari tingkat rata-rata nasional',
            'berbahaya'  => 'Terjadi kejadian berbahaya serupa dan berulang dalam satu tahun terakhir',
            'pak'        => 'Terjadi kejadian akibat penyakit tenaga kerja, dan/atau penyakit akibat kerja dalam satu tahun terakhir',
        ];
    }

    /** Data kinerja Keselamatan Pertambangan pada periode audit. */
    public static function butirKinerja(): array
    {
        return [
            'cidera_ringan' => ['label' => 'Jumlah Kecelakaan Tambang Berakibat Cidera Ringan', 'satuan' => 'kecelakaan'],
            'cidera_berat'  => ['label' => 'Jumlah Kecelakaan Tambang Berakibat Cidera Berat',  'satuan' => 'kecelakaan'],
            'mati'          => ['label' => 'Jumlah Kecelakaan Tambang Berakibat Mati',           'satuan' => 'kecelakaan'],
            'fr'            => ['label' => 'Frequency Rate Kecelakaan Tambang',                  'satuan' => ''],
            'sr'            => ['label' => 'Severity Rate Kecelakaan Tambang',                   'satuan' => ''],
            'berbahaya'     => ['label' => 'Jumlah Kejadian Berbahaya',                          'satuan' => 'kejadian'],
            'asr'           => ['label' => 'Absence Severity Rate',                              'satuan' => ''],
            'mfr'           => ['label' => 'Morbidity Frequency Rate',                           'satuan' => ''],
            'kptk'          => ['label' => 'Jumlah Kejadian Akibat Penyakit Tenaga Kerja',       'satuan' => 'kejadian'],
            'pak'           => ['label' => 'Frekuensi Penyakit Akibat Kerja',                    'satuan' => ''],
        ];
    }

    /**
     * Data trend pada Penyiapan Rencana Audit (Berita Acara Tahap I bagian
     * III.B, Lampiran I SE B-6450/MB.07/DBT.KP/2022) — dasar penetapan
     * metode dan sampel sebagai critical point pembuktian primer.
     */
    public static function trenData(): array
    {
        $kejadian = 'Near Miss, Property Damage, Kejadian Berbahaya, dan/atau Kecelakaan';

        return [
            'penyebab' => ['judul' => "Data Trend Faktor Penyebab {$kejadian}", 'kolom' => 'Faktor Penyebab Dominan'],
            'lokasi'   => ['judul' => "Data Trend Lokasi Terjadinya {$kejadian}", 'kolom' => 'Lokasi'],
            'kegiatan' => ['judul' => "Data Trend Jenis Kegiatan terkait Kejadian {$kejadian}", 'kolom' => 'Jenis Kegiatan'],
            'jabatan'  => ['judul' => "Data Trend Jenis Jabatan terkait Kejadian {$kejadian}", 'kolom' => 'Jenis Jabatan'],
            'deviasi'  => ['judul' => 'Data Trend Deviasi Keselamatan Pertambangan berdasarkan Hasil Temuan Inspeksi', 'kolom' => 'Deviasi Penerapan Keselamatan Pertambangan Hasil Inspeksi'],
            'pak'      => ['judul' => 'Data Trend Faktor Penyebab Kejadian Akibat Penyakit Tenaga Kerja dan/atau Penyakit Akibat Kerja', 'kolom' => 'Faktor Penyebab Dominan'],
        ];
    }

    /** Baris "Data Audit Internal SMKP" tahun sebelumnya pada Berita Acara Tahap I. */
    public static function auditLalu(): array
    {
        $tl = 'Status Penyelesaian Tindak Lanjut Audit untuk Ketidaksesuaian Kategori';

        return [
            'nilai'    => 'Capaian Nilai Audit',
            'kritikal' => "{$tl} Kritikal",
            'mayor'    => "{$tl} Mayor",
            'minor'    => "{$tl} Minor",
            'pembina'  => 'Catatan Hasil Evaluasi dari Instansi Pembina Sektor',
        ];
    }

    /** Distribusi Laporan Audit (Kepdirjen 185.K/37.04/DJB/2019 — sistematika laporan). */
    public const DISTRIBUSI_LAPORAN = [
        'Pimpinan pemegang IUP, IUPK, IUP Operasi Produksi khusus untuk Pengolahan dan/atau Pemurnian, IPR, atau IUJP',
        'Kementerian yang menyelenggarakan urusan pemerintahan di bidang pertambangan mineral dan batubara',
        'Dinas teknis provinsi yang mempunyai tugas pokok dan fungsi di bidang pertambangan mineral dan batubara pada pemerintah provinsi',
    ];

    /** Key leading indicator yang lazim dipantau — usulan isian, dapat diubah. */
    public const KLI_BAWAAN = [
        'Tujuan, Sasaran dan Program',
        'Pencapaian Tingkat Kinerja Keselamatan Pertambangan',
        'Zero Fatality',
        'Zero LTI',
        'Zero Health',
        'Zero PDFR',
    ];

    public static function kelasRisiko(): array
    {
        return ['Rendah', 'Sedang', 'Tinggi'];
    }

    /**
     * Hari kerja audit.
     *
     * Mandays dasar dibagi jumlah auditor, lalu ditambah faktor penyesuaian:
     * bila satu kondisi atau lebih terpenuhi, paling banyak 10% dari hari
     * kerja tersebut (seberapa pun banyak kondisinya); tanpa kondisi, tidak
     * ada tambahan. Tambahan maksimum menjadi bawaan bila auditor tidak
     * menetapkan angka lain. Tahap I mendapat maksimal 10% dari total,
     * sisanya untuk Tahap II — itulah sebabnya alokasi diturunkan, bukan
     * diisi terpisah.
     *
     * @return array{dasar:float,auditor:int,per_auditor:float,kondisi:int,maks_penyesuaian:float,usul_penyesuaian:float,penyesuaian:float,persen_penyesuaian:float,total:float,tahap1:float,tahap2:float}
     */
    public static function mandays(array $p): array
    {
        $dasar   = max(0.0, (float) ($p['mandays_dasar'] ?? 0));
        $auditor = max(1, (int) ($p['jumlah_auditor'] ?? 1));
        $per     = $dasar / $auditor;

        $kondisi = 0;
        foreach (array_keys(self::faktorPenyesuaian()) as $k) {
            if (!empty($p['faktor'][$k])) $kondisi++;
        }
        $maks = $kondisi > 0 ? self::batasPenyesuaian($per) : 0.0;

        // Auditor boleh menetapkan angka lebih kecil; di atas batas dipangkas
        // (isian lama yang tersimpan sebelum aturan ini).
        $tambah = array_key_exists('penyesuaian', $p) && trim((string) $p['penyesuaian']) !== ''
            ? min(max(0.0, (float) $p['penyesuaian']), $maks)
            : $maks;

        $total = $per + $tambah;

        return [
            'dasar'              => round($dasar, 2),
            'auditor'            => $auditor,
            'per_auditor'        => round($per, 2),
            'kondisi'            => $kondisi,
            'maks_penyesuaian'   => $maks,
            'usul_penyesuaian'   => $maks,
            'penyesuaian'        => round($tambah, 2),
            'persen_penyesuaian' => $per > 0 ? round($tambah / $per * 100, 1) : 0.0,
            'total'              => round($total, 2),
            'tahap1'             => round($total * 0.10, 2),
            'tahap2'             => round($total * 0.90, 2),
        ];
    }

    /** Tambahan hari kerja terbesar yang dibolehkan untuk $hari hari kerja audit (dua desimal, dibulatkan ke bawah). */
    public static function batasPenyesuaian(float $hari): float
    {
        return floor(max(0.0, $hari) * self::PENYESUAIAN_MAKS * 100 + 1e-9) / 100;
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

    /* ---------- isian bawaan Formulir Rencana Audit (Lampiran Kepdirjen 185.K/2019) ---------- */

    public const JENIS_AUDIT = [
        'Audit Internal Sistem Manajemen Keselamatan Pertambangan Mineral dan Batubara',
        'Audit Internal Sistem Manajemen Keselamatan Pertambangan Khusus untuk Pengolahan dan/atau Pemurnian',
    ];

    public const KRITERIA_AUDIT = 'Lampiran II Keputusan Direktur Jenderal Mineral dan Batubara Nomor 185.K/37.04/DJB/2019';

    public static function dokumentasiBawaan(): string
    {
        return implode("\n", [
            'Manual Sistem Manajemen Keselamatan Pertambangan',
            'Dokumen dan Rekaman Elemen Kebijakan',
            'Dokumen dan Rekaman Elemen Perencanaan',
            'Dokumen dan Rekaman Elemen Organisasi dan Personel',
            'Dokumen dan Rekaman Elemen Implementasi',
            'Dokumen dan Rekaman Elemen Pemantauan, Evaluasi, dan Tindak Lanjut',
            'Dokumen dan Rekaman Elemen Dokumentasi',
            'Dokumen dan Rekaman Elemen Tinjauan Manajemen dan Peningkatan Kinerja',
        ]);
    }

    public static function fasilitasBawaan(): string
    {
        return implode("\n", [
            'Ruangan untuk rapat pembukaan dan rapat penutupan audit serta penyusunan laporan',
            'Peralatan dan perlengkapan penunjang pelaksanaan audit, termasuk audit lapangan',
            'Pemandu untuk pelaksanaan kontak dan waktu wawancara, pengaturan kunjungan lokasi, pemastian prosedur keselamatan di lokasi diketahui tim audit, serta bantuan pengumpulan informasi',
        ]);
    }

    /** Distribusi laporan menurut jenis auditi. */
    public static function distribusiBawaan(?string $kategori): string
    {
        $penanggung = $kategori === 'pjp'
            ? 'Penanggung Jawab Operasional perusahaan klien audit (salinan)'
            : 'Kepala Teknik Tambang perusahaan klien audit (salinan)';

        return implode("\n", [
            'Pimpinan perusahaan klien audit (asli)',
            $penanggung,
            'Tim Audit (salinan)',
            'Direktur Teknik dan Lingkungan Mineral dan Batubara/Kepala Inspektur Tambang (salinan)',
        ]);
    }

    /** Pihak yang mengesahkan Rencana Audit. */
    /**
     * Pihak yang mengesahkan Rencana Audit. Auditi perusahaan jasa
     * pertambangan (PJP) wajib menyertakan Penanggung Jawab Operasionalnya.
     */
    public static function pengesah(?string $kategori = null): array
    {
        return [
            'ktt'   => ['peran' => 'Kepala Teknik Tambang', 'wajib' => true],
            'pjo'   => ['peran' => 'Penanggung Jawab Operasional', 'wajib' => $kategori === 'pjp'],
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
    public static function rekapRencana(?array $rencana, ?string $kategori = null): array
    {
        $r = $rencana ?? [];
        $terisi = [];

        foreach (array_keys(self::komponenRencana()) as $k) {
            $terisi[$k] = match ($k) {
                'tanggal'    => !empty($r['tanggal_mulai']) && !empty($r['tanggal_selesai']),
                'susunan'    => self::adaBaris($r['susunan'] ?? [], ['kegiatan']),
                'tugas'      => self::adaBaris($r['tugas'] ?? [], ['nama']),
                'metode'     => trim((string) ($r['metode'] ?? '')) !== '',
                'pengesahan' => self::pengesahanLengkap($r['pengesahan'] ?? [], $kategori),
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

    private static function pengesahanLengkap($p, ?string $kategori): bool
    {
        foreach (self::pengesah($kategori) as $k => $def) {
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
