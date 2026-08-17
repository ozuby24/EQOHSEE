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
                     'ket'=>'Pertemuan pertama dengan auditi dan surat pengangkatan tim audit beserta nomor registrasi auditor.'],
                    ['kunci'=>'kinerja',   'judul'=>'Kinerja Keselamatan Pertambangan','jenis'=>'kerja','rute'=>'smkp.tahap1',
                     'ket'=>'Sepuluh angka kinerja pada periode audit — frequency rate, severity rate, kejadian berbahaya, penyakit akibat kerja.'],
                    ['kunci'=>'kelayakan', 'judul'=>'Penentuan Kelayakan Audit',     'jenis'=>'kerja', 'rute'=>'smkp.tahap1',
                     'ket'=>'Tujuh indikator yang memutuskan audit boleh berjalan: profil organisasi, profil risiko, kerja sama auditi, sumber daya.'],
                    ['kunci'=>'mandays',   'judul'=>'Perhitungan Hari Kerja Audit',  'jenis'=>'kerja', 'rute'=>'smkp.tahap1',
                     'ket'=>'Mandays dibagi jumlah auditor lalu disesuaikan tujuh kondisi; Tahap I paling banyak 10% dari total.'],
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
     * Tabel mandays dasar: jumlah pekerja × kelas risiko.
     *
     * Tiap baris [pekerja minimum, pekerja maksimum, Tinggi, Menengah, Rendah].
     * Pekerja yang melampaui baris terakhir memakai baris terakhir.
     *
     * CATATAN SUMBER, dan ini penting sebelum angkanya dipakai menagih hari
     * kerja auditor: tabel ini pola ISO/IEC 17021 sebagai DEFAULT, bukan
     * salinan angka Kepdirjen 185.K/37.04/DJB/2019. Berkas acuan yang
     * menjadi sumbernya menyatakannya sendiri demikian. Angkanya berada di
     * satu tempat ini supaya dapat diganti begitu ketentuan yang berlaku
     * bagi perusahaan diketahui pasti.
     */
    public const MANDAYS_TABLE = [
        [1, 5, 3, 2, 2],          [6, 10, 4, 3, 2],         [11, 15, 5, 4, 3],
        [16, 25, 6, 5, 4],        [26, 45, 8, 6, 4],        [46, 65, 9, 7, 5],
        [66, 85, 10, 8, 6],       [86, 125, 11, 9, 7],      [126, 175, 12, 10, 8],
        [176, 275, 13, 11, 9],    [276, 425, 15, 12, 10],   [426, 625, 16, 13, 11],
        [626, 875, 17, 14, 12],   [876, 1175, 18, 15, 13],  [1176, 1550, 19, 16, 14],
        [1551, 2025, 20, 17, 15], [2026, 3450, 21, 18, 16], [3451, 5450, 22, 19, 17],
        [5451, 10700, 23, 20, 18], [10701, 999999, 25, 22, 20],
    ];

    /** Kolom tabel mandays menurut kelas risiko. */
    private const KOLOM_RISIKO = ['Tinggi' => 2, 'Menengah' => 3, 'Rendah' => 4];

    /**
     * Kondisi yang MENAMBAH hari kerja audit. Tiap yang terpenuhi menambah
     * satu hari.
     */
    public static function faktorPenyesuaian(): array
    {
        return [
            'jarak'       => 'Lokasi/area kerja berjauhan (waktu tempuh ≥ 4 jam antar objek audit)',
            'metode'      => 'Menggunakan lebih dari satu metode penambangan',
            'pengolahan'  => 'Memiliki fasilitas pengolahan dan/atau pemurnian',
            'kecelakaan'  => 'Severity/Frequency rate kecelakaan tahun terakhir > rata-rata nasional',
            'berbahaya'   => 'Terjadi kejadian berbahaya serupa & berulang dalam 1 tahun terakhir',
            'kompleksitas'=> 'Kompleksitas proses / teknologi pertambangan tinggi',
        ];
    }

    /**
     * Kondisi yang MENGURANGI hari kerja audit.
     *
     * Sebelumnya tidak ada sama sekali, dan ketiadaannya bukan netral:
     * mandays hanya dapat bertambah, sehingga perusahaan yang sistem
     * manajemennya matang dan audit sebelumnya bersih tetap ditagih hari
     * sebanyak yang paling bermasalah.
     */
    public static function faktorPengurang(): array
    {
        return [
            'terintegrasi' => 'Sistem manajemen K3 terintegrasi & matang (mis. ISO 45001 tersertifikasi)',
            'kepatuhan'    => 'Riwayat kepatuhan & kinerja keselamatan sangat baik',
            'tanpa_mayor'  => 'Audit periode sebelumnya tanpa temuan kategori Mayor',
            'shift'        => 'Jumlah shift / area kerja terbatas',
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
        return array_keys(self::KOLOM_RISIKO);
    }

    /**
     * Baris tabel mandays yang berlaku bagi sejumlah pekerja.
     *
     * DUA UJUNG DIPERLAKUKAN BERBEDA, dan itu keharusan. Yang melampaui baris
     * terakhir memakai baris terakhir — masuk akal, tambang terbesar memang
     * menuntut hari terbanyak. Yang jatuh DI BAWAH baris pertama memakai
     * baris PERTAMA, bukan terakhir.
     *
     * Bedanya bukan teoretis: jumlah pekerja yang belum diisi terbaca sebagai
     * nol, dan nol tidak masuk baris mana pun. Jatuh ke baris terakhir, audit
     * yang datanya belum lengkap menagih 25 hari — angka yang tampak seperti
     * jawaban sungguhan, tidak menimbulkan galat, dan karena itu tidak pernah
     * dipertanyakan. Jatuh ke baris pertama, ia menampilkan angka terkecil
     * yang jelas menuntut diisi.
     */
    public static function barisMandays(int $pekerja): array
    {
        $awal   = self::MANDAYS_TABLE[0];
        $akhir  = self::MANDAYS_TABLE[count(self::MANDAYS_TABLE) - 1];

        if ($pekerja < $awal[0]) return $awal;

        foreach (self::MANDAYS_TABLE as $r) {
            if ($pekerja >= $r[0] && $pekerja <= $r[1]) return $r;
        }

        return $akhir;
    }

    /**
     * Hari kerja audit.
     *
     * MANDAYS DASAR TIDAK DIKETIK, melainkan dibaca dari tabel menurut jumlah
     * pekerja auditi dan kelas risikonya. Angka yang diketik tangan tidak
     * dapat ditelusuri kembali ke dasarnya, dan dua auditor yang mengetik
     * berbeda untuk perusahaan yang sama menghasilkan tagihan hari berbeda
     * tanpa satu pun yang salah menurut sistem.
     *
     *   dasar  = tabel[pekerja][kelas risiko]
     *   total  = maks(1, dasar + faktor penambah − faktor pengurang)
     *
     * MANDAYS DAN DURASI ITU DUA HAL BERBEDA, dan menyatukannya adalah cacat
     * yang diperbaiki di sini. Mandays satuan USAHA (orang-hari); durasi
     * satuan WAKTU di lapangan. Empat auditor mengerjakan 12 mandays dalam
     * 3 hari — jumlah auditor membagi durasinya, bukan usahanya. Sebelumnya
     * pembagian itu dikenakan pada mandays, sehingga menambah auditor
     * seolah-olah mengurangi beban audit.
     *
     *   durasi = total ÷ jumlah auditor
     *   tahap1 = maks(1, durasi × 10%)   tahap2 = durasi − tahap1
     *
     * @return array{pekerja:int,kelas:string,rentang:string,dasar:int,penambah:int,
     *               pengurang:int,total:int,auditor:int,durasi:float,tahap1:float,tahap2:float}
     */
    public static function mandays(array $p): array
    {
        $pekerja = max(0, (int) ($p['jumlah_pekerja'] ?? 0));
        $kelas   = (string) ($p['kelas_risiko'] ?? '');
        $kelas   = isset(self::KOLOM_RISIKO[$kelas]) ? $kelas : 'Tinggi';
        $auditor = max(1, (int) ($p['jumlah_auditor'] ?? 1));

        $baris = self::barisMandays($pekerja);
        $dasar = (int) $baris[self::KOLOM_RISIKO[$kelas]];

        $penambah  = self::hitungFaktor($p['faktor'] ?? [], self::faktorPenyesuaian());
        $pengurang = self::hitungFaktor($p['pengurang'] ?? [], self::faktorPengurang());

        // Sekurang-kurangnya satu hari: faktor pengurang tidak boleh
        // menghabiskan audit sampai nol hari.
        $total = max(1, $dasar + $penambah - $pengurang);

        $durasi = round($total / $auditor, 1);
        $tahap1 = max(1.0, round($durasi * 0.10, 1));
        $tahap2 = max(0.0, round($durasi - $tahap1, 1));

        return [
            'pekerja'   => $pekerja,
            'kelas'     => $kelas,
            'rentang'   => $baris[0].'–'.($baris[1] >= 999999 ? '∞' : $baris[1]),
            'dasar'     => $dasar,
            'penambah'  => $penambah,
            'pengurang' => $pengurang,
            'total'     => $total,
            'auditor'   => $auditor,
            'durasi'    => $durasi,
            'tahap1'    => $tahap1,
            'tahap2'    => $tahap2,
        ];
    }

    /** Berapa kondisi yang dicentang dari sebuah daftar faktor. */
    private static function hitungFaktor($jawab, array $daftar): int
    {
        $n = 0;
        foreach (array_keys($daftar) as $k) {
            if (!empty(((array) $jawab)[$k])) $n++;
        }

        return $n;
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

    /**
     * Keselarasan Rencana Audit terhadap hitungan hari kerja audit.
     *
     * Tahap I menghitung berapa hari audit ini menuntut; Rencana Audit
     * menetapkan kapan audit dijalankan dan oleh siapa. Keduanya disimpan
     * terpisah dan sampai sekarang tidak pernah dibandingkan — sehingga
     * rencana yang menjadwalkan tiga hari untuk audit yang menuntut empat
     * belas hari tetap lolos sebagai "lengkap", dan selisihnya baru
     * ketahuan di lapangan pada hari terakhir.
     *
     * Yang dikembalikan penilaian, bukan penolakan. Auditor boleh punya
     * alasan menyimpang — yang tidak boleh adalah menyimpang tanpa tahu.
     *
     * @return list<array{kunci:string,judul:string,selaras:bool,ket:string}>
     */
    public static function selarasRencana(?array $rencana, array $mandays): array
    {
        $r = (array) ($rencana ?? []);

        // Auditor yang benar-benar bernama pada pembagian tugas.
        $tim = 0;
        foreach ((array) ($r['tugas'] ?? []) as $b) {
            if (trim((string) ($b['nama'] ?? '')) !== '') $tim++;
        }

        $pembagi = (int) ($mandays['auditor'] ?? 1);
        $tahap2  = (float) ($mandays['tahap2'] ?? 0);
        $hari    = self::rentangHari($r['tanggal_mulai'] ?? null, $r['tanggal_selesai'] ?? null);
        $luar    = self::susunanDiLuarRentang($r);

        return [
            [
                'kunci'   => 'auditor',
                'judul'   => 'Jumlah auditor',
                // Tim yang belum diisi sama sekali bukan ketidakselarasan,
                // hanya pekerjaan yang belum dimulai.
                'selaras' => $tim === 0 || $tim === $pembagi,
                'ket'     => $tim === 0
                    ? 'Pembagian tugas belum diisi; hitungan memakai '.$pembagi.' auditor.'
                    : ($tim === $pembagi
                        ? $tim.' auditor, sama dengan pembagi durasi.'
                        : $tim.' auditor pada pembagian tugas, tetapi durasi dibagi '.$pembagi.'. Durasi di lapangan tidak lagi benar.'),
            ],
            [
                'kunci'   => 'tanggal',
                'judul'   => 'Rentang tanggal audit',
                'selaras' => $hari === null || $hari >= $tahap2,
                'ket'     => $hari === null
                    ? 'Tanggal pelaksanaan belum ditetapkan.'
                    : ($hari >= $tahap2
                        ? $hari.' hari dijadwalkan untuk alokasi Tahap II '.$tahap2.' hari.'
                        : 'Hanya '.$hari.' hari dijadwalkan, sedangkan Tahap II menuntut '.$tahap2.' hari.'),
            ],
            [
                'kunci'   => 'susunan',
                'judul'   => 'Susunan kegiatan',
                'selaras' => $luar === 0,
                'ket'     => $luar === 0
                    ? 'Seluruh kegiatan berada dalam rentang tanggal audit.'
                    : $luar.' kegiatan dijadwalkan di luar rentang tanggal audit.',
            ],
        ];
    }

    /** Jumlah hari kalender sebuah rentang, ujung ke ujung. Null bila belum lengkap. */
    private static function rentangHari($mulai, $selesai): ?int
    {
        if (blank($mulai) || blank($selesai)) return null;

        try {
            $a = \Illuminate\Support\Carbon::parse($mulai)->startOfDay();
            $b = \Illuminate\Support\Carbon::parse($selesai)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        // Sehari penuh dihitung satu hari, bukan nol.
        return $b->lessThan($a) ? null : (int) $a->diffInDays($b) + 1;
    }

    /** Berapa baris susunan kegiatan yang tanggalnya jatuh di luar rentang audit. */
    private static function susunanDiLuarRentang(array $r): int
    {
        if (blank($r['tanggal_mulai'] ?? null) || blank($r['tanggal_selesai'] ?? null)) return 0;

        try {
            $a = \Illuminate\Support\Carbon::parse($r['tanggal_mulai'])->startOfDay();
            $b = \Illuminate\Support\Carbon::parse($r['tanggal_selesai'])->endOfDay();
        } catch (\Throwable) {
            return 0;
        }

        $n = 0;
        foreach ((array) ($r['susunan'] ?? []) as $baris) {
            $t = $baris['tanggal'] ?? null;
            if (blank($t)) continue;

            try {
                $hari = \Illuminate\Support\Carbon::parse($t);
            } catch (\Throwable) {
                continue;
            }

            if ($hari->lessThan($a) || $hari->greaterThan($b)) $n++;
        }

        return $n;
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
