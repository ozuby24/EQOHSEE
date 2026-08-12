<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title','Kuesioner') — EQOHSEE</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
@vite(['resources/css/app.css','resources/js/app.js'])
  <link rel="icon" href="{{ \App\Support\Aset::v('favicon.ico') }}" sizes="any">
  <link rel="icon" type="image/png" href="{{ \App\Support\Aset::v('favicon-32.png') }}">
  <link rel="apple-touch-icon" href="{{ \App\Support\Aset::v('apple-touch-icon.png') }}">
</head>
<body class="bg-cam-bg">
<div class="min-h-screen">
  <header class="brand-gradient text-white">
    <div class="max-w-3xl mx-auto px-5 py-5 flex items-center gap-3">
      <x-brand variant="wordmark" dark class="h-6" />
    </div>
  </header>
  <main class="max-w-3xl mx-auto px-5 py-7">@yield('content')</main>
  <footer class="max-w-3xl mx-auto px-5 pb-8 text-center text-[11px] text-stone-400">
    Jawaban bersifat anonim dan digunakan hanya untuk penilaian PTPKKP.
  </footer>
</div>
</body>
</html>
