<?php

namespace App\Support;

use Illuminate\Support\Facades\Request;

/**
 * Menu bilah samping — sumber tunggal untuk seluruh modul.
 *
 * Sebelumnya larik ini tertulis di dalam layouts/app.blade.php. Selama
 * hanya Blade yang membacanya itu memadai; begitu ada tampilan kedua
 * (Vue lewat Inertia) yang perlu menu yang sama, larik di dalam view
 * tidak dapat dijangkau tanpa merender view-nya. Menyalinnya ke sisi
 * Vue berarti dua daftar menu yang harus diubah bersama setiap kali ada
 * halaman baru — dan yang tertinggal tidak menimbulkan galat, hanya
 * menu yang diam-diam berbeda antara dua halaman.
 *
 * Bentuk tiap butir menu: [label, nama rute, pola URL untuk penanda aktif].
 */
final class Menu
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
    /* Dasbor menyeluruh berdiri paling depan: ia satu-satunya halaman
       yang menyebut keadaan SELURUH modul, dan karena itu tempat yang
       benar untuk memulai hari. Terpisah dari dasbor Learning Center,
       yang menjawab pertanyaan seorang peserta tentang kursusnya
       sendiri, bukan pertanyaan seorang pengawas tentang situsnya. */
    'dasbor' => [
      'label' => 'Dasbor',
      'icon'  => 'M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-4H4v4Zm10-11h6V4h-6v5Z',
      'groups' => [
        '' => [
          ['Ringkasan Situs', 'dasbor', 'dasbor'],
        ],
      ],
    ],
    /* Personalia berdiri di depan Learning Center: yang diurus di sini
       bukan pembelajaran melainkan siapa penggunanya dan di bawah
       perusahaan mana ia bekerja — jawaban yang dipakai hampir seluruh
       modul lain, termasuk kop dokumen dan nama pada sertifikat. */
    'personalia' => [
      'label' => 'Personalia',
      'icon'  => 'M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm-7.5 8c0-3.5 3.4-5.6 7.5-5.6s7.5 2.1 7.5 5.6',
      'groups' => [
        '' => [
          ['Data Diri',        'personalia.index',      'personalia'],
          ['Data Perusahaan',  'personalia.perusahaan', 'personalia/perusahaan'],
          ['Direktori',        'personalia.direktori',  'personalia/direktori'],
          ['Pesan',            'pesan.index',            'pesan'],
        ],
      ],
    ],
    'lms' => [
      'label' => 'Learning Center',
      'icon'  => 'M12 4 3 8l9 4 9-4-9-4zM7 10.5V15c0 1.3 2.7 2.3 5 2.3s5-1 5-2.3v-4.5',
      'groups' => [
        '' => [
          ['Dashboard',      'dashboard',          'dashboard'],
          ['Kursus',         'courses.index',      'courses*'],
          ['Prosedur & SOP', 'procedures.index',   'procedures*'],
          ['Evaluasi SOP',   'sop.index',          'sop*'],
          ['Sertifikat',     'certificates.index', 'certificates*'],
          ['Berita',         'news.index',         'news*'],
          ['Evaluasi',       'evaluations.index',  'evaluations*'],
        ],
      ],
    ],
    /* Tepat sesudah LMS: keduanya berbicara tentang orang yang sama.
       LMS menerbitkan sertifikat pelatihannya; Miners menyimpan seluruh
       sisanya — kelayakan kerja, MCU, induksi, kartu masuk, jadwal
       kehadiran, dan cutinya.

       Namanya Miners, bukan Authority, dan itu bukan sekadar penggantian
       kata. "Authority" adalah SATU isi di dalamnya: paspor kompetensi.
       Selama modulnya dinamai menurut salah satu isinya, tidak ada
       tempat yang jelas bagi field break dan cuti tahunan — keduanya
       jelas tentang orang yang sama, tetapi jelas pula bukan
       "authority", sehingga akan mendarat di modul lain dan memutus
       hubungannya dengan berkas orang itu. */
    'miners' => [
      'label' => 'Miners',

      /* Palet Safe Track — pasir, teal, coral — bukan navy-jingga milik
         seluruh aplikasi. Modul ini dan Investigasi mengurus dokumen
         yang sama-sama dibaca di luar kantor: kartu yang dicetak dan
         dibawa ke gerbang, berkas yang diminta Inspektur Tambang. Warna
         pasirnya membedakan keduanya dari modul harian, dan pembedaan
         itu yang membuat orang tahu ia sedang berada di berkas resmi
         tanpa membaca judulnya lebih dulu.

         Ditulis di sini, bukan di tiap berkas Vue: satu modul punya
         belasan halaman, dan palet yang ditempelkan per halaman akan
         tertinggal pada halaman yang ditambahkan berikutnya. */
      'tema'  => 'safetrack',
      'icon'  => 'M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 14l2 2 4-4',
      /* SUSUNANNYA MENGIKUTI PROJECT1, bukan urutan pembuatannya di sini.
         Di sana bilah sampingnya terbagi menurut PERTANYAAN yang dijawab
         tiap kelompok, bukan menurut jenis dokumennya:

           Pendaftaran  apa yang saya ajukan hari ini
           Riwayat      apa yang pernah terjadi pada dokumen ini
           Data         siapa orangnya dan apa hasilnya
           Pemantauan   apa yang harus dikerjakan sebelum kedaluwarsa

         Bedanya terasa pada MCU, yang muncul di tiga kelompok sekaligus
         dengan arti berbeda: mengajukan surat ke klinik, menelusuri
         hasil yang pernah keluar, dan menagih rujukan yang belum
         ditindaklanjuti. Digabung menjadi satu entri "MCU", ketiganya
         menjadi satu halaman yang tidak menjawab satu pun di antaranya
         dengan baik.

         Kelompok Project1 yang belum punya layarnya — Outstanding,
         SIMPER Lanjutan, Master Data, Cetak Kartu — sengaja BELUM
         didaftarkan di sini. Entri menu yang menunjuk rute yang belum
         ada memulangkan galat saat ditekan, dan menu yang salah satu
         entrinya galat membuat seluruh menunya berhenti dipercaya. */
      'groups' => [
        '' => [
          ['Ringkasan',       'miners.dasbor',    'miners/dasbor*'],
        ],

        'Pendaftaran' => [
          ['MCU',       'miners.mcu.index',     'miners/mcu*'],
          ['Induksi',   'miners.induksi.index', 'miners/induksi*'],
        ],

        /* Urutannya MENGIKUTI ALURNYA, bukan abjad dan bukan urutan
           pembuatannya. Bilah samping adalah tempat orang belajar
           urutan sebuah proses tanpa membaca petunjuk; menyusunnya
           menurut abjad membuang pelajaran itu, dan yang menggantikannya
           adalah tebakan.

           Alurnya: MCU menentukan Mine Permit, Mine Permit menentukan
           SIMPER. Kompetensi berdiri di sampingnya, bukan di dalamnya. */
        'Riwayat' => [
          ['MCU',           'miners.riwayat.mcu',          'miners/riwayat/mcu'],
          ['Induksi',       'miners.riwayat.induksi',      'miners/riwayat/induksi'],
          ['Mine Permit',   'miners.riwayat.mine-permit',  'miners/riwayat/mine-permit'],
          ['SIMPER',        'miners.riwayat.mine-license', 'miners/riwayat/mine-license'],

          /* Dulu bernama "Authority", dan itu keliru sejak awal:
             rutenya memanggil riwayatKompetensi() dan yang ditampilkan
             daftar sertifikat kompetensi. Nama lamanya berasal dari
             Authority Passport milik DBEST — dokumen yang berbeda, dan
             yang justru tidak dibawa Project1. */
          ['Kompetensi',    'miners.riwayat.authority',    'miners/riwayat/authority'],
        ],

        /* Antrean di meja SAYA. Berdiri sendiri, bukan di bawah
           Riwayat: riwayat menjawab "apa yang pernah terjadi", yang ini
           menjawab "apa yang menunggu keputusan saya pagi ini". Bagi
           yang bukan peninjau, ketiganya kosong — dan itu jawaban yang
           benar, bukan kerusakan. */
        'Outstanding' => [
          ['MCU',         'miners.daftar.outstanding-mcu',     'miners/daftar/outstanding-mcu'],
          ['Mine Permit', 'miners.daftar.outstanding-permit',  'miners/daftar/outstanding-permit'],
          ['SIMPER',      'miners.daftar.outstanding-simper',  'miners/daftar/outstanding-simper'],
          ['Induksi',     'miners.daftar.outstanding-induksi', 'miners/daftar/outstanding-induksi'],
        ],

        /* Ketiganya sudah termodelkan sejak dulu — dua sebagai sebab
           terbit kartu, satu sebagai baris unit — dan hanya belum punya
           layarnya. */
        'SIMPER Lanjutan' => [
          ['Penambahan Unit', 'miners.daftar.penambahan-unit', 'miners/daftar/penambahan-unit'],
          ['Upgrade SIMPER',  'miners.daftar.upgrade-simper',  'miners/daftar/upgrade-simper'],
          ['Perpanjangan',    'miners.daftar.perpanjangan',    'miners/daftar/perpanjangan'],
        ],

        'Data' => [
          ['Manpower', 'miners.index',           'miners'],
          ['Rujukan',  'miners.daftar.rujukan',  'miners/daftar/rujukan'],
        ],

        /* Pemantauan berdiri sendiri, bukan di bawah Riwayat: riwayat
           menjawab "apa yang pernah terjadi", yang ini menjawab "apa
           yang harus dikerjakan hari ini". */
        'Pemantauan' => [
          ['Masa Berlaku', 'miners.kedaluwarsa',        'miners/kedaluwarsa'],
          ['Cetak Kartu',  'miners.daftar.cetak-kartu', 'miners/daftar/cetak-kartu'],
        ],
      ],
    ],
    /* Investigasi berdiri sendiri, BUKAN di bawah Miners maupun Hazard
       Report. Miners menjawab "boleh atau tidak orang ini bekerja hari
       ini"; Hazard Report menjawab "ada bahaya, tolong ditangani".
       Yang ini menjawab pertanyaan ketiga yang sama sekali lain —
       "mengapa kejadian ini terjadi" — dan jawabannya berupa berkas
       yang dapat diminta Inspektur Tambang. Menyelipkannya ke dalam
       salah satu modul di atas membuat berkas itu hanya dapat
       ditemukan lewat halaman seorang pekerja atau lewat satu laporan
       bahaya, padahal yang dicari selalu kejadiannya. */
    'investigasi' => [
      'label' => 'Investigasi',
      'tema'  => 'safetrack',
      'icon'  => 'M21 21l-5.2-5.2M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z',
      'groups' => [
        '' => [
          ['Ringkasan',  'investigasi.dasbor',  'investigasi'],
        ],
        'Kejadian' => [
          ['Register Insiden', 'investigasi.insiden',      'investigasi/insiden*'],
          ['Lapor Insiden',    'investigasi.insiden.baru', 'investigasi/insiden/baru'],
        ],
        'Berkas' => [
          ['Investigasi', 'investigasi.daftar', 'investigasi/berkas*'],
        ],
      ],
    ],
    /* Perusahaan Jasa berdiri sendiri, BUKAN di bawah SMKP Audit.
       SMKP Audit menilai sistem keselamatan pemegang IUP atas
       pekerjaannya sendiri; yang ini menilai PIHAK LAIN yang
       dipekerjakan di wilayah izinnya, dengan daftar pertanyaan,
       bobot, dan akibat hukum yang berbeda. Menyelipkannya ke dalam
       menu SMKP membuat orang yang mencari berkas mitra membuka
       audit internal, dan sebaliknya. */
    'pjp' => [
      'label' => 'Perusahaan Jasa',
      'tema'  => 'safetrack',
      'icon'  => 'M4 21h9M6 21V5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v6M9 8h1M9 11.5h1M14.5 17.5l2.2 2.2 4.3-4.3',
      'groups' => [
        '' => [
          ['Ringkasan', 'pjp.dasbor', 'pjp'],
        ],
        'Mitra' => [
          ['Daftar Perusahaan Jasa', 'pjp.index', 'pjp/daftar*'],
          ['Tambah Perusahaan Jasa', 'pjp.baru',  'pjp/baru'],
        ],
      ],
    ],
    'tpkkp' => [
      'label' => 'Safety Maturity Level',
      'icon'  => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
      'groups' => [
        'Penilaian' => [
          ['Beranda',        'tpkkp.index',     'tpkkp'],
          ['Profil',         'tpkkp.profile',   'tpkkp/profil'],
          ['Formulir Nilai', 'tpkkp.assess',    'tpkkp/penilaian'],
          ['Kuesioner',      'tpkkp.kuesioner', 'tpkkp/kuesioner'],
          ['Pengujian',      'tpkkp.pengujian', 'tpkkp/pengujian'],
        ],
        'Hasil & Analisis' => [
          ['Rekapitulasi',   'tpkkp.rekap',     'tpkkp/rekap'],
          ['Visualisasi',    'tpkkp.visual',    'tpkkp/visual'],
          ['Program',        'tpkkp.program',   'tpkkp/program'],
        ],
        'Alat Bantu' => [
          ['Kalkulator Slovin','tpkkp.sampling','tpkkp/sampling'],
          ['Metode Data',      'tpkkp.metode',  'tpkkp/metode'],
          ['Tentang Regulasi', 'tpkkp.tentang', 'tpkkp/tentang'],
        ],
      ],
    ],
    'hazrep' => [
      'label' => 'Hazard & Inspeksi',
      'icon'  => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
      'groups' => [
        'Hazard Report' => [
          ['Monitor Laporan', 'hazard.index',     'hazard'],
          ['Buat Laporan',    'hazard.create',    'hazard/buat'],
          ['Analitik & KPI',  'hazard.analytics', 'hazard/analitik'],
          ['Evaluasi Temuan', 'hazard.evaluasi',  'hazard/evaluasi'],
          ['Pengingat PIC',   'hazard.pengingat', 'hazard/pengingat'],
        ],
        'Lintas Modul' => [
          ['Register Temuan', 'temuan.index', 'temuan'],
        ],
        'Inspeksi' => [
          ['Jenis & Parameter','inspeksi.template.index','inspeksi/jenis*'],
          ['Daftar Inspeksi',  'inspeksi.index',         'inspeksi'],
          ['Buat Inspeksi',    'inspeksi.create',        'inspeksi/buat'],
          ['KPI Inspeksi',     'inspeksi.kpi',           'inspeksi/kpi'],
        ],
      ],
    ],
    'ko' => [
      'label' => 'Keselamatan Operasi',
      'icon'  => 'M12 3l7.5 4v5c0 4.4-3.1 8.5-7.5 9.7C7.6 20.5 4.5 16.4 4.5 12V7L12 3zm-1.1 11.4l-2.2-2.2-1.3 1.4 3.5 3.5 6-6-1.4-1.4-4.6 4.7z',
      'groups' => [
        'Monitoring SPIP' => [
          ['Dashboard KO',   'ko.index',     'ko'],
          ['Register SPIP',  'ko.register',  'ko/register'],
          ['Kelayakan',      'ko.kelayakan', 'ko/kelayakan'],
          ['Jenis Unit',     'ko.unit',      'ko/unit*'],
          ['Uji Kelayakan',  'ko.uji',       'ko/uji*'],
          ['Perawatan',      'ko.perawatan', 'ko/perawatan'],
          ['Pengaman',       'ko.pengaman',  'ko/pengaman'],
        ],
        'Kepatuhan' => [
          ['Kajian Teknis',  'ko.kajian',     'ko/kajian'],
          ['Tenaga Teknis',  'ko.tenaga',     'ko/tenaga'],
          ['Tindak Lanjut',  'ko.tindak',     'ko/tindak*'],
        ],
      ],
    ],
    'smkp' => [
      'label' => 'Audit SMKP',
      'icon'  => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      // Menu mengikuti tahapan audit, bukan satu daftar rata. Butir pada
      // kelompok "Tahap" dan "Berkas" disalurkan ke periode yang sedang
      // berjalan, sebab menu samping tidak membawa identitas audit.
      'groups' => [
        '' => [
          /* Dasbor mendahului daftar periode. Yang dibuka manajemen
             bukan "audit mana yang ada" melainkan "apakah kami
             membaik" — dan daftar tidak menjawab pertanyaan kedua. */
          ['Performa SMKP', 'smkp.dasbor', 'smkp/dasbor'],
        ],
        'Periode' => [
          ['Daftar Audit',   'smkp.index',  'smkp'],
          ['Buat Periode',   'smkp.create', 'smkp/buat'],
        ],
        /* Urut sesuai jalannya audit. Form Penilaian ditaruh SESUDAH
           Permulaan Audit dan Rencana Audit — keduanya yang menetapkan
           lingkup dan sampel, dan menilai sebelum lingkupnya disepakati
           berarti menilai butir yang belum tentu berlaku. Halamannya
           sendiri tetap dapat dibuka lebih awal; yang dijaga urutannya,
           bukan pintunya. */
        'Tahap Audit' => [
          ['Permulaan Audit',   'smkp.ke.tahap1',    'smkp/lanjut/tahap-1'],
          ['Rencana Audit',     'smkp.ke.rencana',   'smkp/lanjut/rencana'],
          ['Form Penilaian Audit','smkp.ke.penilaian','smkp/lanjut/penilaian'],
          ['Rapat & Daftar Hadir','smkp.ke.rapat',   'smkp/lanjut/rapat'],
          ['Temuan & Tindakan', 'smkp.ke.temuan',    'smkp/lanjut/temuan'],
        ],
        /* Delapan keluaran audit, URUT SESUAI BERKASNYA — bukan urut
           abjad dan bukan urut pembuatannya di sini. Berkas audit
           diserahkan sebagai satu bundel bernomor, dan menu yang
           urutannya berbeda dari bundelnya memaksa penyusunnya
           mencocokkan sendiri satu per satu. */
        'Berkas Resmi' => [
          ['1 · Formulir Kriteria',   'smkp.ke.kriteria',       'smkp/lanjut/kriteria'],
          ['2 · Rekap Ketidaksesuaian','smkp.ke.rekap-nc',      'smkp/lanjut/rekap-nc'],
          ['3 · Respon Manajemen',    'smkp.ke.respon',         'smkp/lanjut/respon'],
          ['4 · Rencana Tindak Lanjut','smkp.ke.rencana-tindak','smkp/lanjut/rencana-tindak'],
          ['5 · Berita Acara Tahap I','smkp.ke.berita',         'smkp/lanjut/berita-acara'],
          ['6 · Laporan Rencana Audit','smkp.ke.rencana-cetak', 'smkp/lanjut/laporan-rencana'],
          ['7 · Laporan Audit',       'smkp.ke.laporan',        'smkp/lanjut/laporan-audit'],
          ['8 · NC & Tindak Lanjut',  'smkp.ke.nc-tindak',      'smkp/lanjut/nc-tindak'],

          /* Lembar OFI berdiri SESUDAH kedelapan berkas wajib, dan
             bernomor terpisah. Ia bukan berkas kesembilan dalam bundel
             yang sama: kedelapan di atas melaporkan ketidaksesuaian,
             yang ini melaporkan yang sudah memenuhi seluruhnya. */
          ['Peluang Perbaikan (OFI)', 'smkp.ke.ofi',            'smkp/lanjut/ofi'],
        ],
        'Acuan' => [
          ['Kriteria Kepdirjen', 'smkp.acuan', 'smkp/acuan'],
        ],
      ],
    ],
    'energi' => [
      'label' => 'Energy Performance',
      'icon'  => 'M13 2 4 14h7l-1 8 10-13h-7l0-7Z',
      'groups' => [
        'Pantau' => [
          ['Dashboard',          'energi.index',    'energi'],
          ['Input Lapangan',     'energi.input',    'energi/input'],
          ['Energy Consumption', 'energi.konsumsi', 'energi/konsumsi'],
          ['Fuel Management',    'energi.fuel',     'energi/bahan-bakar'],
          ['Electricity',        'energi.listrik',  'energi/listrik'],
        ],
        'Kinerja' => [
          ['Equipment Performance','energi.equipment','energi/alat*'],
          ['Energy KPI',           'energi.kpi',      'energi/kpi'],
          ['Baseline & Target',    'energi.baseline', 'energi/baseline'],
        ],
        'Optimasi' => [
          ['Saving Opportunities','energi.hemat',      'energi/penghematan*'],
          ['Carbon & Emission',   'energi.karbon',     'energi/karbon'],
          ['Energy Calculator',   'energi.kalkulator', 'energi/kalkulator'],
        ],
        'Data' => [
          ['Laporan Energi', 'energi.laporan', 'energi/laporan'],
          ['Master Data',    'energi.master',  'energi/data-induk*'],
        ],
      ],
    ],
    'konservasi' => [
      'label' => 'Konservasi Minerba',
      'icon'  => 'M12.8 2.6a2 2 0 0 0-1.6 0L2.6 6.5a1 1 0 0 0 0 1.8l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 0-1.8ZM2 12.4a1 1 0 0 0 .6.9l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 .6-.9M2 17.2a1 1 0 0 0 .6.9l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 .6-.9',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard', 'konservasi.index', 'konservasi'],
          ['Data Konservasi', 'konservasi.data', 'konservasi/data'],
          ['Laporan Konservasi', 'konservasi.laporan', 'konservasi/laporan'],
        ],
      ],
    ],
    'operasi' => [
      'label' => 'Mine Operations',
      'icon'  => 'm3 6.5 6-3 6 3 6-3v14l-6 3-6-3-6 3zM9 3.5v14M15 6.5v14',
      'groups' => [
        'Control Tower' => [
          ['Dashboard Operasi', 'operasi.index', 'operasi-tambang'],
          ['Input Data Shift', 'operasi.data', 'operasi-tambang/data'],
          ['Target Bulanan', 'operasi.target', 'operasi-tambang/target'],
        ],
        'Spatial Operations' => [
          ['GIS & Layer Tambang', 'operasi.gis', 'operasi-tambang/gis'],
        ],
      ],
    ],

    /* Penirisan dan Pemeliharaan sudah punya halaman, rute, dan alur
       persetujuan yang lengkap, tetapi belum pernah terdaftar di sini —
       sehingga keduanya hanya dapat dicapai dari kartu halaman depan,
       dan sekali berada di dalam aplikasi tidak ada jalan menuju ke
       sana sama sekali. */
    'air' => [
      'label' => 'Water & Dewatering',
      'icon'  => 'M12 2.7s5.5 6 5.5 9.8a5.5 5.5 0 1 1-11 0C6.5 8.7 12 2.7 12 2.7Z',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Air',   'air.index',   'penirisan'],
          ['Catatan Harian',  'air.catatan', 'penirisan/catatan*'],
          ['Kolam & Pompa',   'air.kolam',   'penirisan/kolam*'],
        ],
        'Data' => [
          ['Laporan Air', 'air.cetak', 'penirisan/cetak'],
        ],
      ],
    ],
    'lingkungan' => [
      'label' => 'Lingkungan & Reklamasi',
      'icon'  => 'M20 4c0 9-5.5 13-11 13a5 5 0 0 1-1.6-.3C6 15.6 5 13.4 5 11 5 6.6 10 4 20 4ZM4 20c2.5-4.5 6-7.5 11-9.5',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Lingkungan', 'lingkungan.index',      'lingkungan'],
          ['Petak & Reklamasi',    'lingkungan.lahan',      'lingkungan/lahan*'],
          ['Pemantauan Mutu',      'lingkungan.pemantauan', 'lingkungan/pemantauan*'],
        ],
        'Acuan & Data' => [
          ['Baku Mutu',            'lingkungan.baku',  'lingkungan/baku-mutu*'],
          ['Laporan Lingkungan',   'lingkungan.cetak', 'lingkungan/cetak'],
        ],
      ],
    ],
    'peledakan' => [
      'label' => 'Drill & Blast',
      'icon'  => 'M12 2.5 9.5 9 3 11.5 9.5 14l2.5 6.5 2.5-6.5 6.5-2.5L14.5 9Z',
      'groups' => [
        'Perencanaan' => [
          ['Dashboard Peledakan', 'peledakan.index',   'peledakan'],
          ['Rencana Peledakan',   'peledakan.rencana', 'peledakan/rencana*'],
        ],
        'Pemantauan' => [
          ['Titik Terlindung', 'peledakan.titik',   'peledakan/titik*'],
          ['Getaran Terukur',  'peledakan.getaran', 'peledakan/getaran*'],
        ],
        'Data' => [
          ['Laporan Peledakan', 'peledakan.cetak', 'peledakan/cetak'],
        ],
      ],
    ],
    'angkutan' => [
      'label' => 'Dispatch & Hauling',
      'icon'  => 'M2.5 16.5V7a1 1 0 0 1 1-1h9v10.5m0 0h-9m9 0h2m6.5 0h-2m2 0V12l-2.5-3.5H15m6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm-12.5 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Angkutan', 'angkutan.index',  'angkutan'],
          ['Regu Angkut',        'angkutan.regu',   'angkutan/regu*'],
          ['Penimbangan Muatan', 'angkutan.muatan', 'angkutan/muatan*'],
        ],
        'Acuan & Data' => [
          ['Armada Angkut',    'angkutan.armada', 'angkutan/armada*'],
          ['Laporan Angkutan', 'angkutan.cetak',  'angkutan/cetak'],
        ],
      ],
    ],
    'biaya' => [
      'label' => 'Pengendalian Biaya',
      'icon'  => 'M12 2.5v19M15.5 7.2c-.6-1.4-2-2.2-3.7-2.2-2.2 0-3.9 1.2-3.9 3s1.5 2.6 4 3.2c2.7.6 4.3 1.5 4.3 3.4 0 2-1.8 3.3-4.2 3.3-2 0-3.5-.9-4.1-2.4',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Biaya',   'biaya.index',     'biaya'],
          ['Realisasi Bulanan', 'biaya.realisasi', 'biaya/realisasi*'],
        ],
        'Acuan & Data' => [
          ['Anggaran Tahunan', 'biaya.anggaran', 'biaya/anggaran*'],
          ['Bagan Akun',       'biaya.akun',     'biaya/bagan-akun*'],
          ['Laporan Biaya',    'biaya.cetak',    'biaya/cetak'],
        ],
      ],
    ],
    'izin' => [
      'label' => 'Izin Kerja Aman',
      'icon'  => 'M9 12.5l2 2 4.5-4.5M8.5 4.5h7a1 1 0 0 1 1 1v1h1.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2H7.5v-1a1 1 0 0 1 1-1Z',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Izin', 'izin.index',  'izin-kerja'],
          ['Daftar Izin',    'izin.daftar', 'izin-kerja/daftar*'],
        ],
        'Acuan & Data' => [
          ['Daftar Periksa', 'izin.syarat', 'izin-kerja/syarat*'],
          ['Ambang Gas',     'izin.ambang', 'izin-kerja/ambang-gas*'],
          ['Laporan Izin',   'izin.cetak',  'izin-kerja/cetak'],
        ],
      ],
    ],
    'geoteknik' => [
      'label' => 'Kestabilan Lereng',
      'icon'  => 'M2.5 19.5h19L15 8l-3.2 5.2L9.4 9.8ZM9.4 9.8 5.6 4.5 2.5 9',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Lereng', 'geoteknik.index',  'geoteknik'],
          ['Pembacaan Alat',   'geoteknik.bacaan', 'geoteknik/bacaan*'],
          ['Lereng & Instrumen','geoteknik.lereng','geoteknik/lereng*'],
        ],
        'Data' => [
          ['Laporan Geoteknik', 'geoteknik.cetak', 'geoteknik/cetak'],
        ],
      ],
    ],
    'maintenance' => [
      'label' => 'Maintenance',
      'icon'  => 'M14.6 6.3a1 1 0 0 0 0 1.4l1.7 1.7a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Keandalan', 'maintenance.index',  'pemeliharaan'],
          ['Perintah Kerja',      'maintenance.order',  'pemeliharaan/order*'],
          ['Armada',              'maintenance.armada', 'pemeliharaan/armada'],
        ],
        'Data' => [
          ['Laporan Keandalan', 'maintenance.cetak', 'pemeliharaan/cetak'],
        ],
      ],
    ],

    'meh' => [
      'label' => 'Mining Engineering',
      'icon'  => 'M3.5 3.5h6.5v8H3.5zM14 3.5h6.5v5H14zM14 12.5h6.5v8H14zM3.5 16h6.5v4.5H3.5z',
      'groups' => [
        'Operasi' => [
          ['Dashboard',            'meh.index',       'mining-engineering-hub'],
          ['Control Tower',        'meh.monitor',     'mining-engineering-hub/monitor'],
          ['Energy Dashboard',     'meh.energy',      'mining-engineering-hub/energy'],
          ['Fleet & Productivity', 'meh.fleet',       'mining-engineering-hub/fleet'],
          ['Mining Equipment',     'meh.equipment',   'mining-engineering-hub/equipment'],
          ['Maintenance',          'meh.maintenance', 'mining-engineering-hub/maintenance'],
        ],
        'Kinerja' => [
          ['HSE & SMKP',      'meh.hse', 'mining-engineering-hub/hse'],
          ['Engineering KPI', 'meh.kpi', 'mining-engineering-hub/kpi'],
        ],
        'Alat & Acuan' => [
          ['Engineering Tools',      'meh.tools',       'mining-engineering-hub/tools'],
          ['Regulations & Standards','meh.regulations', 'mining-engineering-hub/regulations'],
        ],
      ],
    ],
    'gudang' => [
      'label' => 'Gudang & Penyimpanan',
      'icon'  => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
      'groups' => [
        'Persediaan' => [
          ['Dashboard',      'gudang.index',  'gudang'],
          ['Daftar Barang',  'gudang.barang', 'gudang/barang*'],
          ['Lokasi Simpan',  'gudang.lokasi', 'gudang/lokasi*'],
        ],
        'Transaksi' => [
          ['Mutasi Keluar Masuk', 'gudang.mutasi', 'gudang/mutasi'],
          ['Stok Opname',         'gudang.opname', 'gudang/opname'],
        ],
        'Pengawasan' => [
          ['Register B3',    'gudang.b3',      'gudang/b3'],
          ['Laporan Stok',   'gudang.laporan', 'gudang/laporan'],
        ],
      ],
    ],
    'dokumen' => [
      'label' => 'ISO & Dokumen',
      'icon'  => 'M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h3',
      // Dua sisi yang saling melengkapi: register menjawab dokumen apa yang
      // dipunya, ISO menjawab klausul mana yang belum punya dokumen.
      'groups' => [
        'Register' => [
          ['Semua Dokumen',   'dokumen.index',  'dokumen'],
          ['Dokumen Baru',    'dokumen.create', 'dokumen/baru'],
        ],
        'Struktur' => [
          ['Piramida Dokumen','dokumen.piramida',     'struktur-dokumen'],
          ['Daftar Induk',    'dokumen.daftar-induk', 'daftar-induk'],
        ],
        'Standar ISO' => [
          ['Pemenuhan Klausul','iso.index', 'iso'],
        ],
      ],
    ],
    /* Pembelian berdiri sendiri, dan sengaja BUKAN di bawah Admin.
       Admin mengurus aplikasi yang sudah dipakai; yang ini mengurus
       uang yang masuk untuk memakainya — dibaca orang yang berbeda,
       pada waktu yang berbeda. */
    'pembelian' => [
      'label' => 'Pembelian',
      'icon'  => 'M6.5 7.5h11l1.5 10.5a2 2 0 0 1-2 2.3H7a2 2 0 0 1-2-2.3L6.5 7.5Zm2.5 0V6a3 3 0 0 1 6 0v1.5',
      'groups' => [
        '' => [
          ['Katalog', 'pembelian.katalog', 'pembelian'],
          ['Tagihan', 'pembelian.daftar',  'pembelian/tagihan*'],
          ['Daftar Harga', 'pembelian.produk', 'pembelian/produk*'],
        ],
      ],
    ],

    'admin' => [
      'label' => 'Administrasi',
      'icon'  => 'M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Zm7.4-.9a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z',
      'admin' => true,
      'groups' => [
        '' => [
          ['Pusat Kendali',   'admin.system',          'admin/system*'],
          ['Kelola Perusahaan','admin.companies.index','admin/companies*'],
          ['Kelola Pengguna', 'admin.users.index',     'admin/users*'],
          ['Penanda Tangan',  'signatories.index',     'signatories*'],
        ],
      ],
    ],
  ];
    }

    /**
     * Kunci modul yang sedang dibuka, ditentukan dari alamat sekarang.
     *
     * Ditulis sebagai DAFTAR, bukan rantai ternary bersarang.
     * Bentuk sebelumnya berupa dua puluh ternary bertingkat yang
     * ditutup sembilan belas kurung sekaligus di baris terakhir:
     * menambah satu modul menuntut menghitung kurung dengan benar, dan
     * salah satu kurung menghasilkan galat parse yang menumbangkan
     * seluruh aplikasi — bukan hanya menunya.
     *
     * Urutannya berarti: yang PERTAMA cocok yang dipakai, jadi pola
     * yang lebih khusus harus berada di atas yang lebih umum.
     *
     * @var list<array{0:list<string>,1:string}>
     */
    private const PETA_ALAMAT = [
        [['dasbor'],                               'dasbor'],
        [['personalia*', 'pesan*'],                'personalia'],
        [['hazard*', 'inspeksi*', 'temuan*'],      'hazrep'],
        [['tpkkp*'],                               'tpkkp'],
        [['smkp*'],                                'smkp'],
        [['dokumen*', 'iso*', 'struktur-dokumen', 'daftar-induk'], 'dokumen'],
        [['energi*'],                              'energi'],
        [['konservasi*'],                          'konservasi'],
        [['operasi-tambang*'],                     'operasi'],
        [['penirisan*'],                           'air'],
        [['geoteknik*'],                           'geoteknik'],
        [['peledakan*'],                           'peledakan'],
        [['angkutan*'],                            'angkutan'],
        [['biaya*'],                               'biaya'],
        [['izin-kerja*'],                          'izin'],
        [['lingkungan*'],                          'lingkungan'],
        [['pemeliharaan*'],                        'maintenance'],
        [['gudang*'],                              'gudang'],
        [['mining-engineering-hub*'],              'meh'],
        [['miners*'],                           'miners'],

        /* Investigasi didaftarkan SEBELUM pola 'ko*' di bawahnya. Pola
           itu berupa awalan satu kata dan tidak cocok dengan
           'investigasi', tetapi urutan di sini memang sudah menentukan
           sekali — dan menaruh modul baru di tengah daftar adalah cara
           tercepat menemukan bahwa ia tertelan pola lain. */
        [['investigasi*'],                         'investigasi'],
        [['pjp*'],                                 'pjp'],
        [['ko*'],                                  'ko'],
        [['pembelian*'],                           'pembelian'],
        [['admin*', 'signatories*'],               'admin'],
    ];

    /**
     * Peta alamat modul, untuk yang perlu membacanya dari luar.
     *
     * Dipakai PenemuanController membangkitkan robots.txt. Dibuka sebagai
     * pengakses, bukan dengan menjadikan konstantanya public: yang perlu
     * keluar hanya isinya untuk dibaca, dan tabel ini adalah satu-satunya
     * tempat yang menentukan alamat mana milik modul mana.
     *
     * @return list<array{0:list<string>,1:string}>
     */
    public static function petaAlamat(): array
    {
        return self::PETA_ALAMAT;
    }

    public static function modulAktif(): string
    {
        foreach (self::PETA_ALAMAT as [$pola, $kunci]) {
            if (Request::is(...$pola)) {
                return isset(self::all()[$kunci]) ? $kunci : 'lms';
            }
        }

        /* LMS adalah beranda: alamat yang tidak dikenali satu pun pola
           di atas memang berada di sana (dashboard, kursus, profil). */
        return 'lms';
    }

    public static function modul(?string $kunci = null): array
    {
        $kunci ??= self::modulAktif();
        $semua = self::all();

        return $semua[$kunci] ?? $semua['lms'];
    }

    /**
     * Menu yang boleh dilihat pengguna ini.
     *
     * Modul bertanda 'admin' disaring di sini, bukan di tampilan: dua
     * tampilan yang masing-masing menyaring sendiri akan berbeda aturannya
     * cepat atau lambat, dan yang lebih longgar menang tanpa ada yang tahu.
     */
    public static function untuk($pengguna): array
    {
        return array_filter(self::all(), function ($m) use ($pengguna) {
            return !($m['admin'] ?? false) || ($pengguna && $pengguna->isAdmin());
        });
    }

    /** Rute pertama sebuah modul — tujuan saat ikonnya diklik. */
    public static function ruteAwal(array $m): ?string
    {
        foreach ($m['groups'] as $butir) {
            foreach ($butir as $b) return $b[1];
        }

        return null;
    }
}
