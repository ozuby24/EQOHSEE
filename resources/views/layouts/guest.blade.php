<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Masuk') · EQOHSEE</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@500;600&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="{{ \App\Support\Aset::v('brand/favicon.svg') }}">

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  @verbatim
  <style>
    :root{
      --graphite:#12171B; --hair:rgba(255,255,255,.09);
      --cream:#FBFAF7; --ink:#1B2024; --muted:#727B85; --line:#E3DFD7;
      --jingga:#F57C00; --jingga-terang:#FF9800; --perak:#B8BEC5;
      --navy:#0B1117; --navy-2:#151D26;
    }
    .eq-shell *{box-sizing:border-box}
    .eq-shell{min-height:100vh;min-height:100dvh;display:grid;grid-template-columns:1fr;
      font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
      -webkit-font-smoothing:antialiased;background:var(--cream);color:var(--ink)}
    @media(min-width:900px){ .eq-shell{grid-template-columns:1.15fr .85fr} }

    /* ── Panel kiri: kolom strata ── */
    .eq-hero{position:relative;overflow:hidden;background:var(--navy);color:#fff;
      display:flex;flex-direction:column;justify-content:space-between;padding:32px 26px 30px}
    @media(min-width:900px){ .eq-hero{padding:52px 56px 44px 56px} }

    /* Foto operasi tambang sebagai dasar. Diberi gradasi navy pekat dari
       kiri-bawah supaya teks tetap terbaca tanpa menutupi conveyor dan
       cahaya matahari di kanan-atas — bagian yang membuat gambarnya hidup. */
    .eq-foto{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
      object-position:52% 46%;z-index:0}
    @media(min-width:900px){ .eq-foto{object-position:46% 44%} }
    .eq-hero .eq-lapis{position:absolute;inset:0;z-index:0;pointer-events:none;
      background:
        linear-gradient(180deg,rgba(11,17,23,.55) 0%,rgba(11,17,23,.06) 34%,rgba(11,17,23,.62) 74%,rgba(11,17,23,.93) 100%),
        linear-gradient(102deg,rgba(11,17,23,.86) 6%,rgba(11,17,23,.34) 42%,rgba(11,17,23,0) 78%)}
    .eq-hero > .z{position:relative;z-index:2}


    }


    /* Sapuan cahaya melintasi lereng */

    /* Pita strata menyala bergiliran, mengikat panel ke delapan aspek */

    @media (prefers-reduced-motion: reduce){
      }

    .eq-brand{position:relative;z-index:3;align-self:flex-start;margin:0 0 30px;
      display:inline-flex;align-items:center;gap:10px;text-decoration:none}
    .eq-brand img{height:26px;width:auto;display:block}
    .eq-brand .wm{font-weight:800;letter-spacing:.02em;font-size:17px;color:#fff}
    .eq-brand .wm b{color:var(--jingga);font-weight:800}

    /* penampang jenjang (bench) — tread sejajar persis dengan batas pita strata */



    .eq-kicker{font-size:10px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;
      color:rgba(255,255,255,.42);margin:0 0 14px}
    .eq-rule{width:52px;height:2px;border-radius:2px;margin:0 0 20px;
      background:linear-gradient(90deg,var(--jingga),var(--jingga-terang))}
    .eq-h1{font-family:'Playfair Display',Georgia,serif;font-weight:500;color:#fff;margin:0 0 15px;
      font-size:clamp(26px,4.4vw,42px);line-height:1.14;letter-spacing:-.01em;max-width:16ch}
    .eq-h1 em{font-style:normal;color:var(--jingga-terang)}
    .eq-sub{margin:0;max-width:42ch;font-size:13.5px;line-height:1.65;color:rgba(255,255,255,.58)}

    /* Slogan merek: batang jingga di kiri, baris ketiga menyala.
       Urutannya menaik — keadaan hari ini, janji hari esok, lalu sikap
       yang menghubungkan keduanya. */
    .eq-slogan{margin:22px 0 0;padding-left:15px;border-left:3px solid var(--jingga);
      display:flex;flex-direction:column;gap:3px;
      font-weight:800;letter-spacing:.055em;text-transform:uppercase;
      font-size:clamp(13px,1.35vw,16px);line-height:1.32;color:#fff}
    .eq-slogan em{font-style:normal;color:var(--jingga-terang)}

    .eq-aspek{margin:22px 0 0;max-width:44ch;font-size:11.5px;line-height:1.9;
      letter-spacing:.075em;text-transform:uppercase;font-weight:600;
      color:rgba(255,255,255,.46)}

    /* ── Panel kanan: form ── */
    .eq-panel{background:var(--cream);display:flex;align-items:center;justify-content:center;
      padding:38px 22px 46px}
    @media(min-width:900px){ .eq-panel{padding:52px 46px} }
    .eq-formwrap{width:100%;max-width:382px}
    .eq-mark{font-weight:800;font-size:24px;letter-spacing:.02em;color:var(--ink);margin:0 0 26px}
    .eq-mark b{color:var(--jingga);font-weight:800}

    /* dipakai oleh view form (login/register/reset) */
    .eq-title{font-family:'Playfair Display',Georgia,serif;font-weight:600;font-size:23px;margin:0 0 4px}
    .eq-hint{margin:0 0 24px;font-size:13.5px;color:var(--muted)}
    .eq-label{display:block;font-size:11.5px;font-weight:700;letter-spacing:.05em;
      text-transform:uppercase;color:#4E565F;margin:0 0 7px}
    .eq-field{margin-bottom:16px}
    .eq-input{width:100%;padding:12px 14px;font-size:14.5px;font-family:inherit;color:var(--ink);
      background:#fff;border:1px solid var(--line);border-radius:10px;
      transition:border-color .18s,box-shadow .18s}
    .eq-input:focus{outline:none;border-color:var(--jingga);box-shadow:0 0 0 3px rgba(245,124,0,.15)}
    .eq-row{display:flex;align-items:center;justify-content:space-between;margin:4px 0 20px;font-size:13px}
    .eq-check{display:flex;align-items:center;gap:8px;color:#4E565F;cursor:pointer}
    .eq-check input{accent-color:var(--jingga);width:15px;height:15px}
    .eq-link{color:var(--muted);text-decoration:none;border-bottom:1px solid var(--line)}
    .eq-link:hover{color:var(--ink);border-color:var(--ink)}
    .eq-btn{position:relative;width:100%;padding:13px 16px;font-family:inherit;font-size:14.5px;
      font-weight:700;letter-spacing:.02em;color:#fff;background:var(--jingga);border:0;
      border-radius:10px;cursor:pointer;overflow:hidden;
      transition:transform .16s,box-shadow .2s,background .2s}
    .eq-btn::before{content:"";position:absolute;left:0;right:0;top:0;height:2px;
      background:linear-gradient(90deg,var(--jingga),var(--jingga-terang))}
    .eq-btn:hover{background:#DC6E00;box-shadow:0 10px 24px rgba(245,124,0,.32)}
    .eq-btn:active{transform:translateY(1px)}
    .eq-btn:focus-visible{outline:2px solid var(--jingga);outline-offset:2px}
    .eq-after{margin:20px 0 0;font-size:12.5px;color:var(--muted);text-align:center}
    .eq-after a{color:var(--ink);text-decoration:none;border-bottom:1px solid var(--line)}
    .eq-note{margin:0 0 16px;border-radius:10px;padding:11px 13px;font-size:12.5px;line-height:1.55}
    .eq-note.ok{background:#EAF6F4;border:1px solid #CBD5DC;color:#0E6E66}
    .eq-note.bad{background:#FCEDEC;border:1px solid #F3CFCC;color:#A3312A}
    .eq-note ul{margin:0;padding:0;list-style:none}

    @media (prefers-reduced-motion: reduce){ .eq-shell *{transition:none!important} }
  </style>
  @endverbatim
</head>
<body>
<div class="eq-shell">

  {{-- ===== Panel kiri: kolom strata ===== --}}
  <section class="eq-hero">
    <img class="eq-foto" src="{{ asset('brand/tambang.jpg') }}" alt="" aria-hidden="true">
    <span class="eq-lapis" aria-hidden="true"></span>

    <a href="{{ url('/') }}" class="eq-brand">
      <img src="{{ \App\Support\Aset::v('brand/eqohsee-mark-white.svg') }}" alt="EQOHSEE">
      <span class="wm">E<b>Q</b>OHSEE</span>
    </a>


    <div class="z">
      <p class="eq-kicker">Delapan Aspek · Satu Sistem</p>
      <div class="eq-rule"></div>
      <h1 class="eq-h1">Menjaga kinerja, membentuk <em>masa depan</em>.</h1>

      <div class="eq-slogan">
        <span>Safe Today</span>
        <span>Sustainable Tomorrow</span>
        <em>Innovation Always</em>
      </div>

      <p class="eq-aspek">Energy · Quality · Occupational Health · Hygiene ·
        Safety · Environment · Engineering · Konservasi Minerba</p>
    </div>
  </section>

  {{-- ===== Panel kanan: form ===== --}}
  <section class="eq-panel">
    <div class="eq-formwrap">
      <div class="eq-mark">E<b>Q</b>OHSEE</div>
      @yield('form')
    </div>
  </section>

</div>
</body>
</html>
