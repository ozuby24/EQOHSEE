<?php

namespace App\Support\Miners;

/**
 * Angka dan daftar yang mengikat pada modul Miners, di satu tempat.
 *
 * ACUANNYA DUA BERKAS RESMI, bukan kebiasaan yang berjalan:
 *
 *   · 001-SPM-007  Standar Mine Permit / KIMPER
 *   · 007-SOP-OHSE Penerbitan Mine Permit (ID) – SIMPER, revisi III,
 *     berlaku 30 Agustus 2025
 *
 * Keduanya ada pada repo Project1 di docs/sop/. Bila perilaku aplikasi
 * berbeda dari SOP, SOP yang menang — dan yang harus diubah adalah
 * berkas ini, bukan cabang `if` di dalam controller.
 *
 * MENGAPA SEBAGAI KELAS, BUKAN TERSEBAR DI FORM DAN VIEW
 *
 * Pada Project1 angka-angka ini memang tersebar: ambang nilai post test
 * ditulis di dalam controller, daftar kelas SIMPER diketik ulang sebagai
 * <option> di lima berkas Blade, dan masa berlaku permit dihitung di
 * tempat yang sama sekali berbeda dari tempat ia dibaca. Akibatnya
 * bukan kerepotan melainkan perbedaan: SOP menulis nilai kelulusan 80,
 * satu formulir menuliskannya 70, dan tidak ada satu tempat pun yang
 * dapat ditunjuk sebagai yang benar.
 *
 * SATU KETIDAKKONSISTENAN SOP SENGAJA DICATAT, BUKAN DIRAPIKAN.
 *
 * Ketentuan Umum menetapkan nilai kelulusan post test 80; daftar syarat
 * pengajuan baru pada dokumen yang sama menulis 70%. Yang dipakai di
 * sini adalah yang lebih ketat, dan perbedaannya ditulis apa adanya di
 * bawah supaya orang berikutnya tidak menyangka salah ketik lalu
 * "memperbaikinya" menjadi lebih longgar.
 */
final class Acuan
{
    public const DOKUMEN = [
        'nomor'   => '007-SOP-OHSE',
        'judul'   => 'Penerbitan Mine Permit (ID) – SIMPER',
        'revisi'  => 'III',
        'efektif' => '2025-08-30',
    ];

    /* ═══════════════════ MINE PERMIT ═══════════════════ */

    /**
     * Tiga jenis permit beserta masa berlakunya.
     *
     * `hari` NULL berarti aturan tahunan: berlaku sampai 31 Desember
     * tahun terbit. Itu bukan "tanpa batas" — justru sebaliknya, ia
     * batas yang paling ketat di antara ketiganya bagi permit yang
     * terbit di bulan Desember.
     */
    public const JENIS_PERMIT = [
        'full' => [
            'label'        => 'Full Permit',
            'keterangan'   => 'Karyawan tetap, bekerja lebih dari satu bulan.',
            'hari'         => null,
            'boleh_simper' => true,
            'kategori'     => ['Umum', 'Blasting Permit', 'Magazine Permit'],
        ],
        'temporary' => [
            'label'        => 'Temporary Permit',
            'keterangan'   => 'Karyawan sementara, paling lama satu bulan, pekerjaan non-operasional berisiko rendah.',
            'hari'         => 30,
            'boleh_simper' => false,
            'kategori'     => ['Umum'],
        ],
        'visitor' => [
            'label'        => 'Visitor Permit',
            'keterangan'   => 'Tamu perusahaan: rapat, inspeksi, supervisi, audit.',
            'hari'         => 7,
            'boleh_simper' => false,
            'kategori'     => ['Kunjungan', 'Inspeksi / Audit', 'Supervisi'],
        ],
    ];

    /**
     * Zona akses — AREA MANA yang boleh dimasuki.
     *
     * Berbeda dari warna kartu di bawah, dan keduanya memang dua hal
     * yang berbeda: zona menyatakan area, warna menyatakan unit apa
     * yang boleh dikendarai. Seorang admin logistik dapat berzona
     * Restricted dan berwarna putih sekaligus; operator dump truck
     * berzona Unrestricted dan berwarna merah.
     *
     * UNRESTRICTED LEBIH LUAS DARIPADA RESTRICTED, meski namanya
     * terbaca sebaliknya oleh telinga Indonesia. Restricted di sini
     * berarti "dibatasi pada perkantoran"; Unrestricted berarti "boleh
     * masuk area operasional sesuai yang diberikan". Salah membaca
     * pasangan ini menukar kewenangan tersempit dengan yang terluas.
     */
    public const ZONA_AKSES = [
        'FULL' => [
            'label'   => 'Full Access',
            'cakupan' => 'Seluruh area operasional pertambangan.',
            'contoh'  => 'OHSE, PJO perusahaan',
        ],
        'UNRESTRICTED' => [
            'label'   => 'Unrestricted Access',
            'cakupan' => 'Pit, jetty, ROM stockpile, jalan hauling, area proyek, fasilitas tambang dan jetty — sesuai yang diberikan.',
            'contoh'  => 'Operator, pengawas produksi',
        ],
        'RESTRICTED' => [
            'label'   => 'Restricted Access',
            'cakupan' => 'Hanya area perkantoran: site office, workshop, jetty office.',
            'contoh'  => 'HRD, karyawan kantin',
        ],
    ];

    /**
     * Warna strip kartu — UNIT APA yang boleh dikendarai.
     *
     * Acuannya 004-SPM-006.SOP-OHSE butir 7–8. Urutan di bawah adalah
     * URUTAN KEWENANGAN, bukan urutan daftar: seorang operator dump
     * truck hampir selalu juga boleh mengemudikan light vehicle, dan
     * yang dicetak pada kartunya adalah yang tertinggi. Menampilkan
     * golongan yang lebih rendah daripada yang sebenarnya dipegang akan
     * menahan orang yang berwenang di pos jaga.
     */
    public const WARNA_KARTU = [
        'merah' => ['label' => 'A2B / unit mining', 'hex' => '#E8232A'],
        'biru'  => ['label' => 'Unit support',      'hex' => '#1F2FBE'],
        'hijau' => ['label' => 'Sarana (light vehicle)', 'hex' => '#1D7A28'],
        'putih' => ['label' => 'Tidak mengendarai unit', 'hex' => '#FFFFFF'],
    ];

    /* ═══════════════════ SIMPER ═══════════════════ */

    /** Lima kelas SIMPER. MP bukan kelas SIMPER — ia permit tanpa SIMPER. */
    public const KELAS_SIMPER = [
        'PR' => ['label' => 'SIMPER-PR', 'keterangan' => 'Probation. Wajib didampingi pemegang SIMPER-F atau trainer sampai jam terbang terpenuhi.'],
        'F'  => ['label' => 'SIMPER-F',  'keterangan' => 'Full. Wewenang penuh atas satu unit utama.'],
        'R1' => ['label' => 'SIMPER-R1', 'keterangan' => 'Mekanik: inspeksi unit, menghidupkan engine, menggerakkan attachment, memindahkan unit tanpa beban kurang dari 500 m, ground test.'],
        'R2' => ['label' => 'SIMPER-R2', 'keterangan' => 'Pengawas produksi: memindahkan unit ke tempat aman dan/atau dalam keadaan darurat.'],
        'I'  => ['label' => 'SIMPER-I',  'keterangan' => 'Instruktur. Melatih teori dan praktik, boleh mengoperasikan LV dan alat berat.'],
    ];

    /** Kelas SIM Kepolisian yang dikenali matriks SOP. */
    public const KELAS_SIMPOL = ['A', 'B1', 'B1 Umum', 'B2 Umum'];

    /**
     * Golongan unit — matriks SIMPOL, kewajiban SIO, warna, batas usia.
     *
     * Empat belas baris pertama adalah matriks SIMPOL SOP apa adanya.
     * Forklift dan Manitou ditambahkan karena 004-SPM-006 menyebut
     * keduanya sebagai unit support, sedangkan matriks SIMPOL tidak
     * memuatnya sama sekali — `simpol` NULL menyatakan persis itu:
     * SOP belum menetapkan kelas SIM bagi golongan ini, bukan bahwa
     * mengemudikannya tidak menuntut SIM apa pun.
     *
     * `usia` diturunkan dari kelas SIM, bukan dikarang: SOP menetapkan
     * light vehicle 18–50 dan alat berat 21–50, dan yang membedakan
     * keduanya pada matriks adalah B2 Umum. Golongan tanpa kelas SIM
     * mengikuti batas alat berat — yang lebih ketat.
     */
    public const GOLONGAN_UNIT = [
        ['nama' => 'Light Vehicle',     'kode' => 'LV',  'simpol' => 'A',       'sio' => false, 'warna' => 'hijau'],
        ['nama' => 'Passenger Car',     'kode' => null,  'simpol' => 'B1',      'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Man Haul',          'kode' => null,  'simpol' => 'B1',      'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Bus',               'kode' => 'BUS', 'simpol' => 'B1',      'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Elf',               'kode' => 'ELF', 'simpol' => 'B1',      'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Mini Bus',          'kode' => null,  'simpol' => 'B1',      'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Lube Truck',        'kode' => 'LT',  'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Crane Truck',       'kode' => 'CR',  'simpol' => 'B2 Umum', 'sio' => true,  'warna' => 'biru'],
        ['nama' => 'Service Truck',     'kode' => null,  'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Fuel Truck',        'kode' => 'FT',  'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Water Truck',       'kode' => 'WT',  'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Off Highway Truck', 'kode' => 'HDT', 'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'merah',
            'rincian' => ['Heavy Dump Truck', 'Articulated Dump Truck']],
        ['nama' => 'Dump Truck',        'kode' => 'DT',  'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'merah',
            'rincian' => ['Dump Truck PS', 'Dump Truck Tronton']],
        ['nama' => 'Alat Berat',        'kode' => null,  'simpol' => 'B2 Umum', 'sio' => false, 'warna' => 'merah',
            'rincian' => ['Excavator', 'Bulldozer', 'Motor Grader', 'Wheel Loader', 'Compactor']],
        ['nama' => 'Forklift',          'kode' => 'FL',  'simpol' => null,      'sio' => false, 'warna' => 'biru'],
        ['nama' => 'Manitou',           'kode' => 'MT',  'simpol' => null,      'sio' => false, 'warna' => 'biru'],
    ];

    /** Batas usia operator — Ketentuan Umum SOP. */
    public const BATAS_USIA = [
        'ringan' => ['min' => 18, 'maks' => 50],
        'berat'  => ['min' => 21, 'maks' => 50],
    ];

    /* ═══════════════════ INDUKSI ═══════════════════ */

    /**
     * Post test induksi.
     *
     * Ketentuan Umum SOP menetapkan 80 baik untuk pengajuan baru maupun
     * perpanjangan. Daftar syarat pengajuan baru pada dokumen yang sama
     * menulis 70% — SOP tidak konsisten di dua bagian itu, dan yang
     * dipakai di sini adalah yang lebih ketat. Jangan "memperbaikinya"
     * menjadi 70 tanpa penegasan tertulis dari OHSE.
     *
     * `maks_remidi` 2 berarti PALING BANYAK TIGA KALI ujian: satu kali
     * pertama ditambah dua kali mengulang.
     */
    public const POST_TEST = [
        'nilai_lulus'  => 80,
        'maks_remidi'  => 2,
        'maks_ujian'   => 3,
    ];

    /* ═══════════════════ MASA BERLAKU ═══════════════════ */

    public const MASA = [
        /* Mine Permit dan SIMPER berlaku sampai 31 Desember tahun terbit. */
        'permit_akhir_tahun' => true,
        'simper_akhir_tahun' => true,

        /* MCU berlaku satu tahun sejak tanggal pelaksanaan. */
        'mcu_bulan' => 12,

        /* Induksi keselamatan diulang setahun sekali — SOP menuntut
           "refresh induksi" pada tiap perpanjangan tahunan.
           DIPISAHKAN DARI `mcu_bulan` meski angkanya kebetulan sama:
           keduanya dua aturan yang berbeda, dan satu kunci dipakai
           berdua berarti revisi masa berlaku MCU diam-diam ikut
           menggeser masa berlaku sertifikat induksi. */
        'induksi_bulan' => 12,

        /* MCU ulang paling lambat dua minggu sebelum periodenya
           berakhir. Lewat dari itu Mine Permit dan SIMPER DICABUT,
           tanpa toleransi — bunyi SOP, bukan tafsir. */
        'peringatan_mcu_hari' => 14,

        /* Perpanjangan dapat diajukan sebulan sebelum berakhir. */
        'buka_perpanjangan_hari' => 30,

        /* Umur MCU paling tua yang masih diterima, per jenis pengajuan. */
        'maks_umur_mcu_perpanjangan_hari' => 30,
        'maks_umur_mcu_baru_hari'         => 30,
        'maks_umur_mcu_mutasi_hari'       => 180,
    ];

    /* ═══════════════════ DATA INDUK ═══════════════════ */

    /** Lokasi kerja berdasarkan risiko — 001-SPM-007. */
    public const LOKASI_KERJA = [
        'PIT', 'Stock ROM', 'Workshop', 'Main Office',
        'Jetty', 'Jetty Office', 'Fuel Storage', 'Nursery',
    ];

    public const DEPARTEMEN = [
        ['nama' => 'Produksi',       'kode' => 'PRD'],
        ['nama' => 'Plant',          'kode' => 'PLT'],
        ['nama' => 'HSE',            'kode' => 'HSE'],
        ['nama' => 'Logistik',       'kode' => 'LOG'],
        ['nama' => 'Engineering',    'kode' => 'ENG'],
        ['nama' => 'General Affair', 'kode' => 'GA'],
        ['nama' => 'Human Resource', 'kode' => 'HR'],
        ['nama' => 'Finance',        'kode' => 'FIN'],
    ];

    public const JABATAN = [
        'Superintendent Produksi', 'Supervisor Produksi', 'Mine Supervisor',
        'Driver HDT', 'Driver DT', 'Driver LV', 'Operator Excavator',
        'Master Operator', 'Operator', 'Mekanik', 'Foreman Hauling',
        'Fuel Controller', 'Admin Plant', 'Admin Logistik',
        'GA Site Koordinator', 'Safety Officer', 'HSE Admin Staff',
    ];

    /**
     * Hasil MCU, beserta nilai dan kelayakannya.
     *
     * "BELUM DINILAI" ADALAH KEADAAN AWAL, BUKAN SEBUAH HASIL — dan ia
     * harus ada di daftar. Tanpa itu, baris MCU yang baru dibuat
     * terpaksa memilih salah satu hasil yang sebenarnya, dan yang
     * pertama pada daftar biasanya "Fit": seorang pekerja tertandai
     * layak bekerja sebelum seorang dokter pun memeriksanya, pada
     * sistem yang justru dipakai untuk menahan orang yang tidak layak.
     *
     * `layak` TIDAK DITURUNKAN DARI `nilai`, dan itu bukan
     * kelebihan kolom. "Fit With Note" bernilai di bawah Fit penuh —
     * ada catatan medis yang harus diperhatikan — tetapi orangnya TETAP
     * boleh bekerja. Ambang berapa pun yang dipasang pada `nilai` akan
     * salah pada salah satu dari keduanya.
     */
    public const HASIL_MCU = [
        ['nama' => 'Belum Dinilai',   'nilai' => 0,   'layak' => false],
        ['nama' => 'Fit',             'nilai' => 100, 'layak' => true],
        ['nama' => 'Fit With Note',   'nilai' => 80,  'layak' => true],
        ['nama' => 'Temporary Unfit', 'nilai' => 40,  'layak' => false],
        ['nama' => 'Unfit',           'nilai' => 0,   'layak' => false],
    ];

    /* ═══════════════════ KELENGKAPAN BERKAS ═══════════════════ */

    /**
     * Lampiran wajib per jenis pengajuan — SOP butir "Persyaratan".
     *
     * Dipakai sebagai daftar periksa, bukan hiasan: tombol kirim ke
     * OHSE baru terbuka setelah yang wajib terpenuhi. Di Project1
     * pengajuan tetap dapat naik dengan lampiran kurang, dan
     * akibatnya persis yang dikeluhkan pemakainya — berkas bolak-balik
     * antara mitra dan OHSE berhari-hari.
     */
    public const BERKAS_WAJIB = [
        'permit_baru' => [
            'Form permohonan Mine Permit (ID) karyawan baru',
            'Daftar hadir induksi keselamatan',
            'Hasil evaluasi (post test) induksi keselamatan',
            'Hasil MCU dari klinik, dinyatakan Fit untuk bekerja',
            'Hasil pemeriksaan alkohol dan narkoba (negatif 0%)',
            'Surat Rekomendasi Sehat dari Dokter Perusahaan',
            'Fotokopi KTP',
            'Pas foto latar biru 3×4',
            'Form SPDK bertanda tangan pemohon',
        ],
        'permit_perpanjangan' => [
            'Form permohonan Mine Permit (ID) karyawan (perpanjangan)',
            'Daftar hadir refresh induksi keselamatan',
            'Hasil evaluasi (post test) refresh induksi keselamatan',
            'Hasil MCU dari klinik, dinyatakan Fit untuk bekerja',
            'Hasil pemeriksaan alkohol dan narkoba (negatif 0%)',
            'Surat Rekomendasi Sehat dari Dokter Perusahaan',
            'Fotokopi KTP',
            'Pas foto latar biru 3×4',
            'Form SPDK bertanda tangan pemohon',
        ],
        'permit_visitor' => [
            'Form permohonan ID Visitor Permit',
            'Lembar induksi keselamatan untuk tamu',
            'Fotokopi KTP',
        ],
        'simper_baru' => [
            'Daftar hadir induksi keselamatan',
            'Hasil evaluasi induksi keselamatan',
            'Hasil MCU dari klinik, dinyatakan Fit untuk bekerja',
            'Surat Rekomendasi Sehat dari Dokter Perusahaan (Pre Employment)',
            'Hasil pemeriksaan alkohol dan narkoba (negatif 0%)',
            'Fotokopi KTP',
            'Fotokopi SIMPOL yang sesuai',
            'SIO yang masih berlaku sesuai jenis dan kelas unit',
            'Sertifikat kompetensi dengan nilai kelulusan',
            'Lembar Pengesahan SIMPER',
            'Lembar SPDK',
            'Pas foto latar biru 3×4',
        ],
        'simper_perpanjangan' => [
            'Daftar hadir refresh induksi keselamatan',
            'Hasil evaluasi induksi keselamatan',
            'Hasil MCU dari klinik, dinyatakan Fit untuk bekerja',
            'Surat Rekomendasi Sehat dari Dokter Perusahaan (annual)',
            'Hasil pemeriksaan alkohol dan narkoba (negatif 0%)',
            'Fotokopi KTP',
            'Fotokopi SIMPOL yang sesuai',
            'SIO yang masih berlaku sesuai jenis dan kelas unit',
            'Sertifikat refresh kompetensi dengan nilai kelulusan',
            'Lembar Pengesahan SIMPER',
            'Lembar SPDK',
            'Pas foto latar biru 3×4',
        ],
        'simper_penambahan' => [
            'Sertifikat refresh kompetensi / skill up dengan nilai kelulusan',
            'Daftar hadir dan hasil evaluasi induksi keselamatan',
            'Hasil MCU dari klinik, dinyatakan Fit untuk bekerja',
            'Surat Rekomendasi Sehat dari Dokter Perusahaan',
            'Hasil pemeriksaan alkohol dan narkoba (negatif 0%)',
            'Fotokopi KTP',
            'Fotokopi SIMPOL yang sesuai',
            'Rekaman SIO yang masih berlaku sesuai jenis dan kelas unit',
            'Lembar Pengesahan SIMPER',
            'Lembar SPDK',
            'Pas foto latar biru 3×4',
        ],
    ];

    /* ═══════════════════ ALUR ═══════════════════ */

    /**
     * Alur pengesahan menurut SOP, dengan langkah dokter di depan.
     *
     * LANGKAH KTT ADA DI SINI UNTUK MINE PERMIT, dan itu perbedaan
     * paling penting dari Project1. Di sana Mine Permit terbit sesudah
     * OHSE saja — dokumen kesesuaiannya sendiri menandainya
     * "bertentangan dengan SOP" — sehingga kartu yang dibawa ke gerbang
     * tidak pernah disahkan Kepala Teknik Tambang, orang yang justru
     * bertanggung jawab atasnya di hadapan Inspektur Tambang.
     */
    public const ALUR = [
        ['peran' => 'pjo',    'label' => 'Mitra Kerja / PJO', 'aksi' => 'Mengajukan permohonan beserta lampiran persyaratan.'],
        ['peran' => 'dokter', 'label' => 'Dokter Perusahaan', 'aksi' => 'Memvalidasi hasil MCU dan menerbitkan rekomendasi FIT.'],
        ['peran' => 'ohse',   'label' => 'OHSE',              'aksi' => 'Memvalidasi kelengkapan berkas. Yang tidak lolos dikembalikan ke pemohon.'],
        ['peran' => 'ktt',    'label' => 'Kepala Teknik Tambang', 'aksi' => 'Mengesahkan permohonan yang lolos validasi OHSE.'],
    ];

    /* ═══════════════════ TURUNAN ═══════════════════ */

    /** Kelas SIM Kepolisian yang dituntut satu golongan unit. */
    public static function simpol(string $golongan): ?string
    {
        return self::golongan($golongan)['simpol'] ?? null;
    }

    /** Golongan yang menuntut Surat Izin Operator dari Disnaker. */
    public static function wajibSio(string $golongan): bool
    {
        return (bool) (self::golongan($golongan)['sio'] ?? false);
    }

    /**
     * Batas usia operator satu golongan.
     *
     * Yang berkelas SIM A atau B1 mengikuti batas light vehicle; sisanya
     * — termasuk golongan yang belum berkelas SIM — mengikuti batas alat
     * berat, yang lebih ketat. Menebak ke arah yang lebih longgar akan
     * meloloskan operator berusia 18 tahun ke atas dump truck.
     *
     * @return array{min:int,maks:int}
     */
    public static function batasUsia(?string $golongan): array
    {
        $simpol = $golongan === null ? null : self::simpol($golongan);

        return in_array($simpol, ['A', 'B1'], true)
            ? self::BATAS_USIA['ringan']
            : self::BATAS_USIA['berat'];
    }

    /** @return array<string,mixed>|null */
    public static function golongan(string $nama): ?array
    {
        foreach (self::GOLONGAN_UNIT as $g) {
            if (mb_strtolower($g['nama']) === mb_strtolower($nama)) return $g;
        }

        return null;
    }

    /**
     * Warna kartu tertinggi di antara sekumpulan golongan unit.
     *
     * Putih bila tidak satu pun golongan dipegang — "tidak mengendarai
     * unit", yang memang arti warna itu pada SOP.
     *
     * @param  iterable<string>  $golongan
     */
    public static function warnaTertinggi(iterable $golongan): string
    {
        $urutan = array_keys(self::WARNA_KARTU);   // merah, biru, hijau, putih
        $paling = count($urutan) - 1;

        foreach ($golongan as $nama) {
            $warna = self::golongan((string) $nama)['warna'] ?? null;
            if ($warna === null) continue;

            /* Lapis kedua, di belakang pemeriksaan datanya.
               `array_search` menjawab `false` bila tak ketemu, dan
               `(int) false` adalah 0 — indeks MERAH, kewenangan
               TERTINGGI. Satu nama warna yang salah ketik pada daftar
               di atas akan mencetak strip merah pada kartu orang yang
               tidak mengendarai apa pun.
               Yang menjaganya lebih dahulu adalah
               MinersMasterTest::test_tiap_golongan_berwarna_kartu_yang_dikenali,
               yang menolak warna di luar WARNA_KARTU sejak di datanya;
               selama uji itu hijau, cabang ini memang tak pernah
               terlewati. Ia tetap ditulis karena yang dijaga bukan
               kekeliruan hari ini melainkan baris yang ditambahkan
               orang lain nanti, dan kegagalannya tidak berbunyi. */
            $letak = array_search($warna, $urutan, true);
            if ($letak === false) continue;

            $paling = min($paling, $letak);
        }

        return $urutan[$paling];
    }

    /** @return list<string> lampiran wajib bagi satu jenis pengajuan. */
    public static function berkasWajib(string $jenis): array
    {
        return self::BERKAS_WAJIB[$jenis] ?? [];
    }
}
