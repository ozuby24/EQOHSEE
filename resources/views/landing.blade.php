<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EQOHSEE — Platform Terpadu Keselamatan Pertambangan</title>
<meta name="description" content="Satu platform untuk pembelajaran, penilaian kinerja keselamatan, prosedur, dan sertifikasi di industri pertambangan.">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
@vite(['resources/css/app.css','resources/js/app.js'])
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
</head>
<body class="bg-cam-bg">
@php $daftarModul = \App\Support\Modules::all(); @endphp

{{-- ══════════ NAV ══════════ --}}
<header class="sticky top-0 z-40 glass-light border-b border-black/5">
  <div class="max-w-6xl mx-auto px-5 h-[62px] flex items-center gap-3">
    <x-brand variant="wordmark" class="h-6" />
    <nav class="ml-auto hidden md:flex items-center gap-1 text-[12.5px] font-semibold text-stone-500">
      @foreach ([['#pilar','Pilar'],['#modul','Modul'],['#fitur','Fitur'],['#alur','Cara Kerja']] as [$h,$l])
        <a href="{{ $h }}" class="px-3 py-2 rounded-lg hover:text-cam-ink hover:bg-black/5 transition">{{ $l }}</a>
      @endforeach
    </nav>
    <a href="{{ route('login') }}"
       class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2 text-[12.5px] font-bold hover:brightness-105 transition {{ request()->routeIs('login') ? '' : 'md:ml-2' }}">Masuk</a>
  </div>
</header>

{{-- ══════════ HERO ══════════ --}}
{{-- Kedalaman dibangun dari lapisan yang bergerak pada laju berbeda:
     seni tambang paling belakang, pendar di tengah, teks paling depan.
     Laju diatur lewat data-parallax dan dijalankan di rAF. --}}
<section class="relative brand-gradient text-white overflow-hidden aurora">
  {{-- Panorama dibiarkan hampir penuh; yang meredamnya cuma tirai di sisi
       kiri, tepat selebar kolom teks. Menutupi seluruh bidang hanya akan
       membuang adegan yang justru menjadi alasan hero ini ada. --}}
  <div class="absolute inset-0 opacity-[.92]" data-parallax="0.14">@include('partials.art-mine')</div>
  <div class="absolute inset-0 grid-tech pointer-events-none opacity-40" data-parallax="0.06"></div>
  <div class="absolute inset-0 bg-gradient-to-r from-cam-black via-cam-black/55 via-38% to-transparent"></div>
  {{-- Dasar hero dilarutkan ke warna bidang berikutnya, bukan digelapkan
       ke hitam — supaya pergantian bagian tidak menyisakan garis lurus. --}}
  <div class="sambung sambung-bawah h-36 bg-gradient-to-b from-transparent to-cam-ink"></div>
  <div class="absolute -right-24 -top-24 w-[380px] h-[380px] rounded-full bg-cam-lime/20 blur-3xl apung" data-parallax="0.2"></div>
  <div class="absolute -left-16 bottom-[-90px] w-[300px] h-[300px] rounded-full bg-cam-coral/12 blur-3xl apung-2" data-parallax="0.1"></div>

  <div class="relative max-w-6xl mx-auto px-5 py-16 md:py-24">
    <div class="max-w-2xl animate-fadeUp">
      <span class="inline-flex items-center gap-2 glass rounded-full px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.18em] text-cam-lime-light">
        <span class="w-1.5 h-1.5 rounded-full bg-cam-lime animate-pulse"></span>
        Delapan Aspek · Satu Platform
      </span>

      <h1 class="font-display text-[38px] md:text-[58px] font-black mt-5 leading-[1.05] text-shadow">
        Keselamatan tambang,<br>
        {{-- Kilau menyapu dari teal ke pasir hangat, bukan teal ke putih —
             pergeseran suhu warnanya yang memberi kesan bahan, bukan kilapnya. --}}
        <span class="sheen" style="--sheen-base:#2A9D8F; --sheen-hi:#F5E6CA">terukur dan terbukti.</span>
      </h1>

      <p class="text-[14px] md:text-[15px] text-white/55 mt-5 leading-relaxed max-w-lg">
        Satu platform untuk pembelajaran, penilaian kinerja keselamatan, prosedur kerja,
        dan sertifikasi — dirancang mengikuti regulasi keselamatan pertambangan Indonesia.
      </p>

      <div class="flex flex-wrap gap-2.5 mt-7">
        <a href="{{ route('login') }}" class="lime-gradient shadow-glow rounded-xl text-white px-6 py-3 text-[13.5px] font-bold hover:brightness-105 transition">Masuk ke Platform</a>
        <a href="#modul" class="glass rounded-xl px-6 py-3 text-[13.5px] font-bold hover:bg-white/15 transition">Lihat Modul</a>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-10 max-w-xl">
        @foreach ([['194','Item penilaian'],['7','Elemen SMKP'],['6','Modul terpadu'],['24/7','Akses']] as [$n,$l])
          <div class="glass rounded-xl px-4 py-3">
            <div class="stat stat-sm text-cam-lime-light">{{ $n }}</div>
            <div class="text-[10.5px] text-white/45 mt-1.5">{{ $l }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>

{{-- ══════════ PILAR ══════════ --}}
@php
  $pilar = \App\Support\Pillars::all();
  // Ikon heksagon per pilar (dipakai bersama halaman /pilar).
  $pilarIkon = [
    'energy'      => '<path d="M12 1.5A10.5 10.5 0 1 0 22.5 12 10.51 10.51 0 0 0 12 1.5Zm0 19A8.5 8.5 0 1 1 20.5 12 8.51 8.51 0 0 1 12 20.5Z"/><path d="M13.2 5.6 7.4 13h3.3l-.9 5.4 5.8-7.4h-3.3l.9-5.4Z"/>',
    'quality'     => '<path d="M12 2.2S5.6 9.3 5.6 13.6a6.4 6.4 0 0 0 12.8 0C18.4 9.3 12 2.2 12 2.2Zm0 16.1a4.7 4.7 0 0 1-4.7-4.7c0-2.6 3.1-6.8 4.7-8.7 1.6 1.9 4.7 6.1 4.7 8.7a4.7 4.7 0 0 1-4.7 4.7Z"/>',
    'occhealth'   => '<path d="M11 3a5 5 0 0 0-5 4.6h10A5 5 0 0 0 11 3Zm-6 5.6a1 1 0 0 0 0 2h12a1 1 0 0 0 0-2Zm6 3.1a5 5 0 0 0-5 5v2.7h6.3a6.4 6.4 0 0 1 2.2-7.4 5 5 0 0 0-3.5-1.3Z"/><path d="M18.2 12.6a4.6 4.6 0 1 0 0 9.2 4.6 4.6 0 0 0 0-9.2Zm2.3 5.4h-1.6v1.6h-1.4V18h-1.6v-1.4h1.6V15h1.4v1.6h1.6Z"/>',
    'safety'      => '<path d="M12 1.8 3.8 5v6.2c0 5.1 3.5 9.8 8.2 11 4.7-1.2 8.2-5.9 8.2-11V5Zm0 2.2 6.2 2.4v4.8c0 4-2.6 7.8-6.2 8.9-3.6-1.1-6.2-4.9-6.2-8.9V6.4Z"/><path d="m10.9 14.4-2.2-2.2-1.3 1.4 3.5 3.5 6-6-1.4-1.4Z"/>',
    'environment' => '<path d="M20.6 3.6c-8 0-13.4 3.2-13.4 9.4a7.9 7.9 0 0 0 1.1 4.2c1.6-3.6 4.5-6.4 8.2-8-3 2.2-5.3 5.3-6.4 8.9l-.9 2.9h2.1l.6-2c6.6-.4 8.7-6 8.7-15.4Z"/>',
    'hygiene'     => '<path d="M9.4 2.2a1 1 0 0 0-1 1v3.1L4.6 15a4.6 4.6 0 0 0 4.2 6.5h6.4A4.6 4.6 0 0 0 19.4 15l-3.8-8.7V3.2a1 1 0 0 0-1-1Zm1 2h3.2v2.4h-3.2Zm-.2 4.4h3.6l1.3 3H8.9Zm-2.1 4.9h9.8l1.3 2.9a2.6 2.6 0 0 1-2.4 3.6H8.8a2.6 2.6 0 0 1-2.4-3.6Z"/>',
    'konservasi'  => '<path d="M8.1 2.6 4 8.3l8 12.9 8-12.9-4.1-5.7Zm.9 2h6l2.6 3.7h-4.2l-1.4-3.7Zm-.9.6 1.4 3.1H6.1Zm-2.4 5.1h3.6l1.7 6.9Zm5.7 0h1.4l-.7 7Zm3.5 0h3.6L13.7 17Z"/>',
    'engineering' => '<path d="M21 13.1v-2.2l-2.4-.4a6.9 6.9 0 0 0-.8-1.9l1.4-2-1.6-1.6-2 1.4a6.9 6.9 0 0 0-1.9-.8L13.1 3h-2.2l-.4 2.6a6.9 6.9 0 0 0-1.9.8l-2-1.4-1.6 1.6 1.4 2a6.9 6.9 0 0 0-.8 1.9L3 10.9v2.2l2.6.4a6.9 6.9 0 0 0 .8 1.9l-1.4 2 1.6 1.6 2-1.4a6.9 6.9 0 0 0 1.9.8l.4 2.6h2.2l.4-2.6a6.9 6.9 0 0 0 1.9-.8l2 1.4 1.6-1.6-1.4-2a6.9 6.9 0 0 0 .8-1.9ZM12 15.4A3.4 3.4 0 1 1 15.4 12 3.4 3.4 0 0 1 12 15.4Z"/>',
  ];
  // Warna tiap huruf EQOHSEE → aspek. Tujuh huruf, tujuh aspek; aspek
  // kedelapan (Konservasi Minerba) berdiri di luar akronim.
  $wordmark = [
    ['E','energy'], ['Q','quality'], ['O','occhealth'], ['H','hygiene'],
    ['S','safety'], ['E','environment'], ['E','engineering'],
  ];
@endphp
<section id="pilar" class="relative bg-cam-ink text-white overflow-hidden aurora"
         x-data="{ aktif: null, buka(s){ this.aktif = this.aktif === s ? null : s } }">
  <div class="absolute inset-0 grid-tech pointer-events-none"></div>

  <div class="relative max-w-6xl mx-auto px-5 py-20 md:py-28">

    {{-- Kepala bagian --}}
    <div class="text-center max-w-2xl mx-auto reveal">
      <span class="text-[10.5px] font-bold uppercase tracking-[0.28em] text-cam-lime-light">Kerangka Kerja</span>
      <h2 class="font-display text-[32px] md:text-[46px] font-black mt-4 leading-[1.08]">
        <span class="sheen text-white">Delapan aspek, satu sistem</span>
      </h2>
      <p class="text-[13.5px] md:text-[14.5px] text-white/50 mt-4 leading-relaxed">
        Tujuh huruf pada <strong class="text-white/80">EQOHSEE</strong> mewakili satu aspek masing-masing, ditambah Konservasi Minerba di luar akronim.
        Sentuh salah satunya untuk melihat apa yang dikerjakan di dalamnya.
      </p>
    </div>

    {{-- Wordmark: tiap huruf berwarna pilarnya, dan bisa diklik --}}
    <div class="flex justify-center mt-10 reveal reveal-d1">
      <div class="font-display text-[42px] md:text-[64px] font-black tracking-[.04em] flex select-none">
        @foreach ($wordmark as $i => [$huruf, $slug])
          <button type="button" @click="buka('{{ $slug }}')"
                  class="transition-transform duration-300 hover:-translate-y-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/60 rounded"
                  :class="aktif === '{{ $slug }}' ? '-translate-y-2' : ''"
                  style="color:{{ $pilar[$slug]['warna'] }}"
                  aria-label="Lihat pilar {{ $pilar[$slug]['nama'] }}">{{ $huruf }}</button>
        @endforeach
      </div>
    </div>

    {{-- Kartu pilar --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-12 pers">
      @foreach ($pilar as $slug => $p)
        <button type="button" @click="buka('{{ $slug }}')"
                class="group text-left kaca-gelap rounded-2xl p-5 tilt tilt-fast d3 reveal reveal-d{{ min($loop->iteration, 6) }}
                       hover:kaca-gelap-kuat focus:outline-none focus-visible:ring-2 focus-visible:ring-white/50 transition-colors"
                :class="aktif === '{{ $slug }}' ? 'kaca-gelap-kuat' : ''"
                data-tilt
                aria-expanded="false" :aria-expanded="(aktif === '{{ $slug }}').toString()">
          <div class="flex items-start gap-3.5 d3">
            <span class="shrink-0 w-11 h-11 rounded-xl grid place-items-center lift-1 shadow-lg"
                  style="background:{{ \App\Support\Pillars::gradient($slug) }}">
              <svg class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">{!! $pilarIkon[$slug] !!}</svg>
            </span>
            <div class="min-w-0">
              <h3 class="text-[14.5px] font-bold leading-snug">{{ $p['nama'] }}</h3>
              <p class="text-[12px] text-white/45 mt-1 leading-relaxed">{{ $p['ket'] }}</p>
            </div>
          </div>

          <div class="flex items-center gap-1.5 mt-4 text-[11px] font-bold"
               style="color:{{ $p['light'] }}">
            <span x-text="aktif === '{{ $slug }}' ? 'Tutup rincian' : 'Lihat rincian'">Lihat rincian</span>
            <svg class="w-3.5 h-3.5 transition-transform duration-300" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.4"
                 :class="aktif === '{{ $slug }}' ? 'rotate-90' : 'group-hover:translate-x-0.5'">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
        </button>
      @endforeach
    </div>

    {{-- Panel rincian: satu panel dipakai bergantian, memakai transisi 3D --}}
    @foreach ($pilar as $slug => $p)
      <div x-show="aktif === '{{ $slug }}'" x-cloak
           x-transition:enter="transition duration-500 ease-out"
           x-transition:enter-start="opacity-0 translate-y-6 scale-[.97]"
           x-transition:enter-end="opacity-100 translate-y-0 scale-100"
           x-transition:leave="transition duration-200 ease-in"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0"
           class="mt-6 rounded-2xl overflow-hidden kaca-gelap-kuat"
           style="border-color:{{ $p['warna'] }}55">

        <div class="grid md:grid-cols-[minmax(0,1fr)_260px] gap-6 p-6 md:p-8">
          <div class="min-w-0 order-2 md:order-1">
            <div class="flex flex-wrap items-center gap-2.5">
              <span class="w-9 h-9 rounded-lg grid place-items-center shrink-0"
                    style="background:{{ \App\Support\Pillars::gradient($slug) }}">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">{!! $pilarIkon[$slug] !!}</svg>
              </span>
              <h3 class="font-display text-[22px] md:text-[26px] font-black">{{ $p['nama'] }}</h3>
            </div>

            <p class="text-[13px] md:text-[13.5px] text-white/60 mt-4 leading-relaxed max-w-xl">{{ $p['ringkas'] }}</p>

            <hr class="rule-grad my-6">

            <div class="space-y-3.5">
              @foreach ($p['cakupan'] as [$judul, $isi])
                <div class="flex gap-3">
                  <span class="shrink-0 w-1.5 h-1.5 rounded-full mt-2" style="background:{{ $p['light'] }}"></span>
                  <div class="min-w-0">
                    <div class="text-[12.5px] font-bold">{{ $judul }}</div>
                    <div class="text-[12px] text-white/45 mt-0.5 leading-relaxed">{{ $isi }}</div>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="flex flex-wrap gap-1.5 mt-6">
              <span class="text-[10px] font-bold uppercase tracking-wider text-white/30 self-center mr-1">Ditopang modul</span>
              @foreach ($p['modul'] as $m)
                <span class="text-[10.5px] font-semibold rounded-full px-2.5 py-1"
                      style="background:{{ $p['warna'] }}22; color:{{ $p['light'] }}">{{ $m }}</span>
              @endforeach
            </div>
          </div>

          {{-- Adegan pilar --}}
          <div class="order-1 md:order-2 self-center max-w-[240px] mx-auto w-full apung">
            @include('partials.pilar-visual', ['slug' => $slug, 'p' => $p])
          </div>
        </div>
      </div>
    @endforeach

  </div>

  {{-- Peralihan gelap ke terang: bidang paling tajam di halaman ini, jadi
       dilarutkan paling panjang. Pita pasir tipis di ujungnya menahan
       supaya batasnya tetap terasa hangat, bukan mendadak putih. --}}
  <div class="sambung sambung-bawah h-64 bg-gradient-to-b from-transparent via-[#2C4A45] via-55% to-cam-bg"></div>
</section>

{{-- ══════════ MODUL ══════════ --}}
<section id="modul" class="max-w-6xl mx-auto px-5 py-16 md:py-20">
  <div class="text-center max-w-xl mx-auto reveal">
    <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Aplikasi di Dalamnya</span>
    @php
      $jumlahModul = count($daftarModul);
      $modulAktif  = collect($daftarModul)->where('status','aktif')->count();
      $angka = [1=>'Satu',2=>'Dua',3=>'Tiga',4=>'Empat',5=>'Lima',6=>'Enam',7=>'Tujuh',8=>'Delapan',9=>'Sembilan',10=>'Sepuluh'];
    @endphp
    <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3 leading-tight">{{ $angka[$jumlahModul] ?? $jumlahModul }} modul, satu akun</h2>
    <p class="text-[13.5px] text-stone-500 mt-3 leading-relaxed">
      {{ $modulAktif }} modul sudah aktif dan siap dipakai. Semua modul berbagi data perusahaan,
      pengguna, dan peran yang sama — tidak perlu login berulang.
    </p>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-10 pers">
    @foreach($daftarModul as $m)
      @php
        // Warna kartu diambil dari pilar yang ditopang modul ini, sehingga
        // bagian Modul terbaca sebagai kelanjutan bagian Pilar di atasnya.
        $slugPilar = $m['pilar'] ?? \App\Support\Pillars::forModule($m['nama']);
        $wp        = \App\Support\Pillars::get($slugPilar);
        $aktif     = ($m['status'] ?? '') === 'aktif';
        $rute      = $m['rute'] ?? null;
        $tautan    = $rute && \Illuminate\Support\Facades\Route::has($rute) ? route($rute) : null;
        $tag       = $tautan ? 'a' : 'div';
      @endphp

      <{{ $tag }} @if($tautan) href="{{ $tautan }}" @endif data-tilt
         class="group relative overflow-hidden bg-white rounded-2xl shadow-card border border-stone-100 p-6
                tilt tilt-fast d3 reveal reveal-d{{ min($loop->iteration, 6) }}
                {{ $tautan ? 'hover:border-transparent hover:shadow-lg' : '' }} transition-shadow">

        {{-- pita warna pilar di tepi atas --}}
        <span class="absolute inset-x-0 top-0 h-[3px]"
              style="background:{{ $aktif ? \App\Support\Pillars::gradient($slugPilar) : '#E7E5E4' }}"></span>

        <span class="absolute -right-10 -top-10 w-28 h-28 rounded-full transition"
              style="background:{{ $wp['warna'] }}0D"></span>

        <div class="relative d3">
          <div class="w-11 h-11 rounded-xl grid place-items-center lift-1 {{ $aktif ? 'text-white shadow-lg' : 'bg-stone-100 text-stone-400' }}"
               @if($aktif) style="background:{{ \App\Support\Pillars::gradient($slugPilar) }}" @endif>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $m['ikon'] }}"/></svg>
          </div>

          <div class="flex flex-wrap items-center gap-2 mt-4">
            <h3 class="text-[14.5px] font-bold text-cam-ink">{{ $m['nama'] }}</h3>
            @if($aktif)
              <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wide"
                    style="background:{{ $wp['warna'] }}1A; color:{{ $wp['deep'] }}">Aktif</span>
            @else
              <span class="text-[9px] font-bold bg-stone-100 text-stone-400 px-1.5 py-0.5 rounded uppercase tracking-wide">Segera</span>
            @endif
          </div>

          <p class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">{{ $m['ket'] }}</p>

          <div class="flex items-center gap-1.5 mt-4 text-[11px] font-bold"
               style="color:{{ $aktif ? $wp['deep'] : '#A8A29E' }}">
            <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $wp['warna'] }}"></span>
            <span>Pilar {{ $wp['nama'] }}</span>
            @if($tautan)
              <svg class="w-3.5 h-3.5 ml-auto transition-transform group-hover:translate-x-1" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2.4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            @endif
          </div>
        </div>
      </{{ $tag }}>
    @endforeach
  </div>
</section>

{{-- ══════════ FITUR ══════════ --}}
{{-- Garis tegas diganti gradien pasir: bidang terang bertemu terang, jadi
     cukup pergeseran nada tipis untuk memisahkannya. --}}
<section id="fitur" class="relative bg-gradient-to-b from-cam-bg via-white to-cam-bg">
  <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
    <div class="text-center max-w-xl mx-auto reveal">
      <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Fitur Unggulan</span>
      <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3 leading-tight">Dibuat untuk lapangan, bukan sekadar laporan</h2>
    </div>

    <div class="grid gap-x-8 gap-y-9 sm:grid-cols-2 lg:grid-cols-3 mt-11">
      @foreach ([
        ['Sertifikat ber-barcode','Terbit otomatis saat kursus selesai, empat ragam desain, berlogo perusahaan, dan dapat diverifikasi publik lewat pemindaian.','M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['Penilaian PTPKKP lengkap','194 item pengukuran, bobot resmi per parameter, level Dasar hingga Resilient, plus rekapitulasi siap cetak.','M9 17v-6h13M9 17H4V5h5v12zm0 0h13v-6'],
        ['Kuesioner bebas akses','Sebar tautan ke pekerja dan pimpinan unit kerja tanpa perlu akun — hasil langsung terangkum per parameter.','M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2M7 20H2v-2a3 3 0 015.36-1.86M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['Evaluasi trainer','Peserta yang menyelesaikan kursus otomatis masuk daftar tunggu penilaian trainer pada empat aspek kompetensi.','M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.41-9.41a2 2 0 112.82 2.82L11.83 15H9v-2.83l8.59-8.58z'],
        ['Kunci jawaban aman','Soal evaluasi SOP dinilai sepenuhnya di server — kunci jawaban tidak pernah dikirim ke perangkat peserta.','M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ['Kendali penuh admin','Kelola perusahaan, pengguna, peran, penanda tangan, hingga pemeliharaan sistem dari satu pusat kendali.','M10.32 4.32c.43-1.76 2.93-1.76 3.36 0a1.72 1.72 0 002.57 1.07c1.54-.94 3.31.83 2.37 2.37a1.72 1.72 0 001.07 2.57c1.75.43 1.75 2.93 0 3.36a1.72 1.72 0 00-1.07 2.57c.94 1.54-.83 3.31-2.37 2.37a1.72 1.72 0 00-2.57 1.06c-.43 1.76-2.93 1.76-3.36 0a1.72 1.72 0 00-2.57-1.06c-1.54.94-3.31-.83-2.37-2.37a1.72 1.72 0 00-1.06-2.57c-1.76-.43-1.76-2.93 0-3.36a1.72 1.72 0 001.06-2.57c-.94-1.54.83-3.31 2.37-2.37 1 .61 2.3.07 2.57-1.07z'],
      ] as [$j,$k,$i])
        @php
          // Ikon fitur mengambil warna pilar bergiliran, supaya bagian ini
          // tetap satu keluarga dengan Pilar dan Modul di atasnya.
          $wf = \App\Support\Pillars::get(\App\Support\Pillars::slugs()[($loop->index) % 6]);
        @endphp
        <div class="flex gap-4 reveal reveal-d{{ min($loop->iteration, 6) }}">
          <div class="w-10 h-10 rounded-xl grid place-items-center shrink-0 text-white shadow-sm"
               style="background:{{ $wf['warna'] }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $i }}"/></svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-[13.5px] font-bold text-cam-ink">{{ $j }}</h3>
            <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">{{ $k }}</p>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════ ALUR ══════════ --}}
<section id="alur" class="max-w-5xl mx-auto px-5 py-16 md:py-20">
  <div class="text-center max-w-xl mx-auto reveal">
    <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Cara Kerja</span>
    <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3 leading-tight">Empat langkah, satu siklus</h2>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mt-10">
    @foreach ([
      ['Daftarkan perusahaan','Lengkapi spesifikasi IUP/IUJP, KTT, PJO, dan jumlah tenaga kerja.'],
      ['Susun materi & prosedur','Buat kursus, modul, kuis, serta prosedur dan evaluasi SOP.'],
      ['Jalankan penilaian','Isi PTPKKP, sebar kuesioner, dan nilai kompetensi peserta.'],
      ['Terbitkan & tindak lanjut','Sertifikat terbit otomatis, program peningkatan tersusun dari hasil.'],
    ] as $i => [$j,$k])
      <div class="relative bg-white rounded-2xl shadow-card border border-stone-100 p-5
                  reveal reveal-d{{ min($i + 1, 6) }}">
        {{-- Penghubung antar langkah: menandai bahwa keempatnya satu siklus,
             bukan empat hal terpisah. Disembunyikan saat kartu menumpuk. --}}
        @if($i < 3)
          <span class="hidden lg:block absolute top-8 -right-4 w-4 h-px bg-cam-lime/30"></span>
        @endif
        <div class="stat stat-sm text-cam-lime/35">{{ sprintf('%02d', $i+1) }}</div>
        <h3 class="text-[13.5px] font-bold text-cam-ink mt-2">{{ $j }}</h3>
        <p class="text-[12px] text-stone-500 mt-1.5 leading-relaxed">{{ $k }}</p>
      </div>
    @endforeach
  </div>
</section>

{{-- ══════════ CTA ══════════ --}}
<section class="max-w-6xl mx-auto px-5 pb-16 md:pb-20">
  <div class="brand-gradient rounded-3xl p-9 md:p-14 text-white text-center relative overflow-hidden shadow-card">
    <div class="absolute inset-0 opacity-30">@include('partials.art-mine')</div>
    <div class="absolute inset-0 bg-cam-black/70"></div>
    <div class="relative reveal">
      <h2 class="font-display text-[28px] md:text-[38px] font-black leading-tight">Siap menaikkan level keselamatan?</h2>
      <p class="text-[13.5px] text-white/55 mt-3 max-w-md mx-auto leading-relaxed">
        Masuk dengan akun perusahaan Anda dan mulai dari modul yang paling dibutuhkan.
      </p>
      <a href="{{ route('login') }}" class="inline-block lime-gradient shadow-glow rounded-xl text-white px-7 py-3 text-[13.5px] font-bold mt-7 hover:brightness-105 transition">Masuk ke Platform</a>
    </div>
  </div>
</section>

<footer class="border-t border-stone-100 bg-white">
  <div class="max-w-6xl mx-auto px-5 py-7 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-2.5">
      <x-brand variant="wordmark" class="h-6" />
    </div>
    <p class="text-[11.5px] text-stone-400">Platform Terpadu Keselamatan Pertambangan · {{ date('Y') }}</p>
  </div>
</footer>
</body>
</html>
