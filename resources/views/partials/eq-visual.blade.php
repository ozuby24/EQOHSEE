{{--
  Lapisan visual EQOHSEE — disisipkan di awal <body> oleh layouts/app.blade.php.

  Semua gaya di sini berdiri sendiri (CSS biasa, bukan utilitas Tailwind baru),
  jadi tidak perlu `npm run build`. Kelas dan pemilihnya diawali `eq-` atau
  menargetkan id tertentu, supaya tidak menabrak gaya yang sudah ada.
--}}

@php
  /* Ikon nav sidebar — dipetakan dari label menu. Dipakai layouts/app.blade.php. */
  $eqPaths = [
    'dashboard'   => 'M12 3 3 10v11h6v-6h6v6h6V10Z',
    'kursus'      => 'M12 4 3 8l9 4 9-4-9-4Zm-5 6.5V15c0 1.3 2.7 2.3 5 2.3s5-1 5-2.3v-4.5',
    'dokumen'     => 'M7 3h7l5 5v13H7Zm7 0v5h5',
    'periksa'     => 'M9 12l2 2 4-4M5 4h14v16H5Z',
    'sertifikat'  => 'M12 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm-4 11-1 7 5-2.5L17 21l-1-7',
    'berita'      => 'M4 5h16v14H4Zm3 3h7M7 12h10M7 16h10',
    'nilai'       => 'M4 19h16M7 15V9m5 6V5m5 10v-4',
    'grafik'      => 'M4 20h16M7 16v-5m5 5V7m5 9v-3',
    'orang'       => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 8c0-3.3 3.6-5 8-5s8 1.7 8 5',
    'kalkulator'  => 'M6 3h12v18H6Zm2 4h8M8 12h2m4 0h2m-6 4h2m4 0h2',
    'bahaya'      => 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h16.9a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
    'perisai'     => 'M12 3l7.5 4v5c0 4.4-3.1 8.5-7.5 9.7C7.6 20.5 4.5 16.4 4.5 12V7Z',
    'kunci'       => 'M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5Z',
    'gedung'      => 'M4 21V4h9v6h7v11ZM7 7h3M7 11h3M7 15h3m6 0h2m-2 4h2',
    'lonceng'     => 'M12 3a5 5 0 0 0-5 5v4l-2 3h14l-2-3V8a5 5 0 0 0-5-5Zm-2 15a2 2 0 0 0 4 0',
    'kotak'       => 'M4 7l8-4 8 4v10l-8 4-8-4Zm0 0 8 4m0 0 8-4m-8 4v9',
    'gerigi'      => 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm8.5 3-.1-1 1.6-1.3-1.5-2.6-2 .7-1.7-1L16.5 4h-3l-.3 2-1.7 1-2-.7-1.5 2.6L9.6 10l-.1 1 .1 1-1.6 1.3 1.5 2.6 2-.7 1.7 1 .3 2h3l.3-2 1.7-1 2 .7 1.5-2.6-1.6-1.3Z',
    'buku'        => 'M5 4h11a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2Zm2 0v14M18 6h1v14',
    'default'     => 'M5 12h14M5 7h14M5 17h9',
  ];

  $eqIcon = function (string $label) use ($eqPaths) {
      $l = strtolower($label);
      $peta = [
          'dashboard' => 'dashboard', 'beranda' => 'dashboard',
          'kursus' => 'kursus', 'pelatihan' => 'kursus',
          'prosedur' => 'dokumen', 'sop' => 'periksa', 'dokumen' => 'dokumen',
          'sertifikat' => 'sertifikat', 'penanda tangan' => 'sertifikat',
          'berita' => 'berita',
          'evaluasi' => 'periksa', 'rekapitulasi' => 'nilai', 'formulir' => 'nilai',
          'kuesioner' => 'periksa', 'visualisasi' => 'grafik', 'analitik' => 'grafik',
          'kpi' => 'grafik', 'program' => 'nilai', 'profil' => 'orang',
          'slovin' => 'kalkulator', 'kalkulator' => 'kalkulator',
          'metode' => 'kotak', 'laporan' => 'bahaya', 'monitor' => 'bahaya',
          'inspeksi' => 'periksa', 'jenis' => 'kotak', 'temuan' => 'bahaya',
          'pengingat' => 'lonceng', 'buat' => 'dokumen',
          'register' => 'kotak', 'kelayakan' => 'perisai', 'perawatan' => 'gerigi',
          'pengaman' => 'perisai', 'kajian' => 'dokumen', 'tenaga' => 'orang',
          'tindak' => 'periksa', 'pengaturan' => 'gerigi', 'pusat kendali' => 'gerigi',
          'perusahaan' => 'gedung', 'pengguna' => 'orang',
        'regulasi' => 'buku', 'instrumen' => 'buku', 'rubrik' => 'buku', 'tentang' => 'buku',
      ];
      $kunci = 'default';
      foreach ($peta as $cari => $ikon) {
          if (str_contains($l, $cari)) { $kunci = $ikon; break; }
      }
      return '<svg class="eq-navico" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
           . ' stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
           . '<path d="' . $eqPaths[$kunci] . '"/></svg>';
  };
@endphp

<style>
/* ═══════════════════════════════════════════════════════════
   1 · TEKSTUR LATAR — butiran halus + gradasi hangat
   ═══════════════════════════════════════════════════════════ */
body{
  background-color:#FBFAF7;
  background-image:
    radial-gradient(1100px 520px at 88% -8%, rgba(44,176,188,.055), transparent 62%),
    radial-gradient(760px 420px at -6% 104%, rgba(94,174,56,.045), transparent 60%),
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='3' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.028'/%3E%3C/svg%3E");
  background-attachment:fixed,fixed,fixed;
}

/* ═══════════════════════════════════════════════════════════
   2 · TOPBAR — perbaikan tembus pandang
   Konten yang menggulung sempat terlihat menembus header karena
   glass-light terlalu transparan dan backdrop-filter tidak selalu
   aktif di WebView Android.
   ═══════════════════════════════════════════════════════════ */
.eq-topbar{
  background:rgba(251,250,247,.94);
  border-bottom:1px solid rgba(27,32,36,.07);
  box-shadow:0 1px 0 rgba(27,32,36,.03), 0 8px 24px -18px rgba(27,32,36,.35);
  z-index:30;
}
@supports (backdrop-filter:blur(2px)) or (-webkit-backdrop-filter:blur(2px)){
  .eq-topbar{
    background:rgba(251,250,247,.82);
    -webkit-backdrop-filter:saturate(180%) blur(14px);
            backdrop-filter:saturate(180%) blur(14px);
  }
}
.eq-topbar h1{letter-spacing:-.012em}
.eq-topbar::after{
  content:"";position:absolute;left:0;right:0;bottom:-1px;height:1px;
  background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#12897F,#5EAE38,#2CB0BC);
  opacity:.5;
}

/* ═══════════════════════════════════════════════════════════
   3 · SIDEBAR — kertas milimeter, ikon, dan panorama bawah
   ═══════════════════════════════════════════════════════════ */
/* JANGAN menyetel `position` di sini. Sidebar memakai `fixed lg:static`
   dari Tailwind untuk menjadi laci melayang di ponsel dan kolom tetap di
   layar lebar. Selektor ID ini lebih kuat daripada kelas `.fixed`, sehingga
   `position:relative` membuat sidebar tetap ikut arus dan merebut 248px —
   di layar 360px konten hanya kebagian 112px dan terpotong.
   Lapisan hiasan di bawah hanya butuh konteks penumpukan, dan `isolation`
   memberikannya tanpa menyentuh `position`. */
#eqSidebar{isolation:isolate;overflow:hidden}
#eqSidebar > *{position:relative;z-index:2}

#eqSidebar::before{                       /* kertas milimeter tipis */
  content:"";position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.5;
  background-image:
    linear-gradient(rgba(255,255,255,.06) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.06) 1px,transparent 1px);
  background-size:38px 38px;
  -webkit-mask-image:radial-gradient(120% 80% at 15% 8%,#000 25%,transparent 76%);
          mask-image:radial-gradient(120% 80% at 15% 8%,#000 25%,transparent 76%);
}
#eqSidebar::after{                        /* panorama bukit + jenjang tambang */
  content:"";position:absolute;left:0;right:0;bottom:0;height:190px;z-index:1;
  pointer-events:none;opacity:.5;
  background:no-repeat bottom/100% auto
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 248 190' preserveAspectRatio='none'%3E%3Cpath d='M0 150h60v-14h48v-16h56v-14h84V190H0z' fill='%23ffffff' fill-opacity='.045'/%3E%3Cpath d='M0 150h60v-14h48v-16h56v-14h84' fill='none' stroke='%23ffffff' stroke-opacity='.14' stroke-width='1.2'/%3E%3Cpath d='M60 136v14M108 120v16M164 106v14' stroke='%23ffffff' stroke-opacity='.09' stroke-width='1'/%3E%3Cpath d='M0 172h248' stroke='%232CB0BC' stroke-opacity='.28' stroke-width='1.4'/%3E%3Cg fill='%23ffffff' fill-opacity='.10'%3E%3Cpath d='M28 168h34l6-9h16l4 9h10v-14h-8l-5-8H60l-6 8H28z'/%3E%3Ccircle cx='44' cy='170' r='5'/%3E%3Ccircle cx='86' cy='170' r='5'/%3E%3C/g%3E%3C/svg%3E");
}

/* item nav + ikon */
#eqSidebar nav a{gap:10px}
.eq-navico{width:15px;height:15px;flex:none;opacity:.55;transition:opacity .18s,transform .18s}
#eqSidebar nav a:hover .eq-navico{opacity:.95;transform:translateX(1px)}
#eqSidebar nav a.nav-active .eq-navico{opacity:1;color:#C7DE30}
#eqSidebar nav a{position:relative;overflow:hidden}
#eqSidebar nav a::before{                 /* sapuan halus saat disentuh */
  content:"";position:absolute;inset:0;border-radius:inherit;
  background:linear-gradient(90deg,rgba(255,255,255,.10),transparent 62%);
  opacity:0;transition:opacity .2s;
}
#eqSidebar nav a:hover::before{opacity:1}

/* pemilih modul */
#eqSidebar .glass a{position:relative;transition:transform .2s cubic-bezier(.2,.7,.3,1)}
#eqSidebar .glass a:hover{transform:translateY(-1.5px)}

/* ═══════════════════════════════════════════════════════════
   4 · KARTU — bayangan berlapis, tepi presisi, angkat saat disentuh
   ═══════════════════════════════════════════════════════════ */
main .bg-white{
  box-shadow:0 1px 2px rgba(27,32,36,.04), 0 8px 24px -20px rgba(27,32,36,.28);
  transition:box-shadow .22s ease, transform .22s cubic-bezier(.2,.7,.3,1), border-color .22s;
}
main .bg-white:hover{
  box-shadow:0 2px 6px rgba(27,32,36,.05), 0 18px 40px -24px rgba(27,32,36,.34);
  border-color:rgba(27,32,36,.12);
}
main table tbody tr{transition:background .16s}

/* angka besar lebih rapat & presisi */
main .stat{letter-spacing:-.022em;font-variant-numeric:tabular-nums}
main .num{font-variant-numeric:tabular-nums;font-feature-settings:"tnum" 1}

/* ═══════════════════════════════════════════════════════════
   5 · KARTU GRADASI (brand-gradient) — tekstur & kedalaman
   ═══════════════════════════════════════════════════════════ */
main .brand-gradient{position:relative;overflow:hidden;isolation:isolate}
main .brand-gradient::before{             /* kertas milimeter + butiran */
  content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;opacity:.55;
  background-image:
    linear-gradient(rgba(255,255,255,.055) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,.055) 1px,transparent 1px);
  background-size:42px 42px;
  -webkit-mask-image:radial-gradient(120% 100% at 12% 0%,#000 20%,transparent 78%);
          mask-image:radial-gradient(120% 100% at 12% 0%,#000 20%,transparent 78%);
}
main .brand-gradient::after{              /* siluet punggungan di tepi bawah */
  content:"";position:absolute;left:0;right:0;bottom:0;height:78px;z-index:-1;
  pointer-events:none;opacity:.5;
  background:no-repeat bottom/100% 100%
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 78' preserveAspectRatio='none'%3E%3Cpath d='M0 62 92 34l74 20 88-32 96 26 74-18 96 28 80-10v40H0z' fill='%23ffffff' fill-opacity='.05'/%3E%3Cpath d='M0 62 92 34l74 20 88-32 96 26 74-18 96 28 80-10' fill='none' stroke='%23ffffff' stroke-opacity='.12' stroke-width='1.1'/%3E%3C/svg%3E");
}

/* ═══════════════════════════════════════════════════════════
   6 · PITA SPEKTRUM ENAM PILAR — penanda halus di kepala kartu
   ═══════════════════════════════════════════════════════════ */
.eq-seam{height:3px;border-radius:3px;
  background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#12897F,#5EAE38,#2CB0BC)}

/* ═══════════════════════════════════════════════════════════
   7 · KENYAMANAN BACA
   ═══════════════════════════════════════════════════════════ */
main h1,main h2,main h3{letter-spacing:-.011em}
main input,main select,main textarea{transition:border-color .16s,box-shadow .16s}
main a{transition:color .16s}
::selection{background:rgba(44,176,188,.22)}

/* gulir lebih halus di panel gelap */
#eqSidebar nav::-webkit-scrollbar{width:5px}
#eqSidebar nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.14);border-radius:3px}

@media (prefers-reduced-motion: reduce){
  main .bg-white,#eqSidebar nav a,#eqSidebar .glass a,.eq-navico{transition:none!important}
  main .bg-white:hover,#eqSidebar .glass a:hover{transform:none!important}
}
</style>
