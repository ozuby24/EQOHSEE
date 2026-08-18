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

@inertiaHead

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
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
