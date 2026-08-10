<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Dokumen') — EQOHSEE</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
@vite(['resources/css/app.css'])
<link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
</head>

{{--
  Tata letak berkas cetak.

  Berkas audit adalah dokumen resmi: yang tercetak hanya lembar dokumennya,
  tanpa bilah samping, bilah judul, maupun widget bantuan. Kerangka aplikasi
  juga membuat halaman tercetak kosong — bilah samping berposisi tetap dan
  `min-h-screen` mengacaukan pemenggalan halaman pada media bercetak.
--}}
<body class="antialiased bg-stone-100 print:bg-white">

  {{-- Bilah alat; tidak ikut tercetak. --}}
  <div class="print:hidden sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-stone-200">
    <div class="max-w-[850px] mx-auto flex flex-wrap items-center justify-between gap-3 px-4 py-2.5">
      <a href="{{ $kembali ?? url()->previous() }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Kembali</a>
      <div class="flex items-center gap-2">
        <span class="text-[11.5px] text-stone-400 hidden sm:block">@yield('title')</span>
        <button onclick="window.print()"
                class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2 text-[12.5px] font-bold hover:brightness-105 transition">Cetak</button>
      </div>
    </div>
  </div>

  <main class="max-w-[850px] mx-auto px-4 py-5 print:p-0 print:max-w-none space-y-5 print:space-y-0">
    @yield('content')
  </main>

</body>
</html>
