<!DOCTYPE html>
@php
  $eqTema = \App\Support\Tema::pilihan(auth()->user());
@endphp
<html lang="id" @if($eqTema) data-tema="{{ $eqTema }}" @endif style="{{ \App\Support\Tema::gaya(auth()->user()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">

{{-- Tema dipasang sebelum apa pun tergambar. Dijalankan setelah <body>
     dicat, layarnya berkedip terang sesaat sebelum berubah gelap — cacat
     yang paling terlihat justru pada pengguna yang memilih tema gelap. --}}
<script>
(function(){
  var t = null;
  try{
    t = document.documentElement.getAttribute('data-tema') || localStorage.getItem('eqTema');
  }catch(e){}

  /* Selalu diselesaikan menjadi nilai yang tegas. Kalau atribut ini
     dibiarkan kosong ketika orang belum memilih, seluruh aturan gelap
     harus ditulis dua kali — sekali untuk [data-tema="gelap"] dan sekali
     lagi di dalam prefers-color-scheme — dan dua salinan aturan warna
     yang panjang pasti akan berbeda isinya cepat atau lambat. */
  if(t !== 'gelap' && t !== 'terang'){
    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'gelap' : 'terang';
  }
  document.documentElement.setAttribute('data-tema', t);
})();
</script>
<title>@yield('title', 'Dashboard') — EQOHSEE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
</head>
<body class="antialiased">
@php
  // ===== Modul aktif =====
  $modul = request()->is('personalia*') ? 'personalia'
         : (request()->is('hazard*') || request()->is('inspeksi*') ? 'hazrep'
         : (request()->is('tpkkp*') ? 'tpkkp'
         : (request()->is('smkp*') ? 'smkp'
         : (request()->is('dokumen*') || request()->is('iso*') || request()->is('struktur-dokumen') || request()->is('daftar-induk') ? 'dokumen'
         : (request()->is('energi*') ? 'energi'
         : (request()->is('mining-engineering-hub*') ? 'meh'
         : (request()->is('ko*') ? 'ko'
         : (request()->is('admin*') || request()->is('signatories*') ? 'admin' : 'lms'))))))));

  $menu = [
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
  $aktif = $menu[$modul];
@endphp

@php
  /* Lencana lonceng menghitung pengumuman terbaru. Angka tetap pada
     lencana adalah kebohongan kecil yang tidak pernah berubah; ini
     mengikuti isi tabelnya. */
  $eqPengumuman = \Illuminate\Support\Facades\Schema::hasTable('news')
      ? \App\Models\News::where('created_at', '>=', now()->subDays(30))->count()
      : 0;
@endphp

@include('partials.eq-visual')

<div class="min-h-screen flex">

  {{-- ===== SIDEBAR ===== --}}
  <div id="eqOverlay" onclick="eqToggle()" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 hidden lg:hidden"></div>
  <aside id="eqSidebar"
         class="brand-gradient fixed lg:static inset-y-0 left-0 z-40 w-[248px] shrink-0 flex flex-col
                text-white/70 -translate-x-full lg:translate-x-0 transition-transform duration-300">

    {{-- Kepala: lambang, nama, dan janji yang dibawanya. Tagline berdiri
         di bawah nama, bukan di sebelahnya — sebaris dua-duanya membuat
         nama kehilangan bobot, padahal itu yang harus terbaca lebih dulu. --}}
    <a href="{{ route('dashboard') }}" class="eq-merek">
      <img src="{{ asset('brand/eqohsee-mark-white.svg') }}" alt="" width="38" height="42">
      <span>
        <strong>E<em>Q</em>OHSEE</strong>
        <small>Safety is Our Priority</small>
      </span>
    </a>

    {{-- Pemilih modul --}}
    <div class="px-3 pt-3.5">
      <div class="glass rounded-xl p-1 grid grid-cols-3 gap-1">
        @foreach($menu as $key => $m)
          @if(!($m['admin'] ?? false) || (auth()->check() && auth()->user()->isAdmin()))
            <a href="{{ route(collect($m['groups'])->flatten(1)->first()[1]) }}" title="{{ $m['label'] }}"
               class="grid place-items-center py-2 rounded-lg transition {{ $modul === $key ? 'lime-gradient text-white shadow-glow' : 'text-white/40 hover:text-white hover:bg-white/5' }}">
              <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m['icon'] }}"/></svg>
            </a>
          @endif
        @endforeach
      </div>
      <div class="mt-2.5 px-1 text-[10px] font-bold uppercase tracking-[0.18em] text-cam-lime-light">{{ $aktif['label'] }}</div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-3">
      @foreach($aktif['groups'] as $grup => $items)
        @if($grup)
          <p class="px-3 mt-3 mb-1 text-[9.5px] font-semibold uppercase tracking-[0.12em] text-white/20">{{ $grup }}</p>
        @endif
        <div class="space-y-0.5">
          @foreach($items as [$label, $rute, $cocok])
            <a href="{{ route($rute) }}"
               class="{{ request()->is($cocok) ? 'nav-active' : '' }} relative flex items-center gap-3 rounded-xl px-3 py-2.5
                      text-[12.5px] font-semibold hover:bg-white/5 hover:text-white transition">
              <span class="nav-accent absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-5 rounded-r-full bg-cam-lime-light opacity-0"></span>
              {!! \App\Support\IkonNav::svg($label) !!}
              <span>{{ $label }}</span>
            </a>

          @endforeach
        </div>
      @endforeach

    </nav>

    <div class="eq-sisi-kaki">
      <div class="eq-bantuan">
        <span class="eq-bantuan-ikon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3.5a8.5 8.5 0 0 1 8.5 8.5v4.8a2.7 2.7 0 0 1-2.7 2.7h-1.3v-7.2h4M3.5 16.8V12A8.5 8.5 0 0 1 12 3.5"/>
            <path d="M3.5 12.3h3.9v7.2H6.2a2.7 2.7 0 0 1-2.7-2.7Z"/>
          </svg>
        </span>
        <span class="eq-bantuan-teks">
          <strong>Butuh Bantuan?</strong>
          <small>Kami siap membantu Anda kapan saja.</small>
        </span>
      </div>
      <a href="{{ route('news.index') }}" class="eq-bantuan-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.2-.6L3.5 21l1.7-4.6A8.2 8.2 0 0 1 4 11.5a8.4 8.4 0 0 1 9-8.4 8.4 8.4 0 0 1 8 8.4Z"/>
        </svg>
        Hubungi Kami
      </a>

      <div class="eq-sisi-bawah">
        <small>© {{ date('Y') }} EQOHSEE<br>All rights reserved.</small>
        <button type="button" onclick="eqLipat()" class="eq-lipat" aria-label="Lipat bilah samping">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M13 7l-5 5 5 5M18 7l-5 5 5 5"/>
          </svg>
        </button>
      </div>
    </div>

  </aside>

  {{-- ===== KONTEN ===== --}}
  <div class="flex-1 flex flex-col min-w-0">
    <header class="eq-topbar">
      <button onclick="eqToggle()" class="eq-menu-btn" aria-label="Buka menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>

      {{-- min-w-0 wajib: tanpa itu item flex menolak menyusut di bawah lebar
           isinya, sehingga judul panjang mendorong blok pengguna keluar layar
           di ponsel — `truncate` sendiri tidak cukup. --}}
      <div class="eq-judul min-w-0 flex-1">
        <h1>@yield('title', 'Dashboard')</h1>
        @hasSection('subjudul')
          <p>@yield('subjudul')</p>
        @endif
      </div>

      <div class="eq-topbar-aksi">
        @auth
        {{-- Sakelar tema. Pilihannya disimpan di akun supaya ikut berpindah
             antar perangkat, dan dicerminkan ke localStorage supaya
             pemuatan berikutnya tidak berkedip sebelum jawaban server
             sampai. --}}
        <button type="button" onclick="eqTema()" class="eq-bulat eq-tema-btn"
                aria-label="Ganti tema terang atau gelap" title="Tema terang / gelap">
          <svg class="eq-ikon-terang" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="12" r="4.2"/>
            <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
          </svg>
          <svg class="eq-ikon-gelap" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5a8.6 8.6 0 1 0 10.8 10.8Z"/>
          </svg>
        </button>

        <div class="eq-lonceng">
          <a href="{{ route('news.index') }}" class="eq-bulat" aria-label="Pengumuman">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 3.2a5.3 5.3 0 0 0-5.3 5.3v3.7l-1.9 3.1h14.4l-1.9-3.1V8.5A5.3 5.3 0 0 0 12 3.2Z"/>
              <path d="M9.9 18.4a2.2 2.2 0 0 0 4.2 0"/>
            </svg>
            @if(($eqPengumuman ?? 0) > 0)
              <span class="eq-lonceng-titik">{{ $eqPengumuman > 9 ? '9+' : $eqPengumuman }}</span>
            @endif
          </a>
        </div>

        <a href="{{ route('personalia.index') }}" class="eq-profil" title="Data diri">
          @if(auth()->user()->avatar)
            <img class="eq-avatar eq-avatar-foto" src="{{ asset('storage/'.auth()->user()->avatar) }}"
                 alt="" width="38" height="38">
          @else
            <span class="eq-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
          @endif
          <span class="eq-profil-teks">
            <strong>{{ auth()->user()->name }}</strong>
            <small>{{ auth()->user()->position ?: (auth()->user()->isAdmin() ? 'Administrator' : ucfirst(auth()->user()->lms_role ?: 'Peserta')) }}</small>
          </span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
          @csrf
          <button class="eq-keluar" aria-label="Keluar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/>
            </svg>
            <span class="hidden sm:inline">Keluar</span>
          </button>
        </form>
        @endauth
      </div>
    </header>

    <main class="flex-1 p-5 md:p-7 animate-fadeIn">@yield('content')</main>
  </div>
</div>

<script>
  function eqToggle(){
    document.getElementById('eqSidebar').classList.toggle('-translate-x-full');
    document.getElementById('eqOverlay').classList.toggle('hidden');
  }

  /* Lebar bilah samping diingat antar halaman. Kalau tidak, tiap
     perpindahan halaman mengembalikannya ke lebar penuh, dan pilihan itu
     harus diulang terus-menerus sampai orang berhenti memakainya. */
  function eqLipat(){
    const sempit = document.body.classList.toggle('eq-sempit');
    try { localStorage.setItem('eq-sisi-sempit', sempit ? '1' : '0'); } catch (e) { /* mode privat */ }
  }
  try {
    if (localStorage.getItem('eq-sisi-sempit') === '1') document.body.classList.add('eq-sempit');
  } catch (e) { /* mode privat */ }

  /* Sakelar tema.

     Tampilannya berubah lebih dulu, lalu pilihannya dikirim ke server.
     Menunggu jawaban server sebelum mengubah warna membuat tombolnya
     terasa macet pada sambungan lapangan yang lambat — dan kalau
     pengirimannya gagal, yang hilang hanya keawetan pilihan antar
     perangkat, bukan sakelarnya itu sendiri. */
  function eqTema(){
    const akar = document.documentElement;
    const kini = akar.getAttribute('data-tema')
              || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'gelap' : 'terang');
    const baru = kini === 'gelap' ? 'terang' : 'gelap';

    akar.setAttribute('data-tema', baru);
    try { localStorage.setItem('eqTema', baru); } catch (e) { /* mode privat */ }

    const t = document.querySelector('meta[name=csrf-token]');
    if (!t) return;

    fetch(@json(route('personalia.tema')), {
      method: 'POST',
      headers: {'Content-Type':'application/json','X-CSRF-TOKEN':t.content,'Accept':'application/json'},
      body: JSON.stringify({tema: baru})
    }).catch(function(){ /* pilihan tetap berlaku di perangkat ini */ });
  }
</script>
@stack('scripts')
</body>
</html>
