@extends('layouts.app')

@section('title', 'Delapan Aspek')

@section('content')
@php
/*
 | Pemetaan pilar → modul.
 | Sengaja lokal supaya halaman ini tidak terikat App\Support\Pillars.
 | Kalau registry pilar sudah final, ganti blok ini dengan Pillars::all().
 |
 | status: 'aktif' | 'segera'
 | rute  : nama route (null = belum ada)
 */
$pilar = [
  ['key'=>'energy','nama'=>'Energy','warna'=>'#1F6FB8','g1'=>'#2C86D6','g2'=>'#1B5EA0',
   'tag'=>'Energi terkelola, operasi berkelanjutan',
   'ket'=>'Pemantauan konsumsi bahan bakar dan daya, serta efisiensi peralatan.',
   'modul'=>[['Manajemen Energi','segera',null],['Audit ISO 50001','segera',null]]],

  ['key'=>'quality','nama'=>'Quality','warna'=>'#2FA3DE','g1'=>'#4FB6EA','g2'=>'#2189C4',
   'tag'=>'Mutu terjaga di setiap proses',
   'ket'=>'Audit sistem manajemen, temuan, dan tindakan perbaikan.',
   'modul'=>[['Audit SMKP','aktif','audit.index'],['Audit ISO 9001','segera',null]]],

  ['key'=>'health','nama'=>'Occupational Health','warna'=>'#F08A22','g1'=>'#F79B39','g2'=>'#E0770D',
   'tag'=>'Pekerja sehat, produktivitas terjaga',
   'ket'=>'Higiene kerja, pemeriksaan kesehatan, dan kesiapan alat pelindung diri.',
   'modul'=>[['Stok APD','aktif','sigap.apd.index'],['Kesehatan Kerja','segera',null]]],

  ['key'=>'safety','nama'=>'Safety','warna'=>'#DC6E00','g1'=>'#1AA093','g2'=>'#0C6F68',
   'tag'=>'Tanpa kompromi pada keselamatan, tanpa toleransi pada risiko',
   'ket'=>'Pelaporan bahaya, inspeksi lapangan, kompetensi, dan budaya keselamatan.',
   'modul'=>[['Hazard Report & Inspeksi','aktif','hazard.index'],['Safety Maturity Level','aktif','tpkkp.index'],
             ['Learning Center','aktif','lms.index'],['SIGAP','aktif','sigap.dashboard']]],

  ['key'=>'env','nama'=>'Environment','warna'=>'#5EAE38','g1'=>'#6EC245','g2'=>'#4A932C',
   'tag'=>'Menjaga alam untuk generasi berikutnya',
   'ket'=>'Pengelolaan limbah, kualitas air dan udara, serta reklamasi lahan.',
   'modul'=>[['Audit ISO 14001','segera',null],['Pemantauan Lingkungan','segera',null]]],

  ['key'=>'eng','nama'=>'Engineering','warna'=>'#FF9800','g1'=>'#29ABE2','g2'=>'#20949E',
   'tag'=>'Rekayasa andal untuk sarana yang laik',
   'ket'=>'Kelaikan peralatan dan instalasi, kajian teknis, serta tenaga teknis bersertifikat.',
   'modul'=>[['KO / SPIP','segera',null],['APAR & Proteksi Kebakaran','aktif','sigap.apar.index']]],
];

// posisi heksagon pada cincin (searah jarum jam dari atas)
$pos = [['50%','14%'],['81%','32%'],['81%','68%'],['50%','86%'],['19%','68%'],['19%','32%']];
$posTag = [
  'left:2%;top:15%;text-align:right',
  'right:2%;top:26%;text-align:left',
  'right:2%;top:66%;text-align:left',
  'left:50%;top:96%;transform:translateX(-50%);text-align:center;max-width:56%',
  'left:2%;top:64%;text-align:right',
  'left:2%;top:38%;text-align:right',
];
$aman = function ($rute) { return $rute && \Illuminate\Support\Facades\Route::has($rute) ? route($rute) : null; };
@endphp

<style>
  .eqp{--cream:#F7F5F0;--paper:#fff;--char:#1B2024;--muted:#6B7480;--line:#E4E0D8;
       color:var(--char);font-family:inherit}
  .eqp .hero{padding:8px 0 4px;text-align:center}
  .eqp .eyebrow{font-size:11px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;
       color:var(--muted);margin:0 0 14px}
  .eqp .eyebrow::before,.eqp .eyebrow::after{content:"";display:inline-block;width:26px;height:1px;
       background:var(--line);vertical-align:middle;margin:0 12px}
  .eqp h1{font-family:'Playfair Display',Georgia,serif;font-weight:600;letter-spacing:-.01em;
       font-size:clamp(28px,6vw,46px);line-height:1.1;margin:0 0 12px}
  .eqp .lede{max-width:52ch;margin:0 auto;color:#4A525C;font-size:clamp(14px,3.6vw,16px)}

  .eqp .ring{position:relative;width:min(100%,600px);margin:22px auto 8px;aspect-ratio:1/1}
  .eqp .ring .orbit{position:absolute;inset:0;width:100%;height:100%}
  .eqp .core{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:46%;text-align:center}
  .eqp .mark{font-weight:700;letter-spacing:.02em;font-size:clamp(19px,4.4vw,31px);
       display:flex;justify-content:center;gap:1px}
  .eqp .core small{display:block;margin-top:8px;font-size:clamp(8.5px,2vw,11px);letter-spacing:.14em;
       text-transform:uppercase;color:var(--muted);line-height:1.7}
  .eqp .hex{position:absolute;width:23%;aspect-ratio:1/.94;transform:translate(-50%,-50%);
       display:grid;place-items:center;text-decoration:none;
       clip-path:polygon(25% 0,75% 0,100% 50%,75% 100%,25% 100%,0 50%);
       transition:transform .25s cubic-bezier(.2,.7,.3,1)}
  .eqp .hex svg{width:42%;height:42%;fill:#fff}
  .eqp .hex:hover,.eqp .hex:focus-visible{transform:translate(-50%,-50%) scale(1.07)}
  .eqp .hex:focus-visible{outline:2px solid var(--char);outline-offset:3px}
  .eqp .tag{position:absolute;font-size:clamp(9px,2.1vw,12px);line-height:1.4;max-width:19%}
  .eqp .tag b{display:block;font-size:clamp(10px,2.4vw,13px);letter-spacing:.08em;
       text-transform:uppercase;margin-bottom:3px}
  .eqp .tag span{color:var(--muted)}

  .eqp .sect{padding:26px 0 8px}
  .eqp .sect h2{font-family:'Playfair Display',Georgia,serif;font-weight:600;font-size:22px;margin:0 0 4px}
  .eqp .sect .sub{color:var(--muted);font-size:13px;margin:0 0 20px}
  .eqp .grid{display:grid;gap:14px;grid-template-columns:1fr}
  @media(min-width:640px){.eqp .grid{grid-template-columns:1fr 1fr}}
  @media(min-width:1024px){.eqp .grid{grid-template-columns:1fr 1fr 1fr}}
  .eqp .card{background:var(--paper);border:1px solid var(--line);border-radius:14px;
       padding:18px 18px 16px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s}
  .eqp .card::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--c)}
  .eqp .card:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(27,32,36,.09)}
  .eqp .card .top{display:flex;align-items:center;gap:11px;margin-bottom:9px}
  .eqp .chip{width:36px;height:34px;flex:none;background:var(--c);display:grid;place-items:center;
       clip-path:polygon(25% 0,75% 0,100% 50%,75% 100%,25% 100%,0 50%)}
  .eqp .chip svg{width:17px;height:17px;fill:#fff}
  .eqp .card h3{margin:0;font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--c)}
  .eqp .card p{margin:0 0 14px;font-size:13.5px;color:#4A525C}
  .eqp .mods{display:flex;flex-wrap:wrap;gap:6px}
  .eqp .mod{font-size:11.5px;font-weight:500;padding:4px 9px;border-radius:999px;
       border:1px solid var(--line);color:#3D454E;background:#FBFAF7;text-decoration:none}
  .eqp .mod:hover{border-color:var(--c);color:var(--c)}
  .eqp .mod.soon{color:#98A0AA;border-style:dashed;cursor:default}
  .eqp .mod .dot{display:inline-block;width:5px;height:5px;border-radius:50%;background:var(--c);
       margin-right:5px;vertical-align:middle}
  .eqp .mod.soon .dot{background:#C6CBD1}

  @media(max-width:719px){.eqp .ring{display:none}}
  @media(prefers-reduced-motion:reduce){.eqp *{transition:none!important}}
</style>

<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
  <symbol id="pi-energy" viewBox="0 0 24 24"><path d="M12 1.5A10.5 10.5 0 1 0 22.5 12 10.51 10.51 0 0 0 12 1.5Zm0 19A8.5 8.5 0 1 1 20.5 12 8.51 8.51 0 0 1 12 20.5Z"/><path d="M13.2 5.6 7.4 13h3.3l-.9 5.4 5.8-7.4h-3.3l.9-5.4Z"/></symbol>
  <symbol id="pi-quality" viewBox="0 0 24 24"><path d="M12 2.2S5.6 9.3 5.6 13.6a6.4 6.4 0 0 0 12.8 0C18.4 9.3 12 2.2 12 2.2Zm0 16.1a4.7 4.7 0 0 1-4.7-4.7c0-2.6 3.1-6.8 4.7-8.7 1.6 1.9 4.7 6.1 4.7 8.7a4.7 4.7 0 0 1-4.7 4.7Z"/><path d="M4 19.4c2.2 1.2 5 1.8 8 1.8s5.8-.6 8-1.8v1.8c-2.2 1-5 1.6-8 1.6s-5.8-.6-8-1.6Z" opacity=".55"/></symbol>
  <symbol id="pi-health" viewBox="0 0 24 24"><path d="M11 3a5 5 0 0 0-5 4.6h10A5 5 0 0 0 11 3Zm-6 5.6a1 1 0 0 0 0 2h12a1 1 0 0 0 0-2Zm6 3.1a5 5 0 0 0-5 5v2.7h6.3a6.4 6.4 0 0 1 2.2-7.4 5 5 0 0 0-3.5-1.3Z"/><path d="M18.2 12.6a4.6 4.6 0 1 0 0 9.2 4.6 4.6 0 0 0 0-9.2Zm2.3 5.4h-1.6v1.6h-1.4V18h-1.6v-1.4h1.6V15h1.4v1.6h1.6Z"/></symbol>
  <symbol id="pi-safety" viewBox="0 0 24 24"><path d="M12 1.8 3.8 5v6.2c0 5.1 3.5 9.8 8.2 11 4.7-1.2 8.2-5.9 8.2-11V5Zm0 2.2 6.2 2.4v4.8c0 4-2.6 7.8-6.2 8.9-3.6-1.1-6.2-4.9-6.2-8.9V6.4Z"/><path d="m10.9 14.4-2.2-2.2-1.3 1.4 3.5 3.5 6-6-1.4-1.4Z"/></symbol>
  <symbol id="pi-env" viewBox="0 0 24 24"><path d="M20.6 3.6c-8 0-13.4 3.2-13.4 9.4a7.9 7.9 0 0 0 1.1 4.2c1.6-3.6 4.5-6.4 8.2-8-3 2.2-5.3 5.3-6.4 8.9l-.9 2.9h2.1l.6-2c6.6-.4 8.7-6 8.7-15.4Z"/><path d="M4.6 8.5c-1.4 1.5-2 3.3-1.6 5.3.9-1.7 2.3-3 4-3.9a5 5 0 0 0-2.4-1.4Z" opacity=".6"/></symbol>
  <symbol id="pi-eng" viewBox="0 0 24 24"><path d="M21 13.1v-2.2l-2.4-.4a6.9 6.9 0 0 0-.8-1.9l1.4-2-1.6-1.6-2 1.4a6.9 6.9 0 0 0-1.9-.8L13.1 3h-2.2l-.4 2.6a6.9 6.9 0 0 0-1.9.8l-2-1.4-1.6 1.6 1.4 2a6.9 6.9 0 0 0-.8 1.9L3 10.9v2.2l2.6.4a6.9 6.9 0 0 0 .8 1.9l-1.4 2 1.6 1.6 2-1.4a6.9 6.9 0 0 0 1.9.8l.4 2.6h2.2l.4-2.6a6.9 6.9 0 0 0 1.9-.8l2 1.4 1.6-1.6-1.4-2a6.9 6.9 0 0 0 .8-1.9ZM12 15.4A3.4 3.4 0 1 1 15.4 12 3.4 3.4 0 0 1 12 15.4Z"/></symbol>
</defs></svg>

<div class="eqp">

  <section class="hero">
    <p class="eyebrow">Kerangka Kerja EQOHSEE</p>
    <h1>Delapan aspek, satu sistem.</h1>
    <p class="lede">Setiap modul di EQOHSEE berdiri di salah satu pilar ini. Pilih pilar untuk melihat modul yang menopangnya.</p>
  </section>

  <div class="ring">
    <svg class="orbit" viewBox="0 0 500 500" aria-hidden="true">
      <defs><linearGradient id="pgrad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#1F6FB8"/><stop offset=".2" stop-color="#2FA3DE"/>
        <stop offset=".4" stop-color="#F08A22"/><stop offset=".6" stop-color="#DC6E00"/>
        <stop offset=".8" stop-color="#5EAE38"/><stop offset="1" stop-color="#FF9800"/>
      </linearGradient></defs>
      <circle cx="250" cy="250" r="150" fill="none" stroke="url(#pgrad)" stroke-width="3"
              stroke-linecap="round" stroke-dasharray="130 27" stroke-dashoffset="65" opacity=".9"/>
    </svg>

    @foreach ($pilar as $i => $p)
      <a class="hex" href="#pil-{{ $p['key'] }}" aria-label="{{ $p['nama'] }}"
         style="left:{{ $pos[$i][0] }};top:{{ $pos[$i][1] }};background:linear-gradient(150deg,{{ $p['g1'] }},{{ $p['g2'] }})">
        <svg><use href="#pi-{{ $p['key'] }}"/></svg>
      </a>
      <div class="tag" style="{{ $posTag[$i] }}">
        <b style="color:{{ $p['warna'] }}">{{ $p['key'] === 'health' ? 'Occ. Health' : $p['nama'] }}</b>
        <span>{{ $p['tag'] }}</span>
      </div>
    @endforeach

    <div class="core">
      <div class="mark"><span style="color:#1F6FB8">E</span><span style="color:#2FA3DE">Q</span><span style="color:#F08A22">O</span><span style="color:#DC6E00">H</span><span style="color:#DC6E00">S</span><span style="color:#5EAE38">E</span><span style="color:#FF9800">E</span></div>
      <small>Menjaga kinerja,<br>membentuk masa depan</small>
    </div>
  </div>

  <section class="sect">
    <h2>Modul per pilar</h2>
    <p class="sub">Modul bertanda garis putus berarti belum aktif.</p>

    <div class="grid">
      @foreach ($pilar as $p)
        <article class="card" id="pil-{{ $p['key'] }}" style="--c:{{ $p['warna'] }}">
          <div class="top">
            <span class="chip"><svg><use href="#pi-{{ $p['key'] }}"/></svg></span>
            <h3>{{ $p['nama'] }}</h3>
          </div>
          <p>{{ $p['ket'] }}</p>
          <div class="mods">
            @foreach ($p['modul'] as [$namaModul, $status, $rute])
              @php $url = $status === 'aktif' ? $aman($rute) : null; @endphp
              @if ($url)
                <a class="mod" href="{{ $url }}"><span class="dot"></span>{{ $namaModul }}</a>
              @else
                <span class="mod soon"><span class="dot"></span>{{ $namaModul }}</span>
              @endif
            @endforeach
          </div>
        </article>
      @endforeach
    </div>
  </section>

</div>
@endsection
