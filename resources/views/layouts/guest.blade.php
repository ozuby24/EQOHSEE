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
  <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  @verbatim
  <style>
    :root{
      --graphite:#12171B; --hair:rgba(255,255,255,.09);
      --cream:#FBFAF7; --ink:#1B2024; --muted:#727B85; --line:#E3DFD7;
      --energy:#1F6FB8; --quality:#2FA3DE; --health:#F08A22;
      --safety:#12897F; --env:#5EAE38; --eng:#2CB0BC;
    }
    .eq-shell *{box-sizing:border-box}
    .eq-shell{min-height:100vh;min-height:100dvh;display:grid;grid-template-columns:1fr;
      font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
      -webkit-font-smoothing:antialiased;background:var(--cream);color:var(--ink)}
    @media(min-width:900px){ .eq-shell{grid-template-columns:1.15fr .85fr} }

    /* ── Panel kiri: kolom strata ── */
    .eq-hero{position:relative;overflow:hidden;background:var(--graphite);color:#fff;
      display:flex;flex-direction:column;justify-content:space-between;padding:32px 26px 30px}
    @media(min-width:900px){ .eq-hero{padding:52px 150px 44px 56px} }
    .eq-hero::before{content:"";position:absolute;inset:0;opacity:.5;pointer-events:none;
      background-image:linear-gradient(var(--hair) 1px,transparent 1px),
                       linear-gradient(90deg,var(--hair) 1px,transparent 1px);
      background-size:44px 44px;
      -webkit-mask-image:radial-gradient(120% 90% at 20% 20%,#000 30%,transparent 78%);
              mask-image:radial-gradient(120% 90% at 20% 20%,#000 30%,transparent 78%)}
    .eq-hero::after{content:"";position:absolute;left:-10%;top:-20%;width:70%;height:70%;
      pointer-events:none;background:radial-gradient(circle,rgba(44,176,188,.16),transparent 68%)}
    .eq-hero > .z{position:relative;z-index:1}

    .eq-brand{position:relative;z-index:2;align-self:flex-start;margin:0 0 30px;
      display:inline-flex;align-items:center;gap:10px;text-decoration:none}
    .eq-brand img{height:26px;width:auto;display:block}
    .eq-brand .wm{font-weight:800;letter-spacing:.02em;font-size:17px;color:#fff}
    .eq-brand .wm b{color:var(--eng);font-weight:800}

    /* penampang jenjang (bench) — tread sejajar persis dengan batas pita strata */
    .eq-bench{position:absolute;left:0;top:0;bottom:0;right:132px;z-index:0;display:none;
      pointer-events:none}
    @media(min-width:900px){ .eq-bench{display:block} }
    .eq-bench svg{width:100%;height:100%;display:block}

    .eq-strata{position:absolute;right:0;top:0;bottom:0;width:132px;z-index:1;display:none;
      flex-direction:column;border-left:1px solid var(--hair)}
    @media(min-width:900px){ .eq-strata{display:flex} }
    .eq-band{flex:1;position:relative;display:flex;align-items:center;padding-left:16px;
      border-bottom:1px solid var(--hair)}
    .eq-band:last-child{border-bottom:0}
    .eq-band::before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:var(--c)}
    .eq-band i{position:absolute;inset:0;background:var(--c);opacity:.075;transition:opacity .3s}
    .eq-band b{position:relative;font-size:9.5px;font-weight:700;letter-spacing:.16em;
      text-transform:uppercase;color:#fff;opacity:.62;transition:opacity .3s}
    .eq-band:hover i{opacity:.18}
    .eq-band:hover b{opacity:1}

    .eq-seam{display:flex;height:4px;margin-bottom:20px;border-radius:2px;overflow:hidden}
    .eq-seam span{flex:1;background:var(--c)}
    @media(min-width:900px){ .eq-seam{display:none} }

    .eq-kicker{font-size:10px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;
      color:rgba(255,255,255,.42);margin:0 0 14px}
    .eq-rule{width:52px;height:2px;border-radius:2px;margin:0 0 20px;
      background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#12897F,#5EAE38,#2CB0BC)}
    .eq-h1{font-family:'Playfair Display',Georgia,serif;font-weight:500;color:#fff;margin:0 0 15px;
      font-size:clamp(26px,4.4vw,42px);line-height:1.14;letter-spacing:-.01em;max-width:16ch}
    .eq-h1 em{font-style:normal;color:#7ED0D8}
    .eq-sub{margin:0;max-width:42ch;font-size:13.5px;line-height:1.65;color:rgba(255,255,255,.58)}

    /* ── Panel kanan: form ── */
    .eq-panel{background:var(--cream);display:flex;align-items:center;justify-content:center;
      padding:38px 22px 46px}
    @media(min-width:900px){ .eq-panel{padding:52px 46px} }
    .eq-formwrap{width:100%;max-width:382px}
    .eq-mark{font-weight:800;font-size:24px;letter-spacing:.02em;display:flex;gap:1px;
      margin:0 0 28px}

    /* dipakai oleh view form (login/register/reset) */
    .eq-title{font-family:'Playfair Display',Georgia,serif;font-weight:600;font-size:23px;margin:0 0 4px}
    .eq-hint{margin:0 0 24px;font-size:13.5px;color:var(--muted)}
    .eq-label{display:block;font-size:11.5px;font-weight:700;letter-spacing:.05em;
      text-transform:uppercase;color:#4E565F;margin:0 0 7px}
    .eq-field{margin-bottom:16px}
    .eq-input{width:100%;padding:12px 14px;font-size:14.5px;font-family:inherit;color:var(--ink);
      background:#fff;border:1px solid var(--line);border-radius:10px;
      transition:border-color .18s,box-shadow .18s}
    .eq-input:focus{outline:none;border-color:var(--safety);box-shadow:0 0 0 3px rgba(18,137,127,.13)}
    .eq-row{display:flex;align-items:center;justify-content:space-between;margin:4px 0 20px;font-size:13px}
    .eq-check{display:flex;align-items:center;gap:8px;color:#4E565F;cursor:pointer}
    .eq-check input{accent-color:var(--safety);width:15px;height:15px}
    .eq-link{color:var(--muted);text-decoration:none;border-bottom:1px solid var(--line)}
    .eq-link:hover{color:var(--ink);border-color:var(--ink)}
    .eq-btn{position:relative;width:100%;padding:13px 16px;font-family:inherit;font-size:14.5px;
      font-weight:600;letter-spacing:.02em;color:#fff;background:var(--graphite);border:0;
      border-radius:10px;cursor:pointer;overflow:hidden;
      transition:transform .16s,box-shadow .2s,background .2s}
    .eq-btn::before{content:"";position:absolute;left:0;right:0;top:0;height:2px;
      background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#12897F,#5EAE38,#2CB0BC)}
    .eq-btn:hover{background:#1C242A;box-shadow:0 8px 22px rgba(18,23,27,.24)}
    .eq-btn:active{transform:translateY(1px)}
    .eq-btn:focus-visible{outline:2px solid var(--safety);outline-offset:2px}
    .eq-after{margin:20px 0 0;font-size:12.5px;color:var(--muted);text-align:center}
    .eq-after a{color:var(--ink);text-decoration:none;border-bottom:1px solid var(--line)}
    .eq-note{margin:0 0 16px;border-radius:10px;padding:11px 13px;font-size:12.5px;line-height:1.55}
    .eq-note.ok{background:#EAF6F4;border:1px solid #BFE0DB;color:#0E6E66}
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
    <a href="{{ url('/') }}" class="eq-brand">
      <img src="{{ asset('brand/eqohsee-mark-white.svg') }}" alt="EQOHSEE">
      <span class="wm">E<b>Q</b>OHSEE</span>
    </a>

    <div class="eq-bench" aria-hidden="true">
      <svg viewBox="0 0 480 600" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg">
        {{-- garis bantu elevasi: tepat di batas keenam pita strata --}}
        <g stroke="#fff" stroke-opacity=".07" stroke-width="1" vector-effect="non-scaling-stroke">
          <path d="M0 100H480"/><path d="M0 200H480"/><path d="M0 300H480"/>
          <path d="M0 400H480"/><path d="M0 500H480"/>
        </g>

        {{-- massa lereng --}}
        <path d="M40 100H170V200H280V300H375V400H455V600H40Z" fill="#fff" fill-opacity=".022"/>

        {{-- profil jenjang --}}
        <path d="M40 100H170V200H280V300H375V400H455"
              stroke="#fff" stroke-opacity=".26" stroke-width="1.3"
              stroke-linejoin="round" vector-effect="non-scaling-stroke"/>

        {{-- tread diberi warna pilar sesuai pita yang disentuhnya --}}
        <g stroke-width="2.6" vector-effect="non-scaling-stroke" stroke-linecap="round">
          <path d="M40 100H170"  stroke="#2FA3DE" stroke-opacity=".85"/>
          <path d="M170 200H280" stroke="#F08A22" stroke-opacity=".85"/>
          <path d="M280 300H375" stroke="#12897F" stroke-opacity=".85"/>
          <path d="M375 400H455" stroke="#5EAE38" stroke-opacity=".85"/>
        </g>

        {{-- tekstur muka jenjang --}}
        <g stroke="#fff" stroke-opacity=".13" stroke-width="1" vector-effect="non-scaling-stroke">
          <path d="M170 128H196"/><path d="M170 152H188"/><path d="M170 176H200"/>
          <path d="M280 228H304"/><path d="M280 252H296"/><path d="M280 276H308"/>
          <path d="M375 328H398"/><path d="M375 352H390"/><path d="M375 376H402"/>
        </g>

        {{-- titik sudut --}}
        <g fill="#fff" fill-opacity=".5">
          <circle cx="170" cy="100" r="2.4"/><circle cx="280" cy="200" r="2.4"/>
          <circle cx="375" cy="300" r="2.4"/><circle cx="455" cy="400" r="2.4"/>
        </g>
      </svg>
    </div>

    <div class="eq-strata" aria-hidden="true">
      <div class="eq-band" style="--c:var(--energy)"><i></i><b>Energy</b></div>
      <div class="eq-band" style="--c:var(--quality)"><i></i><b>Quality</b></div>
      <div class="eq-band" style="--c:var(--health)"><i></i><b>Occ. Health</b></div>
      <div class="eq-band" style="--c:var(--safety)"><i></i><b>Safety</b></div>
      <div class="eq-band" style="--c:var(--env)"><i></i><b>Environment</b></div>
      <div class="eq-band" style="--c:var(--eng)"><i></i><b>Engineering</b></div>
    </div>

    <div class="z">
      <div class="eq-seam" aria-hidden="true">
        <span style="--c:var(--energy)"></span><span style="--c:var(--quality)"></span>
        <span style="--c:var(--health)"></span><span style="--c:var(--safety)"></span>
        <span style="--c:var(--env)"></span><span style="--c:var(--eng)"></span>
      </div>
      <p class="eq-kicker">Enam Pilar · Satu Sistem</p>
      <div class="eq-rule"></div>
      <h1 class="eq-h1">Menjaga kinerja, membentuk <em>masa depan</em>.</h1>
      <p class="eq-sub">Energi, mutu, kesehatan kerja, keselamatan, lingkungan, dan rekayasa — dikelola dalam satu sistem.</p>
    </div>
  </section>

  {{-- ===== Panel kanan: form ===== --}}
  <section class="eq-panel">
    <div class="eq-formwrap">
      <div class="eq-mark">
        <span style="color:#1F6FB8">E</span><span style="color:#2FA3DE">Q</span><span style="color:#F08A22">O</span><span style="color:#12897F">H</span><span style="color:#12897F">S</span><span style="color:#5EAE38">E</span><span style="color:#2CB0BC">E</span>
      </div>
      @yield('form')
    </div>
  </section>

</div>
</body>
</html>
