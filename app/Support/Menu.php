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
    'tpkkp' => [
      'label' => 'Safety Maturity Level',
      'icon'  => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
      'groups' => [
        'Penilaian' => [
          ['Beranda',        'tpkkp.index',     'tpkkp'],
          ['Profil',         'tpkkp.profile',   'tpkkp/profil'],
          ['Formulir Nilai', 'tpkkp.assess',    'tpkkp/penilaian'],
          ['Kuesioner',      'tpkkp.kuesioner', 'tpkkp/kuesioner'],
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
        'Periode' => [
          ['Daftar Audit',   'smkp.index',  'smkp'],
          ['Buat Periode',   'smkp.create', 'smkp/buat'],
        ],
        'Tahap Audit' => [
          ['Permulaan Audit',   'smkp.ke.tahap1',  'smkp/lanjut/tahap-1'],
          ['Rencana Audit',     'smkp.ke.rencana', 'smkp/lanjut/rencana'],
          ['Rapat & Daftar Hadir','smkp.ke.rapat', 'smkp/lanjut/rapat'],
          ['Temuan & Tindakan', 'smkp.ke.temuan',  'smkp/lanjut/temuan'],
        ],
        'Berkas Resmi' => [
          ['Berita Acara Tahap I',  'smkp.ke.berita',        'smkp/lanjut/berita-acara'],
          ['Laporan Rencana Audit', 'smkp.ke.rencana-cetak', 'smkp/lanjut/laporan-rencana'],
          ['Laporan Audit',         'smkp.ke.laporan',       'smkp/lanjut/laporan-audit'],
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
    'meh' => [
      'label' => 'Mining Engineering',
      'icon'  => 'M9 3v18m6-18v18M3 9h18M3 15h18',
      'groups' => [
        'Operasi' => [
          ['Dashboard',            'meh.index',       'mining-engineering-hub'],
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
    'admin' => [
      'label' => 'Administrasi',
      'icon'  => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
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
  $aktif = $menu[$modul
        ];
    }

    /** Kunci modul yang sedang dibuka, ditentukan dari alamat sekarang. */
    public static function modulAktif(): string
    {
        $kunci = Request::is('personalia*') || Request::is('pesan*') ? 'personalia'
         : (Request::is('hazard*') || Request::is('inspeksi*') ? 'hazrep'
         : (Request::is('tpkkp*') ? 'tpkkp'
         : (Request::is('smkp*') ? 'smkp'
         : (Request::is('dokumen*') || Request::is('iso*') || Request::is('struktur-dokumen') || Request::is('daftar-induk') ? 'dokumen'
         : (Request::is('energi*') ? 'energi'
         : (Request::is('gudang*') ? 'gudang'
         : (Request::is('mining-engineering-hub*') ? 'meh'
         : (Request::is('ko*') ? 'ko'
         : (Request::is('admin*') || Request::is('signatories*') ? 'admin' : 'lms')))))))));

        return isset(self::all()[$kunci]) ? $kunci : 'lms';
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
