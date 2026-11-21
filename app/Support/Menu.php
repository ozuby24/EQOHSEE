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
      /* Ditulis "Dashboard", bukan "Dasbor".
         Seluruh antarmuka memakai istilah Inggris yang sudah terlanjur
         jadi kosakata kerja sehari-hari di tambang — dashboard, report,
         shift — dan menerjemahkan satu di antaranya membuat orang
         mencari menu yang namanya tidak pernah ia dengar disebut siapa
         pun di lapangan. */
      'label' => 'Dashboard',
      'semboyan' => 'Everything At A Glance',
      'kutipan' => 'Yang terukur dapat diperbaiki; yang tidak tercatat tidak pernah terjadi.',
      'icon'  => 'M4 13h6V4H4v9Zm10 7h6v-9h-6v9ZM4 20h6v-4H4v4Zm10-11h6V4h-6v5Z',
      'groups' => [
        '' => [
          ['Ringkasan Situs', 'dashboard', 'dashboard'],
        ],
      ],
    ],
    /* Personalia berdiri di depan Learning Center: yang diurus di sini
       bukan pembelajaran melainkan siapa penggunanya dan di bawah
       perusahaan mana ia bekerja — jawaban yang dipakai hampir seluruh
       modul lain, termasuk kop dokumen dan nama pada sertifikat. */
    'personalia' => [
      'label' => 'Personalia',
      'semboyan' => 'People First Always',
      'kutipan' => 'Orang bukan sumber daya yang dipakai, melainkan orang yang harus pulang.',
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
      'semboyan' => 'Learn Before You Lead',
      'kutipan' => 'Yang tidak pernah diajarkan tidak dapat dituntut untuk dipatuhi.',
      'icon'  => 'M12 4 3 8l9 4 9-4-9-4zM7 10.5V15c0 1.3 2.7 2.3 5 2.3s5-1 5-2.3v-4.5',
      'groups' => [
        '' => [
          ['Dashboard',      'lms.dasbor',         'lms'],
          ['Kursus',         'courses.index',      'courses*'],
          ['Prosedur & SOP', 'procedures.index',   'procedures*'],
          ['Evaluasi SOP',   'sop.index',          'sop*'],
          ['Sertifikat',     'certificates.index', 'certificates*'],
          ['Berita',         'news.index',         'news*'],
          ['Evaluasi',       'evaluations.index',  'evaluations*'],
        ],
        /* Pembinaan operator di lapangan — observasi cycle time loader.
           Satu grup tersendiri, bukan satu butir: tiap halamannya
           menjawab pertanyaan yang berbeda (sesi ini, operator ini,
           bulan ini, temuan ini), dan yang disatukan menjadi satu
           halaman tidak menjawab satu pun dengan baik. */
        'Observasi Operator (FROP)' => [
          ['Sesi Observasi',    'frop.index',    'frop/sesi*'],
          ['Performa Operator', 'frop.operator', 'frop/operator*'],
          ['KPI Bulanan',       'frop.kpi',      'frop/kpi*'],
          ['Temuan & CA',       'frop.tracker',  'frop/tracker*'],
          ['Coaching Log',      'frop.coaching', 'frop/coaching*'],
          ['Impor Berkas',      'frop.impor',    'frop/impor*'],
          ['Panduan & Acuan',   'frop.panduan',  'frop/panduan*'],
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
      'semboyan' => 'Fit To Work Today',
      'kutipan' => 'Tidak ada pekerjaan yang begitu mendesak sampai boleh dikerjakan orang yang tidak layak.',

      /* Rupa layar Safe Track (NiceAdmin) — latar biru pucat, judul
         biru tua, kartu putih — lewat kelas `tema-miners` pada
         resources/css/miners.css. Bukan lagi palet pasir-teal yang
         dipakai bersama HRIS dan Investigasi: keputusan pemilik
         (7 Nov 2026) meminta layar Miners meniru tampilan Safe Track,
         dan HRIS serta Investigasi tidak ikut berubah karenanya.

         Ditulis di sini, bukan di tiap berkas Vue: satu modul punya
         belasan halaman, dan palet yang ditempelkan per halaman akan
         tertinggal pada halaman yang ditambahkan berikutnya. */
      'tema'  => 'miners',
      'icon'  => 'M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 14l2 2 4-4',
      /* SUSUNANNYA MENGIKUTI BILAH SAMPING DBEST (16 Nov 2026), atas
         permintaan pemilik agar isi Miners sama dengan DBEST: REGISTER,
         PENAMBAHAN, HISTORY, OUTSTANDING, MASTER DATA, MASTER PENAMBAHAN,
         MONITORING (Masa Berlaku, Riwayat Karyawan), dan AKTIVITAS.

         Butir DBEST yang di EQOHSEE sudah menjadi modul tersendiri —
         SPIP & Uji Kelayakan (modul KO), Hazard Report, Inspeksi,
         Perizinan Kerja (Izin Kerja Aman), Cuti Tahunan (HRIS), dan
         Field Break (papan kabar = Pengumuman) — DITAUTKAN ke modulnya,
         bukan dibuat ulang di sini. Dua salinan satu register adalah dua
         register yang lambat laun berbeda isinya; tautan memastikan
         orang Miners menemukan semuanya di tempat yang sama dengan di
         DBEST, sementara datanya tetap satu.

         Setiap butir harus membuka halaman yang bekerja bagi admin —
         dijaga MinersMenuTest. */
      'groups' => [
        '' => [
          ['Dashboard', 'miners.dasbor', 'miners/dasbor*'],
        ],

        /* REGISTER DBEST — urut rantainya: MCU → Induction → Mine Permit
           → SIMPER, lalu register unit & kompetensi. */
        'Register' => [
          ['MCU',                'miners.mcu.index',        'miners/mcu*'],
          ['Daftar Induction',   'miners.induksi.index',    'miners/induksi*'],
          ['Mine Permit',        'miners.permit.index',     'miners/permit*'],
          ['SIMPER',             'miners.simper.index',     'miners/simper*'],
          ['SPIP',               'ko.index',                'ko'],
          ['Authority Passport', 'miners.riwayat.authority','miners/riwayat/authority'],
          ['Hazard Report',      'hazard.create',           'hazard/buat'],
          ['Uji Kelayakan',      'ko.uji',                  'ko/uji*'],
        ],

        /* PENAMBAHAN DBEST: penambahan unit SIMPER dan upgrade. */
        'Penambahan' => [
          ['Penambahan SIMPER',  'miners.daftar.penambahan-unit', 'miners/daftar/penambahan-unit'],
          ['Upgrade Authority',  'miners.daftar.upgrade-simper',  'miners/daftar/upgrade-simper'],
        ],

        /* HISTORY DBEST. "Data Karyawan" = Manpower. */
        'History' => [
          ['MCU',            'miners.riwayat.mcu',          'miners/riwayat/mcu'],
          ['Data Karyawan',  'miners.index',                'miners'],
          ['Induction',      'miners.riwayat.induksi',      'miners/riwayat/induksi'],
          ['Mine Permit',    'miners.riwayat.mine-permit',  'miners/riwayat/mine-permit'],
          ['SIMPER',         'miners.riwayat.mine-license', 'miners/riwayat/mine-license'],
          ['Authority',      'miners.riwayat.authority',    'miners/riwayat/authority'],
          ['Hazard',         'hazard.register',             'hazard/register*'],
          ['Inspeksi',       'inspeksi.index',              'inspeksi'],
          ['SPIP',           'ko.register',                 'ko/register*'],
          ['Uji Kelayakan',  'ko.kelayakan',                'ko/kelayakan*'],
        ],

        /* OUTSTANDING DBEST — antrean di meja SAYA. Bawaannya "Perlu
           Approval Saya", dan penanda angkanya sama dengan lonceng tugas. */
        'Outstanding' => [
          ['MCU',         'miners.daftar.outstanding-mcu',     'miners/daftar/outstanding-mcu'],
          ['Induction',   'miners.daftar.outstanding-induksi', 'miners/daftar/outstanding-induksi'],
          ['Mine Permit', 'miners.daftar.outstanding-permit',  'miners/daftar/outstanding-permit'],
          ['SIMPER',      'miners.daftar.outstanding-simper',  'miners/daftar/outstanding-simper'],
          ['SPIP',        'ko.tindak',                         'ko/tindak*'],
        ],

        /* MASTER DATA DBEST (+ daftar acuan EQOHSEE). Unsur keempat
           'kelolaMiners' berarti admin atau HSE perusahaan IUP; mitra
           tidak melihatnya. Perusahaan hanya administrator: layar itu
           mengubah hubungan IUP–IUJP yang menjadi dasar batas data. */
        'Master Data' => [
          ['Unit',               'miners.master.unit',          'miners/master/unit',          'kelolaMiners'],
          ['Type Unit SIMPER',   'miners.master.type-unit',     'miners/master/type-unit',     'kelolaMiners'],
          ['Unit Commissioning', 'ko.unit',                     'ko/unit*',                    'kelolaMiners'],
          ['Sub Unit',           'miners.master.sub-unit',      'miners/master/sub-unit',      'kelolaMiners'],
          ['Jabatan',            'miners.master.jabatan',       'miners/master/jabatan',       'kelolaMiners'],
          ['Departemen',         'miners.master.departemen',    'miners/master/departemen',    'kelolaMiners'],
          ['Hasil MCU',          'miners.master.hasil-mcu',     'miners/master/hasil-mcu',     'kelolaMiners'],
          ['PJO',                'miners.master.pjo',           'miners/master/pjo',           'kelolaMiners'],
          ['Subkontraktor',      'miners.master.subkontraktor', 'miners/master/subkontraktor', 'kelolaMiners'],
          ['Perusahaan',         'miners.master.perusahaan',    'miners/master/perusahaan',    true],
          ['Upload Data Lama',   'miners.data-lama',            'miners/data-lama',            'kelolaMiners'],
        ],

        /* MASTER PENAMBAHAN DBEST — seluruh ajuan lanjutan. */
        'Master Penambahan' => [
          ['Penambahan SIMPER',   'miners.daftar.penambahan-unit',    'miners/daftar/penambahan-unit', 'kelolaMiners'],
          ['Perpanjangan Permit', 'miners.perpanjangan-permit.index', 'miners/perpanjangan-permit*'],
          ['Perpanjangan SIMPER', 'miners.daftar.perpanjangan',       'miners/daftar/perpanjangan'],
        ],

        /* 'medisMiners' — admin, tim OHSE, atau paramedis
           (AksesMiners::medis); yang lain ditolak halaman rujukannya. */
        'Monitoring' => [
          ['Masa Berlaku',     'miners.kedaluwarsa',        'miners/kedaluwarsa'],
          ['Riwayat Karyawan', 'miners.riwayat-karyawan',   'miners/riwayat-karyawan*'],
          ['Rujukan MCU',      'miners.daftar.rujukan',     'miners/daftar/rujukan', 'medisMiners'],
          ['Cetak Kartu',      'miners.daftar.cetak-kartu', 'miners/daftar/cetak-kartu', 'kelolaMiners'],
        ],

        /* AKTIVITAS DBEST. */
        'Aktivitas' => [
          ['Perizinan Kerja', 'izin.index',      'izin-kerja'],
          ['Field Break',     'news.index',      'news'],
          ['Cuti Tahunan',    'hris.cuti.index', 'hris/cuti'],
        ],

        'Administrasi' => [
          ['Alur Persetujuan', 'miners.alur.index', 'miners/alur-persetujuan*', 'kelolaMiners'],
        ],
      ],
    ],
    /* SATU MODUL UNTUK SELURUH HRIS, bukan satu per fiturnya.
       Roster menjawab "kapan seseorang seharusnya bekerja", absensi
       menjawab "apakah ia benar-benar bekerja" — dan keduanya membaca
       daftar orang yang sama, dijalankan bagian yang sama, dan saling
       merujuk pada tiap layarnya.

       Yang berikutnya masih banyak: cuti, lembur, kontrak PKWT,
       penggajian. Tiap-tiapnya sebagai modul tersendiri akan
       menambahkan satu baris lagi ke bilah samping yang sudah berisi
       dua puluh delapan — sampai tidak ada lagi yang dapat menemukan
       apa pun di sana. Sebagai grup di dalam HRIS, bilahnya tidak
       bertambah panjang sama sekali.

       Ditaruh tepat sesudah Miners, dan itu bukan urutan abjad:
       Miners menjawab "BOLEH atau tidak orang ini bekerja", HRIS
       menjawab "KAPAN dan APAKAH ia bekerja". Keduanya bersinggungan
       di satu titik yang menentukan — roster menolak menjadwalkan
       orang yang berkasnya tidak berlaku — dan berdampingan di bilah
       samping, titik itu tidak perlu dijelaskan siapa pun. */
    'hris' => [
      'label' => 'HRIS',
      'semboyan' => 'People Drive Progress',
      'kutipan' => 'Orang yang tepat, di tempat yang tepat, pada waktu yang tepat.',
      'tema'  => 'safetrack',
      'icon'  => 'M17 20h5v-2a3 3 0 0 0-5.36-1.87M17 20H7m10 0v-2c0-.66-.13-1.3-.36-1.87m0 0a5 5 0 0 0-9.28 0M7 20H2v-2a3 3 0 0 1 5.36-1.87M7 20v-2c0-.66.13-1.3.36-1.87m0 0a5 5 0 0 1 9.28 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0z',
      'groups' => [
        '' => [
          ['Ringkasan', 'hris.index', 'hris'],
        ],

        'Roster & Shift' => [
          ['Kalender Regu',  'hris.roster.index',     'hris/roster'],
          ['Pola & Regu',    'hris.roster.pola',      'hris/roster/pola'],
          ['Manpower Plan',  'hris.roster.kebutuhan', 'hris/roster/kebutuhan'],
        ],

        'Absensi' => [
          ['Pemantauan Harian', 'hris.absensi.index', 'hris/absensi'],
          ['Rekap Periode',     'hris.absensi.rekap', 'hris/absensi/rekap'],
          ['Mesin Lapangan',    'hris.absensi.mesin', 'hris/absensi/mesin'],
        ],

        'Cuti & Izin' => [
          ['Pengajuan & Persetujuan', 'hris.cuti.index', 'hris/cuti'],
          ['Saldo Cuti',              'hris.cuti.saldo', 'hris/cuti/saldo'],
        ],

        'Lembur' => [
          ['Perintah Lembur', 'hris.lembur.index', 'hris/lembur'],
          ['Upah Dasar',      'hris.lembur.upah',  'hris/lembur/upah'],
        ],

        'Penggajian' => [
          ['Periode & Slip',   'hris.gaji.index', 'hris/gaji'],
          ['Acuan Pajak & BPJS','hris.gaji.acuan', 'hris/gaji/acuan'],
        ],

        'Kontrak Kerja' => [
          ['PKWT & PKWTT', 'hris.kontrak.index', 'hris/kontrak'],
        ],

        /* Layanan mandiri diletakkan PALING BAWAH, bukan paling atas.
           Bagi staf HR yang membuka modul ini sepanjang hari, halaman
           "milik saya" adalah yang paling jarang dituju; yang paling
           sering justru layar yang mengurus orang lain. */
        'Milik Saya' => [
          ['Beranda Saya',   'hris.saya.index',     'hris/saya'],
          ['Kehadiran Saya', 'hris.saya.kehadiran', 'hris/saya/kehadiran'],
          ['Cuti Saya',      'hris.saya.cuti',      'hris/saya/cuti'],
          ['Slip Gaji Saya', 'hris.saya.gaji',      'hris/saya/gaji'],
          ['Kontrak Saya',   'hris.saya.kontrak',   'hris/saya/kontrak'],
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
      'semboyan' => 'Never Twice The Same',
      'kutipan' => 'Yang dicari bukan siapa yang salah, melainkan apa yang membuatnya mungkin.',
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
      /* "Pertambangan" bukan hiasan pada namanya. Perusahaan jasa
         pertambangan adalah sebutan yang dipakai regulasinya sendiri —
         Kepdirjen Minerba 185/2019 — untuk badan usaha yang wajib
         diprakualifikasi sebelum dipekerjakan di wilayah izin. Tanpa
         kata itu namanya terbaca sebagai vendor mana pun, termasuk
         pemasok alat tulis, dan modul ini justru tidak mengurusi
         mereka. */
      'label' => 'Perusahaan Jasa Pertambangan',
      'semboyan' => 'Proven On Paper',
      'kutipan' => 'Tanggung jawab keselamatan tidak ikut berpindah bersama pekerjaannya.',
      'tema'  => 'safetrack',
      'icon'  => 'M4 21h9M6 21V5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v6M9 8h1M9 11.5h1M14.5 17.5l2.2 2.2 4.3-4.3',
      /* Ketiga aspek berdiri sebagai kelompoknya sendiri, bukan
         dilipat ke bawah Ringkasan. Ketiganya berjalan BERSAMAAN untuk
         tiap mitra — persyaratan, pelaporan, dan evaluasi diisi kapan
         saja tanpa menunggu yang lain — dan menaruhnya sebagai satu
         butir membuat modul ini terbaca seolah bertahap. */
      'groups' => [
        '' => [
          ['Ringkasan', 'pjp.index', 'pjp'],
        ],
        'Aspek Pemantauan' => [
          ['Persyaratan & Penetapan', 'pjp.persyaratan', 'pjp/persyaratan'],
          ['Pemantauan & Pelaporan',  'pjp.pelaporan',   'pjp/pelaporan'],
          ['Evaluasi Kinerja',        'pjp.evaluasi',    'pjp/evaluasi'],
        ],
        'Mitra' => [
          ['Daftar Perusahaan Jasa', 'pjp.daftar', 'pjp/daftar'],
          ['Tambah Perusahaan Jasa', 'pjp.baru',   'pjp/daftar/baru'],
        ],
        'Acuan' => [
          ['Bantuan', 'pjp.bantuan', 'pjp/bantuan'],
        ],
      ],
    ],
    'tpkkp' => [
      'label' => 'Safety Maturity Level',
      'semboyan' => 'Measured Not Assumed',
      'kutipan' => 'Angka yang jujur lebih berguna daripada angka yang bagus.',
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
      'semboyan' => 'See It Say It',
      'kutipan' => 'Bahaya yang dilaporkan sudah setengah dikendalikan.',
      'icon'  => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
      'groups' => [
        'Hazard Report' => [
          ['Monitor Laporan', 'hazard.index',     'hazard'],
          ['Buat Laporan',    'hazard.create',    'hazard/buat'],
          ['Analitik & KPI',  'hazard.analytics', 'hazard/analitik'],
          ['Evaluasi Temuan', 'hazard.evaluasi',  'hazard/evaluasi'],
          ['Temuan Berulang', 'hazard.berulang',  'hazard/berulang'],
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
      'semboyan' => 'Zero Is The Target',
      'kutipan' => 'Nol kecelakaan bukan keberuntungan yang berulang, melainkan sistem yang bekerja.',
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
      'semboyan' => 'Systems That Hold',
      'kutipan' => 'Sistem yang hanya ada di dokumen akan gagal pada hari ia paling dibutuhkan.',
      'icon'  => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
      /* Menu modul audit SMKP mengikuti Aurorasys (porting 7 Nov 2026):
         daftar audit, audit baru, tindak lanjut temuan lintas audit, dan
         acuan kriteria. Langkah per audit (Tahap I, Rencana, Rapat,
         Penilaian, Bukti, Temuan, Formulir, Laporan) dibuka dari halaman
         audit itu sendiri — menu samping tidak membawa identitas audit.

         Daftar Audit tetap menyala di seluruh halaman satu audit (pola
         `smkp/*`); butir lain yang polanya lebih khusus menang di
         alamatnya sendiri — lihat HandleInertiaRequests::menu().
         Audit Baru hanya bagi yang boleh mengisi audit (gerbang `audit`:
         administrator & auditor) — auditi tidak pernah melihatnya. */
      'groups' => [
        '' => [
          ['Daftar Audit',          'smkp.index',         ['smkp', 'smkp/*']],
          /* Dasbor v2 (port DBEST) sesudah Daftar Audit: butir pertama
             adalah beranda modul (HandleInertiaRequests::diBeranda). */
          ['Dasbor',                'smkp.dasbor',        'smkp/dasbor*'],
          ['Audit Baru',            'smkp.create',        'smkp/buat', 'audit'],
          ['Tindak Lanjut Temuan',  'smkp.tindak-lanjut', 'smkp/tindak-lanjut'],
          ['Kriteria & Acuan',      'smkp.acuan',         'smkp/acuan'],
          // Audit perusahaan grup bernama manual (port DBEST #21) — administrator.
          ['Perusahaan Grup',       'smkp.grup',          'smkp/grup*', true],
          // Aturan Akses Audit (port DBEST): auditor & pengunggah per perusahaan — administrator.
          ['Aturan Akses Audit',    'admin.akses-audit',  'admin/akses-audit*', true],
        ],
      ],
    ],
    'energi' => [
      'label' => 'Energy Performance',
      'semboyan' => 'Every Watt Counted',
      'kutipan' => 'Energi yang paling murah adalah energi yang tidak jadi dipakai.',
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
          ['EnPI & EnB',           'energi.enpi',     'energi/enpi'],
          ['Significant Energy Use','energi.seu',     'energi/seu'],
        ],
        /* Manajemen Energi & Karbon (PRD/FSD Energi-Karbon). Halaman
           Karbon lama kini menampilkan inventarisasi GRK, jadi alamat
           lamanya ikut menandai butir ini. */
        'Karbon & GRK' => [
          ['Inventarisasi GRK',   'energi.grk.index',    ['energi/grk', 'energi/karbon']],
          ['Batas & Tahun Dasar', 'energi.grk.batas',    'energi/grk/batas'],
          ['Scope 3',             'energi.grk.s3',       'energi/grk/scope3'],
          ['Aksi Mitigasi',       'energi.grk.mitigasi', 'energi/grk/mitigasi'],
          ['Registri Faktor',     'energi.faktor.index', 'energi/faktor*'],
        ],
        'Manajemen Energi' => [
          ['Status PP 33/2023',   'energi.me.index',     'energi/manajemen'],
          ['Manajer & Auditor',   'energi.me.personel',  'energi/manajemen/personel'],
          ['Program Konservasi',  'energi.me.program',   'energi/manajemen/program'],
          ['Audit Energi',        'energi.me.audit',     'energi/manajemen/audit*'],
          ['Monev & Tinjauan',    'energi.me.tinjauan',  'energi/manajemen/tinjauan'],
        ],
        'Optimasi' => [
          ['Saving Opportunities','energi.hemat',      'energi/penghematan*'],
          ['Energy Calculator',   'energi.kalkulator', 'energi/kalkulator'],
        ],
        'Pelaporan' => [
          ['Verifikasi & Periode', 'energi.periode.index', 'energi/periode*'],
          ['Pusat Pelaporan',      'energi.lapor.index',   'energi/pelaporan*'],
          ['Kalender Kepatuhan',   'energi.kalender',      'energi/kalender'],
          ['Laporan Energi',       'energi.laporan',       'energi/laporan'],
          ['Master Data',          'energi.master',        'energi/data-induk*'],
        ],
        // Executive Control Tower — hanya BOD, KTT, keuangan, manajer energi/GRK, dan admin.
        'Kontrol Direksi' => [
          ['Control Tower',        'energi.bod.index',     'energi/bod*', 'energi.bod'],
        ],
      ],
    ],
    'konservasi' => [
      'label' => 'Konservasi Minerba',
      'semboyan' => 'Take Only What Lasts',
      'kutipan' => 'Cadangan yang ditinggalkan hari ini adalah tambang bagi yang datang kemudian.',
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
      'semboyan' => 'Steady Every Shift',
      'kutipan' => 'Produksi yang dikejar dengan memotong jalan selalu dibayar di tempat lain.',
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
      'semboyan' => 'Clean Water Downstream',
      'kutipan' => 'Yang mengalir ke hilir adalah yang kita kerjakan di hulu.',
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
      'semboyan' => 'Leave It Better',
      'kutipan' => 'Lahan dipinjam dari yang akan menempatinya sesudah kita.',
      'icon'  => 'M20 4c0 9-5.5 13-11 13a5 5 0 0 1-1.6-.3C6 15.6 5 13.4 5 11 5 6.6 10 4 20 4ZM4 20c2.5-4.5 6-7.5 11-9.5',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Lingkungan', 'lingkungan.index',      'lingkungan'],
          ['Petak & Reklamasi',    'lingkungan.lahan',      'lingkungan/lahan*'],
          ['Pemantauan Mutu',      'lingkungan.pemantauan', 'lingkungan/pemantauan*'],
        ],
        /* Audit Kinerja Pengelolaan & Pemantauan Lingkungan tinggal di
           modul lingkungan, tempat orang mencarinya. Sebelumnya ia
           terselip di bawah Pemenuhan pada ISO & Dokumen, dan yang
           membuka modul Lingkungan menyimpulkan fiturnya tidak ada. */
        'Audit & Penghargaan' => [
          ['Audit Kinerja Lingkungan', 'audit-lingkungan.index', 'audit-lingkungan*'],
        ],
        'Acuan & Data' => [
          ['Baku Mutu',            'lingkungan.baku',  'lingkungan/baku-mutu*'],
          ['Laporan Lingkungan',   'lingkungan.cetak', 'lingkungan/cetak'],
        ],
      ],
    ],
    'peledakan' => [
      'label' => 'Drill & Blast',
      'semboyan' => 'Precision Under Pressure',
      'kutipan' => 'Ketelitian sebelum ledakan adalah satu-satunya kendali yang kita punya.',
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
      'semboyan' => 'Safe Load Safe Road',
      'kutipan' => 'Muatan yang selamat sampai adalah muatan yang dimuat dengan benar.',
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
      'semboyan' => 'Every Rupiah Traced',
      'kutipan' => 'Biaya keselamatan selalu lebih murah daripada harga kecelakaan.',
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
      'semboyan' => 'Cleared Before Started',
      'kutipan' => 'Izin kerja bukan formalitas; ia janji bahwa bahayanya sudah diperiksa.',
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
      'semboyan' => 'Ground You Can Trust',
      'kutipan' => 'Lereng memberi peringatan sebelum runtuh — bagi yang memantaunya.',
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
      'semboyan' => 'Fixed Before It Fails',
      'kutipan' => 'Alat yang dirawat tepat waktu tidak pernah berhenti pada waktu yang salah.',
      'icon'  => 'M14.6 6.3a1 1 0 0 0 0 1.4l1.7 1.7a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z',
      'groups' => [
        'Pengawasan' => [
          ['Dashboard Keandalan', 'maintenance.index',  'pemeliharaan'],
          ['Perintah Kerja',      'maintenance.order',  'pemeliharaan/order*'],
          ['Armada',              'maintenance.armada', 'pemeliharaan/armada'],
          ['P2H Unit',            'maintenance.p2h',    'pemeliharaan/p2h'],
        ],
        'Data' => [
          ['Laporan Keandalan', 'maintenance.cetak', 'pemeliharaan/cetak'],
        ],
      ],
    ],

    'meh' => [
      'label' => 'Mining Engineering',
      'semboyan' => 'Designed To Last',
      'kutipan' => 'Rancangan yang baik menghilangkan bahaya sebelum ada yang harus menghindarinya.',
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
      'semboyan' => 'Right Part Right Time',
      'kutipan' => 'Suku cadang yang ada tetapi tidak ditemukan sama saja dengan tidak ada.',
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
      'semboyan' => 'One Source Of Truth',
      'kutipan' => 'Prosedur yang tidak dapat ditemukan tidak dapat diikuti.',
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
        /* Pertanyaan ketiga, dan yang paling sering ditanyakan auditor:
           kewajiban apa yang mengikat kita, dan sudah dipenuhi belum.
           Register dokumen menjawab apa yang dipunya, ISO menjawab
           klausul mana yang kosong — keduanya tidak menjawab yang ini. */
        'Pemenuhan' => [
          ['Dasbor Pemenuhan', 'kepatuhan.dasbor', 'kepatuhan'],
          ['Register Kewajiban','kepatuhan.index', 'kepatuhan/register'],
          ['Rekap Bulanan',    'kepatuhan.rekap',  'kepatuhan/rekap'],
          ['Pustaka Daftar Periksa', 'kepatuhan.pustaka', 'kepatuhan/pustaka'],
        ],
      ],
    ],
    /* Pembelian berdiri sendiri, dan sengaja BUKAN di bawah Admin.
       Admin mengurus aplikasi yang sudah dipakai; yang ini mengurus
       uang yang masuk untuk memakainya — dibaca orang yang berbeda,
       pada waktu yang berbeda. */
    'pembelian' => [
      'label' => 'Pembelian',
      'semboyan' => 'Better Solutions Brighter Tomorrow',
      'kutipan' => 'Yang dibeli murah tetapi tidak sesuai selalu dibayar dua kali.',
      'icon'  => 'M6.5 7.5h11l1.5 10.5a2 2 0 0 1-2 2.3H7a2 2 0 0 1-2-2.3L6.5 7.5Zm2.5 0V6a3 3 0 0 1 6 0v1.5',
      'groups' => [
        '' => [
          ['Katalog', 'pembelian.katalog', 'pembelian'],
          ['Tagihan', 'pembelian.daftar',  'pembelian/tagihan*'],
          /* Unsur keempat `true`: hanya untuk administrator. Tanpanya
             pengguna biasa melihat butir yang berujung 403 — halamannya
             sendiri menolak siapa pun selain admin. Bisa pula berupa nama
             gerbang (mis. 'audit'): butir tampil bagi yang lolos gerbang
             itu — sama dengan middleware `can:` rutenya. */
          ['Daftar Harga', 'pembelian.produk', 'pembelian/produk*', true],
        ],
      ],
    ],

    'admin' => [
      'label' => 'Administrasi',
      'semboyan' => 'Reliable System For A Safer Tomorrow',
      'kutipan' => 'Sistem yang andal mendukung operasional tambang yang aman dan berkelanjutan.',
      'icon'  => 'M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Zm7.4-.9a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z',
      'admin' => true,
      'groups' => [
        '' => [
          ['Pusat Kendali',   'admin.system',          'admin/system*'],
          ['Kelola Perusahaan','admin.companies.index','admin/companies*'],
          ['Kelola Pengguna', 'admin.users.index',     'admin/users*'],
          ['Aturan Akses Audit','admin.akses-audit',   'admin/akses-audit*'],
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
        [['dashboard', 'dasbor'],                  'dasbor'],
        [['personalia*', 'pesan*'],                'personalia'],
        [['hazard*', 'inspeksi*', 'temuan*'],      'hazrep'],
        [['tpkkp*'],                               'tpkkp'],
        [['smkp*'],                                'smkp'],
        /* 'kepatuhan*' didaftarkan BERSAMA dokumen dan ISO, bukan
           dibiarkan tanpa pasangan. Alamat yang tidak cocok dengan pola
           mana pun tidak menimbulkan galat sama sekali: halamannya tetap
           terbuka, hanya membawa sampul dan menu samping modul yang
           salah — dan yang membukanya menyimpulkan ia tersesat. */
        [['dokumen*', 'iso*', 'kepatuhan*', 'struktur-dokumen', 'daftar-induk'], 'dokumen'],
        [['energi*'],                              'energi'],
        [['konservasi*'],                          'konservasi'],
        [['operasi-tambang*'],                     'operasi'],
        [['penirisan*'],                           'air'],
        [['geoteknik*'],                           'geoteknik'],
        [['peledakan*'],                           'peledakan'],
        [['angkutan*'],                            'angkutan'],
        [['biaya*'],                               'biaya'],
        [['izin-kerja*'],                          'izin'],
        [['lingkungan*', 'audit-lingkungan*'],     'lingkungan'],
        [['pemeliharaan*'],                        'maintenance'],
        [['gudang*'],                              'gudang'],
        [['mining-engineering-hub*'],              'meh'],
        [['miners*'],                           'miners'],
        [['hris*'],                             'hris'],

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

        /* LMS menampung alamat yang tidak dikenali satu pun pola di atas
           — kursus, sertifikat, profil. Sejak /dashboard menjadi
           ringkasan situs, ia TIDAK lagi ikut ke sini: polanya disebut
           tersendiri pada PETA_ALAMAT, dan tanpa itu ringkasan situs akan
           menggambar bilah samping Learning Center di sekelilingnya. */
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
