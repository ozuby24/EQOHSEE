<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $judul }} — EQOHSEE</title>
<meta name="description" content="{{ $ringkas }}">

{{-- Halaman ini WAJIB terbuka tanpa login dan tanpa JavaScript.
     Google Play memeriksanya dari perangkat peninjau yang tidak punya
     akun di sini; halaman yang mengalihkan ke /login menggagalkan
     peninjauan dengan pesan yang tidak menyinggung sebabnya. Karena
     itu Blade biasa, bukan Inertia: tidak ada bundel JS yang harus
     dimuat lebih dulu, dan tetap terbaca di jaringan site tambang. --}}
<link rel="icon" href="/favicon-32.png" sizes="32x32">
<style>
  :root{
    --tinta:#12181D; --redup:#5A6873; --garis:#E4E9EC;
    --oranye:#C86A00; --latar:#FFFFFF; --kotak:#F7F9FA;
  }
  @media (prefers-color-scheme: dark){
    :root{ --tinta:#E7ECEF; --redup:#9FADB8; --garis:#243039;
           --oranye:#FFA23D; --latar:#0E1418; --kotak:#161F26; }
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--latar);color:var(--tinta);
    font:16px/1.65 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    -webkit-text-size-adjust:100%}
  .bungkus{max-width:46rem;margin:0 auto;padding:2.5rem 1.15rem 4rem}
  header{border-bottom:1px solid var(--garis);padding-bottom:1.4rem;margin-bottom:2rem}
  .merek{display:flex;align-items:center;gap:.6rem;font-weight:800;letter-spacing:-.02em;font-size:1.05rem}
  .merek span{width:.62rem;height:.62rem;border-radius:.2rem;background:var(--oranye)}
  h1{font-size:1.7rem;line-height:1.25;letter-spacing:-.025em;margin:1.1rem 0 .4rem}
  .tanggal{color:var(--redup);font-size:.87rem;margin:0}
  .alih{margin-top:1rem;font-size:.87rem}
  .alih a{color:var(--oranye)}
  h2{font-size:1.08rem;letter-spacing:-.015em;margin:2.2rem 0 .55rem;scroll-margin-top:1rem}
  h3{font-size:.97rem;margin:1.35rem 0 .35rem}
  p,li{color:var(--tinta)}
  p{margin:.55rem 0}
  ul{margin:.55rem 0;padding-left:1.15rem}
  li{margin:.3rem 0}
  a{color:var(--oranye)}
  .kotak{background:var(--kotak);border:1px solid var(--garis);
    border-radius:.7rem;padding:.95rem 1.05rem;margin:1.1rem 0}
  .kotak p:first-child{margin-top:0} .kotak p:last-child{margin-bottom:0}
  /* Hanya <strong> pada baris PERTAMA kotak yang jadi judul. Tanpa
     pembatas :first-child, tiap penekanan di tengah kalimat ikut
     pindah baris dan paragrafnya pecah jadi potongan-potongan. */
  .kotak p:first-child strong{display:block;margin-bottom:.3rem}
  table{width:100%;border-collapse:collapse;margin:1rem 0;font-size:.93rem;display:block;overflow-x:auto}
  th,td{text-align:left;padding:.55rem .6rem;border-bottom:1px solid var(--garis);vertical-align:top}
  th{font-weight:700;white-space:nowrap}
  footer{margin-top:3rem;padding-top:1.3rem;border-top:1px solid var(--garis);
    color:var(--redup);font-size:.85rem}
  code{background:var(--kotak);padding:.1rem .3rem;border-radius:.25rem;font-size:.9em}
</style>
</head>
<body>
<div class="bungkus">
  <header>
    <div class="merek"><span></span>EQOHSEE</div>
    <h1>{{ $judul }}</h1>
    <p class="tanggal">{{ $tanggal }}</p>
    <p class="alih">{!! $alih !!}</p>
  </header>

  @yield('isi')

  <footer>
    <p>EQOHSEE · {{ $kaki }}</p>
  </footer>
</div>
</body>
</html>
