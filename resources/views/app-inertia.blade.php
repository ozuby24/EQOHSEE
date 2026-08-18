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

{{-- Sama persis dengan layouts/app.blade.php: tema dipasang sebelum apa
     pun tergambar, supaya halaman Inertia tidak berkedip terang sesaat
     sebelum berubah gelap. --}}
{{-- nonce WAJIB ada di sini.

     Content-Security-Policy pada TajukKeamanan melarang skrip sebaris
     tanpa nonce. Tanpa atribut ini, skrip tema tidak dijalankan sama
     sekali — dan kegagalannya sunyi: tidak ada galat di sisi server,
     hanya halaman yang berkedip terang lalu berubah gelap, persis cacat
     yang skrip ini ada untuk mencegahnya. --}}
<script nonce="{{ \App\Http\Middleware\TajukKeamanan::nonce() }}">
(function(){
  var t = null;
  try{
    t = document.documentElement.getAttribute('data-tema') || localStorage.getItem('eqTema');
  }catch(e){}

  if(t !== 'gelap' && t !== 'terang'){
    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'gelap' : 'terang';
  }
  document.documentElement.setAttribute('data-tema', t);
})();
</script>

{{-- Judul dan keterangan berbagi, digambar SERVER.

     Aplikasi ini memakai Inertia tanpa SSR, jadi seluruh <title> dipasang
     JavaScript sesudah halaman tiba. Bagi orang itu tidak terasa; bagi
     apa pun yang tidak menjalankan JavaScript — pratinjau tautan di
     WhatsApp, LinkedIn, Telegram — halaman ini datang tanpa judul, tanpa
     uraian, dan tanpa gambar sama sekali.

     Ditaruh SEBELUM @inertiaHead supaya judul dari <Head> di sisi Vue
     tetap menang begitu halamannya hidup. Yang di sini berlaku untuk
     pembaca yang tidak pernah sampai ke tahap itu. --}}
@php($eqSeo = \App\Support\Seo::untuk(request()))
<title>{{ $eqSeo['judul'] }}</title>
<meta name="description" content="{{ $eqSeo['uraian'] }}">
<link rel="canonical" href="{{ $eqSeo['kanonik'] }}">
@unless($eqSeo['indeks'])
<meta name="robots" content="noindex, nofollow">
@endunless

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ \App\Support\Seo::NAMA }}">
<meta property="og:locale" content="id_ID">
<meta property="og:title" content="{{ $eqSeo['judul'] }}">
<meta property="og:description" content="{{ $eqSeo['uraian'] }}">
<meta property="og:url" content="{{ $eqSeo['kanonik'] }}">
<meta property="og:image" content="{{ url($eqSeo['gambar']) }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $eqSeo['judul'] }}">
<meta name="twitter:description" content="{{ $eqSeo['uraian'] }}">
<meta name="twitter:image" content="{{ url($eqSeo['gambar']) }}">

{{-- Data terstruktur hanya di halaman pendaratan. Memasangnya di setiap
     halaman berarti menyatakan bahwa tiap alamat adalah aplikasinya
     sendiri — dan pernyataan yang berulang di dua ratus alamat bukan
     penekanan, melainkan kekeliruan yang diulang dua ratus kali. --}}
@if($eqSeo['indeks'] && request()->route()?->getName() === 'beranda')
<script type="application/ld+json" nonce="{{ \App\Http\Middleware\TajukKeamanan::nonce() }}">{!! \App\Support\Seo::dataTerstruktur() !!}</script>
@endif

@inertiaHead

{{-- Huruf disajikan sendiri dari /fonts/, tidak lagi dari
     fonts.googleapis.com. Lihat resources/css/fonts.css. Berkasnya
     dimuat lewat app.css, jadi tidak ada lagi permintaan ke luar di
     sini — dan tidak ada lagi halaman yang tergambar dengan huruf
     cadangan ketika jaringannya tertutup. --}}
<link rel="preload" href="{{ \App\Support\Aset::v('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
@vite(['resources/css/app.css', 'resources/js/inertia.ts'])
<link rel="icon" type="image/svg+xml" href="{{ \App\Support\Aset::v('brand/favicon.svg') }}">
<link rel="icon" href="{{ \App\Support\Aset::v('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" href="{{ \App\Support\Aset::v('favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ \App\Support\Aset::v('apple-touch-icon.png') }}">
</head>
<body class="antialiased">

{{-- Lapisan gaya yang sama dipakai kedua tampilan. Menyalinnya ke berkas
     Vue tersendiri berarti dua salinan aturan warna yang panjang, dan dua
     salinan seperti itu pasti berbeda isinya cepat atau lambat. --}}
@include('partials.eq-visual')

{{-- Chart.js TIDAK lagi disisipkan di sini.

     Dulu partial tpkkp._chart memuatnya dari cdn.jsdelivr.net pada setiap
     halaman Inertia. Di jaringan tambang yang tertutup — tempat aplikasi
     ini justru dipakai — skripnya gagal dimuat dan grafiknya kosong tanpa
     satu pun penjelasan; Pages/Admin/Sistem.vue sudah ditulis ulang
     menjadi SVG karena persis itu.

     Kini pustakanya ikut dibundel lewat resources/js/bagan.ts, jadi ia
     tiba bersama halamannya. --}}

@inertia
</body>
</html>
