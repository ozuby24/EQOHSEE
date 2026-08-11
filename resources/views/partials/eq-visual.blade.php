{{--
  Lapisan visual EQOHSEE — disisipkan di awal <body> oleh layouts/app.blade.php.

  Semua gaya di sini berdiri sendiri (CSS biasa, bukan utilitas Tailwind baru),
  jadi tidak perlu `npm run build`. Kelas dan pemilihnya diawali `eq-` atau
  menargetkan id tertentu, supaya tidak menabrak gaya yang sudah ada.
--}}

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
.eq-topbar{min-height:66px;display:flex;align-items:center;gap:14px;
  padding:11px 16px;position:sticky;top:0}
@media (min-width:1024px){.eq-topbar{padding:11px 26px}}

.eq-menu-btn{width:40px;height:40px;flex:none;border-radius:12px;display:grid;place-items:center;
  color:#1B2024;background:#fff;border:1px solid rgba(27,32,36,.09);
  box-shadow:0 1px 2px rgba(27,32,36,.05);
  transition:border-color .18s,color .18s,transform .18s cubic-bezier(.21,.6,.35,1)}
.eq-menu-btn:hover{border-color:#12897F;color:#12897F}
.eq-menu-btn:active{transform:scale(.94)}
.eq-menu-btn svg{width:19px;height:19px}
@media (min-width:1024px){.eq-menu-btn{display:none}}

.eq-judul h1{font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.2;color:#14385A;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.eq-judul p{font-size:12.5px;color:#7C8894;margin-top:2px;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media (max-width:640px){.eq-judul p{display:none}.eq-judul h1{font-size:16px}}

.eq-topbar-aksi{display:flex;align-items:center;gap:10px;flex:none}
.eq-lonceng{position:relative}
.eq-bulat{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;
  color:#7C8894;background:#fff;border:1px solid rgba(27,32,36,.09);
  box-shadow:0 1px 2px rgba(27,32,36,.05);
  transition:color .18s,border-color .18s,transform .18s cubic-bezier(.21,.6,.35,1)}
.eq-bulat:hover{color:#12897F;border-color:#12897F}
.eq-bulat:active{transform:scale(.94)}
.eq-bulat svg{width:19px;height:19px}
.eq-lonceng-titik{position:absolute;top:-2px;right:-2px;min-width:19px;height:19px;padding:0 5px;
  border-radius:999px;background:#E5484D;color:#fff;font-size:10.5px;font-weight:700;
  line-height:19px;text-align:center;border:2px solid #FBFAF7}

.eq-profil{display:flex;align-items:center;gap:10px;padding-left:2px}
.eq-avatar{width:40px;height:40px;flex:none;border-radius:50%;display:grid;place-items:center;
  background:linear-gradient(135deg,#0E747E,#2CB0BC);color:#fff;font-weight:800;font-size:14px}
.eq-profil-teks{display:flex;flex-direction:column;line-height:1.25}
.eq-profil-teks strong{font-size:13px;color:#14385A;font-weight:700}
.eq-profil-teks small{font-size:11.5px;color:#7C8894}
@media (max-width:860px){.eq-profil-teks{display:none}}

.eq-keluar{display:flex;align-items:center;gap:7px;padding:9px 13px;border-radius:11px;
  font-size:12.5px;font-weight:600;color:#7C8894;
  border:1px solid rgba(27,32,36,.09);background:#fff;
  transition:color .18s,border-color .18s,background-color .18s}
.eq-keluar:hover{color:#12897F;border-color:#12897F;background:rgba(18,137,127,.06)}
.eq-keluar svg{width:17px;height:17px}

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

/* ── Kepala: lambang, nama, tagline ──
   Tagline duduk di bawah nama, bukan di sebelahnya: sebaris keduanya
   membuat nama kehilangan bobot, padahal itu yang harus terbaca lebih
   dulu. */
.eq-merek{
  display:flex;align-items:center;gap:11px;
  padding:18px 18px 15px;flex:none;
  border-bottom:1px solid rgba(255,255,255,.09);
}
.eq-merek img{display:block;flex:none}
.eq-merek span{display:flex;flex-direction:column;line-height:1.12;min-width:0}
.eq-merek strong{font-size:20px;font-weight:900;letter-spacing:-.015em;color:#EAF1F5}
.eq-merek strong em{font-style:normal;color:#2CB0BC}
.eq-merek small{font-size:10.5px;color:rgba(255,255,255,.42);margin-top:3px;letter-spacing:.005em}

/* ── Kaki bilah samping ── */
.eq-sisi-kaki{flex:none;padding:10px 12px 14px}
.eq-bantuan{display:flex;gap:11px;align-items:flex-start;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);
  border-radius:14px;padding:13px}
.eq-bantuan-ikon{width:34px;height:34px;border-radius:11px;flex:none;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(135deg,#0E747E,#2CB0BC)}
.eq-bantuan-ikon svg{width:17px;height:17px}
.eq-bantuan-teks{min-width:0}
.eq-bantuan-teks strong{display:block;font-size:12.5px;color:#fff;font-weight:700}
.eq-bantuan-teks small{display:block;font-size:11px;color:rgba(255,255,255,.45);
  margin-top:2px;line-height:1.45}
.eq-bantuan-btn{display:flex;align-items:center;justify-content:center;gap:8px;
  margin-top:9px;padding:10px 12px;border-radius:12px;
  font-size:12px;font-weight:700;color:#fff;
  background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.11);
  transition:background-color .18s,border-color .18s}
.eq-bantuan-btn:hover{background:rgba(255,255,255,.17);border-color:rgba(255,255,255,.2)}
.eq-bantuan-btn svg{width:15px;height:15px}

.eq-sisi-bawah{display:flex;align-items:flex-end;justify-content:space-between;gap:10px;
  margin-top:13px;padding-top:12px;border-top:1px solid rgba(255,255,255,.08)}
.eq-sisi-bawah small{font-size:9.5px;color:rgba(255,255,255,.34);line-height:1.65}
.eq-lipat{width:30px;height:30px;flex:none;border-radius:9px;display:grid;place-items:center;
  color:rgba(255,255,255,.45);border:1px solid rgba(255,255,255,.1);
  transition:background-color .18s,color .18s}
.eq-lipat:hover{background:rgba(255,255,255,.09);color:#fff}
.eq-lipat svg{width:15px;height:15px;transition:transform .28s cubic-bezier(.21,.6,.35,1)}

/* ── Keadaan terlipat (layar lebar saja) ── */
@media (min-width:1024px){
  body.eq-sempit #eqSidebar{width:72px}
  body.eq-sempit .eq-merek{justify-content:center;padding:18px 0 15px}
  body.eq-sempit .eq-merek span,
  body.eq-sempit #eqSidebar nav a span:not(.nav-accent),
  body.eq-sempit .eq-bantuan-teks,
  body.eq-sempit .eq-bantuan-btn span,
  body.eq-sempit .eq-sisi-bawah small,
  body.eq-sempit #eqSidebar nav p,
  body.eq-sempit #eqSidebar .glass + div{display:none}
  body.eq-sempit #eqSidebar nav a{justify-content:center;padding-inline:0}
  body.eq-sempit .eq-bantuan{justify-content:center;padding:11px 0}
  body.eq-sempit .eq-bantuan-btn{padding:10px 0}
  body.eq-sempit .eq-sisi-bawah{justify-content:center;border-top:0}
  body.eq-sempit .eq-lipat svg{transform:rotate(180deg)}
  body.eq-sempit #eqSidebar .grid-cols-3{grid-template-columns:repeat(1,minmax(0,1fr))}
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

/* ═══════════════════════════════════════════════════════════
   9 · DASHBOARD LMS
   Susunannya: sambutan, deret angka, katalog kursus, kolom
   pendamping, lalu pintasan. Urutan itu bukan selera — yang
   ditanyakan orang saat membuka halaman ini berurutan begitu:
   siapa saya, sejauh mana, apa berikutnya.
   ═══════════════════════════════════════════════════════════ */

/* ── Nada warna kartu ── */
.t-hijau {background:rgba(94,174,56,.13);color:#4A8E2C}
.t-biru  {background:rgba(31,111,184,.12);color:#1F6FB8}
.t-toska {background:rgba(18,137,127,.13);color:#0E747E}
.t-kuning{background:rgba(240,138,34,.14);color:#C96F12}
.t-ungu  {background:rgba(124,92,206,.13);color:#6B4FBE}
.t-merah {background:rgba(214,69,69,.12);color:#C03A3A}

/* ── Sambutan ── */
.eq-hero{
  position:relative;overflow:hidden;border-radius:22px;
  background:linear-gradient(115deg,#0B2E30 0%,#0E4A44 46%,#12897F 100%);
  color:#fff;padding:32px 34px;min-height:214px;
  display:flex;align-items:center;
  box-shadow:0 20px 44px -26px rgba(11,46,48,.75);
}
.eq-hero-foto{
  position:absolute;inset:0 0 0 auto;width:56%;height:100%;
  object-fit:cover;object-position:46% 42%;
  -webkit-mask-image:linear-gradient(90deg,transparent 0,#000 52%,#000 100%);
          mask-image:linear-gradient(90deg,transparent 0,#000 52%,#000 100%);
  opacity:.62;pointer-events:none;
}
.eq-hero::after{
  content:"";position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(100deg,#0B2E30 0%,rgba(14,74,68,.86) 42%,rgba(18,137,127,.22) 100%);
}
.eq-hero-isi{position:relative;z-index:2;max-width:58ch}
.eq-hero-isi h2{font-size:clamp(23px,2.4vw,31px);font-weight:800;letter-spacing:-.022em;line-height:1.18}
.eq-hero-isi p{font-size:14px;color:rgba(255,255,255,.72);margin-top:10px;line-height:1.6;max-width:44ch}
.eq-hero-btn{
  display:inline-flex;align-items:center;gap:9px;margin-top:22px;
  padding:12px 22px;border-radius:13px;background:#fff;color:#0E4A44;
  font-size:13.5px;font-weight:700;
  transition:transform .18s cubic-bezier(.21,.6,.35,1),box-shadow .18s;
}
.eq-hero-btn:hover{transform:translateY(-2px);box-shadow:0 14px 28px -14px rgba(0,0,0,.6)}
.eq-hero-btn svg{width:16px;height:16px}
@media (max-width:720px){
  .eq-hero{padding:24px 20px;min-height:0}
  .eq-hero-foto{width:100%;opacity:.28}
}

/* ── Deret angka ── */
.eq-kpi-baris{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(215px,1fr))}
.eq-kpi{
  display:flex;gap:13px;align-items:flex-start;
  background:#fff;border:1px solid rgba(27,32,36,.07);border-radius:16px;padding:17px;
  box-shadow:0 1px 2px rgba(27,32,36,.04),0 12px 26px -20px rgba(27,32,36,.3);
  transition:transform .22s cubic-bezier(.21,.6,.35,1),box-shadow .22s;
}
.eq-kpi:hover{transform:translateY(-3px);box-shadow:0 4px 10px rgba(27,32,36,.06),0 22px 40px -24px rgba(27,32,36,.4)}
.eq-kpi-ikon{width:44px;height:44px;flex:none;border-radius:13px;display:grid;place-items:center}
.eq-kpi-ikon svg{width:21px;height:21px}
.eq-kpi-isi{min-width:0;display:flex;flex-direction:column}
.eq-kpi-label{font-size:11.5px;font-weight:600;color:#7C8894}
.eq-kpi-nilai{font-size:26px;font-weight:800;letter-spacing:-.03em;line-height:1.15;color:#14385A;
  font-variant-numeric:tabular-nums lining-nums;margin-top:2px}
.eq-kpi-ket{font-size:11px;color:#98A2AE;margin-top:3px}

/* ── Panel ── */
.eq-panel{background:#fff;border:1px solid rgba(27,32,36,.07);border-radius:18px;padding:20px 22px;
  box-shadow:0 1px 2px rgba(27,32,36,.04),0 12px 28px -22px rgba(27,32,36,.3)}
.eq-panel-kepala{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;
  gap:12px;margin-bottom:16px}
.eq-panel-kepala h3{font-size:16px;font-weight:800;color:#14385A;letter-spacing:-.015em}
.eq-panel-ket{font-size:11.5px;color:#98A2AE}
.eq-panel-kaki{font-size:11.5px;color:#7C8894;margin-top:12px}
.eq-tautan{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;color:#0E747E;
  transition:gap .18s}
.eq-tautan:hover{gap:9px}
.eq-tautan svg{width:14px;height:14px}
.eq-chip{font-size:11.5px;font-weight:600;color:#7C8894;background:#F4F6F8;
  border:1px solid rgba(27,32,36,.07);border-radius:9px;padding:5px 11px}

.eq-kisi-utama{display:grid;gap:16px;grid-template-columns:minmax(0,1fr) minmax(0,340px)}
@media (max-width:1180px){.eq-kisi-utama{grid-template-columns:minmax(0,1fr)}}
.eq-kolom-sisi{display:flex;flex-direction:column;gap:16px;min-width:0}

/* ── Kartu kursus ── */
.eq-kursus-kisi{display:grid;gap:15px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
.eq-kursus{border:1px solid rgba(27,32,36,.08);border-radius:16px;overflow:hidden;background:#fff;
  display:flex;flex-direction:column;
  transition:transform .22s cubic-bezier(.21,.6,.35,1),box-shadow .22s,border-color .22s}
.eq-kursus:hover{transform:translateY(-3px);border-color:rgba(18,137,127,.35);
  box-shadow:0 18px 34px -22px rgba(27,32,36,.5)}
.eq-kursus-gambar{position:relative;aspect-ratio:16/10;overflow:hidden;background:#EDF0F2}
.eq-kursus-gambar img{width:100%;height:100%;object-fit:cover;
  transition:transform .5s cubic-bezier(.21,.6,.35,1)}
.eq-kursus:hover .eq-kursus-gambar img{transform:scale(1.06)}
.eq-kursus-lencana{position:absolute;left:11px;top:11px;display:flex;gap:6px;flex-wrap:wrap}
/* Lencana kaca: pil membulat penuh yang membiarkan fotonya terbaca.
   Latar pekat menempel seperti stiker dan memotong gambar; yang tembus
   pandang tetap terbaca berkat blur + garis tepi terang, sekaligus
   membiarkan sampulnya utuh. Bayangan teks menjaga huruf putih tetap
   terbaca ketika kebetulan jatuh di bagian foto yang terang.

   Aturan dasar ini sengaja TIDAK menetapkan background: pemilihnya
   (kelas + elemen) lebih kuat daripada kelas warna tunggal seperti
   .k-kuning, sehingga satu latar bersama di sini akan menimpa seluruh
   warna kategori dan membuat semua lencana tampak sama. */
.eq-kursus-lencana i{font-style:normal;font-size:9.5px;font-weight:800;letter-spacing:.07em;
  padding:5px 11px;border-radius:999px;color:#fff;
  border:1px solid rgba(255,255,255,.34);
  backdrop-filter:blur(9px) saturate(1.5);
  -webkit-backdrop-filter:blur(9px) saturate(1.5);
  text-shadow:0 1px 3px rgba(6,32,30,.55);
  box-shadow:0 2px 10px rgba(6,32,30,.22)}

/* Warna dibawa sebagai semburat tipis, bukan blok penuh — cukup untuk
   membedakan sekilas tanpa menutup fotonya. */
.l-utama{background:linear-gradient(135deg,rgba(14,116,126,.42),rgba(14,74,68,.30))}
.l-ikut{background:linear-gradient(135deg,rgba(31,111,184,.44),rgba(20,80,140,.30))}
.l-selesai{background:linear-gradient(135deg,rgba(107,178,58,.44),rgba(74,142,44,.30))}

/* Tiap kategori berwarna sendiri, ditetapkan App\Support\Kategori menurut
   namanya. Satu warna untuk semua kategori membuat lencananya hanya
   mengulang tulisan yang sudah ada di dalamnya.

   Kepekatannya sengaja lebih tinggi daripada lencana status: warna yang
   terlalu tipis di atas foto ramai terbaca sebagai abu-abu yang sama,
   sehingga kategorinya justru tidak terbedakan sama sekali. */
.k-toska {background:linear-gradient(135deg,rgba(16,146,136,.80),rgba(12,92,86,.66))}
.k-biru  {background:linear-gradient(135deg,rgba(33,118,196,.80),rgba(18,74,132,.66))}
.k-kuning{background:linear-gradient(135deg,rgba(232,132,26,.82),rgba(178,92,10,.68))}
.k-hijau {background:linear-gradient(135deg,rgba(101,176,50,.80),rgba(64,128,36,.66))}
.k-ungu  {background:linear-gradient(135deg,rgba(126,94,212,.80),rgba(84,58,158,.66))}
.k-merah {background:linear-gradient(135deg,rgba(214,66,66,.80),rgba(154,40,40,.66))}

/* Lencana kategori di luar kartu dashboard — katalog dan halaman kursus —
   memakai bentuk dan warna yang sama supaya kategori dikenali di mana pun
   ia muncul. */
.eq-lencana-kat{display:inline-block;font-size:10px;font-weight:800;letter-spacing:.06em;
  padding:5px 11px;border-radius:999px;color:#fff;
  border:1px solid rgba(255,255,255,.32);
  backdrop-filter:blur(9px) saturate(1.5);
  -webkit-backdrop-filter:blur(9px) saturate(1.5);
  text-shadow:0 1px 3px rgba(6,32,30,.55)}

/* Peramban tanpa backdrop-filter menampilkan lencana nyaris tanpa latar,
   jadi warnanya dinaikkan agar tulisannya tetap terbaca. */
@supports not ((backdrop-filter:blur(1px)) or (-webkit-backdrop-filter:blur(1px))){
  .l-utama{background:rgba(14,74,68,.82)}
  .l-ikut{background:rgba(31,111,184,.82)}
  .l-selesai{background:rgba(74,142,44,.84)}
  .k-toska {background:rgba(14,116,126,.86)}
  .k-biru  {background:rgba(31,111,184,.86)}
  .k-kuning{background:rgba(200,110,20,.88)}
  .k-hijau {background:rgba(74,142,44,.86)}
  .k-ungu  {background:rgba(107,79,190,.86)}
  .k-merah {background:rgba(192,58,58,.86)}
}

.eq-kursus-isi{padding:15px 16px 16px;display:flex;flex-direction:column;flex:1}
.eq-kursus-isi h4{font-size:14px;font-weight:700;color:#14385A;line-height:1.35}
.eq-kursus-isi > p{font-size:12px;color:#7C8894;margin-top:6px;line-height:1.55}
.eq-kursus-meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:11.5px;color:#98A2AE}
.eq-kursus-meta span{display:inline-flex;align-items:center;gap:5px}
.eq-kursus-meta svg{width:14px;height:14px}
.eq-kursus-maju{display:flex;align-items:baseline;justify-content:space-between;gap:10px;
  margin-top:14px;font-size:11.5px;color:#7C8894}
.eq-kursus-maju b{font-size:12.5px;font-weight:800;color:#0E747E;font-variant-numeric:tabular-nums}
.eq-bilah{height:7px;border-radius:999px;background:#EDF0F2;overflow:hidden;margin-top:6px}
.eq-bilah i{display:block;height:100%;border-radius:999px;
  background:linear-gradient(90deg,#0E747E,#2CB0BC);transition:width 1s cubic-bezier(.21,.6,.35,1)}
.eq-kursus-aksi{display:flex;gap:8px;margin-top:auto;padding-top:15px}

.eq-btn-utama{display:inline-flex;align-items:center;justify-content:center;gap:7px;flex:1;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:700;
  background:linear-gradient(135deg,#0E747E,#12897F);color:#fff;
  transition:filter .18s,transform .18s cubic-bezier(.21,.6,.35,1)}
.eq-btn-utama:hover{filter:brightness(1.08)}
.eq-btn-utama:active{transform:scale(.97)}
.eq-btn-utama svg{width:13px;height:13px}
.eq-btn-lain{display:inline-flex;align-items:center;justify-content:center;gap:7px;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:600;
  color:#5C6874;background:#fff;border:1px solid rgba(27,32,36,.11);
  transition:border-color .18s,color .18s}
.eq-btn-lain:hover{border-color:#12897F;color:#0E747E}
.eq-btn-blok{width:100%;margin-top:13px}

/* ── Keadaan kosong ── */
.eq-kosong{text-align:center;padding:34px 20px;color:#7C8894;font-size:12.5px;line-height:1.7}
.eq-kosong strong{color:#14385A;font-size:13.5px}
.eq-kosong .eq-btn-utama{display:inline-flex;flex:none;margin-top:14px;padding-inline:22px}
.eq-kosong-kecil{padding:24px 12px}
.eq-kosong-kecil .halus{color:#98A2AE;font-size:11.5px;margin-top:4px}

/* ── Progress mingguan ── */
.eq-pekan-label{display:flex;margin-top:6px;padding-left:52px}
.eq-pekan-label span{flex:1;text-align:center;font-size:10.5px;color:#98A2AE}

/* ── Pengumuman ── */
.eq-warta li + li{border-top:1px solid rgba(27,32,36,.07)}
.eq-warta a{display:flex;gap:11px;align-items:flex-start;padding:12px 2px;
  border-radius:10px;transition:background-color .18s}
.eq-warta a:hover{background:#F7F9FA}
.eq-warta-ikon{width:34px;height:34px;flex:none;border-radius:11px;display:grid;place-items:center}
.eq-warta-ikon svg{width:16px;height:16px}
.eq-warta-teks{flex:1;min-width:0}
.eq-warta-teks strong{display:block;font-size:12.5px;font-weight:700;color:#14385A;line-height:1.4}
.eq-warta-teks small{display:block;font-size:11.5px;color:#98A2AE;margin-top:2px;line-height:1.5}
.eq-warta time{font-size:10.5px;color:#98A2AE;flex:none;padding-top:2px}

/* ── Kategori ── */
.eq-kategori{display:grid;gap:11px;grid-template-columns:repeat(auto-fit,minmax(178px,1fr))}
.eq-kategori a,.eq-admin-angka{display:flex;align-items:center;gap:11px;padding:13px 15px;
  border:1px solid rgba(27,32,36,.08);border-radius:14px;background:#fff;
  transition:border-color .2s,transform .2s cubic-bezier(.21,.6,.35,1)}
.eq-kategori a:hover{border-color:rgba(18,137,127,.4);transform:translateY(-2px)}
.eq-kategori-ikon{width:36px;height:36px;flex:none;border-radius:11px;display:grid;place-items:center}
.eq-kategori-ikon svg{width:17px;height:17px}
.eq-kategori strong{display:block;font-size:12.5px;font-weight:700;color:#14385A}
.eq-kategori small{display:block;font-size:11px;color:#98A2AE;margin-top:1px}
.eq-admin-angka{flex-direction:column;align-items:flex-start;gap:2px}
.eq-admin-angka small{font-size:11.5px;color:#98A2AE}

/* ── Pintasan modul ── */
.eq-modul{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(206px,1fr))}
.eq-modul a{display:block;padding:16px;border:1px solid rgba(27,32,36,.08);border-radius:15px;
  background:#fff;transition:border-color .2s,transform .2s cubic-bezier(.21,.6,.35,1),box-shadow .2s}
.eq-modul a:hover{border-color:rgba(18,137,127,.36);transform:translateY(-3px);
  box-shadow:0 16px 30px -22px rgba(27,32,36,.45)}
.eq-modul-atas{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.eq-modul-nilai{font-size:27px;font-weight:800;letter-spacing:-.03em;line-height:1;
  font-variant-numeric:tabular-nums}
.eq-modul-ikon{width:38px;height:38px;flex:none;border-radius:12px;display:grid;place-items:center}
.eq-modul-ikon svg{width:18px;height:18px}
.eq-modul strong{display:block;font-size:12.5px;font-weight:700;color:#14385A;margin-top:12px}
.eq-modul small{display:block;font-size:11px;color:#98A2AE;margin-top:2px;line-height:1.5}

</style>
