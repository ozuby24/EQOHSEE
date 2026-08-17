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
.eq-menu-btn:hover{border-color:#DC6E00;color:#DC6E00}
.eq-menu-btn:active{transform:scale(.94)}
.eq-menu-btn svg{width:19px;height:19px}
@media (min-width:1024px){.eq-menu-btn{display:none}}

.eq-judul h1{font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.2;color:var(--eq-judul,#0F1720);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.eq-judul p{font-size:12.5px;color:var(--eq-redup,#7C8894);margin-top:2px;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media (max-width:640px){.eq-judul p{display:none}.eq-judul h1{font-size:16px}}

.eq-topbar-aksi{display:flex;align-items:center;gap:10px;flex:none}
.eq-lonceng{position:relative}
.eq-bulat{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;
  color:var(--eq-redup,#7C8894);background:#fff;border:1px solid rgba(27,32,36,.09);
  box-shadow:0 1px 2px rgba(27,32,36,.05);
  transition:color .18s,border-color .18s,transform .18s cubic-bezier(.21,.6,.35,1)}
.eq-bulat:hover{color:#DC6E00;border-color:#DC6E00}
.eq-bulat:active{transform:scale(.94)}
.eq-bulat svg{width:19px;height:19px}
.eq-lonceng-titik{position:absolute;top:-2px;right:-2px;min-width:19px;height:19px;padding:0 5px;
  border-radius:999px;background:#E5484D;color:#fff;font-size:10.5px;font-weight:700;
  line-height:19px;text-align:center;border:2px solid #FBFAF7}

.eq-profil{display:flex;align-items:center;gap:10px;padding-left:2px}
.eq-avatar{width:40px;height:40px;flex:none;border-radius:50%;display:grid;place-items:center;
  background:linear-gradient(135deg,#F57C00,#FF9800);color:#fff;font-weight:800;font-size:14px}
.eq-profil-teks{display:flex;flex-direction:column;line-height:1.25}
.eq-profil-teks strong{font-size:13px;color:var(--eq-judul,#0F1720);font-weight:700}
.eq-profil-teks small{font-size:11.5px;color:var(--eq-redup,#7C8894)}
@media (max-width:860px){.eq-profil-teks{display:none}}

.eq-keluar{display:flex;align-items:center;gap:7px;padding:9px 13px;border-radius:11px;
  font-size:12.5px;font-weight:600;color:var(--eq-redup,#7C8894);
  border:1px solid rgba(27,32,36,.09);background:#fff;
  transition:color .18s,border-color .18s,background-color .18s}
.eq-keluar:hover{color:#DC6E00;border-color:#DC6E00;background:rgba(18,137,127,.06)}
.eq-keluar svg{width:17px;height:17px}

.eq-topbar h1{letter-spacing:-.012em}
.eq-topbar::after{
  content:"";position:absolute;left:0;right:0;bottom:-1px;height:1px;
  background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#DC6E00,#5EAE38,#FF9800);
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

#eqSidebar::before{                       /* pendar bidang, bukan kisi */
  /* Dulu kertas milimeter 38 px. Kotaknya berulang rapi sepanjang kolom
     setinggi layar, dan justru itu yang membuatnya terbaca sebagai kertas
     berpetak alih-alih sebagai bidang — persis alasan yang sama sudah
     ditulis untuk .grid-tech di app.css, lalu terulang di sini.

     Diganti dua pendar lebar yang tidak berulang: satu hangat di dekat
     kop tempat merek berada, satu dingin lebih ke bawah. Teksturnya
     terasa tanpa pernah menampakkan pola, dan tidak ada garis yang
     bersaing dengan daftar menu di atasnya. */
  content:"";position:absolute;inset:0;z-index:0;pointer-events:none;
  background-image:
    radial-gradient(78% 30% at 18% 4%,  rgba(245,124,0,.16), transparent 72%),
    radial-gradient(70% 26% at 88% 34%, rgba(44,176,188,.10), transparent 74%),
    radial-gradient(90% 34% at 50% 96%, rgba(255,255,255,.045), transparent 76%);
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
.eq-merek strong{font-size:20px;font-weight:900;letter-spacing:-.015em;color:#E8ECF0}
.eq-merek strong em{font-style:normal;color:#F57C00}
.eq-merek small{font-size:9.5px;color:rgba(255,255,255,.42);margin-top:3px;letter-spacing:.005em}

/* ── Kaki bilah samping ── */
.eq-sisi-kaki{flex:none;padding:10px 12px 14px}
.eq-bantuan{display:flex;gap:11px;align-items:flex-start;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);
  border-radius:14px;padding:13px}
.eq-bantuan-ikon{width:34px;height:34px;border-radius:11px;flex:none;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(135deg,#F57C00,#FF9800)}
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
/* Teks hak cipta. Sebelumnya .34 alfa di atas navy — sekitar 2,4:1,
   di bawah ambang keterbacaan mana pun. Dinaikkan ke .58 supaya masih
   jelas berperan sekunder tetapi tetap dapat dibaca. */
.eq-sisi-bawah small{font-size:10px;color:rgba(255,255,255,.58);line-height:1.65}

/* Tombol lipat.
   DISEMBUNYIKAN di bawah 1024 px, sebab di sana ia memang tidak dapat
   melakukan apa pun: keadaan terlipat hanya didefinisikan pada layar
   lebar, dan di bawahnya bilah samping berupa laci yang menutup penuh.
   Sebelumnya tombolnya tetap tampil dan tetap dapat ditekan — kelas
   eq-sempit berpindah, tidak ada yang berubah di layar, dan tombolnya
   terbaca sebagai rusak. Menyembunyikan yang tidak berfungsi lebih
   jujur daripada menampilkan yang diam saja. */
.eq-lipat{display:none}

@media (min-width:1024px){
  .eq-lipat{
    /* Bidang sentuhnya 44 px sesuai anjuran ukuran sasaran minimum,
       sementara chip yang terlihat tetap 30 px lewat kotak-dalam.
       Sebelumnya 30 px seluruhnya — cukup untuk tetikus, meleset
       untuk jari. */
    width:44px;height:44px;flex:none;padding:7px;margin:-7px -7px -7px 0;
    background:none;border:0;display:grid;place-items:center;cursor:pointer;
  }
  .eq-lipat::before{
    content:"";position:absolute;width:30px;height:30px;border-radius:9px;
    border:1px solid rgba(255,255,255,.16);
    transition:background-color .18s,border-color .18s;
  }
  .eq-lipat{position:relative;color:rgba(255,255,255,.62)}
  .eq-lipat:hover{color:#fff}
  .eq-lipat:hover::before{background:rgba(255,255,255,.10);border-color:rgba(255,255,255,.26)}
  /* Umpan balik tekan bekerja pada sentuhan, tempat :hover tidak ada. */
  .eq-lipat:active::before{background:rgba(255,255,255,.18)}
  .eq-lipat:focus-visible::before{border-color:#FF9800;box-shadow:0 0 0 2px rgba(255,152,0,.35)}
  .eq-lipat svg{width:15px;height:15px;position:relative;
    transition:transform .28s cubic-bezier(.21,.6,.35,1)}
}

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
main .brand-gradient::before{             /* pendar bidang, bukan kisi */
  /* Sama seperti bilah samping: kisi 42 px diganti pendar lebar yang
     tidak berulang. Pada panel selebar layar, kotak yang berulang
     membuat mata mengikuti garisnya alih-alih isinya. */
  content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
  background-image:
    radial-gradient(52% 62% at 16% 6%,  rgba(245,124,0,.14), transparent 70%),
    radial-gradient(44% 54% at 84% 40%, rgba(44,176,188,.09), transparent 72%);
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
  background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#DC6E00,#5EAE38,#FF9800)}

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
.t-toska {background:rgba(18,137,127,.13);color:#F57C00}
.t-kuning{background:rgba(240,138,34,.14);color:#C96F12}
.t-ungu  {background:rgba(124,92,206,.13);color:#6B4FBE}
.t-merah {background:rgba(214,69,69,.12);color:#C03A3A}

/* ── Sambutan ── */
.eq-hero{
  position:relative;overflow:hidden;border-radius:22px;
  background:linear-gradient(115deg,#0B1117 0%,#151D26 52%,#243140 100%);
  color:#fff;padding:32px 34px;min-height:214px;
  display:flex;align-items:center;
  box-shadow:0 20px 44px -26px rgba(11,17,23,.8);
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
  background:linear-gradient(100deg,#0B1117 0%,rgba(21,29,38,.88) 44%,rgba(21,29,38,.30) 100%);
}
.eq-hero-isi{position:relative;z-index:2;max-width:58ch}
.eq-hero-isi h2{font-size:clamp(23px,2.4vw,31px);font-weight:800;letter-spacing:-.022em;line-height:1.18}
.eq-hero-isi p{font-size:14px;color:rgba(255,255,255,.72);margin-top:10px;line-height:1.6;max-width:44ch}
.eq-hero-btn{
  display:inline-flex;align-items:center;gap:9px;margin-top:22px;
  padding:12px 22px;border-radius:13px;background:#F57C00;color:#fff;
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
.eq-kpi-label{font-size:11.5px;font-weight:600;color:var(--eq-redup,#7C8894)}
.eq-kpi-nilai{font-size:26px;font-weight:800;letter-spacing:-.03em;line-height:1.15;color:var(--eq-judul,#0F1720);
  font-variant-numeric:tabular-nums lining-nums;margin-top:2px}
.eq-kpi-ket{font-size:11px;color:var(--eq-redup2,#98A2AE);margin-top:3px}

/* ── Panel ── */
.eq-panel{background:#fff;border:1px solid rgba(27,32,36,.07);border-radius:18px;padding:20px 22px;
  box-shadow:0 1px 2px rgba(27,32,36,.04),0 12px 28px -22px rgba(27,32,36,.3)}
.eq-panel-kepala{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;
  gap:12px;margin-bottom:16px}
.eq-panel-kepala h3{font-size:16px;font-weight:800;color:var(--eq-judul,#0F1720);letter-spacing:-.015em}
.eq-panel-ket{font-size:11.5px;color:var(--eq-redup2,#98A2AE)}
.eq-panel-kaki{font-size:11.5px;color:var(--eq-redup,#7C8894);margin-top:12px}
.eq-tautan{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;color:#F57C00;
  transition:gap .18s}
.eq-tautan:hover{gap:9px}
.eq-tautan svg{width:14px;height:14px}
.eq-chip{font-size:11.5px;font-weight:600;color:var(--eq-redup,#7C8894);background:#F4F6F8;
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
.eq-kursus-isi h4{font-size:14px;font-weight:700;color:var(--eq-judul,#0F1720);line-height:1.35}
.eq-kursus-isi > p{font-size:12px;color:var(--eq-redup,#7C8894);margin-top:6px;line-height:1.55}
.eq-kursus-meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:11.5px;color:var(--eq-redup2,#98A2AE)}
.eq-kursus-meta span{display:inline-flex;align-items:center;gap:5px}
.eq-kursus-meta svg{width:14px;height:14px}
.eq-kursus-maju{display:flex;align-items:baseline;justify-content:space-between;gap:10px;
  margin-top:14px;font-size:11.5px;color:var(--eq-redup,#7C8894)}
.eq-kursus-maju b{font-size:12.5px;font-weight:800;color:#F57C00;font-variant-numeric:tabular-nums}
.eq-bilah{height:7px;border-radius:999px;background:#EDF0F2;overflow:hidden;margin-top:6px}
.eq-bilah i{display:block;height:100%;border-radius:999px;
  background:linear-gradient(90deg,#F57C00,#FF9800);transition:width 1s cubic-bezier(.21,.6,.35,1)}
.eq-kursus-aksi{display:flex;gap:8px;margin-top:auto;padding-top:15px}

.eq-btn-utama{display:inline-flex;align-items:center;justify-content:center;gap:7px;flex:1;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:700;
  background:linear-gradient(135deg,#F57C00,#DC6E00);color:#fff;
  transition:filter .18s,transform .18s cubic-bezier(.21,.6,.35,1)}
.eq-btn-utama:hover{filter:brightness(1.08)}
.eq-btn-utama:active{transform:scale(.97)}
.eq-btn-utama svg{width:13px;height:13px}
.eq-btn-lain{display:inline-flex;align-items:center;justify-content:center;gap:7px;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:600;
  color:#5C6874;background:#fff;border:1px solid rgba(27,32,36,.11);
  transition:border-color .18s,color .18s}
.eq-btn-lain:hover{border-color:#DC6E00;color:#F57C00}
.eq-btn-blok{width:100%;margin-top:13px}

/* ── Tombol keputusan ──
   Setujui dan Tolak dulu digambar sebagai tautan teks kecil di antara
   tautan teks kecil lainnya, sederet dengan "Tarik" dan "Hapus". Dua
   akibatnya nyata: tombol yang paling sering dicari tidak terlihat, dan
   tindakan yang tidak dapat dibatalkan berukuran sama dengan tindakan
   biasa — sehingga yang menekannya tidak pernah merasa sedang
   memutuskan apa pun.

   Setujui berbentuk tombol padat, Tolak bergaris, sisanya tetap teks.
   Urutan bobotnya karena itu terbaca dari bentuknya saja, sebelum satu
   kata pun dibaca. */
.eq-btn-setuju,.eq-btn-tolak{display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:8px 16px;border-radius:10px;font-size:12px;font-weight:700;
  transition:filter .18s,transform .18s cubic-bezier(.21,.6,.35,1),background-color .18s}
.eq-btn-setuju{background:#16A34A;color:#fff;box-shadow:0 1px 2px rgba(22,163,74,.28)}
.eq-btn-setuju:hover{filter:brightness(1.07)}
.eq-btn-setuju:active{transform:scale(.97)}
.eq-btn-tolak{background:#fff;color:#D92D20;border:1.5px solid rgba(217,45,32,.35)}
.eq-btn-tolak:hover{background:#FEF3F2;border-color:#D92D20}
.eq-btn-tolak:active{transform:scale(.97)}

/* Tindakan sekunder: bentuknya tetap tombol supaya sasaran tekannya
   cukup besar di layar sentuh, tetapi bobotnya jelas di bawah keduanya. */
.eq-btn-mini{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:9px;
  font-size:11.5px;font-weight:600;color:#5C6874;background:#F5F5F4;
  transition:background-color .18s,color .18s}
.eq-btn-mini:hover{background:#E7E5E4;color:#1B2024}
.eq-btn-mini.bahaya{color:#B42318}
.eq-btn-mini.bahaya:hover{background:#FEF3F2;color:#912018}

/* ── Keadaan kosong ── */
.eq-kosong{text-align:center;padding:34px 20px;color:var(--eq-redup,#7C8894);font-size:12.5px;line-height:1.7}
.eq-kosong strong{color:var(--eq-judul,#0F1720);font-size:13.5px}
.eq-kosong .eq-btn-utama{display:inline-flex;flex:none;margin-top:14px;padding-inline:22px}
.eq-kosong-kecil{padding:24px 12px}
.eq-kosong-kecil .halus{color:var(--eq-redup2,#98A2AE);font-size:11.5px;margin-top:4px}

/* ── Progress mingguan ── */
.eq-pekan-label{display:flex;margin-top:6px;padding-left:52px}
.eq-pekan-label span{flex:1;text-align:center;font-size:10.5px;color:var(--eq-redup2,#98A2AE)}

/* ── Pengumuman ── */
.eq-warta li + li{border-top:1px solid rgba(27,32,36,.07)}
.eq-warta a{display:flex;gap:11px;align-items:flex-start;padding:12px 2px;
  border-radius:10px;transition:background-color .18s}
.eq-warta a:hover{background:#F7F9FA}
.eq-warta-ikon{width:34px;height:34px;flex:none;border-radius:11px;display:grid;place-items:center}
.eq-warta-ikon svg{width:16px;height:16px}
.eq-warta-teks{flex:1;min-width:0}
.eq-warta-teks strong{display:block;font-size:12.5px;font-weight:700;color:var(--eq-judul,#0F1720);line-height:1.4}
.eq-warta-teks small{display:block;font-size:11.5px;color:var(--eq-redup2,#98A2AE);margin-top:2px;line-height:1.5}
.eq-warta time{font-size:10.5px;color:var(--eq-redup2,#98A2AE);flex:none;padding-top:2px}

/* ── Kategori ── */
.eq-kategori{display:grid;gap:11px;grid-template-columns:repeat(auto-fit,minmax(178px,1fr))}
.eq-kategori a,.eq-admin-angka{display:flex;align-items:center;gap:11px;padding:13px 15px;
  border:1px solid rgba(27,32,36,.08);border-radius:14px;background:#fff;
  transition:border-color .2s,transform .2s cubic-bezier(.21,.6,.35,1)}
.eq-kategori a:hover{border-color:rgba(18,137,127,.4);transform:translateY(-2px)}
.eq-kategori-ikon{width:36px;height:36px;flex:none;border-radius:11px;display:grid;place-items:center}
.eq-kategori-ikon svg{width:17px;height:17px}
.eq-kategori strong{display:block;font-size:12.5px;font-weight:700;color:var(--eq-judul,#0F1720)}
.eq-kategori small{display:block;font-size:11px;color:var(--eq-redup2,#98A2AE);margin-top:1px}
.eq-admin-angka{flex-direction:column;align-items:flex-start;gap:2px}
.eq-admin-angka small{font-size:11.5px;color:var(--eq-redup2,#98A2AE)}

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
.eq-modul strong{display:block;font-size:12.5px;font-weight:700;color:var(--eq-judul,#0F1720);margin-top:12px}
.eq-modul small{display:block;font-size:11px;color:var(--eq-redup2,#98A2AE);margin-top:2px;line-height:1.5}

/* ═══════════════════════════════════════════════════════════
   10 · TEMA GELAP DAN WARNA PERUSAHAAN
   ═══════════════════════════════════════════════════════════

   Hanya ada satu salinan aturan gelap. Skrip di <head> selalu
   menyelesaikan tema menjadi nilai yang tegas — pilihan pengguna kalau
   ada, kalau tidak setelan perangkat — sehingga [data-tema="gelap"]
   cukup untuk keduanya. Menuliskannya juga di dalam
   prefers-color-scheme berarti dua salinan daftar warna yang panjang,
   dan dua salinan seperti itu pasti berbeda isinya cepat atau lambat.

   --eq-aksen dan --eq-dasar ditanam pada elemen akar oleh
   App\Support\Tema, diturunkan dari logo perusahaan yang diunggah.
   Nilainya berbeda tiap perusahaan sehingga tidak dapat ditulis di
   berkas gaya. */

.eq-tema-btn .eq-ikon-gelap{display:none}

/* Kontrol asli peramban ikut temanya.
   Meta color-scheme di <head> hanya menyatakan kedua tema didukung; yang
   menentukan bagaimana peramban menggambar select, kotak centang, pemilih
   tanggal, dan bilah gulir adalah properti CSS ini. Tanpa dinyatakan,
   pengguna yang memilih gelap sementara sistemnya terang mendapat
   kontrol berinternal terang — dan teks pada <select> menjadi gelap di
   atas kotak gelap, sehingga pilihannya tampak kosong sama sekali. */
:root[data-tema="gelap"]{color-scheme:dark}
:root[data-tema="terang"]{color-scheme:light}

:root[data-tema="gelap"] body{
  background-color:#0D1417;
  background-image:
    radial-gradient(1100px 520px at 88% -8%, rgba(44,176,188,.10), transparent 62%),
    radial-gradient(760px 420px at -6% 104%, rgba(94,174,56,.06), transparent 60%);
  color:#D6DEE2;
}
:root[data-tema="gelap"] .eq-topbar{background:rgba(16,25,29,.88);border-bottom-color:#1E2C31}
:root[data-tema="gelap"] .eq-judul h1{color:#E8EFF2}
:root[data-tema="gelap"] .eq-judul p{color:#8FA1A8}

/* Permukaan kartu. Ditulis sebagai daftar pemilih, bukan satu kelas
   bersama, karena kartu di aplikasi ini lahir dari beberapa generasi
   penulisan dan belum sempat disatukan. */
/* Latar kartu pada mode gelap.

   Pemilihnya harus menyasar elemen yang benar-benar MEMBAWA latar
   putihnya, bukan wadah di atasnya. `.eq-modul` sempat terdaftar di
   sini padahal latar putihnya ada pada `.eq-modul a`: wadahnya menjadi
   gelap, kartunya tetap putih, sementara --eq-judul sudah berubah
   menjadi #E8EFF2. Hasilnya judul nyaris putih di atas kartu putih —
   terukur 1,16:1, praktis tidak terbaca, dan justru pada enam pintasan
   modul di halaman pertama yang dilihat orang.

   `.eq-admin-angka` bahkan tidak pernah terdaftar sama sekali,
   sekalipun ia berbagi baris deklarasi yang sama dengan
   `.eq-kategori a` yang terdaftar. Keduanya jenis kelalaian yang
   diperingatkan catatan di bawah, dan keduanya terjadi pada daftar
   latar, bukan pada daftar warna teks.

   Yang menjaganya sekarang uji, bukan kewaspadaan: lihat
   ModeGelapLatarTest. */
:root[data-tema="gelap"] main .bg-white,
:root[data-tema="gelap"] .eq-panel,
:root[data-tema="gelap"] .eq-kpi,
:root[data-tema="gelap"] .eq-kursus,
:root[data-tema="gelap"] .eq-modul a,
:root[data-tema="gelap"] .eq-warta,
:root[data-tema="gelap"] .eq-kategori > a,
:root[data-tema="gelap"] .eq-admin-angka,
:root[data-tema="gelap"] .kartu-lux{
  background:#141F23;border-color:#223238;color:#D6DEE2}

/* Warna teks diganti lewat variabel, bukan dengan menulis ulang tiap
   pemilih. Judul dan teks redup muncul di belasan komponen, dan daftar
   pemilih yang disalin akan tertinggal setiap kali ada komponen baru —
   yang tampak sebagai satu-dua tulisan gelap di atas latar gelap,
   persis jenis cacat yang lolos dari pemeriksaan sepintas. */
:root[data-tema="gelap"]{
  --eq-judul:#E8EFF2;
  --eq-redup:#96A8AF;
  --eq-redup2:#8397A0;
}

:root[data-tema="gelap"] main .text-stone-900,
:root[data-tema="gelap"] main .text-stone-800,
:root[data-tema="gelap"] main .text-cam-ink,
:root[data-tema="gelap"] .stat{color:#E8EFF2}

:root[data-tema="gelap"] main .text-stone-600,
:root[data-tema="gelap"] main .text-stone-500,
:root[data-tema="gelap"] main .text-stone-400,
:root[data-tema="gelap"] main .text-stone-300{color:#96A8AF}

/* Tautan aksi hijau lumut milik tema terang terlalu redup di atas
   permukaan gelap; dinaikkan terangnya, bukan diganti warnanya. */
:root[data-tema="gelap"] main .text-cam-lime-deep{color:#9BD24A}
:root[data-tema="gelap"] main .hover\:bg-stone-50:hover{background:#1A272C}

:root[data-tema="gelap"] .eq-bilah{background:#223238}
:root[data-tema="gelap"] .eq-bulat{background:#1A272C;border-color:#26363C;color:#C4D2D7}
:root[data-tema="gelap"] .eq-btn-lain{background:#1A272C;border-color:#26363C;color:#C4D2D7}

/* Tolak berlatar putih pada mode terang; tanpa pasangan ini teksnya
   diterangkan oleh --eq-judul dan berakhir nyaris putih di atas putih.
   Setujui tidak perlu pasangan — latarnya warna padat yang sama di
   kedua mode, dan itulah gunanya ia padat. */
:root[data-tema="gelap"] .eq-btn-tolak{background:#241A1A;border-color:#5A2B27;color:#FF9B92}
:root[data-tema="gelap"] .eq-btn-tolak:hover{background:#2E1F1F;border-color:#7A3B36}
:root[data-tema="gelap"] .eq-btn-mini{background:#1A272C;color:#C4D2D7}
:root[data-tema="gelap"] .eq-btn-mini:hover{background:#22333A;color:#E8F0F2}
:root[data-tema="gelap"] .eq-btn-mini.bahaya{color:#FF9B92}
:root[data-tema="gelap"] .eq-btn-mini.bahaya:hover{background:#2E1F1F}
:root[data-tema="gelap"] input,
:root[data-tema="gelap"] select,
:root[data-tema="gelap"] textarea{background:#101A1E;border-color:#26363C;color:#D6DEE2}

:root[data-tema="gelap"] .eq-tema-btn .eq-ikon-terang{display:none}
:root[data-tema="gelap"] .eq-tema-btn .eq-ikon-gelap{display:block}

/* Tombol dan kepingan yang latarnya putih tetap dari tema terang. */
:root[data-tema="gelap"] .eq-keluar,
:root[data-tema="gelap"] .eq-menu-btn{background:#1A272C;border-color:#26363C;color:#C4D2D7}
:root[data-tema="gelap"] .eq-lonceng-titik{border-color:#101A1E}
:root[data-tema="gelap"] .eq-kosong{background:#101A1E;border-color:#223238}
:root[data-tema="gelap"] .eq-hero-isi p{color:rgba(255,255,255,.78)}

/* Batas antar bagian di dalam kartu. Garis terang di atas permukaan
   gelap terbaca sebagai goresan, bukan sebagai pemisah. */
:root[data-tema="gelap"] .border-stone-100,
:root[data-tema="gelap"] .border-stone-200{border-color:#223238}
:root[data-tema="gelap"] .bg-stone-50{background:#101A1E}

/* ── Warna teks yang memang dirancang untuk latar terang ──
   Tailwind menyusun tangga *-600 dan *-700 untuk dibaca di atas putih.
   Di mode gelap kartunya menjadi gelap tetapi warna teksnya tetap, dan
   hasilnya angka besar yang praktis tidak terbaca — terukur 1,64:1 pada
   text-stone-700 di atas kartu gelap, jauh di bawah ambang mana pun.

   Yang paling merugikan justru angka KPI: ia dicetak besar dan tebal
   supaya terbaca sekilas, lalu menghilang persis pada mode yang dipakai
   orang saat bekerja malam di ruang kendali.

   Dipetakan ke tangga *-300 yang setara terangnya di atas gelap.
   Cakupannya dibatasi pada `main` supaya tidak mengganggu bilah samping
   dan tombol yang latarnya memang selalu berwarna. */
:root[data-tema="gelap"] main .text-stone-700,
:root[data-tema="gelap"] main .text-stone-800,
:root[data-tema="gelap"] main .text-cam-ink{color:#D6DEE2}
:root[data-tema="gelap"] main .text-stone-600{color:#AEBCC2}
:root[data-tema="gelap"] main .text-stone-500{color:#93A3AA}
:root[data-tema="gelap"] main .text-stone-400{color:#7E8E96}

:root[data-tema="gelap"] main .text-sky-700{color:#7DD3FC}
:root[data-tema="gelap"] main .text-violet-700{color:#C4B5FD}
:root[data-tema="gelap"] main .text-emerald-700{color:#6EE7B7}
:root[data-tema="gelap"] main .text-amber-700{color:#FCD34D}
:root[data-tema="gelap"] main .text-orange-700{color:#FDBA74}
:root[data-tema="gelap"] main .text-red-700,
:root[data-tema="gelap"] main .text-red-600{color:#FCA5A5}
:root[data-tema="gelap"] main .text-emerald-600{color:#5EEAD4}
:root[data-tema="gelap"] main .text-cam-orange-dark{color:#FDBA74}
:root[data-tema="gelap"] main .text-sky-600{color:#7DD3FC}
:root[data-tema="gelap"] main .text-sky-800{color:#93D5FD}
:root[data-tema="gelap"] main .text-violet-600{color:#C4B5FD}
:root[data-tema="gelap"] main .text-amber-600{color:#FCD34D}
:root[data-tema="gelap"] main .text-amber-800{color:#FDE08A}
:root[data-tema="gelap"] main .text-emerald-800{color:#8FEFC8}
:root[data-tema="gelap"] main .text-red-800{color:#FDBDBD}

/* ── Bidang bernada terang di mode gelap ──
   Kotak catatan dan kartu peringatan memakai latar bernada sangat muda
   (bg-red-50, bg-amber-50, bg-cam-orange-soft). Latar itu tidak ikut
   digelapkan, sehingga tetap krem di tengah halaman gelap — dan begitu
   warna teks *-700 di atas dipetakan ke tangga terang, isinya berubah
   dari gelap-di-atas-krem menjadi terang-di-atas-krem: terukur 1,24:1,
   lebih buruk daripada sebelum diperbaiki.

   Karena itu latarnya harus ikut digelapkan bersama teksnya. Nadanya
   dipertahankan — merah tetap terbaca merah, kuning tetap kuning —
   sebab warna itulah yang membedakan peringatan dari catatan biasa. */
:root[data-tema="gelap"] main .bg-red-50{background:#2A1618}
:root[data-tema="gelap"] main .bg-amber-50{background:#2A2213}
:root[data-tema="gelap"] main .bg-emerald-50{background:#12251D}
:root[data-tema="gelap"] main .bg-sky-50{background:#122029}
:root[data-tema="gelap"] main .bg-cam-orange-soft,
:root[data-tema="gelap"] main .bg-cam-lime-soft{background:#2A1E12}
:root[data-tema="gelap"] main .border-red-100{border-color:#4A2226}
:root[data-tema="gelap"] main .border-amber-100{border-color:#4A3A18}
:root[data-tema="gelap"] main .border-emerald-100{border-color:#1D4034}
:root[data-tema="gelap"] main .bg-red-50\/60{background:#241416}
:root[data-tema="gelap"] main .bg-amber-50\/60{background:#241E12}
:root[data-tema="gelap"] main .bg-stone-50\/60{background:#18242A}

/* Batang grafik. Warnanya tidak boleh memakai bg-cam-ink: tinta gelap
   di atas halaman gelap adalah batang yang tingginya benar dan tidak
   terlihat sama sekali. */
.eq-batang{background:#0F1720;border-radius:3px 3px 0 0}
:root[data-tema="gelap"] .eq-batang{background:#5FA8D3}

/* Keping status — Draf, Menunggu tinjauan, Disetujui, Ditolak — memakai
   nada -100 yang lebih pekat daripada kotak catatan di atas, dan
   luputnya punya sebab yang layak dicatat: selama sepuluh halaman modul
   merender kosong, tidak ada satu pun keping status yang pernah tergambar
   di layar, sehingga pemindaian kontras terdahulu tidak menemukan apa
   pun untuk diukur. Begitu halaman-halaman itu hidup, keping hijau
   "Disetujui" terukur 1,34:1 dan merah "Melanggar" 1,55:1 — praktis
   tidak terbaca.

   Nadanya tetap dipertahankan: warna keping inilah yang membedakan
   disetujui dari ditolak pada pandangan pertama, dan menyeragamkannya
   menjadi abu berarti membuang satu-satunya isyarat yang terbaca tanpa
   membaca. */
:root[data-tema="gelap"] main .bg-stone-100{background:#1F2B30}
:root[data-tema="gelap"] main .bg-red-100{background:#3B1D21}
:root[data-tema="gelap"] main .bg-emerald-100{background:#153529}
:root[data-tema="gelap"] main .bg-amber-100{background:#3A2D12}
:root[data-tema="gelap"] main .bg-orange-100{background:#3A2412}

/* Tanda wajib isi pada formulir. Terukur 4,46:1 di mode gelap — meleset
   dari ambang oleh selisih yang tak terlihat, tetapi tanda inilah yang
   memberi tahu kolom mana yang tidak boleh kosong. */
:root[data-tema="gelap"] main .text-red-500{color:#F87171}

/* Keping kecil. Latarnya terang dan teksnya abu — di mode gelap ia
   tertinggal sebagai satu-satunya bidang putih di halaman, dan teksnya
   terukur 2,28:1 di atasnya. */
:root[data-tema="gelap"] .eq-chip{background:#1A272C;border-color:#26363C;color:#B6C6CC}

/* Tagline di bawah merek dan teks bantuan pada kaki bilah samping.
   Keduanya alfa rendah di atas navy — 4,0:1, tepat di bawah ambang.
   Dinaikkan secukupnya, tidak lebih: keduanya memang berperan sekunder. */
.eq-merek small{color:rgba(255,255,255,.62)}
.eq-bantuan-teks small{color:rgba(255,255,255,.62)}

/* Warna perusahaan dipakai untuk aksen, bukan untuk seluruh permukaan:
   logo yang kebetulan sangat terang atau sangat pekat akan membuat teks
   di atasnya tidak terbaca kalau dijadikan latar. */
.eq-btn-utama,
.eq-bilah i{background:linear-gradient(135deg,var(--eq-aksen,#F57C00),
                                       color-mix(in srgb,var(--eq-aksen,#F57C00) 78%,#FF9800))}
.eq-kursus-maju b,
.eq-panel-lihat{color:var(--eq-aksen,#F57C00)}
.brand-gradient{background:linear-gradient(165deg,var(--eq-dasar,#0B1117),
                                           color-mix(in srgb,var(--eq-dasar,#0B1117) 62%,#12403E))}

</style>
