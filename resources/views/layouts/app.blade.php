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
<link rel="icon" type="image/svg+xml" href="{{ \App\Support\Aset::v('brand/favicon.svg') }}">
  <link rel="icon" href="{{ \App\Support\Aset::v('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" href="{{ \App\Support\Aset::v('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ \App\Support\Aset::v('apple-touch-icon.png') }}">
</head>
<body class="antialiased">
@php
  /* Menu dan modul aktif dibaca dari App\Support\Menu — sumber tunggal
     yang juga dipakai tampilan Inertia. Larik ini dulu tertulis di sini;
     dua salinan menu akan berbeda diam-diam setiap kali ada halaman baru. */
  $modul = \App\Support\Menu::modulAktif();
  $menu  = \App\Support\Menu::all();
  $aktif = \App\Support\Menu::modul($modul);

  /* Sekali di sini, bukan sekali per butir menu — lihat App\Support\Lencana. */
  $lencana = \App\Support\Lencana::semua(auth()->user());
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
      <img src="{{ asset('brand/eqohsee-mark.png') }}" alt="" width="40" height="40">
      <span>
        <strong>E<em>Q</em>OHSEE</strong>
        <small>Safe Today · Sustainable Tomorrow</small>
      </span>
    </a>

    {{-- Pemilih modul --}}
    <div class="px-3 pt-3.5">
      <div class="glass rounded-xl p-1 grid grid-cols-3 gap-1">
        @foreach($menu as $key => $m)
          @if(!($m['admin'] ?? false) || (auth()->check() && auth()->user()->isAdmin()))
            <a href="{{ route(collect($m['groups'])->flatten(1)->first()[1]) }}" title="{{ $m['label'] }}"
               class="relative grid place-items-center py-2 rounded-lg transition {{ $modul === $key ? 'lime-gradient text-white shadow-glow' : 'text-white/40 hover:text-white hover:bg-white/5' }}">
              <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m['icon'] }}"/></svg>
              @if(\App\Support\Lencana::modul($m, $lencana))
                <span class="absolute top-0.5 right-0.5 min-w-[7px] h-[7px] rounded-full bg-cam-lime-light"></span>
              @endif
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
              @if($n = ($lencana[$rute] ?? null))
                <span class="ml-auto text-[10px] font-bold leading-none px-1.5 py-1 rounded-full bg-cam-lime-light text-cam-ink">
                  {{ $n > 99 ? '99+' : $n }}
                </span>
              @endif
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
      <a href="{{ route('bantuan.index') }}" class="eq-bantuan-btn">
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
