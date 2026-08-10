<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
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
  $modul = request()->is('hazard*') || request()->is('inspeksi*') ? 'hazrep'
         : (request()->is('tpkkp*') ? 'tpkkp'
         : (request()->is('smkp*') ? 'smkp'
         : (request()->is('dokumen*') || request()->is('iso*') || request()->is('struktur-dokumen') || request()->is('daftar-induk') ? 'dokumen'
         : (request()->is('energi*') ? 'energi'
         : (request()->is('ko*') ? 'ko'
         : (request()->is('admin*') || request()->is('signatories*') ? 'admin' : 'lms'))))));

  $menu = [
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

@include('partials.eq-visual')

<div class="min-h-screen flex">

  {{-- ===== SIDEBAR ===== --}}
  <div id="eqOverlay" onclick="eqToggle()" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 hidden lg:hidden"></div>
  <aside id="eqSidebar"
         class="brand-gradient fixed lg:static inset-y-0 left-0 z-40 w-[248px] shrink-0 flex flex-col
                text-white/70 -translate-x-full lg:translate-x-0 transition-transform duration-300">

    <div class="h-[62px] flex items-center gap-2.5 px-5 border-b border-white/10">
      <img src="{{ asset('brand/eqohsee-mark-white.svg') }}" alt="EQOHSEE" style="width:26px;height:29px;flex:none">
      <span style="font-size:17px;font-weight:900;letter-spacing:-.01em;line-height:1;color:#EAF1F5">E<span style="color:#2CB0BC">Q</span>OHSEE</span>
    </div>

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
              {!! isset($eqIcon) ? $eqIcon($label) : '' !!}
              <span>{{ $label }}</span>
            </a>

          @endforeach
        </div>
      @endforeach
    </nav>

    <div class="m-3 rounded-xl glass p-3.5">
      <div class="text-[11px] font-bold text-white/90">Butuh bantuan?</div>
      <div class="text-[11px] text-white/45 mt-0.5 leading-relaxed">Hubungi tim HSE untuk pertanyaan seputar pelatihan.</div>
    </div>
  </aside>

  {{-- ===== KONTEN ===== --}}
  <div class="flex-1 flex flex-col min-w-0">
    <header class="eq-topbar h-[62px] relative flex items-center gap-3 px-4 lg:px-7 sticky top-0">
      <button onclick="eqToggle()" class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-black/5 shrink-0" aria-label="Menu">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      {{-- min-w-0 wajib: tanpa itu item flex menolak menyusut di bawah lebar
           isinya, sehingga judul panjang mendorong blok pengguna keluar layar
           di ponsel — `truncate` sendiri tidak cukup. --}}
      <h1 class="text-[15px] font-bold text-cam-ink truncate min-w-0 flex-1">@yield('title', 'Dashboard')</h1>

      <div class="flex items-center gap-2.5 shrink-0">
        @auth
        <div class="text-right leading-tight hidden sm:block">
          <div class="text-[12.5px] font-bold text-cam-ink">{{ auth()->user()->name }}</div>
          <div class="text-[10.5px] text-stone-400">{{ auth()->user()->isAdmin() ? 'Administrator' : ucfirst(auth()->user()->lms_role ?: 'Peserta') }}</div>
        </div>
        <div class="w-9 h-9 shrink-0 rounded-xl lime-gradient text-white grid place-items-center font-bold text-[13px] shadow-glow">
          {{ strtoupper(substr(auth()->user()->name,0,1)) }}
        </div>
        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
          @csrf
          {{-- Di ponsel hanya ikon keluar; teks muncul mulai lebar sm. --}}
          <button class="px-2 sm:px-3 py-2 rounded-lg text-[12.5px] font-semibold text-stone-500 hover:text-cam-lime-deep hover:bg-cam-lime-soft transition"
                  aria-label="Keluar">
            <span class="hidden sm:inline">Keluar</span>
            <svg class="w-5 h-5 sm:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5M20 12H9M12 19H6a2 2 0 01-2-2V7a2 2 0 012-2h6"/>
            </svg>
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
</script>
@stack('scripts')
</body>
</html>
