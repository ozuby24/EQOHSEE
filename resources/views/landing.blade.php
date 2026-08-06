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
<section class="relative brand-gradient text-white overflow-hidden">
  <div class="absolute inset-0 opacity-[.55]">@include('partials.art-mine')</div>
  <div class="absolute inset-0 bg-gradient-to-r from-cam-black via-cam-black/85 to-transparent"></div>
  <div class="absolute -right-24 -top-24 w-[380px] h-[380px] rounded-full bg-cam-lime/20 blur-3xl floaty"></div>

  <div class="relative max-w-6xl mx-auto px-5 py-16 md:py-24">
    <div class="max-w-2xl animate-fadeUp">
      <span class="inline-flex items-center gap-2 glass rounded-full px-3 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.18em] text-cam-lime-light">
        <span class="w-1.5 h-1.5 rounded-full bg-cam-lime animate-pulse"></span>
        Health · Safety · Environment
      </span>

      <h1 class="font-display text-[38px] md:text-[58px] font-black mt-5 leading-[1.05] text-shadow">
        Keselamatan tambang,<br>
        <span class="text-transparent bg-clip-text bg-gradient-to-r from-cam-lime-light to-cam-lime">terukur dan terbukti.</span>
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
    'engineering' => '<path d="M21 13.1v-2.2l-2.4-.4a6.9 6.9 0 0 0-.8-1.9l1.4-2-1.6-1.6-2 1.4a6.9 6.9 0 0 0-1.9-.8L13.1 3h-2.2l-.4 2.6a6.9 6.9 0 0 0-1.9.8l-2-1.4-1.6 1.6 1.4 2a6.9 6.9 0 0 0-.8 1.9L3 10.9v2.2l2.6.4a6.9 6.9 0 0 0 .8 1.9l-1.4 2 1.6 1.6 2-1.4a6.9 6.9 0 0 0 1.9.8l.4 2.6h2.2l.4-2.6a6.9 6.9 0 0 0 1.9-.8l2 1.4 1.6-1.6-1.4-2a6.9 6.9 0 0 0 .8-1.9ZM12 15.4A3.4 3.4 0 1 1 15.4 12 3.4 3.4 0 0 1 12 15.4Z"/>',
  ];
  // Warna tiap huruf EQOHSEE → pilar (O+H = Occupational Health).
  $wordmark = [
    ['E','energy'], ['Q','quality'], ['O','occhealth'], ['H','occhealth'],
    ['S','safety'], ['E','environment'], ['E','engineering'],
  ];
@endphp
<section id="pilar" class="relative bg-cam-ink text-white overflow-hidden">
  <div class="absolute -left-24 -bottom-24 w-[360px] h-[360px] rounded-full bg-cam-lime/15 blur-3xl"></div>
  <div class="relative max-w-6xl mx-auto px-5 py-16 md:py-20">
    <div class="text-center max-w-xl mx-auto">
      <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-light">Kerangka Kerja</span>
      <h2 class="font-display text-[30px] md:text-[38px] font-black mt-3 leading-tight">Nama kami adalah janjinya</h2>
      <p class="text-[13.5px] text-white/55 mt-3 leading-relaxed">
        <strong class="text-white/80">EQOHSEE</strong> berdiri di atas enam pilar. Setiap modul di platform ini
        menopang salah satunya — sehingga keselamatan bukan program terpisah, melainkan satu sistem utuh.
      </p>

      {{-- Wordmark: tiap huruf mewakili satu pilar --}}
      <div class="mt-7 font-display font-black tracking-tight leading-none flex justify-center"
           style="font-size:clamp(34px,9vw,62px)" aria-label="EQOHSEE">
        @foreach ($wordmark as [$huruf,$slug])
          <span style="color:{{ $pilar[$slug]['warna'] }}">{{ $huruf }}</span>
        @endforeach
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-11">
      @foreach ($pilar as $slug => $p)
        <div class="group glass-panel rounded-2xl p-6 relative overflow-hidden card-hover"
             style="border-top:3px solid {{ $p['warna'] }}">
          <div class="flex items-center gap-3">
            <span class="w-11 h-10 grid place-items-center shrink-0"
                  style="background:{{ $p['warna'] }};clip-path:polygon(25% 0,75% 0,100% 50%,75% 100%,25% 100%,0 50%)">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="#fff" aria-hidden="true">{!! $pilarIkon[$slug] !!}</svg>
            </span>
            <h3 class="text-[14.5px] font-bold" style="color:{{ $p['warna'] }}">{{ $p['nama'] }}</h3>
          </div>
          <p class="text-[12.5px] text-white/60 mt-3 leading-relaxed">{{ $p['ket'] }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- ══════════ MODUL ══════════ --}}
<section id="modul" class="max-w-6xl mx-auto px-5 py-16 md:py-20">
  <div class="text-center max-w-xl mx-auto">
    <span class="text-[10.5px] font-bold uppercase tracking-[0.22em] text-cam-lime-dark">Aplikasi di Dalamnya</span>
    <h2 class="font-display text-[30px] md:text-[38px] font-black text-cam-ink mt-3 leading-tight">Enam modul, satu akun</h2>
    <p class="text-[13.5px] text-stone-500 mt-3 leading-relaxed">
      Semua modul berbagi data perusahaan, pengguna, dan peran yang sama — tidak perlu login berulang.
    </p>
  </div>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mt-10">
    @foreach($daftarModul as $m)
      @php [$nama,$ket,$status,$ikon] = [$m['nama'],$m['ket'],$m['status'],$m['ikon']]; @endphp
      <div class="group bg-white rounded-2xl shadow-card border border-stone-100 p-6 card-hover relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-28 h-28 rounded-full bg-cam-lime/5 group-hover:bg-cam-lime/10 transition"></div>
        <div class="relative">
          <div class="w-11 h-11 rounded-xl {{ $status==='aktif' ? 'lime-gradient shadow-glow text-white' : 'bg-stone-100 text-stone-400' }} grid place-items-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ikon }}"/></svg>
          </div>
          <div class="flex items-center gap-2 mt-4">
            <h3 class="text-[14.5px] font-bold text-cam-ink">{{ $nama }}</h3>
            @if($status === 'aktif')
              <span class="text-[9px] font-bold bg-cam-lime-soft text-cam-lime-deep px-1.5 py-0.5 rounded uppercase tracking-wide">Aktif</span>
            @else
              <span class="text-[9px] font-bold bg-stone-100 text-stone-400 px-1.5 py-0.5 rounded uppercase tracking-wide">Segera</span>
            @endif
          </div>
          <p class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">{{ $ket }}</p>
        </div>
      </div>
    @endforeach
  </div>
</section>

{{-- ══════════ FITUR ══════════ --}}
<section id="fitur" class="bg-white border-y border-stone-100">
  <div class="max-w-6xl mx-auto px-5 py-16 md:py-20">
    <div class="text-center max-w-xl mx-auto">
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
        <div class="flex gap-4">
          <div class="w-10 h-10 rounded-xl bg-cam-lime-soft text-cam-lime-deep grid place-items-center shrink-0">
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
  <div class="text-center max-w-xl mx-auto">
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
      <div class="relative bg-white rounded-2xl shadow-card border border-stone-100 p-5">
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
    <div class="relative">
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
