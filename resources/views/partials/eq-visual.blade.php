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
/* Varian berfoto. Tanpa `object-fit`, potret yang tidak persegi
   dipipihkan ke dalam lingkaran 40px alih-alih dipotong — dan `.eq-avatar`
   sendiri tidak dapat memakainya, sebab varian berhuruf memakai `grid`
   untuk menengahkan inisialnya. */
.eq-avatar-foto{object-fit:cover}
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

/* ── Kaki bilah samping di laci ponsel ──
 *
 * Di bawah 1024px bilah samping menjadi laci yang dibatasi tinggi layar,
 * dan tiap piksel yang dipakai kaki adalah piksel yang tidak dapat dipakai
 * daftar menunya. Terukur pada layar 727px sebelum ini: kisi modul 341px
 * dan kaki 213px menyisakan 99px untuk seluruh daftar menu — dua butir
 * terlihat, sisanya harus digulir dalam jendela setinggi dua baris.
 *
 * Yang dibuang hanya penjelasannya, bukan jalannya. "Butuh Bantuan? Kami
 * siap membantu Anda kapan saja" adalah kalimat sambutan; tombol "Hubungi
 * Kami" di bawahnya yang benar-benar mengantar orang ke tujuan, dan ia
 * tetap ada. Di desktop keduanya tampil seperti semula — di sana ruangnya
 * memang ada.
 *
 * Tombol lipat juga disembunyikan: ia melipat bilah samping menjadi kolom
 * ikon, keadaan yang hanya ada di layar lebar (lihat blok min-width:1024px
 * di atas). Di laci ponsel menekannya tidak pernah melakukan apa pun yang
 * terlihat.
 *
 * Letaknya SESUDAH seluruh definisi .eq-bantuan dan .eq-sisi-bawah, dan
 * itu bukan kebetulan: kekhususannya sama persis, jadi yang menang adalah
 * yang tertulis belakangan. Ditaruh di atas, aturan ini terpasang rapi dan
 * tidak berpengaruh apa pun.
 */
@media (max-width:1023.98px){
  .eq-sisi-kaki{padding:8px 12px 10px}
  .eq-bantuan{display:none}
  .eq-lipat{display:none}
  .eq-sisi-bawah{justify-content:center}
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
  transition:gap .18s;
  /* Sasaran sentuh: padding tegak memperbesar area ketuk, margin negatif
     mengembalikan tinggi tata letaknya persis seperti semula. */
  padding-block:5px;margin-block:-5px}
.eq-tautan:hover{gap:9px}
.eq-tautan svg{width:14px;height:14px}
.eq-chip{font-size:11.5px;font-weight:600;color:var(--eq-redup,#7C8894);background:#F4F6F8;
  border:1px solid rgba(27,32,36,.07);border-radius:9px;padding:5px 11px}

.eq-kisi-utama{display:grid;gap:16px;grid-template-columns:minmax(0,1fr) minmax(0,340px);align-items:start}
@media (max-width:1180px){.eq-kisi-utama{grid-template-columns:minmax(0,1fr)}}
.eq-kolom-sisi{display:flex;flex-direction:column;gap:16px;min-width:0}

/* ── Kartu kursus ── */
/* auto-fill, BUKAN auto-fit. Keduanya sama selama kartunya banyak;
   bedanya baru terlihat saat kartunya satu — auto-fit meruntuhkan jalur
   yang kosong sehingga kartu tunggal meregang selebar panelnya, dan
   gambar 16/10 selebar 600px menjadi 375px tinggi. Satu kursus lalu
   memakan layar lebih banyak daripada dua puluh satu modul di bawahnya. */
.eq-kursus-kisi{display:grid;gap:15px;grid-template-columns:repeat(auto-fill,minmax(230px,1fr))}
.eq-kursus-kisi .eq-kursus-gambar{aspect-ratio:16/9}
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

/* `flex:1` berarti flex-basis:0 — tombolnya mulai dari lebar NOL lalu
   tumbuh dari sisa ruang. Di baris yang sempit sisa ruangnya bisa lebih
   kecil daripada teksnya sendiri, dan tombolnya menyusut sampai tulisannya
   keluar dari kotaknya. Terukur di layar 393px: tombol "Terapkan" pada
   saringan energi menjadi 47px untuk teks yang butuh 85px.

   `min-width:fit-content` menjadi lantainya. Tombolnya tetap boleh tumbuh
   berbagi ruang seperti sebelumnya — yang hilang hanya kemampuannya
   menyusut sampai lebih kecil daripada tulisannya, dan itu memang tidak
   pernah berguna. */
.eq-btn-utama{display:inline-flex;align-items:center;justify-content:center;gap:7px;flex:1;
  min-width:fit-content;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:700;
  background:linear-gradient(135deg,#F57C00,#DC6E00);color:#fff;
  transition:filter .18s,transform .18s cubic-bezier(.21,.6,.35,1)}
.eq-btn-utama:hover{filter:brightness(1.08)}
.eq-btn-utama:active{transform:scale(.97)}
.eq-btn-utama svg{width:13px;height:13px}
.eq-btn-lain{display:inline-flex;align-items:center;justify-content:center;gap:7px;
  min-width:fit-content;
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

/* ── Penyaring keadaan dan lencana keadaan ──

   Dipakai formulir penilaian audit, tempat 100 butir disaring menurut
   keadaan kesesuaiannya. Yang aktif dibedakan oleh BENTUK — latar
   terangkat dan bergaris — bukan oleh warna saja, sebab titik warna di
   dalamnya sudah dipakai menandai keadaan dan dua makna warna pada satu
   kendali tidak dapat dibedakan lagi.

   Warna titik dan garis lencana datang dari data, bukan dari kelas:
   sumbernya berkas acuan kategori temuan, sehingga tanda di layar dan
   kategori di Formulir Kriteria tidak pernah berbeda. */
.eq-saring{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:999px;
  font-size:11.5px;font-weight:600;color:#5C6874;background:#F4F6F8;
  border:1.5px solid transparent;cursor:pointer;
  transition:background-color .18s,color .18s,border-color .18s}
.eq-saring:hover{background:#E7E5E4;color:#1B2024}
.eq-saring.aktif{background:#fff;border-color:#DC6E00;color:#B45309;box-shadow:0 1px 2px rgba(0,0,0,.07)}
.eq-saring b{font-weight:800}
.eq-saring .titik{width:8px;height:8px;border-radius:999px;display:inline-block;flex:none}
.eq-keadaan{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;
  font-size:10.5px;font-weight:700;line-height:1.5;border:1.5px solid;white-space:nowrap}

/* ── Tangga nilai butir kriteria ──

   Satu tombol per anak tangga, angkanya besar dan namanya di bawahnya.
   Menggantikan daftar tarik-turun: yang dipilih auditor bukan angka
   melainkan tingkat pemenuhan, dan daftar tarik-turun menyembunyikan
   namanya sampai dibuka.

   Yang terpilih dibedakan oleh BENTUK — garis menebal dan kartunya
   terangkat — sebelum warnanya berperan. Warnanya sendiri datang dari
   kategori temuan yang dihasilkan angka itu, dipasang sebaris dari
   data, sehingga memilih nilai berarti sekaligus melihat akibatnya. */
.eq-nilai{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;
  min-width:66px;padding:5px 9px;border-radius:10px;border:1.5px solid #E7E5E4;background:#fff;
  font-size:9.5px;font-weight:600;color:#7C8894;cursor:pointer;
  transition:border-color .16s,background-color .16s,box-shadow .16s,transform .16s}
.eq-nilai b{font-size:13.5px;font-weight:800;line-height:1.25;color:#1B2024}
.eq-nilai:hover{border-color:#C9CFD4;background:#FAFAF9}
.eq-nilai.terpilih{border-width:2.5px;padding:4px 8px;background:#FAFAF9;
  transform:translateY(-1px);box-shadow:0 2px 6px rgba(0,0,0,.10)}
.eq-nilai.terpilih b{color:inherit}

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

/* Penyaring aktif berlatar putih pada mode terang — pasangan gelapnya
   wajib, sebab teksnya sudah ikut diterangkan. Titik warna dan garis
   lencana tidak perlu dipasangkan: keduanya warna padat dari data yang
   sama terbacanya di kedua latar. */
:root[data-tema="gelap"] .eq-saring{background:#1A272C;color:#C4D2D7}
:root[data-tema="gelap"] .eq-saring:hover{background:#22333A;color:#E8F0F2}
:root[data-tema="gelap"] .eq-saring.aktif{background:#22333A;border-color:#DC6E00;color:#FFC078}
:root[data-tema="gelap"] .eq-nilai{background:#131F24;border-color:#26363C;color:#98A8AF}
:root[data-tema="gelap"] .eq-nilai b{color:#E8F0F2}
:root[data-tema="gelap"] .eq-nilai:hover{background:#1A272C;border-color:#33474F}
:root[data-tema="gelap"] .eq-nilai.terpilih{background:#1C2B31}
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
:root[data-tema="gelap"] main .text-green-700{color:#86EFAC}
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
:root[data-tema="gelap"] main .bg-green-50{background:#12251D}
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
.eq-panel-lihat{padding-block:5px;margin-block:-5px}
.brand-gradient{background:linear-gradient(165deg,var(--eq-dasar,#0B1117),
                                           color-mix(in srgb,var(--eq-dasar,#0B1117) 62%,#12403E))}

</style>

<style>
/* ==========================================================================
   TEMA SAFE TRACK — pasir · teal · coral
   --------------------------------------------------------------------------
   Dipakai modul Miners dan Investigasi. Keduanya mengurus dokumen yang
   dibaca DI LUAR kantor: kartu yang dicetak dan dibawa ke gerbang, berkas
   yang diminta Inspektur Tambang. Warna pasirnya membedakan keduanya dari
   modul harian, dan pembedaan itu membuat orang tahu ia sedang berada di
   berkas resmi tanpa membaca judulnya lebih dulu.

   ── MENGAPA MENIMPA UTILITAS, BUKAN MENGGANTI MARKUPNYA ──

   Halaman Miners dan Investigasi memakai puluhan kelas Tailwind langsung
   di markupnya: bg-white, border-stone-100, text-cam-ink, shadow-card.
   Mengganti palet dengan menyunting tiap kelas itu berarti menyentuh
   belasan berkas Vue — dan halaman yang ditambahkan bulan depan akan
   lahir dengan palet lama, tanpa satu pun galat.

   Menimpanya dari SATU tempat membuat seluruh modul berganti sekaligus,
   termasuk halaman yang belum ditulis. Yang menentukan modul mana
   memakainya adalah Menu::all(), bukan berkas ini.

   Kekhususan (specificity) sengaja hanya satu tingkat di atas Tailwind:
   `.tema-safetrack .bg-white` menang atas `.bg-white` tanpa perlu
   !important. Yang ditulis inline dengan style="" tetap menang, dan itu
   benar — warna yang disebut langsung pada satu unsur memang keputusan
   yang lebih khusus daripada tema.
   ========================================================================== */

.tema-safetrack{
  /* Palet diambil apa adanya dari tema-safetrack.css milik paketnya. */
  --st-teal:#0F766E; --st-teal-gelap:#0B5A54; --st-teal-tua:#08302D;
  --st-teal-lembut:#E7F2F0;
  --st-coral:#FF7F50; --st-coral-gelap:#F0663A; --st-coral-lembut:#FFF0E9;
  --st-pasir:#F5E6CA; --st-pasir-muda:#FBF5EA; --st-pasir-lembut:#F6EEDF;
  --st-tinta:#08302D; --st-teks:#33403D; --st-redup:#7D8C89;
  --st-garis:#E4DCCB; --st-garis-tegas:#DCD2BE;

  background:var(--st-pasir-muda);
  color:var(--st-teks);
}

/* Mode gelap: pasirnya diganti, bukan sekadar diredupkan. Pasir yang
   digelapkan menjadi cokelat lumpur; yang dipakai di sini gelap
   bersemu teal, sehingga modulnya tetap terbaca sebagai modul yang
   sama tanpa menyakiti mata. */
:root[data-tema="gelap"] .tema-safetrack{
  background:#0E1A1D;
  color:#C7D5D2;
  --st-garis:#22343A;
  --st-garis-tegas:#2C4148;
  --st-pasir-lembut:#14242A;
  --st-pasir:#1A2C33;
  --st-tinta:#E8EFF2;
  --st-redup:#8FA1A8;
}

/* ---------- permukaan ---------- */
.tema-safetrack .bg-white{ background-color:#fff; }
.tema-safetrack .bg-stone-50\/70,
.tema-safetrack .bg-stone-50\/60,
.tema-safetrack .bg-stone-50{ background-color:var(--st-pasir-lembut) !important; }
.tema-safetrack .bg-stone-100{ background-color:var(--st-pasir); }
.tema-safetrack .hover\:bg-stone-50:hover{ background-color:var(--st-pasir-lembut); }

/* Kartu: sudut lebih rapat dan bayangan lebih tipis daripada tema utama.
   Pasir memantulkan lebih banyak cahaya daripada abu; bayangan yang sama
   tebalnya terbaca kotor di atasnya. */
.tema-safetrack .shadow-card{
  box-shadow:0 1px 3px rgba(8,48,45,.05),0 10px 30px -18px rgba(8,48,45,.20);
}
.tema-safetrack .rounded-2xl{ border-radius:14px; }

/* ---------- garis ---------- */
.tema-safetrack .border-stone-100{ border-color:var(--st-garis); }
.tema-safetrack .border-stone-200{ border-color:var(--st-garis-tegas); }
.tema-safetrack .border-stone-300{ border-color:#CFC3AC; }
.tema-safetrack .divide-stone-100 > * + *{ border-color:var(--st-garis); }
.tema-safetrack .hover\:border-stone-200:hover{ border-color:var(--st-garis-tegas); }

/* ---------- teks ---------- */
.tema-safetrack .text-cam-ink{ color:var(--st-tinta); }
.tema-safetrack .text-stone-400{ color:var(--st-redup); }
.tema-safetrack .text-stone-500{ color:#5A6B68; }
.tema-safetrack .text-stone-600{ color:var(--st-teks); }
.tema-safetrack .text-stone-300{ color:#B3BFBC; }

/* Tautan dan angka penting memakai teal, bukan jingga. */
.tema-safetrack .text-cam-lime-deep,
.tema-safetrack .text-cam-lime{ color:var(--st-teal); }

/* ---------- tombol ---------- */
.tema-safetrack .eq-btn-utama{
  background:linear-gradient(135deg,var(--st-teal),var(--st-teal-gelap));
  border-radius:9px;
}
.tema-safetrack .bg-cam-ink{ background-color:var(--st-teal-tua); }
.tema-safetrack .hover\:bg-stone-700:hover{ background-color:var(--st-teal-gelap); }

/* ---------- medan isian ---------- */
.tema-safetrack input,
.tema-safetrack select,
.tema-safetrack textarea{
  border-color:var(--st-garis-tegas);
  background:#fff;
  border-radius:9px;
}

/* Pasangan mode gelapnya, pada ELEMEN YANG SAMA.
   Tanpa baris ini medannya tetap putih sementara teksnya sudah
   diterangkan — tulisan nyaris putih di atas kotak putih, dan yang
   melihatnya adalah orang yang bekerja malam di ruang kendali, bukan
   orang yang menuliskannya. */
:root[data-tema="gelap"] .tema-safetrack input,
:root[data-tema="gelap"] .tema-safetrack select,
:root[data-tema="gelap"] .tema-safetrack textarea{
  background:#121D22;
  border-color:#294049;
  color:#E8EFF2;
}
.tema-safetrack input:focus,
.tema-safetrack select:focus,
.tema-safetrack textarea:focus{
  border-color:var(--st-teal);
  box-shadow:0 0 0 3px rgba(15,118,110,.13);
}

/* ---------- kepala tabel ---------- */
.tema-safetrack thead tr{ background:var(--st-pasir-lembut); }
.tema-safetrack thead th{ color:var(--st-redup); }
.tema-safetrack tbody tr{ border-color:var(--st-garis); }
.tema-safetrack tbody tr:hover{ background:var(--st-pasir-lembut); }

/* ---------- judul kartu: pola "Judul | keterangan" ----------
   Bilah pemisahnya TIDAK digambar CSS. Percobaan pertama memasangnya
   sebagai ::before dan hasilnya dua bilah berjajar — halamannya memang
   sudah mengetik "|" sendiri di dalam teksnya. Yang tampak di layar
   "Surat pengajuan MCU | | 2 dari 2".

   Dibiarkan diketik, dan yang diatur di sini hanya warnanya, supaya
   bilahnya senada garis kartu alih-alih sepekat teksnya. */
.tema-safetrack h3 > span.font-normal{ color:var(--st-redup); }

/* ═══════════════════════════════════════════════════════════
   KERANGKA BARU — pemindah modul, sapaan, cari, akun di kaki
   ═══════════════════════════════════════════════════════════ */

/* ── Pemindah modul ──
   Menggantikan kisi 22 ikon telanjang. Yang terlihat hanya modul yang
   sedang dibuka; daftarnya muncul saat diminta, dengan labelnya. */
.eq-modul-pilih{width:100%;display:flex;align-items:center;justify-content:space-between;
  gap:10px;padding:9px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);
  background:rgba(255,255,255,.06);color:#fff;cursor:pointer;transition:background .18s}
.eq-modul-pilih:hover{background:rgba(255,255,255,.11)}
.eq-modul-pilih svg{width:15px;height:15px;flex:none;opacity:.7;transition:transform .18s}
.eq-modul-nama{font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;
  color:var(--eq-lime,#B7E44B);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.eq-modul-daftar{margin-top:6px;max-height:280px;overflow-y:auto;
  border-radius:12px;background:rgba(0,0,0,.22);padding:4px}
.eq-modul-butir{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;
  font-size:12px;font-weight:600;color:rgba(255,255,255,.62);position:relative;transition:.15s}
.eq-modul-butir:hover{background:rgba(255,255,255,.08);color:#fff}
.eq-modul-butir svg{width:15px;height:15px;flex:none}
.eq-modul-butir span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.eq-modul-aktif{background:rgba(255,255,255,.13);color:#fff}
.eq-modul-titik{position:absolute;right:8px;width:6px;height:6px;border-radius:50%;
  background:var(--eq-lime,#B7E44B);flex:none}

/* ── Akun di kaki bilah samping ── */
.eq-sisi-akun{display:flex;align-items:center;gap:8px;margin-top:10px;
  padding:8px;border-radius:13px;background:rgba(255,255,255,.06)}
.eq-sisi-akun-tautan{display:flex;align-items:center;gap:9px;min-width:0;flex:1}
.eq-sisi-avatar{width:34px;height:34px;border-radius:11px;flex:none;display:grid;
  place-items:center;font-size:14px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,#F57C00,#FF9F35)}
.eq-sisi-avatar-foto{object-fit:cover}
.eq-sisi-akun-teks{min-width:0;display:flex;flex-direction:column;line-height:1.25}
.eq-sisi-akun-teks strong{font-size:12.5px;color:#fff;font-weight:700;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.eq-sisi-akun-teks small{font-size:10.5px;color:rgba(255,255,255,.5);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.eq-sisi-keluar{width:30px;height:30px;border-radius:9px;flex:none;display:grid;
  place-items:center;color:rgba(255,255,255,.6);background:transparent;
  border:1px solid rgba(255,255,255,.12);cursor:pointer;transition:.15s}
.eq-sisi-keluar:hover{color:#fff;background:rgba(255,255,255,.1)}
.eq-sisi-keluar svg{width:15px;height:15px}

/* ── Sapaan di kepala halaman ──
   Menggantikan judul halaman, yang sudah dicetak tiap halaman di
   badannya sendiri. Keduanya bersebelahan mencetak kalimat yang sama
   dua kali berjarak dua sentimeter. */
.eq-sapa{display:flex;flex-direction:column;line-height:1.2;flex:none}
.eq-sapa small{font-size:11.5px;color:var(--eq-redup,#7C8894)}
.eq-sapa strong{font-size:16px;font-weight:800;color:var(--eq-judul,#0F1720);
  letter-spacing:-.012em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ── Kotak cari ── */
.eq-cari{flex:1;min-width:0;max-width:460px;margin:0 auto;position:relative;display:flex;
  align-items:center}
.eq-cari svg{position:absolute;left:13px;width:16px;height:16px;
  color:var(--eq-redup,#7C8894);pointer-events:none}
.eq-cari input{width:100%;height:40px;padding:0 14px 0 37px;border-radius:12px;
  font-size:12.5px;color:var(--eq-judul,#0F1720);
  border:1px solid var(--eq-garis,#E4E8EC);background:var(--eq-kartu,#fff);
  transition:border-color .15s,box-shadow .15s}
.eq-cari input::placeholder{color:var(--eq-redup,#9AA5B1)}
.eq-cari input:focus{outline:none;border-color:var(--eq-aksen,#F57C00);
  box-shadow:0 0 0 3px rgba(245,124,0,.13)}
@media (max-width:860px){.eq-cari{display:none}}

/* ── Perusahaan yang sedang dilihat ── */
.eq-perusahaan{display:flex;align-items:center;gap:8px;height:40px;padding:0 13px;
  border-radius:12px;font-size:12.5px;font-weight:700;flex:none;
  color:var(--eq-judul,#0F1720);border:1px solid var(--eq-garis,#E4E8EC);
  background:var(--eq-kartu,#fff);max-width:190px}
.eq-perusahaan svg{width:16px;height:16px;flex:none;color:var(--eq-aksen,#F57C00)}
.eq-perusahaan span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media (max-width:640px){.eq-perusahaan span{display:none}}

/* ── Pasangan mode gelap ──
   Ketiganya memakai --eq-kartu dan --eq-garis yang memang sudah
   bertukar nilai pada tema gelap, jadi yang perlu disebut ulang hanya
   yang warnanya ditulis tetap. Tanpa blok ini, kotak carinya tetap
   putih di atas halaman gelap — teksnya terbaca, kotaknya menyilaukan. */
:root[data-tema="gelap"] .eq-cari input,
:root[data-tema="gelap"] .eq-perusahaan{
  background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));
  color:var(--eq-judul,#E8ECF0);
}
:root[data-tema="gelap"] .eq-cari input::placeholder{color:rgba(255,255,255,.38)}
:root[data-tema="gelap"] .eq-sapa strong{color:var(--eq-judul,#E8ECF0)}


/* ═══════════════════════════════════════════════════════════
   HALAMAN MINERS — hero, kartu ringkasan, ubin ber-ikon
   ═══════════════════════════════════════════════════════════ */

.miners-hero{border-radius:20px;overflow:hidden;position:relative;
  background:var(--eq-kartu,#fff);border:1px solid var(--eq-garis,#EFEBE4);
  box-shadow:0 1px 2px rgba(16,24,40,.04)}

/* Aksen jingga miring di kanan, menggantikan foto alat berat pada
   acuan. Foto bitmap harus ikut dikirim, ikut diunduh, dan ikut salah
   potong di tiap lebar layar; gradasi menghasilkan kesan yang sama
   tanpa satu bita pun tambahan. Disembunyikan di layar sempit, tempat
   ia hanya menutupi tombolnya. */
.miners-hero::after{content:"";position:absolute;top:0;right:0;bottom:0;width:38%;
  background:linear-gradient(115deg,transparent 0 38%,rgba(245,124,0,.10) 38% 62%,rgba(245,124,0,.20) 62%);
  pointer-events:none}
@media (max-width:900px){.miners-hero::after{display:none}}

.miners-hero-isi{position:relative;z-index:1;display:flex;flex-wrap:wrap;
  align-items:flex-end;justify-content:space-between;gap:16px;padding:22px 24px 18px}
.miners-hero-isi h2{font-size:23px;font-weight:800;letter-spacing:-.015em;
  color:var(--eq-judul,#0F1720)}
.miners-hero-isi p{font-size:12.5px;color:var(--eq-redup,#7C8894);margin-top:4px}
.miners-hero-aksi{display:flex;gap:8px;flex:none}

.miners-pita{position:relative;z-index:1;display:flex;align-items:flex-start;gap:11px;
  margin:0 24px 22px;padding:13px 15px;border-radius:14px;border:1px solid}
.miners-pita-gawat{background:rgba(245,158,11,.09);border-color:rgba(245,158,11,.32)}
.miners-pita-aman{background:rgba(22,163,74,.09);border-color:rgba(22,163,74,.30)}
.miners-pita-ikon{width:30px;height:30px;border-radius:10px;flex:none;display:grid;place-items:center}
.miners-pita-gawat .miners-pita-ikon{background:rgba(245,158,11,.20);color:#B45309}
.miners-pita-aman .miners-pita-ikon{background:rgba(22,163,74,.18);color:#15803D}
.miners-pita-ikon svg{width:16px;height:16px}
.miners-pita strong{display:block;font-size:13px;font-weight:700;color:var(--eq-judul,#0F1720)}
.miners-pita-aman strong{color:#15803D}
.miners-pita small{display:block;font-size:11.5px;color:var(--eq-redup,#7C8894);margin-top:2px}
.miners-pita-angka{flex:none;font-size:12.5px;font-weight:800;color:#DC2626}

/* ── Kartu ringkasan ── */
.miners-kartu{display:flex;flex-direction:column;border-radius:18px;overflow:hidden;
  background:var(--eq-kartu,#fff);border:1px solid var(--eq-garis,#EFEBE4);
  box-shadow:0 1px 2px rgba(16,24,40,.04)}
.miners-kartu header{padding:16px 18px 0}
.miners-kartu header h3{font-size:14px;font-weight:800;color:var(--eq-judul,#0F1720)}
.miners-kartu header p{font-size:11.5px;color:var(--eq-redup,#7C8894);margin-top:2px}
.miners-kartu-isi{flex:1;padding:16px 18px}

/* Kaki kartu: tautan, bukan hiasan. Tiap kartu ringkasan menjawab
   sebagian pertanyaan; barisnya selalu ada di halaman lain. */
.miners-kaki{display:flex;align-items:center;justify-content:space-between;
  padding:11px 18px;font-size:12px;font-weight:700;color:var(--eq-aksen,#F57C00);
  border-top:1px solid var(--eq-garis,#EFEBE4);transition:background .15s}
.miners-kaki:hover{background:var(--eq-lembut,rgba(245,124,0,.06))}

.miners-donat{width:104px;height:104px;flex:none}
.miners-donat-angka{font-size:8.5px;font-weight:800;text-anchor:middle;
  fill:var(--eq-judul,#0F1720)}
.miners-donat-teks{font-size:3.1px;text-anchor:middle;fill:var(--eq-redup,#7C8894)}
.miners-titik{width:8px;height:8px;border-radius:3px;flex:none}

.miners-bilah{flex:1;height:7px;border-radius:99px;overflow:hidden;
  background:var(--eq-garis,#EFEBE4)}
.miners-bilah i{display:block;height:100%;border-radius:99px;min-width:2px}

.miners-perisai{width:74px;height:74px;margin:0 auto}

/* ── Chip ikon ── */
.miners-chip{width:26px;height:26px;border-radius:9px;flex:none;display:grid;place-items:center}
.miners-chip svg{width:14px;height:14px}
.miners-chip-besar{width:40px;height:40px;border-radius:13px}
.miners-chip-besar svg{width:19px;height:19px}

/* ── Ubin angka ── */
.miners-ubin{display:flex;align-items:center;gap:13px;padding:16px 18px;border-radius:18px;
  background:var(--eq-kartu,#fff);border:1px solid var(--eq-garis,#EFEBE4);
  box-shadow:0 1px 2px rgba(16,24,40,.04);transition:border-color .15s,transform .15s}
.miners-ubin:hover{border-color:var(--eq-aksen,#F57C00);transform:translateY(-1px)}
.miners-ubin-angka{display:block;font-size:24px;font-weight:800;line-height:1}
.miners-ubin-label{display:block;font-size:11.5px;color:var(--eq-redup,#7C8894);margin-top:3px}

/* ── Pasangan mode gelap ──
   Pita dan chip memakai warna tetap ber-alfa, jadi ia sudah menyesuaikan
   diri. Yang perlu disebut ulang hanya yang bertumpu pada putih. */
:root[data-tema="gelap"] .miners-hero,
:root[data-tema="gelap"] .miners-kartu,
:root[data-tema="gelap"] .miners-ubin{
  background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));
}
:root[data-tema="gelap"] .miners-pita strong,
:root[data-tema="gelap"] .miners-hero-isi h2,
:root[data-tema="gelap"] .miners-kartu header h3{color:var(--eq-judul,#E8ECF0)}
:root[data-tema="gelap"] .miners-donat-angka{fill:var(--eq-judul,#E8ECF0)}


/* ═══════════════════════════════════════════════════════════
   PEMBELIAN — isian, tombol jumlah
   ═══════════════════════════════════════════════════════════ */

.beli-isian{width:100%;border-radius:11px;padding:9px 12px;font-size:12.5px;
  color:var(--eq-judul,#0F1720);border:1px solid var(--eq-garis,#E4E8EC);
  background:var(--eq-kartu,#fff);transition:border-color .15s,box-shadow .15s}
.beli-isian::placeholder{color:var(--eq-redup,#9AA5B1)}
.beli-isian:focus{outline:none;border-color:var(--eq-aksen,#F57C00);
  box-shadow:0 0 0 3px rgba(245,124,0,.13)}

/* Tombol tambah/kurang. min-width menjaga agar angkanya tidak menggeser
   tombolnya saat berubah dari 9 ke 10. */
.beli-plusmin{width:26px;height:26px;min-width:26px;border-radius:8px;
  display:grid;place-items:center;font-size:15px;line-height:1;font-weight:700;
  color:var(--eq-judul,#0F1720);border:1px solid var(--eq-garis,#E4E8EC);
  background:var(--eq-kartu,#fff);cursor:pointer;transition:.15s}
.beli-plusmin:hover{border-color:var(--eq-aksen,#F57C00);color:var(--eq-aksen,#F57C00)}

:root[data-tema="gelap"] .beli-isian,
:root[data-tema="gelap"] .beli-plusmin{
  background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));
  color:var(--eq-judul,#E8ECF0);
}
:root[data-tema="gelap"] .beli-isian::placeholder{color:rgba(255,255,255,.38)}

/* Kotak centang daftar harga.

   Dibiarkan bawaan, pada mode gelap ia mewarisi color-scheme:dark dan
   tergambar abu tua di atas kartu abu tua — centangnya ada, tetapi
   nyaris tidak terbaca. Yang dijawabnya pertanyaan "butir ini dijual
   atau tidak", jadi salah baca di sini berarti salah tentang apa yang
   sedang terjual. Kata "Ya"/"Tidak" di sebelahnya memang sudah
   menyebutkannya, tetapi yang dilihat mata lebih dulu kotaknya. */
/* Sakelar "dijual" pada daftar harga. Warna DAN kata, sebab warna
   sendirian tidak terbaca oleh yang tidak membedakan hijau dan abu. */
.harga-sakelar{border-radius:999px;padding:4px 12px;font-size:11px;font-weight:700;
  border:1px solid transparent;cursor:pointer;transition:.15s;min-width:64px}
.harga-sakelar-hidup{background:#DCFCE7;border-color:#86EFAC;color:#166534}
.harga-sakelar-mati{background:#F1F5F9;border-color:#E2E8F0;color:#64748B}
.harga-sakelar:disabled{opacity:.55;cursor:not-allowed}

:root[data-tema="gelap"] .harga-sakelar-hidup{background:#12341F;border-color:#1F6B3A;color:#86EFAC}
:root[data-tema="gelap"] .harga-sakelar-mati{background:#1B242B;border-color:rgba(255,255,255,.12);color:#94A3B8}



/* ═══════════════════════════════════════════════════════════
   ETALASE JUAL — chip saring, bilah keranjang
   ═══════════════════════════════════════════════════════════ */

/* Halaman publik, selalu di atas latar terang: ia memakai kerangka
   kosong seperti halaman depan, di luar <main> yang dipetakan ulang
   mode gelap. Karena itu warnanya ditulis tetap, bukan lewat peubah
   tema — peubah tema di sini akan mengambil nilai yang disiapkan untuk
   kartu gelap dan mencetak chip gelap di tengah halaman terang. */

/* ── Garis kontur hero ──

   Peta topografi adalah gambar yang setiap hari dibaca orang tambang.
   Digambar sebagai satu <svg> inline, bukan berkas: ia mengisi seluruh
   lebar hero tanpa satu permintaan jaringan pun, dan tidak pernah
   tampil sebagai bingkai gambar rusak pada jaringan site. */
.etalase-topo{position:absolute;inset:auto 0 0 0;height:58%;width:100%;
  color:rgba(255,255,255,.22);pointer-events:none}

/* ── Cahaya sudut pada panel kaca ──

   Panel kaca yang rata terbaca sebagai kotak abu; satu sumber cahaya di
   sudutnya memberi kedalaman tanpa menambah satu unsur pun yang harus
   dibaca. */
.etalase-kilau{position:absolute;top:-40%;right:-30%;width:70%;height:120%;
  border-radius:999px;pointer-events:none;
  background:radial-gradient(closest-side,rgba(199,231,68,.20),transparent 72%)}

/* ── Bintik halus pada kartu putih ──

   Dipakai kartu "paket disusun sesuai kebutuhan", satu-satunya kartu
   besar tanpa foto di halaman ini. Rata putih, ia terbaca sebagai
   ruang yang belum diisi. */
.etalase-bintik{position:absolute;inset:0;pointer-events:none;opacity:.55;
  background-image:radial-gradient(rgba(15,23,32,.07) 1px,transparent 1px);
  background-size:18px 18px;
  -webkit-mask-image:radial-gradient(ellipse at 50% 0%,#000,transparent 72%);
  mask-image:radial-gradient(ellipse at 50% 0%,#000,transparent 72%)}

/* ── Aura warna di balik grid aplikasi ──

   Dua bulatan sangat lembut, memecah bidang putih besar yang di
   belakang dua puluh satu kartu terbaca sebagai kertas. */
.etalase-aura{position:absolute;width:38rem;height:38rem;border-radius:999px;
  pointer-events:none;filter:blur(10px)}
.etalase-aura-kiri{top:6rem;left:-14rem;
  background:radial-gradient(closest-side,rgba(245,124,0,.10),transparent 70%)}
.etalase-aura-kanan{bottom:2rem;right:-15rem;
  background:radial-gradient(closest-side,rgba(30,136,229,.10),transparent 70%)}

/* ── Kartu aspek berfoto ──

   Rasio dikunci supaya delapan kartu berfoto berbeda tetap sebaris
   rapi; tanpa itu tingginya mengikuti isi dan barisannya bergerigi. */
.etalase-aspek{position:relative;overflow:hidden;border-radius:1rem;
  aspect-ratio:4/3;min-height:9.5rem;text-align:left;cursor:pointer;
  border:1px solid rgba(255,255,255,.10);background:#0F1720;
  transition:transform .25s cubic-bezier(.21,.6,.35,1),border-color .25s,box-shadow .25s}
.etalase-aspek:hover{transform:translateY(-3px);border-color:rgba(255,255,255,.24);
  box-shadow:0 12px 28px -14px rgba(0,0,0,.75)}
.etalase-aspek-aktif{border-color:#C7E744;box-shadow:0 0 0 2px rgba(199,231,68,.35)}

/* Cap ikon pada kartu aspek yang belum punya foto. Diputar sedikit
   supaya tidak terbaca sebagai ikon kedua yang kebetulan kebesaran. */
.etalase-aspek-cap{color:rgba(255,255,255,.14);transform:rotate(-8deg) translate(12%,10%);
  pointer-events:none}

/* ── Kartu aplikasi ── */
.etalase-kartu{position:relative;overflow:hidden;display:flex;flex-direction:column;
  background:#fff;border:1px solid #F0F0EF;border-radius:1rem;padding:1.5rem;
  box-shadow:0 1px 2px rgba(15,23,32,.05),0 8px 24px -18px rgba(15,23,32,.35);
  transition:transform .25s cubic-bezier(.21,.6,.35,1),box-shadow .25s,border-color .25s}
.etalase-kartu:hover{transform:translateY(-4px);border-color:#E4E4E2;
  box-shadow:0 6px 14px -8px rgba(15,23,32,.14),0 22px 44px -26px rgba(15,23,32,.42)}

/* Cap air: ikon modulnya sendiri, dibesarkan dan diredupkan. Tiap kartu
   jadi berbeda tanpa satu berkas gambar pun ditambahkan. */
.etalase-cap{position:absolute;right:-1.6rem;bottom:-1.8rem;
  width:9.5rem;height:9.5rem;opacity:.07;pointer-events:none;
  transition:opacity .3s,transform .3s cubic-bezier(.21,.6,.35,1)}
.etalase-kartu:hover .etalase-cap{opacity:.12;transform:translate(-.35rem,-.35rem) rotate(-4deg)}

/* ── Empat langkah ── */
.etalase-langkah{position:relative;display:inline-grid;place-items:center;
  width:4rem;height:4rem;border-radius:1.15rem;color:#fff;
  background:linear-gradient(135deg,#F57C00,#DC6E00);
  box-shadow:0 10px 24px -12px rgba(245,124,0,.75)}
.etalase-langkah-angka{position:absolute;top:-.35rem;right:-.35rem;
  width:1.35rem;height:1.35rem;border-radius:999px;display:grid;place-items:center;
  font-size:10px;font-weight:800;color:#0F1720;background:#C7E744;
  border:2px solid #F7F7F5}

/* Rel penghubung antar langkah. Hanya pada lebar yang benar-benar
   menampung empat kolom sejajar: pada dua kolom ia menyambungkan
   langkah 2 ke langkah 3 yang berada di baris berbeda, menggambar
   urutan yang tidak pernah terjadi. */
.etalase-rel{display:none}
@media (min-width:1024px){
  .etalase-rel{display:block;position:absolute;top:2rem;left:12.5%;right:12.5%;height:2px;
    background:linear-gradient(90deg,transparent,#E4E8EC 12%,#E4E8EC 88%,transparent);
    pointer-events:none}
}
.etalase-chip{border-radius:999px;padding:6px 13px;font-size:11.5px;font-weight:700;
  border:1px solid #E4E8EC;background:#fff;color:#54606B;cursor:pointer;
  transition:border-color .15s,background .15s,color .15s}
.etalase-chip:hover{border-color:#C6CFD6}
.etalase-chip-aktif{background:#0F1720;border-color:#0F1720;color:#fff}
.etalase-chip-aktif:hover{border-color:#0F1720}

/* Pasangan mode gelapnya tetap ditulis meski etalase hari ini selalu
   terang. Kartu putih tanpa pasangan adalah cacat yang tidak terlihat
   oleh yang menuliskannya — ia hanya terlihat oleh yang membacanya
   malam hari — dan halaman yang pindah ke kerangka bertema nanti tidak
   akan mengingatkan siapa pun bahwa barisnya belum ada. */
:root[data-tema="gelap"] .etalase-chip{
  background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));
  color:var(--eq-teks,#B7C2CC)}
:root[data-tema="gelap"] .etalase-chip:hover{border-color:rgba(255,255,255,.22)}
:root[data-tema="gelap"] .etalase-chip-aktif{
  background:#E8ECF0;border-color:#E8ECF0;color:#0F1720}
:root[data-tema="gelap"] .etalase-kartu{
  background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10))}

/* Bilah keranjang menyelinap dari bawah, bukan muncul begitu saja:
   sesuatu seukuran itu yang terbit mendadak di tepi layar terbaca
   sebagai iklan yang menutupi halaman, dan yang pertama dicari
   pembacanya adalah tombol menutupnya. */
.etalase-bilah-enter-active,.etalase-bilah-leave-active{transition:transform .22s ease,opacity .22s ease}
.etalase-bilah-enter-from,.etalase-bilah-leave-to{transform:translateY(120%);opacity:0}

@media (prefers-reduced-motion:reduce){
  .etalase-bilah-enter-active,.etalase-bilah-leave-active{transition:none}
  .etalase-bilah-enter-from,.etalase-bilah-leave-to{transform:none;opacity:1}

  /* Kartu yang terangkat dan cap air yang berputar sama-sama gerakan,
     dan permintaan "kurangi gerakan" tidak berhenti pada video. */
  .etalase-kartu,.etalase-aspek,.etalase-cap{transition:none}
  .etalase-kartu:hover,.etalase-aspek:hover{transform:none}
  .etalase-kartu:hover .etalase-cap{transform:none}
}


/* ═══════════════════════════════════════════════════════════
   TOMBOL YANG SEDANG MATI
   ═══════════════════════════════════════════════════════════ */

/* Sampai sekarang tidak ada satu pun aturan untuk :disabled, sehingga
   tombol yang dimatikan tergambar persis seperti tombol yang hidup:
   jingga penuh, berbayang, dan menyusut saat ditekan. Yang menekannya
   tidak mendapat apa pun dan tidak diberi tahu apa-apa — dan tombol
   yang tampak hidup tetapi diam terbaca sebagai aplikasi yang rusak,
   bukan sebagai syarat yang belum terpenuhi.

   Terlihat pada layar daftar harga: dua puluh dua tombol "Simpan"
   jingga penuh, dan hanya satu di antaranya yang benar-benar
   mengerjakan sesuatu.

   :disabled tidak pernah cocok dengan <a> — ia hanya berlaku pada
   unsur formulir — jadi aturan ini tidak dapat menyentuh tautan. */
.eq-btn-utama:disabled,.eq-btn-lain:disabled,.eq-btn-mini:disabled,
.eq-btn-blok:disabled,.eq-btn-setuju:disabled,.eq-btn-tolak:disabled{
  opacity:.42;filter:grayscale(.6);cursor:not-allowed;box-shadow:none}

.eq-btn-utama:disabled:hover,.eq-btn-lain:disabled:hover,.eq-btn-mini:disabled:hover,
.eq-btn-blok:disabled:hover,.eq-btn-setuju:disabled:hover,.eq-btn-tolak:disabled:hover{
  filter:grayscale(.6)}

.eq-btn-utama:disabled:active,.eq-btn-lain:disabled:active,.eq-btn-mini:disabled:active,
.eq-btn-blok:disabled:active,.eq-btn-setuju:disabled:active,.eq-btn-tolak:disabled:active{
  transform:none}


/* ── Bagian harga halaman depan ── */

/* Kontur pada bidang navy di antara dua bagian terang. Tanpa tekstur, ia
   terbaca sebagai jeda kosong, bukan sebagai bagian yang berisi. */
.harga-topo{position:absolute;inset:auto 0 0 0;height:64%;width:100%;
  color:rgba(255,255,255,.07);pointer-events:none}

/* Cahaya sudut pada kartu paket, menandainya sebagai yang dituju tanpa
   menambah satu kata pun. */
.harga-kilau{position:absolute;top:-45%;right:-30%;width:75%;height:130%;
  border-radius:999px;pointer-events:none;
  background:radial-gradient(closest-side,rgba(199,231,68,.16),transparent 72%)}

</style>
