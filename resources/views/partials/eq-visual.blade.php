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
.eq-menu-btn:hover{border-color:#C85804;color:#C85804}
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
.eq-bulat:hover{color:#C85804;border-color:#C85804}
.eq-bulat:active{transform:scale(.94)}
.eq-bulat svg{width:19px;height:19px}
.eq-lonceng-titik{position:absolute;top:-2px;right:-2px;min-width:19px;height:19px;padding:0 5px;
  border-radius:999px;background:#E5484D;color:#fff;font-size:10.5px;font-weight:700;
  line-height:19px;text-align:center;border:2px solid #FBFAF7}

.eq-profil{display:flex;align-items:center;gap:10px;padding-left:2px}
.eq-avatar{width:40px;height:40px;flex:none;border-radius:50%;display:grid;place-items:center;
  background:linear-gradient(135deg,#F36F0F,#FF9800);color:#fff;font-weight:800;font-size:14px}
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
.eq-keluar:hover{color:#C85804;border-color:#C85804;background:rgba(18,137,127,.06)}
.eq-keluar svg{width:17px;height:17px}

.eq-topbar h1{letter-spacing:-.012em}
.eq-topbar::after{
  content:"";position:absolute;left:0;right:0;bottom:-1px;height:1px;
  background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#C85804,#5EAE38,#FF9800);
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
.eq-merek strong em{font-style:normal;color:#F36F0F}
.eq-merek small{font-size:9.5px;color:rgba(255,255,255,.42);margin-top:3px;letter-spacing:.005em}

/* ── Kaki bilah samping ──
 *
 * `1 0 auto`, bukan `1 1 auto`. Kakinya BOLEH MEMUAI mengisi rongga pada
 * modul bermenu pendek, tetapi TIDAK BOLEH MENYUSUT di bawah tinggi
 * isinya sendiri.
 *
 * Dengan flex-shrink 1 ia ikut menanggung kekurangan ruang bersama nav,
 * dibagi menurut besar masing-masing. Tetapi anak-anaknya tidak dapat
 * menyusut: tombol bantuan `flex:none`, kartu semboyan berbatas bawah
 * 132px, baris hak cipta `flex:none`. Yang menyusut hanya kotaknya,
 * bukan isinya — dan #eqSidebar yang `overflow:hidden` memotong
 * selisihnya.
 *
 * Terukur pada 1440x820 sebelum ini: kaki 152px memuat isi setinggi
 * 244px. Kartu semboyan berakhir di 843px dan baris hak cipta beserta
 * tombol lipat terhampar di 843–886px — seluruhnya di luar layar.
 * Yang terlihat pengguna: kartu terpotong di tengah kalimat, dan tombol
 * lipat yang hilang sama sekali.
 *
 * Sekarang seluruh kekurangan ruang jatuh ke nav, satu-satunya anak yang
 * memang dirancang menanggungnya: ia `min-h-0` dan `overflow-y-auto`,
 * jadi menyusut baginya berarti bergulir, bukan terpotong. */
.eq-sisi-kaki{flex:1 0 auto;min-height:0;display:flex;flex-direction:column;
  /* TANPA jarak bawah: kartu semboyan di dalamnya yang turun sampai
     tepi paling bawah bilah. Jarak 14px di sini meninggalkan segaris
     navy di bawah kartu, yang terbaca sebagai kartu yang berhenti
     sebelum ujungnya. */
  padding:10px 12px 0}
.eq-bantuan{display:flex;gap:11px;align-items:flex-start;
  background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);
  border-radius:14px;padding:13px}
.eq-bantuan-ikon{width:34px;height:34px;border-radius:11px;flex:none;
  display:grid;place-items:center;color:#fff;
  background:linear-gradient(135deg,#F36F0F,#FF9800)}
.eq-bantuan-ikon svg{width:17px;height:17px}
.eq-bantuan-teks{min-width:0}
.eq-bantuan-teks strong{display:block;font-size:12.5px;color:#fff;font-weight:700}
.eq-bantuan-teks small{display:block;font-size:11px;color:rgba(255,255,255,.45);
  margin-top:2px;line-height:1.45}
.eq-bantuan-btn{flex:none;display:flex;align-items:center;justify-content:center;gap:8px;
  margin-top:9px;padding:10px 12px;border-radius:12px;
  font-size:12px;font-weight:700;color:#fff;
  background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.11);
  transition:background-color .18s,border-color .18s}
.eq-bantuan-btn:hover{background:rgba(255,255,255,.17);border-color:rgba(255,255,255,.2)}
.eq-bantuan-btn svg{width:15px;height:15px}

/* Baris hak cipta dan tombol lipat, DI DALAM kartu semboyan.
   Jaraknya sendiri yang menjauhkannya dari tepi — kaki bilahnya sudah
   tidak berjarak bawah — dan garis atasnya memisahkannya dari gambar
   tanpa memotong kartunya. */
.eq-sisi-bawah{flex:none;display:flex;align-items:center;justify-content:space-between;gap:10px;
  margin-top:auto;padding:9px 13px 11px;border-top:1px solid rgba(255,255,255,.10);
  background:rgba(8,14,17,.72)}
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
  .eq-sisi-kaki{padding:8px 12px 0}
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
  background:linear-gradient(90deg,#1F6FB8,#2FA3DE,#F08A22,#C85804,#5EAE38,#FF9800)}

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
.t-toska {background:rgba(18,137,127,.13);color:#F36F0F}
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
  padding:12px 22px;border-radius:13px;background:#F36F0F;color:#fff;
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
.eq-tautan{display:inline-flex;align-items:center;gap:6px;font-size:12.5px;font-weight:700;color:#F36F0F;
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
.eq-kursus-maju b{font-size:12.5px;font-weight:800;color:#F36F0F;font-variant-numeric:tabular-nums}
.eq-bilah{height:7px;border-radius:999px;background:#EDF0F2;overflow:hidden;margin-top:6px}
.eq-bilah i{display:block;height:100%;border-radius:999px;
  background:linear-gradient(90deg,#F36F0F,#FF9800);transition:width 1s cubic-bezier(.21,.6,.35,1)}
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
/* TOMBOL SELEBAR ISINYA, bukan selebar barisnya.
 *
 * Aturan ini dulu berbunyi `flex:1`, dimaksudkan untuk baris berisi dua
 * tombol yang membagi lebarnya rata. Tetapi ia berlaku pada SETIAP wadah
 * flex, dan sebagian besar tombol utama tidak duduk di baris semacam itu
 * — ia duduk di samping penyaring, di samping kalimat keterangan, atau
 * sendirian di ujung baris.
 *
 * Terukur dengan menyapu 244 halaman: 57 di antaranya memuat tombol yang
 * memuai jauh melampaui isinya. "Terbitkan" pada Kalender Regu selebar
 * 444px berdampingan dengan "Susun baseline" selebar 129px; "Simpan
 * produksi" pada Input Energi selebar 520px. Keduanya terbaca sebagai
 * bilah, bukan sebagai tombol, dan keduanya membuat baris yang memuatnya
 * kehilangan proporsinya.
 *
 * Baris yang memang hendak membagi rata sekarang menyebutkannya sendiri
 * lewat `.eq-btn-baris` di bawah. Yang menyatakan maksudnya satu tempat;
 * yang diam mendapat perilaku yang benar. */
.eq-btn-utama{display:inline-flex;align-items:center;justify-content:center;gap:7px;
  flex:0 0 auto;min-width:fit-content;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:700;
  background:linear-gradient(135deg,#F36F0F,#C85804);color:#fff;
  transition:filter .18s,transform .18s cubic-bezier(.21,.6,.35,1)}

/* Baris tombol yang SENGAJA dibagi rata — dua tindakan setara pada kaki
   dialog atau kartu, tempat lebar yang sama menandakan bobot yang sama. */
/* `> *`, bukan `> .eq-btn-utama, > .eq-btn-lain`. Di dalam baris ini
   semuanya memang tombol, dan menyebut nama kelasnya sekali lagi
   melahirkan aturan kedua bernama sama — yang membuat penjaga lantai
   lebar tombol membaca aturan yang salah. */
.eq-btn-baris{display:flex;gap:8px;flex-wrap:wrap}
.eq-btn-baris > *{flex:1 1 0%}
.eq-btn-utama:hover{filter:brightness(1.08)}
.eq-btn-utama:active{transform:scale(.97)}
.eq-btn-utama svg{width:13px;height:13px}
.eq-btn-lain{display:inline-flex;align-items:center;justify-content:center;gap:7px;
  min-width:fit-content;
  padding:10px 14px;border-radius:11px;font-size:12.5px;font-weight:600;
  color:#5C6874;background:#fff;border:1px solid rgba(27,32,36,.11);
  transition:border-color .18s,color .18s}
.eq-btn-lain:hover{border-color:#C85804;color:#F36F0F}
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
.eq-saring.aktif{background:#fff;border-color:#C85804;color:#B45309;box-shadow:0 1px 2px rgba(0,0,0,.07)}
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

/* `a` DAN `.eq-warta-buka`. Pengumuman kini dibuka sebagai pop-out,
   jadi barisnya sebuah tombol — tautan yang tidak pernah menuju ke mana
   pun menipu menu klik-kanan, Ctrl+klik, dan pembaca layar sekaligus.
   Baris lain di panel ini tetap tautan sungguhan; keduanya harus tampak
   sama persis, sebab yang membedakannya bukan rupanya melainkan apa
   yang terjadi sesudah ditekan. */
.eq-warta a,
.eq-warta .eq-warta-buka{display:flex;gap:11px;align-items:flex-start;padding:12px 2px;
  border-radius:10px;transition:background-color .18s}
.eq-warta .eq-warta-buka{width:100%;text-align:left;background:none;border:0;
  font:inherit;color:inherit;cursor:pointer}
.eq-warta a:hover,
.eq-warta .eq-warta-buka:hover{background:#F7F9FA}

/* Sorotan mode gelap. Tanpa ini barisnya berkedip PUTIH di atas panel
   gelap tiap kali tetikus melewatinya — cacat yang sudah ada sejak
   panel ini ditulis dan tidak pernah terlihat karena hover memang
   tidak muncul pada tangkapan layar. */
:root[data-tema="gelap"] .eq-warta a:hover,
:root[data-tema="gelap"] .eq-warta .eq-warta-buka:hover{background:rgba(255,255,255,.055)}
.eq-warta-ikon{width:34px;height:34px;flex:none;border-radius:11px;display:grid;place-items:center}
.eq-warta-ikon svg{width:16px;height:16px}

/* Sampul kecil, bila pengumumannya punya. Yang tanpa gambar TIDAK
   mendapat kotak abu-abu pengganti — teksnya melebar menempati
   ruangnya, dan barisnya tidak menjadi lebih pendek. */
.eq-warta-gambar{width:44px;height:44px;flex:none;border-radius:10px;object-fit:cover;
  background:rgba(27,32,36,.06)}
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
.eq-kategori-ikon{width:38px;height:38px;flex:none;border-radius:13px;display:grid;place-items:center}
.eq-kategori-ikon svg{width:18px;height:18px}
.eq-kategori strong{display:block;font-size:12.5px;font-weight:700;color:var(--eq-judul,#0F1720)}
.eq-kategori small{display:block;font-size:11px;color:var(--eq-redup2,#98A2AE);margin-top:1px}
.eq-admin-angka{flex-direction:column;align-items:flex-start;gap:2px}
.eq-admin-angka small{font-size:11.5px;color:var(--eq-redup2,#98A2AE)}

/* ── Pintasan modul ── */
/* ══════════ UBIN IKON BERDIMENSI ══════════

   Satu kelas, dipakai tiap ubin ikon di seluruh aplikasi.

   ── KENAPA VEKTOR, BUKAN GAMBAR 3D ──

   Ikon 3D hasil render terlihat mahal pada satu tangkapan layar dan
   mahal betulan sesudahnya: tiga puluh tiga berkas raster, buram pada
   layar beresolusi tinggi, tidak ikut berubah saat mode gelap menyala,
   dan tebal garisnya tidak pernah persis sama antara satu ikon dan yang
   lain karena masing-masing dirender terpisah. Ia juga menjadi berkas
   yang harus ikut diunduh tiap pemakai di jaringan site tambang.

   Yang dikerjakan di sini caranya sendiri: glyph garis yang sudah ada
   — semuanya digambar pada kanvas 24x24 dengan tebal yang sama —
   ditaruh di atas ubin bergradien dengan cahaya di tepi atas, bayangan
   di tepi bawah, dan bayangan jatuh yang mengambil warna ubinnya. Mata
   membaca susunan itu sebagai benda yang punya tebal, dan hasilnya
   tetap tajam pada ukuran apa pun, ikut mode gelap, serta tidak
   menambah satu berkas pun.

   ── URUTAN BAYANGANNYA MENENTUKAN ──

   Cahaya di ATAS dan gelap di BAWAH. Dibalik, ubinnya terbaca cekung
   seperti lubang, bukan menonjol — ini satu-satunya hal pada blok ini
   yang kalau tertukar langsung terlihat salah tanpa dapat dijelaskan
   penyebabnya oleh yang melihatnya. */
.ikon-3d{
  /* Ujung terang gradiennya: --c-terang bila ubinnya menyebutnya,
     kalau tidak dihitung dari --c.

     Dua peubah dan bukan satu, karena sebuah custom property tidak
     boleh menyebut DIRINYA SENDIRI di dalam var()-nya. Rantai seperti
     itu dianggap tidak sah dan nilainya hilang sama sekali — bukan
     jatuh ke cadangannya — sehingga ubinnya kehilangan gradien tanpa
     satu pun galat yang tercatat. */
  --ikon-hitung:color-mix(in srgb,var(--c,#F36F0F) 62%,#fff);
  --ikon-puncak:var(--c-terang,var(--ikon-hitung));
  background:linear-gradient(145deg,var(--ikon-puncak),var(--c,#F36F0F));
  color:#fff;
  box-shadow:
    0 8px 16px -9px color-mix(in srgb,var(--c,#F36F0F) 85%,transparent),
    inset 0 1px 0 rgba(255,255,255,.35)}

/* ── Kenapa hanya segini, padahal "3D" ──

   Percobaan sebelumnya menumpuk sorot cahaya di sudut kiri atas,
   kilau di separuh atas, pantulan di tepi bawah, dan bayangan pada
   glyph-nya. Masing-masing masuk akal sendiri-sendiri; bersama-sama
   mereka menuang putih ke atas warnanya sampai ubin kuning tampak
   berkabut, dan yang hilang justru warnanya — hal yang paling
   diperhatikan orang.

   Ditaruh berdampingan dengan acuannya, bedanya langsung terlihat:
   acuannya hanya punya gradien, satu garis cahaya setebal satu piksel
   di tepi atas, dan bayangan jatuh yang mengambil warna ubinnya.
   Kedalamannya datang dari gradien itu, bukan dari lapisan di atasnya.

   Jadi tidak ada ::before dan ::after di sini. Bukan karena
   disederhanakan, melainkan karena keduanya membuat hasilnya lebih
   buruk — dan itu baru ketahuan sesudah dibandingkan berdampingan,
   bukan sesudah dilihat sendirian. */

/* Mode gelap: bayangan berwarnanya diredupkan. Yang setebal 85% di
   atas kartu gelap berubah dari bayangan menjadi cahaya neon. */
:root[data-tema="gelap"] .ikon-3d{
  box-shadow:
    0 8px 18px -10px color-mix(in srgb,var(--c,#F36F0F) 62%,transparent),
    inset 0 1px 0 rgba(255,255,255,.22)}

/* CATATAN: jangan menyetel stroke-width pada .ikon-3d svg.

   Sebagian besar glyph-nya diisi, jadi aturan itu tampak tidak
   berakibat apa-apa — kecuali pada satu ikon. Batang beliung di ikon
   Mine Operations digambar dengan GARIS setebal 2.5, dan CSS menang
   atas atribut presentasi pada SVG. Menyetelnya di sini menimpa tebal
   itu diam-diam, dan beliungnya berubah kurus tanpa ada yang tahu
   sebabnya. */

@media (prefers-reduced-motion:reduce){.ikon-3d{transition:none}}

/* ══════════ keadaan tiap modul ══════════
   Satu kartu per MODUL, bukan per butir. Lebih rapat daripada .eq-modul
   karena jumlahnya dua puluh lima dan tugasnya berbeda: bukan menjelaskan
   satu hal, melainkan memberi gambaran seluruhnya dalam satu tarikan
   mata. Warna sisi kirinya memikul seluruh beban penandaan. */
.eq-mdl-kisi{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(232px,1fr))}
/* Satu kolom yang boleh menyempit (minmax 0), isi dirapatkan ke atas.
   Tanpa itu kolom kisi mengikuti lebar judul yang tidak boleh patah,
   dan angka di ujung kanan terdorong keluar kartu ("Perusahaan Jasa
   Pertambangan"); kartu yang ditarik setinggi barisnya pun membagi sisa
   tingginya ke kepala, sehingga kepala kartu sebaris tidak sejajar. */
.eq-mdl{display:grid;grid-template-columns:minmax(0,1fr);align-content:start;
  gap:8px;padding:13px 14px;border-radius:14px;background:#fff;
  border:1px solid rgba(27,32,36,.08);border-left:4px solid var(--c);
  box-shadow:0 1px 2px rgba(27,32,36,.05),0 8px 18px -16px rgba(27,32,36,.4);
  transition:transform .18s cubic-bezier(.21,.6,.35,1),box-shadow .18s,border-color .18s}
.eq-mdl:hover{transform:translateY(-2px);
  box-shadow:0 2px 4px rgba(27,32,36,.06),0 16px 28px -18px rgba(27,32,36,.5)}
.eq-mdl-kepala{display:flex;align-items:center;gap:9px;min-width:0;min-height:38px}
.eq-mdl-ikon{display:grid;place-items:center;width:38px;height:38px;border-radius:13px;
  flex:none}
.eq-mdl-ikon svg{width:21px;height:21px}
/* Nama modul dibaca utuh: boleh turun satu baris, tidak dipotong titik. */
.eq-mdl-nama{flex:1;min-width:0;font-size:12.5px;font-weight:700;line-height:1.25;
  color:var(--eq-judul,#0F1720);overflow-wrap:anywhere;text-wrap:balance}
.eq-mdl-angka{flex:none;font-size:22px;font-weight:800;letter-spacing:-.035em;line-height:1;
  color:var(--c);font-variant-numeric:tabular-nums}
.eq-mdl-butir{display:grid;gap:3px;min-width:0}
.eq-mdl-butir span{font-size:11px;line-height:1.4;color:var(--eq-lemah,#6B7785);
  overflow-wrap:anywhere}
.eq-mdl-butir b{font-weight:700;color:var(--eq-judul,#0F1720)}
.eq-mdl-bersih{font-size:11px;line-height:1.4;color:#16A34A;font-weight:600}
@media (prefers-reduced-motion:reduce){.eq-mdl:hover{transform:none}}

.eq-modul{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(206px,1fr))}
.eq-modul a{display:block;padding:16px;border:1px solid rgba(27,32,36,.08);border-radius:15px;
  background:#fff;transition:border-color .2s,transform .2s cubic-bezier(.21,.6,.35,1),box-shadow .2s}
.eq-modul a:hover{border-color:rgba(18,137,127,.36);transform:translateY(-3px);
  box-shadow:0 16px 30px -22px rgba(27,32,36,.45)}
.eq-modul-atas{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.eq-modul-nilai{font-size:27px;font-weight:800;letter-spacing:-.03em;line-height:1;
  font-variant-numeric:tabular-nums}
.eq-modul-ikon{width:44px;height:44px;flex:none;border-radius:15px;display:grid;place-items:center}
.eq-modul-ikon svg{width:24px;height:24px}
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
:root[data-tema="gelap"] .eq-mdl,
:root[data-tema="gelap"] .eq-warta,
:root[data-tema="gelap"] .eq-kategori > a,
:root[data-tema="gelap"] .eq-admin-angka,
:root[data-tema="gelap"] .kartu-lux{
  background:#141F23;border-color:#223238;color:#D6DEE2}

/* Bilah warna di tepi kiri kartu modul dikembalikan.
   Aturan di atas menyetel border-color untuk KEEMPAT sisinya, jadi
   tepi kiri yang memikul seluruh penandaan nada ikut jadi abu-abu —
   dan dasbor mode gelap kehilangan satu-satunya petunjuk mana modul
   yang gawat dan mana yang bersih, tanpa satu pun tanda bahwa ada
   yang hilang. */
:root[data-tema="gelap"] .eq-mdl{border-left-color:var(--c)}

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
:root[data-tema="gelap"] .eq-saring.aktif{background:#22333A;border-color:#C85804;color:#FFC078}
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
:root[data-tema="gelap"] main .text-amber-900{color:#FCE4A6}
:root[data-tema="gelap"] main .text-emerald-800{color:#8FEFC8}
:root[data-tema="gelap"] main .text-red-800{color:#FDBDBD}

/* Jingga menandai capaian 40–59 pada modul PJP — tepat di antara amber
   (cukup) dan merah (kritis). Nadanya dijaga tetap di antara keduanya di
   sini juga: kalau ia bergeser terlalu dekat ke salah satunya, ketiga
   tingkat itu berhenti dapat dibedakan justru pada mode yang dipakai
   saat bekerja malam. */
:root[data-tema="gelap"] main .text-orange-600{color:#FDBA74}
:root[data-tema="gelap"] main .text-orange-800{color:#FED7AA}

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
.eq-bilah i{background:linear-gradient(135deg,var(--eq-aksen,#F36F0F),
                                       color-mix(in srgb,var(--eq-aksen,#F36F0F) 78%,#FF9800))}
.eq-kursus-maju b,
.eq-panel-lihat{color:var(--eq-aksen,#F36F0F)}
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
/* Nama modul boleh turun baris: "LINGKUNGAN & RE…" dan "PERUSAHAAN
   JASA PER…" tidak menjawab di modul mana orangnya berada. */
.eq-modul-nama{flex:1;min-width:0;text-align:left;font-size:11px;font-weight:800;letter-spacing:.16em;
  line-height:1.35;text-transform:uppercase;color:var(--eq-lime,#B7E44B);overflow-wrap:anywhere}

.eq-modul-daftar{margin-top:6px;max-height:280px;overflow-y:auto;
  border-radius:12px;background:rgba(0,0,0,.22);padding:4px}
.eq-modul-butir{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:9px;
  font-size:12px;font-weight:600;color:rgba(255,255,255,.62);position:relative;transition:.15s}
.eq-modul-butir:hover{background:rgba(255,255,255,.08);color:#fff}
.eq-modul-butir svg{width:15px;height:15px;flex:none}
.eq-modul-butir span{min-width:0;line-height:1.3;overflow-wrap:anywhere}
.eq-modul-aktif{background:rgba(255,255,255,.13);color:#fff}
.eq-modul-titik{position:absolute;right:8px;width:6px;height:6px;border-radius:50%;
  background:var(--eq-lime,#B7E44B);flex:none}

/* ── Akun di kaki bilah samping ── */
.eq-sisi-akun{display:flex;align-items:center;gap:8px;margin-top:10px;
  padding:8px;border-radius:13px;background:rgba(255,255,255,.06)}
.eq-sisi-akun-tautan{display:flex;align-items:center;gap:9px;min-width:0;flex:1}
.eq-sisi-avatar{width:34px;height:34px;border-radius:11px;flex:none;display:grid;
  place-items:center;font-size:14px;font-weight:800;color:#fff;
  background:linear-gradient(135deg,#F36F0F,#FF9F35)}
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
.eq-cari input:focus{outline:none;border-color:var(--eq-aksen,#F36F0F);
  box-shadow:0 0 0 3px rgba(245,124,0,.13)}
@media (max-width:860px){.eq-cari{display:none}}

/* Ponsel sempit: sapaan boleh MENYUSUT, bukan mendorong chip akun keluar
   layar. Di 390 px bilah atas administrator (tema, lonceng, pemilih
   perusahaan, akun) melebar 55 px dan avatar akun terpotong — menu akun
   tidak dapat dijangkau sama sekali, karena halaman tidak dapat digeser
   mendatar. Tanggal disembunyikan lebih dulu; sapaan turun menjadi dua
   baris bila masih kurang — "Selamat pa…" bukan sapaan. */
@media (max-width:480px){
  .eq-topbar{gap:10px;padding-left:12px;padding-right:12px}
  .eq-sapa{flex:0 1 auto;min-width:0}
  .eq-sapa small{display:none}
  .eq-sapa strong{font-size:14px;line-height:1.15;white-space:normal;overflow:visible;overflow-wrap:anywhere}
  .eq-topbar-aksi{gap:6px}
}
@media (max-width:380px){
  .eq-topbar-aksi .eq-bulat{width:36px;height:36px}
}

/* ── Perusahaan yang sedang dilihat ──
   LEBARNYA MENGIKUTI NAMANYA, TIDAK DIPATOK. Batas 190px yang dulu
   ada memotong "PT Tampilan Pertambangan Jaya Persada" menjadi "PT
   Tampilan Pertamb…", dan nama perusahaan adalah hal yang paling
   tidak boleh ditebak-tebak pada aplikasi yang dipakai beberapa
   perusahaan sekaligus. Batas atasnya kini mengikuti lebar layar dan
   baru bekerja ketika bilahnya benar-benar sempit. */
.eq-perusahaan{display:flex;align-items:center;gap:8px;height:40px;padding:0 13px;
  border-radius:12px;font-size:12.5px;font-weight:700;flex:none;
  color:var(--eq-judul,#0F1720);border:1px solid var(--eq-garis,#E4E8EC);
  background:var(--eq-kartu,#fff);max-width:min(340px,32vw)}
.eq-perusahaan svg{width:16px;height:16px;flex:none;color:var(--eq-aksen,#F36F0F)}
.eq-perusahaan span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media (max-width:640px){.eq-perusahaan span{display:none}}

/* ── Bilah pindah sub-halaman (beranda modul) ── */
.eq-pindah{display:flex;gap:8px}

/* SATU BARIS YANG DIGESER — PADA LEBAR MANA PUN, bukan hanya di layar
   sempit.
   Versi pertama aturan ini memakai titik henti 900px, dan itu mengulang
   kesalahan yang sama dengan kisi ubin: yang diukur lebar LAYAR,
   padahal pilnya duduk di kolom isi yang sudah dipotong bilah samping
   selebar 248px. Pada layar 1024px — di atas ambang, jadi membungkus —
   sembilan belas pil menjadi LIMA baris setinggi 240px, mendorong isi
   halaman jauh ke bawah persis seperti sebelumnya.

   Digeser pada tiap lebar, tingginya selalu satu baris berapa pun
   jumlah pil dan berapa pun lebar layarnya. Tepinya diberi bayangan
   yang memudar supaya terlihat masih ada yang di kanan: baris yang
   terpotong rapi di tepi terbaca sebagai baris yang memang berakhir di
   situ. */
.eq-pindah{
  flex-wrap:nowrap;overflow-x:auto;scroll-snap-type:x proximity;
  padding-bottom:4px;margin-inline:-4px;padding-inline:4px;
  -webkit-overflow-scrolling:touch;
  scrollbar-width:none;
  -webkit-mask-image:linear-gradient(90deg,#000 calc(100% - 32px),transparent);
          mask-image:linear-gradient(90deg,#000 calc(100% - 32px),transparent);
}
.eq-pindah::-webkit-scrollbar{display:none}
.eq-pindah-pil{scroll-snap-align:start;flex:none}

.eq-pindah-pil{display:inline-flex;align-items:center;gap:7px;
  padding:8px 14px 8px 11px;border-radius:11px;font-size:12.5px;font-weight:600;
  text-decoration:none;white-space:nowrap;
  color:var(--eq-teks,#44505C);background:var(--eq-kartu,#fff);
  border:1px solid var(--eq-garis,#E4E8EC);
  transition:border-color .15s ease,color .15s ease,background .15s ease}
.eq-pindah-pil svg{width:15px;height:15px;flex:none;opacity:.7}
.eq-pindah-pil:hover{border-color:var(--eq-aksen,#F36F0F);color:var(--eq-judul,#0F1720)}
.eq-pindah-pil:focus-visible{outline:2px solid var(--eq-aksen,#F36F0F);outline-offset:2px}

/* Yang sedang dibuka berlatar aksen PENUH, bukan sekadar bertepi tebal.
   Pada baris berisi belasan pil, tepi yang berbeda tipis tidak pernah
   ditemukan mata — dan pembacanya kehilangan satu-satunya tanda di
   mana ia sedang berdiri. */
.eq-pindah-kini{background:var(--eq-aksen,#F36F0F);border-color:var(--eq-aksen,#F36F0F);
  color:#fff;box-shadow:0 2px 10px -2px rgba(245,124,0,.45)}
.eq-pindah-kini svg{opacity:1}
.eq-pindah-kini:hover{color:#fff}

.eq-pindah-lencana{display:inline-grid;place-items:center;min-width:17px;height:17px;
  padding:0 5px;border-radius:999px;font-size:9.5px;font-weight:800;
  background:#DC2626;color:#fff}
.eq-pindah-kini .eq-pindah-lencana{background:rgba(255,255,255,.28)}

:root[data-tema="gelap"] .eq-pindah-pil{background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));color:var(--eq-teks,#A8B2B8)}
:root[data-tema="gelap"] .eq-pindah-pil:hover{color:var(--eq-judul,#E8ECF0)}
:root[data-tema="gelap"] .eq-pindah-kini{background:var(--eq-aksen,#F36F0F);
  border-color:var(--eq-aksen,#F36F0F);color:#fff}

/* ── Akun di bilah atas ── */
.eq-akun{position:relative;flex:none}

/* Tombolnya membawa nama dan jabatan, bukan lingkarannya saja.
   `max-width` ada supaya nama sepanjang apa pun tidak mendorong kotak
   cari; yang lebih panjang dipotong dengan elipsis di `.eq-akun-nama`. */
.eq-akun-tombol{position:relative;display:flex;align-items:center;gap:9px;
  max-width:250px;padding:4px 8px 4px 4px;border:1px solid transparent;
  background:none;cursor:pointer;border-radius:999px;line-height:0;
  transition:background .15s ease,border-color .15s ease}
.eq-akun-tombol:hover{background:var(--eq-aksen-tipis,rgba(245,124,0,.10));
  border-color:var(--eq-garis,#E6EBF0)}
.eq-akun-buka .eq-akun-tombol{background:var(--eq-aksen-tipis,rgba(245,124,0,.10));
  border-color:var(--eq-garis,#E6EBF0)}
.eq-akun-tombol:focus-visible{outline:2px solid var(--eq-aksen,#F36F0F);outline-offset:3px}

/* Pembungkus avatar. Titik hijaunya berjangkar ke SINI, bukan ke
   tombolnya: sejak tombolnya melebar, `right:0` pada tombol menaruh
   titik itu di ujung kanan chip, jauh dari avatar yang ditandainya. */
.eq-akun-rupa{position:relative;display:block;flex:none;line-height:0}

.eq-akun-nama{display:flex;flex-direction:column;gap:1px;min-width:0;
  line-height:1.25;text-align:left}
.eq-akun-nama strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  font-size:12.5px;font-weight:700;color:var(--eq-judul,#0F1720)}
.eq-akun-nama small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  font-size:10.5px;font-weight:600;letter-spacing:.01em;text-transform:uppercase;
  color:var(--eq-redup,#7C8894)}

.eq-akun-panah{width:15px;height:15px;flex:none;opacity:.5;
  color:var(--eq-redup,#7C8894);transition:transform .18s ease}
.eq-akun-buka .eq-akun-panah{transform:rotate(180deg)}

/* Pada layar sempit chip kembali menjadi lingkaran saja. Jabatan yang
   membungkus dua baris menaikkan tinggi SELURUH bilah atas, dan itu
   terlihat pada tiap halaman sekaligus. */
@media (max-width:1180px){
  .eq-akun-nama,.eq-akun-panah{display:none}
  .eq-akun-tombol{gap:0;max-width:none;padding:0;border-radius:50%;
    background:none;border-color:transparent}
  .eq-akun-tombol:hover,.eq-akun-buka .eq-akun-tombol{background:none;
    border-color:transparent}
}

.eq-akun-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;
  font-size:13px;font-weight:800;letter-spacing:.01em;color:#fff;
  background:linear-gradient(140deg,var(--eq-aksen,#F36F0F),var(--eq-aksen-gelap,#C75F00));
  box-shadow:0 2px 10px -2px rgba(245,124,0,.5)}
.eq-akun-foto{object-fit:cover}
.eq-akun-avatar-besar{width:42px;height:42px;font-size:14px;flex:none}

/* Titik hijau: penanda sesi aktif, bukan hiasan. Diberi cincin sewarna
   bilahnya supaya ia terbaca sebagai lencana di atas avatar dan bukan
   sebagai noda pada fotonya. */
.eq-akun-titik{position:absolute;right:0;bottom:0;width:11px;height:11px;border-radius:50%;
  background:#22C55E;border:2.5px solid var(--eq-kartu,#fff)}

.eq-akun-panel{position:absolute;top:calc(100% + 8px);right:0;z-index:41;min-width:212px;
  padding:6px;border-radius:14px;background:var(--eq-kartu,#fff);
  border:1px solid var(--eq-garis,#E4E8EC);box-shadow:0 18px 44px -12px rgba(15,23,32,.22)}

.eq-akun-kepala{display:flex;align-items:center;gap:10px;padding:8px 9px 11px;
  margin-bottom:5px;border-bottom:1px solid var(--eq-garis,#E4E8EC)}
.eq-akun-kepala strong{display:block;font-size:12.5px;font-weight:700;line-height:1.3;
  color:var(--eq-judul,#0F1720);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.eq-akun-kepala small{display:block;font-size:11px;color:var(--eq-redup,#9AA5B1)}

.eq-akun-butir{display:flex;align-items:center;gap:9px;width:100%;padding:8px 9px;
  border:0;border-radius:9px;background:none;font:inherit;font-size:12.5px;font-weight:600;
  text-align:left;cursor:pointer;text-decoration:none;color:var(--eq-teks,#44505C)}
.eq-akun-butir svg{width:16px;height:16px;flex:none;opacity:.65}
.eq-akun-butir:hover{background:var(--eq-aksen-tipis,rgba(245,124,0,.10));
  color:var(--eq-judul,#0F1720)}
.eq-akun-butir:focus-visible{outline:2px solid var(--eq-aksen,#F36F0F);outline-offset:-2px}
.eq-akun-keluar:hover{background:rgba(220,38,38,.10);color:#B91C1C}

:root[data-tema="gelap"] .eq-akun-nama strong{color:var(--eq-judul,#E8ECF0)}
:root[data-tema="gelap"] .eq-akun-tombol:hover,
:root[data-tema="gelap"] .eq-akun-buka .eq-akun-tombol{border-color:rgba(255,255,255,.12)}
:root[data-tema="gelap"] .eq-akun-panel{background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));
  box-shadow:0 18px 44px -12px rgba(0,0,0,.6)}
:root[data-tema="gelap"] .eq-akun-kepala{border-bottom-color:rgba(255,255,255,.10)}
:root[data-tema="gelap"] .eq-akun-kepala strong{color:var(--eq-judul,#E8ECF0)}
:root[data-tema="gelap"] .eq-akun-butir{color:var(--eq-teks,#A8B2B8)}
:root[data-tema="gelap"] .eq-akun-butir:hover{color:var(--eq-judul,#E8ECF0)}
:root[data-tema="gelap"] .eq-akun-keluar:hover{background:rgba(220,38,38,.16);color:#F5A9A9}
:root[data-tema="gelap"] .eq-akun-titik{border-color:var(--eq-kartu,#141A21)}

/* Jalur redup di belakang batang grafik.
   Abu-abu terang #F5F5F4 tepat di atas kartu putih; di atas kartu
   gelap ia menjadi balok TERANG yang lebih menonjol daripada batangnya
   sendiri. Paling kentara pada grafik yang nilainya nol: enam baris
   tanpa data menggambar enam balok terang berderet. */
:root[data-tema="gelap"] .grafik-jalur{background:rgba(255,255,255,.07)}

/* ── Kartu semboyan di kaki bilah samping ──
   DISEMBUNYIKAN LEBIH DAHULU DARIPADA APA PUN saat ruangnya menyempit:
   ia hiasan, dan hiasan yang mendorong butir menu keluar dari layar
   membuat butir itu tidak pernah ditemukan siapa pun. */
/* Kartunya: membulat HANYA di atas, dan tanpa jarak bawah, supaya ia
   menyatu dengan tepi paling bawah bilah alih-alih melayang di atasnya.
   Ia yang memuai mengisi sisa ruang kaki — batas atas 260px yang dulu
   ada di semboyan sengaja dilepas, sebab rongga navy yang tersisa di
   bawah kartu itulah yang hendak dihilangkan. */
.eq-sisi-kartu{position:relative;flex:1 1 auto;min-height:0;margin-top:12px;
  display:flex;flex-direction:column;justify-content:flex-end;
  border-radius:14px 14px 0 0;overflow:hidden;background:#0A1114}

/* Kartu tanpa gambar adalah kotak gelap tanpa isi: saat semboyannya
   disembunyikan, latar dan sudutnya ikut dilepas sehingga yang tersisa
   hanya baris hak cipta seperti sebelum kartu ini ada. */
body.eq-sempit .eq-sisi-kartu{margin-top:6px;border-radius:0;background:none}
body.eq-sempit .eq-sisi-bawah{background:none}

.eq-semboyan{position:relative;overflow:hidden;
  flex:1 1 auto;min-height:132px;
  display:flex;flex-direction:column;justify-content:flex-end;
  padding:13px 14px 14px;isolation:isolate;background:#0A1114}
.eq-semboyan-gambar{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  object-position:62% 30%;z-index:-2}
.eq-semboyan-tirai{position:absolute;inset:0;z-index:-1;
  background:linear-gradient(0deg,rgba(8,14,17,.93) 18%,rgba(8,14,17,.55) 58%,rgba(8,14,17,.2) 100%)}
.eq-semboyan-teks{margin:0;font-size:12.5px;font-weight:800;line-height:1.34;color:#fff;
  letter-spacing:-.01em;text-shadow:0 1px 10px rgba(0,0,0,.5)}
.eq-semboyan-garis{display:block;width:52px;height:3px;margin-top:9px;border-radius:999px;
  background:var(--eq-aksen,#F36F0F)}

/* Bilah yang dilipat tidak punya lebar untuk teksnya sama sekali. */
body.eq-sempit .eq-semboyan{display:none}

/* MENGECIL LEBIH DAHULU, BARU HILANG.
   Ambang 760px yang dipakai semula terlalu galak: peramban di tablet
   dan di layar laptop pendek menyisakan tinggi CSS di bawah itu, dan
   kartunya lenyap justru pada perangkat yang paling banyak dipakai di
   lapangan — meninggalkan sudut kiri bawah kosong, yang persis
   keadaan yang hendak diperbaikinya. */
@media (max-height:820px){
  .eq-semboyan{min-height:104px;padding:11px 12px 12px}
  .eq-semboyan-teks{font-size:11.5px;line-height:1.3}
}
@media (max-height:660px){
  .eq-semboyan{min-height:82px}
  .eq-semboyan-teks{font-size:11px}
  .eq-semboyan-garis{margin-top:7px;width:42px}
}

/* Di bawah ini menunya sendiri yang kehabisan ruang, dan hiasan tidak
   boleh mendorong satu pun butir menu keluar dari layar. */
@media (max-height:540px){
  .eq-semboyan{display:none}
  .eq-sisi-kartu{margin-top:6px;border-radius:0;background:none}
  .eq-sisi-bawah{background:none}
}

/* ── Batang gulir bilah samping ──
   Bawaan peramban menggambar batang abu-abu terang selebar 15px di
   atas kolom gelap — terbaca sebagai garis yang tidak disengaja,
   persis di sebelah butir menu. */
#eqSidebar nav{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}
#eqSidebar nav::-webkit-scrollbar{width:6px}
#eqSidebar nav::-webkit-scrollbar-track{background:transparent}
#eqSidebar nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.16);border-radius:999px}
#eqSidebar nav::-webkit-scrollbar-thumb:hover{background:rgba(255,255,255,.28)}

/* ── Pemilih perusahaan (administrator) ── */
.eq-perusahaan-pilih{position:relative;flex:none}

.eq-perusahaan-tombol{cursor:pointer;font-family:inherit;text-align:left;
  transition:border-color .15s ease,box-shadow .15s ease}
.eq-perusahaan-tombol:hover{border-color:var(--eq-aksen,#F36F0F)}
.eq-perusahaan-tombol:focus-visible{outline:none;border-color:var(--eq-aksen,#F36F0F);
  box-shadow:0 0 0 3px rgba(245,124,0,.13)}

.eq-perusahaan-panah{width:14px;height:14px;margin-left:auto;opacity:.5;
  color:var(--eq-judul,#0F1720);transition:transform .18s ease}
.eq-perusahaan-buka .eq-perusahaan-panah{transform:rotate(180deg)}

/* Tirai penutup: menangkap klik di mana pun supaya daftarnya tertutup.
   Tanpa ini, daftar yang terbuka hanya dapat ditutup dengan menekan
   tombolnya lagi — dan yang menekan di luar mengira aplikasinya
   membeku. */
.eq-perusahaan-tirai{position:fixed;inset:0;z-index:40}

.eq-perusahaan-daftar{position:absolute;top:calc(100% + 6px);right:0;z-index:41;
  min-width:100%;max-width:min(340px,80vw);max-height:min(380px,60vh);overflow-y:auto;
  margin:0;padding:5px;list-style:none;border-radius:13px;
  background:var(--eq-kartu,#fff);border:1px solid var(--eq-garis,#E4E8EC);
  box-shadow:0 18px 44px -12px rgba(15,23,32,.22)}

.eq-perusahaan-butir{display:block;width:100%;padding:8px 11px;border:0;border-radius:9px;
  background:none;font:inherit;font-size:12.5px;font-weight:600;text-align:left;cursor:pointer;
  color:var(--eq-teks,#44505C);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.eq-perusahaan-butir:hover{background:var(--eq-aksen-tipis,rgba(245,124,0,.10));
  color:var(--eq-judul,#0F1720)}
.eq-perusahaan-butir:focus-visible{outline:2px solid var(--eq-aksen,#F36F0F);outline-offset:-2px}

.eq-perusahaan-kini{background:var(--eq-aksen-tipis,rgba(245,124,0,.13));
  color:var(--eq-aksen,#F36F0F)}

:root[data-tema="gelap"] .eq-perusahaan-daftar{
  background:var(--eq-kartu,#141A21);
  border-color:var(--eq-garis,rgba(255,255,255,.10));
  box-shadow:0 18px 44px -12px rgba(0,0,0,.6)}
:root[data-tema="gelap"] .eq-perusahaan-butir{color:var(--eq-teks,#A8B2B8)}
:root[data-tema="gelap"] .eq-perusahaan-butir:hover{color:var(--eq-judul,#E8ECF0)}
:root[data-tema="gelap"] .eq-perusahaan-panah{color:var(--eq-judul,#E8ECF0)}

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
  padding:11px 18px;font-size:12px;font-weight:700;color:var(--eq-aksen,#F36F0F);
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
.miners-ubin:hover{border-color:var(--eq-aksen,#F36F0F);transform:translateY(-1px)}
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
.beli-isian:focus{outline:none;border-color:var(--eq-aksen,#F36F0F);
  box-shadow:0 0 0 3px rgba(245,124,0,.13)}

/* Tombol tambah/kurang. min-width menjaga agar angkanya tidak menggeser
   tombolnya saat berubah dari 9 ke 10. */
.beli-plusmin{width:26px;height:26px;min-width:26px;border-radius:8px;
  display:grid;place-items:center;font-size:15px;line-height:1;font-weight:700;
  color:var(--eq-judul,#0F1720);border:1px solid var(--eq-garis,#E4E8EC);
  background:var(--eq-kartu,#fff);cursor:pointer;transition:.15s}
.beli-plusmin:hover{border-color:var(--eq-aksen,#F36F0F);color:var(--eq-aksen,#F36F0F)}

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




/* ═══════════════════════════════════════════════════════════
   ETALASE JUAL
   ═══════════════════════════════════════════════════════════

   Halaman publik, selalu di atas latar terang: ia memakai kerangka
   kosong seperti halaman depan, di luar <main> yang dipetakan ulang mode
   gelap. Warnanya karena itu ditulis tetap, bukan lewat peubah tema —
   peubah tema di sini akan mengambil nilai yang disiapkan untuk kartu
   gelap dan mencetak kartu gelap di tengah halaman terang.

   ── HURUFNYA YANG SUDAH ADA, BUKAN YANG DIUNDUH ──

   Judulnya memakai Inter tebal, bukan grotesk lain yang harus diambil
   dari Google Fonts. Repo ini menyajikan hurufnya sendiri dan sudah
   sekali melepas pustaka dari CDN; menambah satu keluarga huruf dari
   jaringan berarti judul yang belum tergambar pada sambungan site
   tambang — persis pada kalimat yang harus terbaca lebih dulu.

   Label kecilnya memakai monospace bawaan sistem, juga tanpa unduhan.
   Yang membuatnya bekerja bukan huruf tertentu melainkan bentuknya:
   huruf besar, tebal sedang, dan jarak antarhuruf yang lapang.

   ── SATU LENGKUNG WAKTU UNTUK SELURUH HALAMAN ──

   cubic-bezier(.22,1,.36,1): melambat di ujung, tidak pernah melampaui
   tujuannya. Angka yang sama dipakai v-singkap; gerakan masuk dan
   gerakan sentuh harus terasa berasal dari satu tangan.
   ═══════════════════════════════════════════════════════════ */



/* Bagian harga di halaman depan MEMINJAM kosakata ini.

   Peubahnya hidup pada `.jual`; sebuah bagian di halaman lain yang
   memakai .jual-kartu tanpa induk itu akan mewarisi peubah yang tidak
   pernah ditetapkan, dan yang tergambar kartu tanpa warna sama sekali.
   `.jual-lugas` memberi peubah yang sama tanpa membawa latar halamannya
   — halaman depan sudah punya latarnya sendiri. */
/* ── SATU DAFTAR TOKEN UNTUK KEDUA PEMBUNGKUS ──

   Blok ini sebelumnya ditulis DUA KALI: sekali pada .jual (dipakai
   /katalog) dan sekali lagi pada .jual-lugas (dipakai halaman depan),
   dengan isi yang identik byte-per-byte.

   Dan itulah persis jenis duplikasi yang tidak pernah terasa salah
   sampai tiba-tiba salah. Menaikkan --j-samar agar terbaca hanya
   mengenai satu blok, sehingga /katalog mendapat abu yang baru dan
   halaman depan tetap memakai yang lama — dua warna berbeda untuk hal
   yang sama, pada dua halaman yang saling bertautan satu klik, tanpa
   satu pun galat. Ketahuan hanya karena kontrasnya diukur, bukan
   dilihat.

   Satu selektor untuk keduanya menutup kemungkinan itu. */
.jual,
.jual-lugas{
  /* Krem hangat, bukan abu dingin.
     #F7F8F6 berlatar biru-abu, dan foto tambang — tanah merah, debu,
     besi kuning — duduk di atasnya seperti ditempel pada kertas
     laboratorium. Krem ini senada dengan warna material yang ada di
     tiap fotonya, sehingga halamannya terbaca sebagai satu benda. */
  --j-dasar:#F4F3EC;
  --j-kartu:#FFFFFF;
  --j-tinta:#1C1817;
  --j-redup:#6B635E;
  /* Dinaikkan dari #958D87. Warna ini dipakai sebagai TULISAN — kepala
     tabel, keterangan kartu aspek, kode elemen, "Berlaku 1 tahun",
     baris sumber — dan pada krem ia hanya 2,93:1, pada putih 3,26:1.
     Keduanya di bawah 4,5:1 yang dituntut teks berukuran biasa, dan
     yang paling dirugikan justru pembaca di layar lapangan yang silau.
     Sekarang 4,56:1 pada krem dan 5,08:1 pada putih. */
  --j-samar:#756D66;
  --j-garis:#E5E1D8;
  --j-garis-tebal:#D6D1C5;
  --j-aksen:#F36F0F;
  /* Jingga merek #F36F0F dipakai pada dua pekerjaan yang berbeda
     tuntutannya. Sebagai LATAR tombol ia sempurna. Sebagai TULISAN
     kecil — label monospace berjarak .2em, pita "Paling lengkap",
     satuan angka — ia hanya 2,66:1 pada krem: terbaca sebagai jingga,
     tidak terbaca sebagai huruf.
     Jadi tulisannya memakai jingga yang lebih dalam. Masih jingga yang
     sama keluarganya, tetapi 5,21:1 pada krem dan 5,80:1 pada putih. */
  --j-aksen-teks:#A8490A;
  --j-aksen-lembut:#FDEDE0;
  --j-hijau:#12BE15;
  --j-hijau-lembut:#E4FBE4;
  --j-hijau-tua:#095F0A;
  --j-gelap:#1C1817;
  --j-lengkung:cubic-bezier(.22,1,.36,1);
  font-feature-settings:"kern" 1,"liga" 1,"cv11" 1;
  background:var(--j-dasar);color:var(--j-tinta);
}


.jual-lugas-gelap{background:var(--j-gelap);color:#fff}

/* Gulir halus untuk tautan jangkar bilah atas.
   Lompatan seketika dari Harga ke Fitur menghapus rasa bahwa keduanya
   berada pada satu halaman yang sama; yang terlihat adalah dua layar
   berbeda yang saling menggantikan. Dengan gulir, arah dan jaraknya
   ikut terbaca.

   `scroll-padding-top` menyetarai bilah atas yang melayang — tanpa
   itu, judul bagian yang dituju berhenti persis DI BALIK bilahnya. */
html:has(.jual-lugas){scroll-behavior:smooth;scroll-padding-top:5.5rem}

/* Diminta berhenti, ia berhenti. Gerakan gulir yang tidak diminta
   memicu mual pada sebagian orang, dan halaman jual adalah tempat
   mereka tidak punya pilihan menghindarinya. */
@media (prefers-reduced-motion:reduce){
  html:has(.jual-lugas){scroll-behavior:auto}
}

.jual-lebar{max-width:78rem;margin-inline:auto;padding-inline:1.25rem}
@media (min-width:768px){.jual-lebar{padding-inline:2.5rem}}

/* ── tipografi ── */

/* Label monospace berhuruf besar dengan jarak .2em. Satu-satunya unsur
   yang bukan Inter, dan itulah gunanya: ia menandai awal tiap bagian
   tanpa perlu garis, kotak, maupun ikon. */
.jual-mata{font-family:ui-monospace,SFMono-Regular,"SF Mono",Menlo,Consolas,monospace;
  font-size:12px;font-weight:600;letter-spacing:.2em;line-height:1.4;
  text-transform:uppercase;color:var(--j-samar)}
.jual-mata-aksen{color:var(--j-aksen-teks)}
.jual-mata-terang{color:rgba(255,255,255,.5)}

/* Ukurannya ditahan supaya tetap dua baris pada lebar meja kerja.

   Dibiarkan naik, baris kedua pecah dan menyisakan satu kata sendirian di
   baris ketiga — janda, yang pada judul setebal ini terbaca sebagai
   kalimat terpotong, bukan sebagai baris baru. Diukur, bukan dikira. */
/* ── WARNANYA DIWARISI, TIDAK DIPAKU ──
   Semula `color:var(--j-tinta)`, dan itu benar selama satu-satunya
   pemakainya halaman depan yang berlatar krem. Etalase memakai kelas
   yang sama di atas .jual-lugas-gelap — dan di sana tinta gelap
   digambar tepat di atas latar gelap yang sama persis: rasio 1:1, judul
   utama halaman jual lenyap sepenuhnya.

   Tidak ada galat, tidak ada peringatan; tulisannya ADA di DOM, dapat
   dipilih, dibaca pembaca layar, dan terindeks mesin pencari. Yang
   tidak bisa melihatnya hanya orang.

   `inherit` membuatnya mengambil warna dari pembungkusnya — tinta pada
   .jual-lugas, putih pada .jual-lugas-gelap — sehingga bug yang sama
   tidak dapat terjadi lagi pada pembungkus mana pun yang dibuat nanti. */
.jual-judul{font-size:clamp(2.35rem,4.3vw,3.5rem);font-weight:800;line-height:1.06;
  letter-spacing:-.03em;color:inherit}
/* Sama alasannya dengan .jual-judul di atas, dan sama kerasnya
   akibatnya: rgba(28,24,23,.42) yang dipaku membuat separuh judul
   etalase hilang di atas hero gelap. Diturunkan dari currentColor, ia
   selalu setengah-pudar TERHADAP warna yang berlaku di sekitarnya —
   abu pada halaman krem, putih redup pada hero gelap — tanpa satu pun
   selektor tambahan yang harus diingat untuk pembungkus berikutnya. */
.jual-judul-tipis{color:color-mix(in srgb,currentColor 45%,transparent)}

.jual-h2{font-size:clamp(2rem,4.2vw,3.4rem);font-weight:800;line-height:1.08;
  letter-spacing:-.028em;margin-top:1.25rem}
.jual-h2-terang{color:#fff}

.jual-h3{font-size:1.35rem;font-weight:700;line-height:1.25;letter-spacing:-.016em}
.jual-h4{font-size:1.05rem;font-weight:700;line-height:1.35;letter-spacing:-.01em}

.jual-tubuh{font-size:16px;line-height:1.6;color:var(--j-redup)}
.jual-tubuh-besar{font-size:19px;line-height:1.55;color:var(--j-redup)}
.jual-tubuh-kecil{font-size:14px;line-height:1.55;color:var(--j-redup)}
.jual-tubuh-terang{color:rgba(255,255,255,.62)}

/* Angka harga. tabular-nums menjaga digitnya selebar sama, sehingga
   Rp 9.500.000 dan Rp 13.500.000 tidak saling menggeser. */
.jual-angka{font-size:clamp(2rem,3.6vw,2.75rem);font-weight:700;line-height:1;
  letter-spacing:-.035em;font-variant-numeric:tabular-nums}
/* Menempel pada angkanya, jadi warnanya harus ikut angkanya. Abu yang
   dipaku terbaca cukup di atas kartu putih, tetapi di hero gelap
   etalase ia jatuh ke 3,47:1 — "Rp" yang nyaris lenyap tepat di
   sebelah harga yang justru ingin ditonjolkan. */
.jual-angka-kecil{font-size:.44em;font-weight:600;letter-spacing:-.01em;
  margin-right:.3em;vertical-align:.5em;
  color:color-mix(in srgb,currentColor 62%,transparent)}

/* ── tombol ── */

.jual-tombol{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
  padding:.8rem 1.4rem;border-radius:8px;
  font-size:14px;font-weight:600;letter-spacing:-.008em;
  background:var(--j-gelap);color:#fff;border:1px solid var(--j-gelap);
  transition:background .3s var(--j-lengkung),border-color .3s var(--j-lengkung),
  color .3s var(--j-lengkung),transform .3s var(--j-lengkung),box-shadow .3s var(--j-lengkung)}
.jual-tombol:hover{background:var(--j-aksen);border-color:var(--j-aksen);
  box-shadow:0 8px 20px -10px rgba(245,124,0,.65)}
.jual-tombol:active{transform:translateY(1px)}
.jual-tombol:disabled{opacity:.34;cursor:not-allowed;background:var(--j-gelap);
  border-color:var(--j-gelap);transform:none;box-shadow:none}

/* Tombol utama JINGGA, bukan hitam.
   Hitam pada palet ini disediakan untuk satu tempat saja — tombol di
   bilah navigasi. Dipakai juga oleh tombol ajakan di badan halaman, ia
   berhenti menandai apa pun: yang paling gelap di layar seharusnya
   cuma satu, dan yang satu itu sudah dipakai. */
/* Panel ajakan penutup: kartu PUTIH di atas krem, bukan blok hitam.
   Blok hitam dulu dipakai untuk memisahkannya dari halaman; pada palet
   ini hitam sudah dipesan untuk tombol navigasi, dan dipakai dua kali
   ia berhenti menandai keduanya. Putih di atas krem memisahkan dengan
   cara yang sama tanpa meminjam warna yang sudah ada tugasnya. */
/* ══════════ hero bervideo ══════════ */

/* Tirai condong ke KIRI, bukan rata gelap.
   Rata gelap membuat videonya hanya jadi tekstur; condong, sisi kanan
   tetap memperlihatkan tambangnya sementara sisi kiri cukup pekat
   untuk menahan tulisan putih di atasnya. */
.jual-hero{background:var(--j-gelap);color:#fff}
.jual-hero .jual-hero-tirai{
  background:linear-gradient(100deg,
    rgba(20,17,16,.95) 0%, rgba(20,17,16,.88) 38%,
    rgba(20,17,16,.55) 68%, rgba(20,17,16,.35) 100%);
}
.jual-judul-hero{color:#fff}
.jual-judul-tipis-hero{color:rgba(255,255,255,.55)}
.jual-hero-teks{color:rgba(255,255,255,.72)}
.jual-hero .jual-mata-aksen{color:#FFAE5E}

/* ── pita tiga bagian di kaki hero ──
   Ketiganya menjawab pertanyaan yang berbeda: seberapa banyak isinya,
   seperti apa layarnya, dan harus mulai dari mana. Dipisah garis tipis
   dari judul di atasnya supaya terbaca sebagai lapis kedua, bukan
   sebagai ekor paragraf. */
.jual-pita-hero{
  display:grid;gap:1.5rem;align-items:center;
  padding-top:2rem;border-top:1px solid rgba(255,255,255,.14);
}
@media (min-width:900px){
  .jual-pita-hero{grid-template-columns:auto minmax(0,1fr) auto;gap:2.5rem}
}

/* Keping fitur: kartu putih berbentuk pil dengan foto di ujungnya —
   bentuk yang dipakai acuannya, dan yang membuat pita ini punya satu
   benda terang sebagai jangkar mata. */
.jual-keping{
  display:flex;align-items:center;gap:1rem;
  max-width:26rem;padding:.55rem .55rem .55rem 1.15rem;
  border-radius:99px;background:var(--j-kartu);
  text-decoration:none;color:var(--j-tinta);
  box-shadow:0 18px 40px -22px rgba(0,0,0,.75);
  transition:transform .18s var(--j-lengkung),box-shadow .18s var(--j-lengkung);
}
.jual-keping:hover{transform:translateY(-2px);box-shadow:0 22px 46px -22px rgba(0,0,0,.85)}
.jual-keping-teks{min-width:0}
.jual-keping-teks b{display:block;font-size:13px;font-weight:700;line-height:1.3}
.jual-keping-teks small{display:block;font-size:11.5px;line-height:1.45;
  color:var(--j-redup);margin-top:.12rem}
.jual-keping-foto{flex:none;width:3.4rem;height:3.4rem;border-radius:99px;overflow:hidden}
.jual-keping-foto img{width:100%;height:100%;object-fit:cover;display:block}

/* Ajakan ketiga: teks kecil dengan satu tombol bulat jingga. */
.jual-mulai{display:flex;align-items:center;gap:1.1rem}
.jual-mulai b{display:block;font-size:13px;font-weight:700;color:#fff}
.jual-mulai small{display:block;font-size:11.5px;line-height:1.45;
  color:rgba(255,255,255,.58);margin-top:.12rem;max-width:15rem}
.jual-bulat-panah{
  display:grid;place-items:center;flex:none;
  width:3.1rem;height:3.1rem;border-radius:99px;
  background:linear-gradient(135deg,#C85804,var(--j-aksen));color:#fff;
  box-shadow:0 10px 26px -8px rgba(243,111,15,.9);
  transition:filter .16s,transform .16s;
}
.jual-bulat-panah svg{width:1.15rem;height:1.15rem}
.jual-bulat-panah:hover{filter:brightness(1.07);transform:translateY(-1px)}

/* Di hero, tumpukan fotonya bertepi gelap dan berketerangan terang. */
.jual-hero .jual-tumpuk-foto img{border-color:rgba(20,17,16,.9)}
.jual-hero .jual-tumpuk b{color:#fff}
.jual-hero .jual-tumpuk b+span{color:rgba(255,255,255,.58)}

.jual-ajakan{background:var(--j-kartu);color:var(--j-tinta);border:1px solid var(--j-garis);
  box-shadow:0 30px 70px -40px rgba(28,24,23,.4)}

.jual-tombol-aksen{background:var(--j-aksen);color:#fff;border-color:var(--j-aksen);
  box-shadow:0 10px 24px -10px rgba(243,111,15,.85)}
.jual-tombol-aksen:hover{filter:brightness(1.06);background:var(--j-aksen);
  border-color:var(--j-aksen);box-shadow:0 12px 28px -10px rgba(243,111,15,.95)}

/* Panel video hero. Pada halaman krem, video tidak lagi terbentang
   sebagai latar — latar terang di belakang tulisan gelap tidak
   menyisakan beda cukup untuk dibaca. Ia dibingkai, dan bingkainya
   membuatnya terbaca sebagai tayangan produk, bukan sebagai tempelan
   di belakang teks. */
.jual-tayang{position:relative;border-radius:26px;overflow:hidden;
  background:var(--j-gelap);aspect-ratio:4/3;
  box-shadow:0 30px 70px -30px rgba(28,24,23,.55)}
.jual-tayang video,.jual-tayang img{position:absolute;inset:0;
  width:100%;height:100%;object-fit:cover}
.jual-tayang::after{content:"";position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(180deg,transparent 55%,rgba(28,24,23,.45) 100%)}

.jual-tombol-lain{background:transparent;color:var(--j-tinta);border-color:var(--j-garis-tebal)}
.jual-tombol-lain:hover{background:var(--j-tinta);border-color:var(--j-tinta);color:#fff;
  box-shadow:none}

.jual-tombol-terang{background:#fff;color:var(--j-gelap);border-color:#fff}
.jual-tombol-terang:hover{background:var(--j-aksen);border-color:var(--j-aksen);color:#fff}
.jual-tombol-terang:disabled{background:rgba(255,255,255,.35);border-color:transparent}

.jual-tombol-garis{background:transparent;color:#fff;border-color:rgba(255,255,255,.3)}
.jual-tombol-garis:hover{background:#fff;border-color:#fff;color:var(--j-gelap);box-shadow:none}

.jual-tombol-kecil{padding:.58rem 1rem;font-size:13px}

/* Garis bawah yang menyapu, bukan yang menyala serentak. background-size
   tidak memicu tata letak dihitung ulang, jadi sapuannya tetap halus
   meski ada dua puluh satu tautan di layar. */
.jual-tautan{display:inline-block;font-size:14px;font-weight:600;color:var(--j-tinta);
  padding-bottom:2px;min-height:24px;
  background-image:linear-gradient(currentColor,currentColor);
  /* content-box, supaya garis bawahnya tetap menempel pada hurufnya
     ketika kotak sentuhnya ditinggikan — tanpa ini garisnya ikut turun
     dan tampak melayang di bawah kata. */
  background-origin:content-box;
  background-repeat:no-repeat;background-position:0 100%;background-size:100% 1.5px;
  transition:background-size .4s var(--j-lengkung),color .3s var(--j-lengkung)}
.jual-tautan:hover{color:var(--j-aksen);background-size:0% 1.5px;background-position:100% 100%}
.jual-tautan-terang{color:#fff}
.jual-tautan-terang:hover{color:var(--j-aksen)}

/* ── kepala ── */

/* ── TANPA backdrop-filter, DAN ITU DISENGAJA ──
 *
 * Kepala ini melekat (position:fixed) dan sebelumnya mengaburkan apa pun
 * di belakangnya. backdrop-filter memaksa peramban membentuk "backdrop
 * root": tiap bingkai, seluruh yang tergambar di belakangnya dipotret
 * ulang lebih dulu. Selama halaman digulir itu berarti seluruh halaman —
 * dan pada penggambar tablet, potret yang gagal diperbarui muncul sebagai
 * kotak putih. Bukan di kepalanya, melainkan di tengah isi halaman,
 * persis seperti yang terlihat pada bagian paket.
 *
 * Yang lebih buruk lagi, backdrop-filter itu ikut DIANIMASIKAN pada
 * daftar transition di bawah — properti termahal yang ada, dijalankan
 * empat ratus milidetik tiap kali kepala berganti keadaan.
 *
 * Diganti latar pekat. Pada halaman berlatar terang hasilnya nyaris tak
 * terbedakan: yang hilang hanya kaburnya isi yang lewat di belakang,
 * yang memang tidak pernah terbaca. Bukti bahwa harganya murah: aturan
 * lama bahkan tidak menulis -webkit-backdrop-filter, sehingga Safari dan
 * iOS SUDAH menampilkannya tanpa kabur sejak awal — dan tidak seorang pun
 * menyebutnya rusak.
 */
.jual-kepala{position:fixed;inset:0 0 auto 0;z-index:40;height:4.5rem;color:#fff;
  border-bottom:1px solid transparent;
  transition:background .4s var(--j-lengkung),border-color .4s var(--j-lengkung),
  color .4s var(--j-lengkung)}
/* Bilah atas kini SELALU terang — halaman di bawahnya krem sejak
   baris pertama. Yang berubah saat digulir hanya bayangannya: tanpa
   itu, bilah dan halaman menyatu jadi satu bidang dan tepi bawahnya
   hilang tepat ketika isi mulai lewat di belakangnya. */
.jual-kepala-berbayang{box-shadow:0 1px 0 var(--j-garis),0 10px 30px -18px rgba(28,24,23,.35)}

.jual-kepala-turun{background:var(--j-dasar);color:var(--j-tinta);
  border-bottom-color:var(--j-garis)}

/* ── SASARAN SENTUH, BUKAN SEKADAR TULISAN ──
   Setinggi 21px, tautan ini di bawah 24×24px yang dituntut WCAG 2.5.8
   (AA). Bukan soal aturan: sebelas tautan sekecil itu di bilah atas dan
   kaki halaman adalah sebelas kali seseorang meleset — dan yang
   membukanya dari ponsel di lapangan sering memakai sarung tangan.

   Tautan di DALAM kalimat dikecualikan aturan ini karena tingginya
   ditentukan baris di sekitarnya; yang di sini berdiri sendiri, jadi
   tidak dikecualikan. */
.jual-nav{display:inline-flex;align-items:center;min-height:24px;
  font-size:14px;font-weight:500;color:currentColor;opacity:.66;
  transition:opacity .28s var(--j-lengkung)}
.jual-nav:hover{opacity:1}

/* ── hero ── */

.jual-hero{position:relative;overflow:hidden;background:var(--j-gelap)}
.jual-hero-media{position:absolute;inset:0;width:100%;height:100%;
  object-fit:cover;object-position:center 42%;opacity:.3}
.jual-hero-tirai{position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(102deg,#1C1817F7 0%,#1C1817E0 46%,#1C181799 100%)}

/* ── tiruan dasbor untuk hero halaman depan ──

   Potongan produk yang sesungguhnya, bukan gambar hiasan. Halaman jual
   yang memperlihatkan barangnya lebih meyakinkan daripada halaman jual
   yang memperlihatkan ikon tentang barangnya — dan sebelum ini,
   satu-satunya gambar produk di seluruh situs justru ada di /katalog,
   bukan di halaman depan yang dilihat lebih dulu.

   Angkanya contoh, dan memang terbaca sebagai contoh: tanpa nama
   perusahaan, tanpa klaim, hanya bentuk layarnya. */
.jual-dasbor{background:var(--j-kartu);color:var(--j-tinta);border-radius:22px;overflow:hidden;
  box-shadow:0 34px 68px -34px rgba(0,0,0,.6),0 2px 6px rgba(0,0,0,.16);
  width:min(23rem,100%);color:var(--j-tinta)}
.jual-dasbor-kepala{display:flex;align-items:center;justify-content:space-between;gap:1rem;
  padding:.95rem 1.15rem;border-bottom:1px solid var(--j-garis)}
.jual-dasbor-judul{font-size:13px;font-weight:700;letter-spacing:-.01em}
.jual-dasbor-masa{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  font-size:11px;color:var(--j-samar)}

.jual-dasbor-angka{display:flex;align-items:baseline;justify-content:space-between;gap:.75rem;
  padding:.7rem 1.15rem}
.jual-dasbor-angka+.jual-dasbor-angka{border-top:1px solid var(--j-garis)}
.jual-dasbor-angka>span:first-child{font-size:12.5px;color:var(--j-redup)}
.jual-dasbor-nilai{display:inline-flex;align-items:baseline;gap:.5rem}
.jual-dasbor-nilai b{font-size:16px;font-weight:700;letter-spacing:-.02em;
  font-variant-numeric:tabular-nums}
.jual-delta{font-size:10.5px;font-weight:700;border-radius:40px;padding:.1rem .4rem;
  font-variant-numeric:tabular-nums}
.jual-delta-naik{background:var(--j-hijau-lembut);color:var(--j-hijau-tua)}
.jual-delta-turun{background:var(--j-aksen-lembut);color:var(--j-aksen)}

/* Bagan batang dua belas bulan. Digambar dengan flex, bukan pustaka
   grafik: ia tidak pernah dibaca datanya, hanya bentuknya — dan menarik
   Chart.js ke halaman depan demi dua belas batang adalah 200 kB yang
   diunduh sebelum kalimat pertama terbaca. */
.jual-dasbor-bagan{display:flex;align-items:flex-end;gap:4px;height:3.25rem;
  padding:0 1.15rem;margin-top:.35rem}
.jual-dasbor-bagan i{flex:1;border-radius:2px 2px 0 0;background:var(--j-garis-tebal)}
.jual-dasbor-bagan i:nth-last-child(-n+3){background:var(--j-aksen)}
.jual-dasbor-kaki{display:flex;align-items:center;justify-content:space-between;
  padding:.65rem 1.15rem 1rem;font-size:11px;color:var(--j-samar)}

/* ── kartu tiruan tagihan ──

   Potongan produk yang sesungguhnya, bukan gambar hiasan. Ia sekaligus
   memperlihatkan apa yang akan diterima pembeli sesudah menekan tombol:
   nomor tagihan, barisnya, totalnya, dan cara membayarnya. Halaman jual
   yang memperlihatkan barangnya lebih meyakinkan daripada halaman jual
   yang memperlihatkan ikon tentang barangnya. */
/* ── LATAR TERANG MEMBAWA TINTANYA SENDIRI ──
   .jual-lugas-gelap memasang color:#fff untuk seluruh isinya. Kartu ini
   memasang latar PUTIH di dalamnya, lalu membiarkan warna tulisannya
   diwarisi — jadi seluruh isi tagihan contoh tergambar putih di atas
   putih: nomor invoice, kedua baris aplikasi beserta harganya, total,
   dan "Bayar dengan QRIS". Yang tampak hanya kartu kosong berlencana
   "Lunas", dan yang membacanya menyimpulkan tagihannya gagal dimuat.

   Aturannya sederhana dan berlaku untuk semuanya di bawah: kelas yang
   memasang latar terang wajib memasang tintanya juga. */
.jual-mock{background:var(--j-kartu);color:var(--j-tinta);border-radius:16px;padding:1.25rem;
  box-shadow:0 30px 60px -30px rgba(0,0,0,.55),0 2px 6px rgba(0,0,0,.14);
  width:min(23rem,100%)}
.jual-mock-kepala{display:flex;align-items:center;justify-content:space-between;gap:1rem;
  padding-bottom:.9rem;border-bottom:1px solid var(--j-garis)}
.jual-mock-nomor{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  font-size:12px;font-weight:600;letter-spacing:.04em}
.jual-mock-lencana{display:inline-flex;align-items:center;gap:.4rem;border-radius:40px;
  padding:.25rem .6rem;font-size:11px;font-weight:600;
  background:var(--j-hijau-lembut);color:var(--j-hijau-tua)}
.jual-mock-lencana::before{content:"";width:5px;height:5px;border-radius:999px;
  background:var(--j-hijau)}
.jual-mock-baris{display:flex;align-items:baseline;justify-content:space-between;gap:1rem;
  padding:.6rem 0;font-size:13px}
.jual-mock-baris+.jual-mock-baris{border-top:1px solid var(--j-garis)}
.jual-mock-baris span:last-child{font-variant-numeric:tabular-nums;font-weight:600}
.jual-mock-total{display:flex;align-items:center;justify-content:space-between;gap:1rem;
  margin-top:.35rem;padding-top:.85rem;border-top:1px solid var(--j-garis-tebal)}
.jual-mock-total b{font-size:1.15rem;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.jual-mock-bayar{display:flex;align-items:center;gap:.75rem;margin-top:1rem;
  background:var(--j-dasar);border-radius:12px;padding:.75rem}
/* Ikon garis, BUKAN kisi titik yang menyerupai kode sungguhan.

   Kisi titik terbaca dua cara, dan keduanya buruk: sebagai gambar yang
   gagal dimuat, atau sebagai kode yang dapat dipindai. Yang kedua lebih
   buruk — orang benar-benar akan mengarahkan kameranya ke sana, dan yang
   didapatnya tidak ada. Ikon tidak pernah disalahpahami begitu. */
.jual-mock-qr{width:2.5rem;height:2.5rem;border-radius:8px;flex:none;
  display:grid;place-items:center;background:#fff;color:var(--j-tinta);
  border:1px solid var(--j-garis-tebal)}
.jual-mock-qr svg{width:1.35rem;height:1.35rem}

/* ── bagian ── */

.jual-bagian{padding-block:5rem}
@media (min-width:768px){.jual-bagian{padding-block:7rem}}
.jual-bagian-gelap{background:var(--j-gelap);color:#fff}
.jual-bagian-putih{background:var(--j-kartu)}

.jual-kepala-bagian{max-width:46rem;margin-bottom:3rem}
@media (min-width:768px){.jual-kepala-bagian{margin-bottom:4rem}}

/* ── warna aspek, dipakai dengan takaran ──

   Tiap pilar punya warnanya sendiri, dan itu berguna: pada dua puluh satu
   kartu, warna adalah satu-satunya pembeda yang terbaca sebelum
   membaca. Yang membuatnya murahan bukan warnanya melainkan takarannya —
   medali bergradien penuh pada tiap kartu membuat halaman terlihat
   seperti papan ikon, bukan seperti daftar produk.

   Di sini warnanya dipakai encer: latar tipis dengan goresan pekat di
   atasnya. Terbaca jelas, tetap tenang, dan tetap cukup gelap untuk
   dibaca — kebalikannya, teks berwarna pilar di atas putih, jatuh di
   bawah ambang keterbacaan untuk beberapa pilar.

   color-mix menghitungnya dari satu nilai, sehingga menambah pilar baru
   tidak menuntut satu baris CSS pun ditulis. */
.jual-tanda{width:2.55rem;height:2.55rem;border-radius:14px;flex:none;
  display:grid;place-items:center;
  transition:background .3s var(--j-lengkung)}
.jual-kartu:hover .jual-tanda:not(.ikon-3d),.jual-modul:hover .jual-tanda:not(.ikon-3d){
  background:color-mix(in srgb,var(--c,#F36F0F) 20%,#fff)}
.jual-tanda svg{width:1.3rem;height:1.3rem}

.jual-label{display:inline-flex;align-items:center;gap:.35rem;border-radius:40px;
  padding:.22rem .6rem;font-size:11px;font-weight:600;letter-spacing:.01em;
  background:color-mix(in srgb,var(--c,#F36F0F) 12%,#fff);
  /* 80% warna pilar meninggalkan label kuning (Energy, #FF9800) pada
     1,94:1 — terbaca sebagai noda warna, bukan sebagai nama modul.
     Lebih banyak tinta membuat tiap pilar tetap dikenali warnanya
     sambil hurufnya benar-benar terbaca. */
  color:color-mix(in srgb,var(--c,#F36F0F) 45%,#1C1817)}

/* Goresan warna di tepi atas kartu modul. Dua piksel penuh, bukan
   gradien: gradien pada garis setipis ini hanya terbaca sebagai warna
   yang kotor. */
.jual-modul{position:relative;overflow:hidden;display:flex;flex-direction:column;
  background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:12px;
  padding:1.5rem;
  transition:border-color .3s var(--j-lengkung),box-shadow .3s var(--j-lengkung),
  transform .3s var(--j-lengkung)}
.jual-modul::before{content:"";position:absolute;inset:0 0 auto 0;height:2px;
  background:var(--c,#D6D1C5)}
.jual-modul:hover{border-color:var(--j-garis-tebal);transform:translateY(-2px);
  box-shadow:0 14px 32px -22px rgba(18,22,26,.4)}
.jual-modul-mati{opacity:.72}
.jual-modul-mati::before{background:var(--j-garis-tebal)}

/* Lencana keadaan. Hijau untuk yang sudah dapat dipakai, kelabu untuk
   yang belum — dan kata di dalamnya, bukan warna sendirian. */
.jual-status{display:inline-flex;align-items:center;gap:.3rem;border-radius:40px;
  padding:.16rem .5rem;font-size:10px;font-weight:700;letter-spacing:.04em;
  text-transform:uppercase}
.jual-status-hidup{background:var(--j-hijau-lembut);color:var(--j-hijau-tua)}
.jual-status-hidup::before{content:"";width:4px;height:4px;border-radius:999px;
  background:var(--j-hijau)}
.jual-status-nanti{background:var(--j-dasar);color:var(--j-samar)}

/* ── pita galeri selebar layar ──

   Foto lapangan sebelumnya diapit kolom setengah lebar di sebelah
   kerangka SMKP, sehingga tingginya tinggal 128px dan yang tergambar
   hanya potongan helm. Foto yang dipakai sebagai bukti bahwa aplikasinya
   dipakai di tambang sungguhan tidak berguna bila terlalu kecil untuk
   memperlihatkan tambangnya.

   Digulir mendatar pada layar sempit, bukan diperkecil lagi. */
.jual-pita-galeri{display:grid;gap:1rem;grid-auto-flow:column;
  grid-auto-columns:minmax(17rem,1fr);overflow-x:auto;
  padding-bottom:.5rem;scroll-snap-type:x mandatory;
  scrollbar-width:thin}
@media (min-width:1024px){
  .jual-pita-galeri{grid-auto-flow:row;grid-template-columns:repeat(3,1fr);
    overflow:visible;padding-bottom:0}
}
/* Kartu setinggi barisnya, dengan tombol didorong ke kaki.

   Tanpa ini, tombol "Putar video" duduk tepat di bawah keterangannya —
   dan keterangan yang panjangnya berbeda membuat enam tombol berhenti
   pada enam ketinggian berbeda. Deretan yang tepinya tidak sejajar
   terbaca sebagai halaman yang tidak dirapikan, dan mata ikut mencari
   pola yang tidak ada alih-alih membaca fotonya. */
.jual-pita-galeri>article{scroll-snap-align:start;display:flex;flex-direction:column}
.jual-pita-galeri>article>div{display:flex;flex-direction:column;flex:1}
.jual-pita-galeri>article .jual-bilah-aksi{margin-top:auto;align-self:flex-start}

.jual-galeri-bingkai{position:relative;display:block;overflow:hidden;
  border-radius:12px;aspect-ratio:4/3;background:#1B2126}
.jual-galeri-foto{position:absolute;inset:0 0 auto 0;width:100%;height:124%;
  object-fit:cover;object-position:center 34%;transform-origin:50% 0;
  transition:transform 1s var(--j-lengkung)}
.jual-pita-galeri>article:hover .jual-galeri-foto{transform:scale(1.045)}

/* ── empat langkah dengan rel penghubung ── */
.jual-alur{position:relative;display:grid;gap:2rem 1.5rem;grid-template-columns:1fr}
@media (min-width:640px){.jual-alur{grid-template-columns:repeat(2,1fr)}}
@media (min-width:1024px){.jual-alur{grid-template-columns:repeat(4,1fr)}}
.jual-alur-butir{position:relative}

/* Hanya pada lebar yang benar-benar menampung empat kolom sejajar. Pada
   dua kolom rel ini menyambungkan langkah 2 ke langkah 3 yang berada di
   baris berbeda — menggambar urutan yang tidak pernah terjadi. */
.jual-alur-rel{display:none}
@media (min-width:1024px){
  .jual-alur-rel{display:block;position:absolute;top:1.15rem;left:12.5%;right:12.5%;height:1px;
    background:var(--j-garis-tebal);pointer-events:none}
}

/* ── di atas latar gelap ── */

.jual-gelap-kartu{background:rgba(255,255,255,.045);border:1px solid rgba(255,255,255,.09);
  border-radius:12px;padding:1.4rem;
  transition:border-color .3s var(--j-lengkung),background .3s var(--j-lengkung),
  transform .3s var(--j-lengkung)}
.jual-gelap-kartu:hover{border-color:rgba(255,255,255,.2);background:rgba(255,255,255,.075);
  transform:translateY(-2px)}
.jual-gelap-kartu-aktif{border-color:var(--j-aksen);background:rgba(245,124,0,.09)}

.jual-tanda-gelap{width:2.4rem;height:2.4rem;border-radius:10px;flex:none;
  display:grid;place-items:center;
  background:color-mix(in srgb,var(--c,#F36F0F) 26%,transparent);
  color:color-mix(in srgb,var(--c,#F36F0F) 45%,#fff)}
.jual-tanda-gelap svg{width:1.25rem;height:1.25rem}

/* Angka hero: dipisah garis rambut, bukan dikotakkan satu per satu.
   Empat ubin berbingkai di bawah judul adalah bentuk yang paling sering
   dipakai halaman bangkitan mesin. */
/* Sekat dan lekukannya dihitung per BARIS, bukan per unsur.

   `:not(:first-child)` benar pada satu baris dan salah pada dua: butir
   ketiga membuka baris kedua tetapi tetap mendapat lekukan kiri, sehingga
   angka di kolom kiri tidak sejajar dengan angka di atasnya — meleset
   dua puluh piksel, cukup untuk terbaca sebagai tata letak yang tidak
   dirapikan. `:nth-child` menghitungnya menurut kolom yang sebenarnya. */
.jual-statistik{display:grid;grid-template-columns:repeat(2,1fr);
  border-top:1px solid rgba(255,255,255,.14)}
.jual-statistik>div{padding:1.1rem 1.25rem 0 0;
  border-right:1px solid var(--j-garis)}
.jual-statistik>div:nth-child(2n){border-right:0;padding-right:0;padding-left:1.25rem}

@media (min-width:640px){
  .jual-statistik{grid-template-columns:repeat(4,1fr)}
  .jual-statistik>div{padding:1.1rem 1.25rem 0;border-right:1px solid var(--j-garis)}
  .jual-statistik>div:first-child{padding-left:0}
  .jual-statistik>div:last-child{border-right:0;padding-right:0}
}
.jual-statistik b{display:block;font-size:1.6rem;font-weight:700;line-height:1;
  letter-spacing:-.03em;font-variant-numeric:tabular-nums}
.jual-statistik span{display:block;font-size:11.5px;color:var(--j-redup);
  margin-top:.5rem}

/* ── bobot SMKP sebagai SATU bilah ──

   Sebelumnya tujuh kartu kecil berjajar dua kolom, masing-masing dengan
   bilahnya sendiri. Bentuk itu menyembunyikan satu-satunya hal yang
   menarik dari angkanya: bahwa Implementasi sendirian menanggung 35%
   sementara Dokumentasi hanya 3%. Perbandingan tidak terbaca ketika tiap
   bilah punya seratus persennya sendiri.

   Satu bilah utuh yang dibagi tujuh membuat perbandingan itu terbaca
   dalam sekali lihat — dan jumlahnya memang tepat seratus, jadi bilahnya
   jujur secara harfiah. */
.jual-takaran{display:flex;height:2.75rem;border-radius:8px;overflow:hidden;
  border:1px solid var(--j-garis-tebal)}
.jual-takaran>i{display:block;height:100%;position:relative;
  border-right:1px solid rgba(18,22,26,.35);
  transition:filter .3s var(--j-lengkung)}
.jual-takaran>i:last-child{border-right:0}
.jual-takaran:hover>i{filter:saturate(.45) opacity(.55)}
.jual-takaran>i:hover{filter:none}

.jual-takaran-daftar{display:grid;gap:.55rem;margin-top:1.25rem}
.jual-takaran-daftar li{display:flex;align-items:baseline;gap:.65rem;font-size:12.5px}
.jual-takaran-titik{width:.5rem;height:.5rem;border-radius:2px;flex:none;
  transform:translateY(-1px)}
.jual-takaran-nama{color:var(--j-redup)}
.jual-takaran-bobot{margin-left:auto;font-variant-numeric:tabular-nums;font-weight:700;
  color:var(--j-tinta)}

/* Pita penanda pada kartu rencana. Kata, bukan warna sendirian: warna
   saja tidak terbaca oleh yang tidak membedakan jingga dan abu. */
.jual-pita{display:inline-flex;align-items:center;align-self:flex-start;
  border-radius:40px;padding:.28rem .7rem;font-size:11px;font-weight:700;
  letter-spacing:.02em;background:var(--j-aksen-lembut);color:var(--j-aksen-teks)}
.jual-pita-sunyi{background:var(--j-dasar);color:var(--j-redup)}

/* ── kartu ── */

.jual-kartu{background:var(--j-kartu);color:var(--j-tinta);border:1px solid var(--j-garis);border-radius:12px;
  padding:1.65rem;
  transition:border-color .3s var(--j-lengkung),box-shadow .3s var(--j-lengkung),
  transform .3s var(--j-lengkung)}
.jual-kartu:hover{border-color:var(--j-garis-tebal);transform:translateY(-2px);
  box-shadow:0 14px 32px -22px rgba(18,22,26,.4)}

/* ── paket ── */

.jual-paket{display:grid;gap:1rem;align-items:stretch}
@media (min-width:1024px){.jual-paket{grid-template-columns:minmax(0,1fr) 21rem}}

.jual-paket-isi{background:var(--j-kartu);color:var(--j-tinta);border:1px solid var(--j-garis);
  border-radius:12px;padding:2rem}
@media (min-width:768px){.jual-paket-isi{padding:2.5rem}}

/* Tiga kolom baru mulai 1280px, bukan 1024px.

   Kartunya bukan selebar layar — ia berbagi baris dengan panel harga
   selebar 21rem — sehingga pada 1024px tiap kolom tinggal sekitar 190px,
   dan nama seperti "Sistem Informasi Gudang & Penyimpanan" pecah menjadi
   tiga baris. Daftar yang barisnya setinggi satu, dua, dan tiga baris
   bergantian terbaca sebagai daftar yang tidak dirapikan, padahal yang
   kurang hanya ruang. */
.jual-paket-daftar{display:grid;gap:.65rem 1.75rem;margin-top:1.75rem;
  grid-template-columns:1fr;font-size:14px;color:var(--j-redup)}
@media (min-width:640px){.jual-paket-daftar{grid-template-columns:1fr 1fr}}
@media (min-width:1280px){.jual-paket-daftar{grid-template-columns:1fr 1fr 1fr}}
.jual-paket-daftar li{display:flex;align-items:center;gap:.55rem}
.jual-centang{width:1rem;height:1rem;border-radius:999px;flex:none;display:grid;
  place-items:center;background:var(--j-hijau-lembut)}
.jual-centang svg{width:.6rem;height:.6rem;color:var(--j-hijau-tua)}

/* ── SETINGGI ISINYA, BUKAN SETINGGI KARTU DI SEBELAHNYA ──
 *
 * Sebelumnya panel ini diregangkan mengikuti kartu daftar aplikasi yang
 * memuat dua puluh satu baris, dan tombolnya dipaku ke dasar. Yang
 * terbentuk lubang kosong hampir empat ratus piksel antara harga dan
 * tombolnya — bukan ruang bernapas melainkan kekosongan, dan pada panel
 * hitam pekat kekosongan itu yang paling dulu terlihat.
 *
 * Sekarang ia setinggi isinya sendiri.
 *
 * TIDAK melekat saat digulir, dan itu sekarang PILIHAN, bukan lagi
 * keterpaksaan.
 *
 * Dulu `position:sticky` memang tidak dapat bekerja di halaman mana pun:
 * app.css memasang `overflow-x:hidden` pada html dan body sebagai
 * pengaman geser-ke-samping, dan itu menjadikan halaman wadah gulir
 * tersendiri sehingga sticky tidak punya apa pun untuk dilekati. Terukur
 * di sini (top 333 → 33 → −67 → −167, bukan berhenti pada 88) dan
 * kemudian terukur jauh lebih mahal pada bilah samping, yang mengaku
 * dipaku setinggi layar tetapi ikut hanyut sampai hilang sama sekali.
 *
 * Pengamannya kini `overflow-x:clip`, yang memotong tanpa membuat wadah
 * gulir — sticky bekerja lagi di seluruh aplikasi. Panel harga tetap
 * tidak dibuat melekat: sebabnya isinya, bukan CSS-nya. Ia setinggi
 * isinya sendiri dan sudah terlihat utuh tanpa perlu mengikuti gulir.
 */
.jual-paket-harga{background:var(--j-gelap);color:#fff;border-radius:12px;padding:2rem;
  display:flex;flex-direction:column;align-self:start}
.jual-paket-tombol{margin-top:1.75rem}

/* Harga website kedua dan seterusnya, di dalam kartu harga yang gelap.
   Dipisahkan garis atas, bukan sekadar diberi jarak: ia aturan harga
   yang lain, bukan keterangan tambahan atas angka di atasnya. */
.jual-paket-tambahan{margin-top:1.25rem;padding-top:1.25rem;
  border-top:1px solid rgb(255 255 255 / .16);
  font-size:12.5px;line-height:1.6;color:rgb(255 255 255 / .72)}
.jual-paket-tambahan b{color:#fff;font-weight:700}

/* Kartu layanan tahunan: berdampingan dengan paketnya, bukan di
   halaman lain. Biaya yang datang lagi tiap tahun dan baru diketahui
   sesudah menandatangani adalah biaya yang merusak kepercayaan,
   seberapa pun wajar angkanya. */
.jual-layanan{background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:12px;
  padding:1.75rem;margin-top:1.25rem;display:grid;gap:.85rem}
.jual-layanan-kepala{display:flex;align-items:baseline;justify-content:space-between;
  gap:1rem;flex-wrap:wrap}
.jual-layanan-harga{font-size:1.5rem;font-weight:800;letter-spacing:-.02em;white-space:nowrap}
.jual-layanan-spek{display:flex;flex-wrap:wrap;gap:.4rem}
.jual-layanan-spek span{border:1px solid var(--j-garis);border-radius:999px;
  padding:.2rem .7rem;font-size:11.5px;font-weight:600}

.jual-kosong{background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:12px;
  padding:2.5rem;max-width:42rem}

/* ══════════ PILAR: kisi di kiri, rincian di kanan ══════════

   Rinciannya dulu terbuka DI BAWAH kisinya, mendorong seluruh halaman
   turun — dan yang baru menekan sebuah kartu kehilangan kartu itu dari
   pandangan tepat saat ia ingin membandingkannya dengan yang lain.

   Kolom kanannya tetap ada bahkan sebelum ada yang ditekan (aspek
   pertama terbuka sejak awal), supaya lebar kisinya tidak berubah
   mendadak pada penekanan pertama. */
.jual-pilar-tata{display:grid;gap:.75rem;align-items:start}
@media (min-width:1024px){
  .jual-pilar-tata{grid-template-columns:minmax(0,1fr) minmax(0,25rem)}
}

.jual-pilar-kisi{display:grid;gap:.75rem;grid-template-columns:1fr}
@media (min-width:640px){.jual-pilar-kisi{grid-template-columns:repeat(2,1fr)}}

/* Kartu aspek.
   Seragam — tanpa ubin dan tautan berwarna sendiri-sendiri. Delapan
   kartu yang masing-masing membawa warnanya terbaca sebagai pelangi:
   tidak ada yang menonjol karena semuanya menonjol. Yang berwarna penuh
   hanya kartu yang sedang dipilih, satu pada satu waktu. */
.jual-pilar-kartu{display:grid;grid-template-columns:auto 1fr;gap:.2rem .85rem;
  align-items:center;text-align:left;width:100%;
  /* Catatan di sini dulu berbunyi sebaliknya: JANGAN pakai var(--j-kartu)
     karena kartunya akan putih dengan tulisan putih. Itu benar selama
     bagian ini masih gelap. Sesudah halaman depan menjadi krem,
     nasihatnya berbalik menjadi penyebab — putih 4,5% di atas krem
     tidak menggambar apa pun, sehingga tujuh dari delapan kartu aspek
     berdiri tanpa bingkai dan tanpa petunjuk bahwa ia dapat ditekan.

     Catatan yang benar pada satu keadaan dan menyesatkan pada keadaan
     berikutnya lebih berbahaya daripada tidak ada catatan sama sekali,
     karena ia menghentikan orang berikutnya dari memeriksa sendiri. */
  background:var(--j-kartu);border:1px solid var(--j-garis);
  border-radius:12px;padding:1rem 1.1rem;cursor:pointer;
  transition:border-color .18s ease,background .18s ease,transform .18s ease,
             box-shadow .18s ease}
.jual-pilar-kartu:hover{border-color:var(--j-garis-tebal);
  transform:translateY(-1px);box-shadow:0 10px 26px -20px rgba(18,22,26,.45)}
.jual-pilar-kartu:focus-visible{outline:2px solid #F36F0F;outline-offset:2px}

.jual-pilar-tanda{grid-row:span 2;display:grid;place-items:center;width:38px;height:38px;
  /* Putih 6% dengan ikon putih 62%: benar di atas kartu gelap, sama
     sekali tak terlihat di atas kartu krem. Tujuh dari delapan ikon
     aspek lenyap karenanya — dan ikon yang hilang tidak menimbulkan
     galat, hanya kartu yang tampak belum selesai dibuat. */
  border-radius:10px;background:var(--j-dasar);color:var(--j-redup);
  transition:background .18s ease,color .18s ease}
.jual-pilar-nama{font-size:13.5px;font-weight:700;line-height:1.3}
.jual-pilar-ket{font-size:11.5px;line-height:1.45;color:var(--j-samar)}

.jual-pilar-kartu-aktif{background:rgb(245 124 0 / .10);border-color:rgb(245 124 0 / .55)}
.jual-pilar-kartu-aktif .jual-pilar-tanda{background:rgb(245 124 0 / .18);color:#FF9800}
.jual-pilar-kartu-aktif .jual-pilar-ket{color:var(--j-redup)}

/* Panel rincian. Melekat saat digulir pada layar lebar supaya tetap
   terbaca ketika kisinya lebih panjang daripada panelnya. */
.jual-pilar-panel{background:var(--j-kartu);color:var(--j-tinta);
  border:1px solid var(--j-garis);border-radius:12px;padding:1.5rem;
  box-shadow:0 18px 40px -32px rgba(18,22,26,.5)}
@media (min-width:1024px){.jual-pilar-panel{position:sticky;top:5.5rem}}

.jual-pilar-panel-tanda{display:grid;place-items:center;width:48px;height:48px;
  border-radius:15px}

/* ── Cat hanya untuk ubin yang TIDAK berdimensi ──

   Kedua aturan di bawah ditulis SESUDAH .ikon-3d di berkas ini, dan
   kekhususannya sama. Tanpa :not(), latar datarnya menang atas gradien
   .ikon-3d semata-mata karena urutan barisnya — yang tersisa di layar
   hanya bayangannya, sehingga ubinnya tampak punya bayangan tanpa punya
   badan. Tertangkap pada tangkapan layar, bukan oleh uji. */
.jual-tanda:not(.ikon-3d){
  background:color-mix(in srgb,var(--c,#F36F0F) 13%,#fff);
  color:color-mix(in srgb,var(--c,#F36F0F) 78%,#1C1817)}
.jual-pilar-panel-tanda:not(.ikon-3d){
  background:color-mix(in srgb,var(--c) 18%,transparent);color:var(--c)}

.jual-pilar-cakupan{display:grid;gap:.9rem;margin-top:1.35rem;list-style:none;padding:0}
.jual-pilar-cakupan li{border-left:2px solid var(--c);padding-left:.7rem}
.jual-pilar-cakupan b{display:block;font-size:12.5px;font-weight:700;line-height:1.35}
.jual-pilar-cakupan span{display:block;font-size:11.5px;line-height:1.5;
  color:var(--j-redup);margin-top:.15rem}

.jual-pilar-modul-judul{margin-top:1.4rem;font-size:10px;font-weight:700;
  letter-spacing:.14em;text-transform:uppercase;color:var(--j-samar)}

/* Perpindahan antaraspek: satu gerakan pendek, bukan pantulan.
   mode="out-in" pada Vue membuat yang lama keluar dulu, jadi tidak ada
   dua panel yang sesaat bertumpuk. */
.jual-panel-enter-active{transition:opacity .22s ease,transform .22s ease}
.jual-panel-leave-active{transition:opacity .13s ease,transform .13s ease}
.jual-panel-enter-from{opacity:0;transform:translateX(10px)}
.jual-panel-leave-to{opacity:0;transform:translateX(-6px)}
@media (max-width:1023px){
  .jual-panel-enter-from{transform:translateY(8px)}
  .jual-panel-leave-to{transform:translateY(-4px)}
}
@media (prefers-reduced-motion:reduce){
  .jual-panel-enter-active,.jual-panel-leave-active{transition:opacity .12s ease}
  .jual-panel-enter-from,.jual-panel-leave-to{transform:none}
  .jual-pilar-kartu:hover{transform:none}
}

/* ══════════ pemutar video ══════════ */
.jual-pemutar{position:fixed;inset:0;z-index:50;display:grid;place-items:center;
  background:rgb(0 0 0 / .86);padding:1.25rem}
.jual-pemutar-isi{width:100%;max-width:56rem}
.jual-pemutar-kepala{display:flex;align-items:center;justify-content:space-between;
  gap:1rem;margin-bottom:.75rem;color:#fff}
.jual-pemutar-kepala span{font-size:13px;font-weight:700}
.jual-pemutar-kepala button{font-size:1.5rem;line-height:1;color:#fff;
  background:none;border:0;cursor:pointer;padding:0 .25rem}
.jual-pemutar-video{width:100%;border-radius:14px;display:block;background:#000}
.jual-pemutar-enter-active,.jual-pemutar-leave-active{transition:opacity .18s ease}
.jual-pemutar-enter-from,.jual-pemutar-leave-to{opacity:0}

/* ── cakupan ── */

.jual-aspek-kisi{display:grid;gap:1rem;grid-template-columns:1fr}
@media (min-width:640px){.jual-aspek-kisi{grid-template-columns:repeat(2,1fr)}}
@media (min-width:1024px){.jual-aspek-kisi{grid-template-columns:repeat(3,1fr)}}

.jual-aspek{text-align:left;cursor:pointer;display:block;border-radius:12px;overflow:hidden;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.09);
  transition:border-color .3s var(--j-lengkung),transform .3s var(--j-lengkung),
  background .3s var(--j-lengkung)}
.jual-aspek:hover{border-color:rgba(255,255,255,.22);transform:translateY(-2px);
  background:rgba(255,255,255,.07)}

.jual-aspek-bingkai{position:relative;display:block;overflow:hidden;aspect-ratio:16/10;
  background:#1B2126}

/* Tingginya DILEBIHKAN dan dijangkarkan ke atas, dan itu bukan pilihan
   gaya: berkas galerinya membawa tulisan yang terbakar di dalam
   gambarnya — occhealth.jpg mencetak "Occupational Health" di tengah
   bawah, dan lima berkas lain membawa lencana bintang penyunting di
   kanan bawah. Ditampilkan utuh, kartunya menyebut nama aspeknya dua
   kali: sekali sebagai label, sekali sebagai tulisan buram yang tidak
   sejajar dengan apa pun.

   Berkas fotonya sendiri tidak disentuh. Menggantinya dengan yang bersih
   membuat baris ini tidak lagi berguna, tetapi juga tidak merusak apa
   pun. */
.jual-aspek-foto{position:absolute;inset:0 0 auto 0;width:100%;height:124%;
  object-fit:cover;object-position:center 34%;transform-origin:50% 0;
  transition:transform 1s var(--j-lengkung)}
.jual-aspek:hover .jual-aspek-foto{transform:scale(1.05)}
.jual-aspek-polos{background:
  linear-gradient(155deg,rgba(255,255,255,.09),rgba(0,0,0,.32)),
  color-mix(in srgb,var(--w,#2A323B) 55%,#1B2126);
  display:grid;place-items:center}
/* Cukup terlihat untuk menandai ubinnya disengaja, cukup redup supaya
   nama aspek di bawahnya tetap yang pertama dibaca. */
.jual-aspek-polos svg{color:rgba(255,255,255,.34)}
.jual-aspek-tirai{position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(180deg,transparent 50%,#1C181766 100%)}

.jual-aspek-baris{display:flex;align-items:center;justify-content:space-between;gap:1rem;
  padding:1rem 1.15rem}
.jual-aspek-nama{font-size:15px;font-weight:700;letter-spacing:-.012em}
/* .42 memberi 4,07:1 di atas ubin gelap — di bawah 4,5:1 yang dituntut
   teks 11px. .58 membawanya ke 5,6:1 tanpa membuat angkanya bersaing
   dengan nama aspek di sebelahnya. */
.jual-aspek-jumlah{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  font-size:11px;color:rgba(255,255,255,.58);font-variant-numeric:tabular-nums}

/* ── daftar harga ── */

.jual-grup{margin-top:2.5rem}
.jual-grup:first-of-type{margin-top:0}

.jual-grup-kepala{display:flex;align-items:center;gap:.75rem;margin-bottom:.85rem}
.jual-grup-titik{width:.55rem;height:.55rem;border-radius:999px;flex:none}
.jual-grup-nama{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  font-size:12px;font-weight:600;letter-spacing:.14em;text-transform:uppercase}
.jual-grup-jumlah{margin-left:auto;font-size:13px;color:var(--j-samar);
  font-variant-numeric:tabular-nums}

.jual-baris{display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;
  background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:12px;
  padding:1.15rem 1.35rem;margin-bottom:.6rem;
  transition:border-color .28s var(--j-lengkung),box-shadow .28s var(--j-lengkung)}
.jual-baris:hover{border-color:var(--j-garis-tebal);
  box-shadow:0 10px 26px -20px rgba(18,22,26,.4)}

.jual-baris-teks{flex:1 1 19rem;min-width:0}
.jual-baris-nama{display:block;font-size:15px;font-weight:700;letter-spacing:-.014em}
.jual-baris-ket{display:block;font-size:13.5px;line-height:1.5;color:var(--j-redup);
  margin-top:.25rem;max-width:36rem}

.jual-baris-aksi{display:flex;align-items:center;gap:1.25rem;margin-left:auto}
.jual-baris-harga{text-align:right;min-width:7.5rem;font-variant-numeric:tabular-nums}
.jual-baris-harga .num{display:block;font-size:15px;font-weight:700;letter-spacing:-.015em}
.jual-baris-masa{display:block;font-size:12px;color:var(--j-samar);margin-top:.1rem}

/* ── penghitung ── */

.jual-hitung{display:inline-flex;align-items:center;background:var(--j-dasar);
  border:1px solid var(--j-garis);border-radius:8px}
.jual-hitung button{width:1.95rem;height:1.95rem;display:grid;place-items:center;
  font-size:15px;line-height:1;color:var(--j-redup);cursor:pointer;border-radius:7px;
  transition:color .22s var(--j-lengkung),background .22s var(--j-lengkung)}
.jual-hitung button:hover{color:var(--j-tinta);background:#fff}
.jual-hitung .num{width:2rem;text-align:center;font-size:13px;font-weight:700;
  font-variant-numeric:tabular-nums}

.jual-paket-harga .jual-hitung{background:rgba(255,255,255,.07);border-color:rgba(255,255,255,.16)}
.jual-paket-harga .jual-hitung button{color:rgba(255,255,255,.66)}
.jual-paket-harga .jual-hitung button:hover{color:#fff;background:rgba(255,255,255,.12)}

/* ── cara beli ── */

.jual-langkah{display:grid;gap:1rem;grid-template-columns:1fr}
@media (min-width:640px){.jual-langkah{grid-template-columns:repeat(2,1fr)}}
@media (min-width:1024px){.jual-langkah{grid-template-columns:repeat(4,1fr)}}
/* Bulat dan pekat, bukan kotak berlatar jingga samar.
   Angka urut adalah satu-satunya hal pada langkah yang harus terbaca
   lebih dulu daripada judulnya; berlatar samar ia justru yang paling
   redup di seluruh kartunya. */
.jual-langkah-angka{display:inline-flex;align-items:center;justify-content:center;
  width:2.3rem;height:2.3rem;border-radius:99px;
  font-size:12.5px;font-weight:800;font-variant-numeric:tabular-nums;
  background:linear-gradient(135deg,#C85804,var(--j-aksen));color:#fff;
  box-shadow:0 6px 16px -5px rgba(245,124,0,.7)}

.jual-jaminan{display:grid;gap:1rem;grid-template-columns:1fr}
@media (min-width:640px){.jual-jaminan{grid-template-columns:repeat(2,1fr)}}
@media (min-width:1024px){.jual-jaminan{grid-template-columns:repeat(4,1fr)}}

/* ── pemesanan ── */

.jual-pesanan{background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:12px;
  overflow:hidden}
.jual-pesanan li{display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;
  padding:1.1rem 1.35rem}
.jual-pesanan li+li{border-top:1px solid var(--j-garis)}
.jual-pesanan-teks{flex:1 1 13rem;min-width:0}
.jual-pesanan-aksi{display:flex;align-items:center;gap:1.15rem;margin-left:auto}
.jual-pesanan-jumlah{font-size:15px;font-weight:700;min-width:7.5rem;text-align:right;
  font-variant-numeric:tabular-nums;letter-spacing:-.015em}
.jual-pesanan-kosong{background:var(--j-kartu);border:1px dashed var(--j-garis-tebal);
  border-radius:12px;padding:2.5rem 1.5rem;text-align:center;
  font-size:14px;color:var(--j-samar)}

.jual-buang{width:1.5rem;height:1.5rem;display:grid;place-items:center;font-size:16px;
  line-height:1;color:#C3CAC5;cursor:pointer;border-radius:6px;
  transition:color .22s var(--j-lengkung),background .22s var(--j-lengkung)}
.jual-buang:hover{color:#B91C1C;background:#FEF2F2}

.jual-total{display:flex;align-items:center;justify-content:space-between;gap:1rem;
  background:var(--j-gelap);color:#fff;border-radius:12px;padding:1.25rem 1.35rem;
  margin-top:.6rem}
.jual-total span:first-child{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  font-size:11px;font-weight:600;letter-spacing:.16em;text-transform:uppercase;
  color:rgba(255,255,255,.5)}
.jual-total span:last-child{font-size:1.4rem;font-weight:700;letter-spacing:-.03em;
  font-variant-numeric:tabular-nums}

/* ── formulir ──

   Label di ATAS isian, bukan sebagai placeholder yang hilang begitu
   diketik. Placeholder sebagai label membuat orang yang berhenti sejenak
   di tengah pengisian kehilangan nama medannya, dan yang paling sering
   berhenti adalah yang paling ragu. */
.jual-form{background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:12px;
  padding:1.75rem}

.jual-isian{display:block;margin-top:1rem}
.jual-isian:first-of-type{margin-top:0}
.jual-isian>span{display:block;font-size:12px;font-weight:600;color:var(--j-redup);
  margin-bottom:.4rem}
.jual-isian input,.jual-isian textarea{width:100%;background:var(--j-dasar);
  border:1px solid var(--j-garis);border-radius:8px;padding:.65rem .8rem;
  font-size:14px;color:var(--j-tinta);
  transition:border-color .25s var(--j-lengkung),background .25s var(--j-lengkung),
  box-shadow .25s var(--j-lengkung)}
.jual-isian input::placeholder,.jual-isian textarea::placeholder{color:#AEB6B1}
.jual-isian input:focus,.jual-isian textarea:focus{outline:none;background:#fff;
  border-color:var(--j-aksen);box-shadow:0 0 0 3px rgba(245,124,0,.13)}
.jual-isian textarea{resize:vertical}

.jual-galat{font-size:12.5px;color:#B91C1C;margin-top:.35rem}

/* ── kaki ── */

.jual-kaki{background:var(--j-kartu);border-top:1px solid var(--j-garis);padding-block:2.25rem}

/* ── bilah keranjang ──

   Melekat di bawah layar, bukan hanya di panel yang jauh: pada ponsel,
   dua puluh satu baris berarti pilihannya berada beberapa layar di atas
   tombol pesannya, dan yang memilih tidak punya cara tahu bahwa
   pilihannya tercatat. */
.jual-bilah-bungkus{position:fixed;inset:auto 0 0 0;z-index:40;padding:0 1rem 1rem}
.jual-bilah{max-width:42rem;margin-inline:auto;display:flex;align-items:center;gap:1.25rem;
  background:var(--j-gelap);color:#fff;border-radius:14px;
  padding:.85rem .85rem .85rem 1.35rem;
  box-shadow:0 24px 48px -24px rgba(18,22,26,.75)}
.jual-bilah-angka{display:block;font-size:16px;font-weight:700;line-height:1;
  letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.jual-bilah-ket{display:block;font-size:11.5px;color:rgba(255,255,255,.5);margin-top:.3rem}

.jual-bilah-enter-active,.jual-bilah-leave-active{
  transition:transform .42s var(--j-lengkung),opacity .42s var(--j-lengkung)}
.jual-bilah-enter-from,.jual-bilah-leave-to{transform:translateY(130%);opacity:0}

/* ── dua unsur yang memang tetap terang ──

   Keduanya hidup DI ATAS latar gelap, bukan di atas kartu: tombol terang
   berdiri di hero dan di panel harga paket, dan ubin QRIS berada di dalam
   kartu tiruan yang putih pada kedua mode. Digelapkan mengikuti aturan
   umum, keduanya justru hilang — putih di atas gelap adalah yang
   dimaksudkan, bukan yang terlewat.

   Ditulis tetap begini supaya niatnya terbaca, bukan disamarkan lewat
   peubah agar lolos pemeriksaan. */
:root[data-tema="gelap"] .jual-tombol-terang{background:#fff;color:#1C1817;border-color:#fff}
:root[data-tema="gelap"] .jual-mock-qr{background:#fff;color:#1C1817}

/* ── gerakan boleh diminta berhenti ──

   Bukan soal selera: gerakan memicu mual dan pusing pada sebagian orang,
   dan halaman jual adalah tempat mereka tidak punya pilihan untuk
   menghindarinya. v-singkap sudah tidak memasang keadaan awalnya sama
   sekali bila diminta; yang tersisa di sini gerakan sentuh. */
@media (prefers-reduced-motion:reduce){
  .jual-kepala,.jual-tombol,.jual-tautan,.jual-nav,.jual-aspek,.jual-aspek-foto,
  .jual-kartu,.jual-baris,.jual-hitung button,.jual-buang,
  .jual-isian input,.jual-isian textarea,
  .jual-bilah-enter-active,.jual-bilah-leave-active{transition:none}

  .jual-modul,.jual-gelap-kartu,.jual-tanda,.jual-galeri-foto,.jual-takaran>i{transition:none}
  .jual-pita-galeri>article:hover .jual-galeri-foto{transform:none}
  .jual-takaran:hover>i{filter:none}
  .jual-aspek:hover,.jual-kartu:hover,.jual-modul:hover,
  .jual-gelap-kartu:hover{transform:none}
  .jual-aspek:hover .jual-aspek-foto{transform:none}
  .jual-tombol:active{transform:none}
  .jual-bilah-enter-from,.jual-bilah-leave-to{transform:none;opacity:1}
}

/* ── Kartu cuaca pada sampul modul ───────────────────────────────────
   Ditulis di sini, bukan di dalam komponen Vue, dengan alasan yang sama
   seperti seluruh berkas ini: dua salinan aturan yang sama akan berbeda
   isinya cepat atau lambat.

   Gerak di sini murni hiasan — angka dan labelnya sudah lengkap tanpa
   animasi apa pun. Karena itu pada perangkat yang meminta gerak
   dikurangi, seluruhnya dimatikan tanpa ada keterangan yang hilang. */
.eq-cuaca{
  background:rgba(12,10,9,.42);
  border:1px solid rgba(255,255,255,.18);
  backdrop-filter:blur(10px) saturate(140%);
  -webkit-backdrop-filter:blur(10px) saturate(140%);
  box-shadow:0 8px 24px -12px rgba(0,0,0,.7), inset 0 1px 0 rgba(255,255,255,.12);
}

/* Sinar matahari berdenyut pelan. */
.eq-cuaca-surya{transform-origin:center;animation:eq-surya 4.5s ease-in-out infinite}
@keyframes eq-surya{
  0%,100%{opacity:.55;transform:scale(.92)}
  50%    {opacity:1;  transform:scale(1.06)}
}

/* Tetes hujan jatuh bergantian. Tiap tetes diberi tundaan sendiri lewat
   --eq-tunda supaya tidak jatuh serentak seperti satu benda. */
.eq-cuaca-tetes{animation:eq-tetes 1.5s linear infinite;animation-delay:var(--eq-tunda,0s)}
@keyframes eq-tetes{
  0%  {opacity:0;   transform:translateY(-2px)}
  25% {opacity:.95}
  100%{opacity:0;   transform:translateY(7px)}
}

/* Kotak meter yang sedang aktif berdenyut samar, sisanya diam. */
.eq-cuaca-aktif{animation:eq-cuaca-aktif 2.4s ease-in-out infinite}
@keyframes eq-cuaca-aktif{0%,100%{opacity:1}50%{opacity:.62}}

@media (prefers-reduced-motion: reduce){
  .eq-cuaca-surya,.eq-cuaca-tetes,.eq-cuaca-aktif{animation:none}
  .eq-cuaca-surya{opacity:1;transform:none}
  .eq-cuaca-tetes{opacity:.95;transform:none}
}


/* ══════════════════════════════════════════════════════════════════
   Unsur halaman depan yang diangkat dari rancangan acuan.

   Yang diambil bukan isinya — acuannya sebuah situs perjalanan —
   melainkan cara ia disusun: satu warna aksen yang dipakai dengan
   disiplin, sudut yang membulat lebar, tombol berbentuk pil berpanah,
   dan angka bertanda besar. Nuansa tambangnya tidak berubah: foto,
   warna tanah, dan bahasanya tetap.
   ══════════════════════════════════════════════════════════════════ */

/* ── pil panah, disisipkan DI DALAM baris judul ──
   Bentuk yang paling menandai acuannya: sebuah tombol jingga duduk
   sebaris dengan huruf judul, bukan di bawahnya. Barisan judulnya
   mendapat satu titik berwarna tanpa menambah satu baris pun tinggi
   halaman. */
.jual-pil-panah{
  display:inline-flex;align-items:center;justify-content:center;
  vertical-align:middle;
  width:4.6rem;height:2.5rem;margin:0 .55rem .35rem 0;
  border:0;border-radius:99px;
  background:linear-gradient(135deg,#C85804,var(--j-aksen));
  color:#fff;cursor:pointer;
  box-shadow:0 8px 22px -6px rgba(245,124,0,.7);
  transition:filter .16s,box-shadow .16s,transform .16s;
}
.jual-pil-panah svg{width:1.35rem;height:1.35rem}
.jual-pil-panah:hover{filter:brightness(1.07);box-shadow:0 10px 26px -6px rgba(245,124,0,.85)}
.jual-pil-panah:active{transform:translateY(1px)}
.jual-pil-panah:focus-visible{outline:2px solid #fff;outline-offset:3px}
@media (min-width:768px){
  .jual-pil-panah{width:5.6rem;height:3rem}
  .jual-pil-panah svg{width:1.6rem;height:1.6rem}
}

/* ── kelompok angka bertumpuk ──
   Foto kecil yang saling menindih di samping satu angka besar. Angka
   tanpa gambar terbaca sebagai klaim; gambar di sebelahnya membuatnya
   terbaca sebagai sesuatu yang benar-benar ada. */
.jual-tumpuk{display:flex;align-items:center;gap:.9rem}
.jual-tumpuk-foto{display:flex;flex:none}
.jual-tumpuk-foto img{
  width:2.9rem;height:2.9rem;object-fit:cover;
  border-radius:50% 50% 50% 10px;
  border:2px solid var(--j-dasar);
}
.jual-tumpuk-foto img+img{margin-left:-.85rem}
.jual-tumpuk b{display:block;font-size:1.55rem;font-weight:800;letter-spacing:-.02em;line-height:1.1}
/* Keterangannya disebut lewat `b+span`, BUKAN `.jual-tumpuk span`.
   Yang terakhir ikut mengenai wadah fotonya — yang juga sebuah span —
   dan spesifisitasnya (0,1,1) menang atas `.jual-tumpuk-foto` (0,1,0):
   wadahnya berhenti menjadi flex, ketiga fotonya menumpuk tegak, dan
   tidak ada satu pun galat yang menyebutkannya. */
.jual-tumpuk b+span{display:block;font-size:12.5px;color:var(--j-redup);margin-top:.1rem}

/* ── nomor bulat pada gundukan lembut ──
   Acuannya menaruh 01 / 02 / 03 di atas bulatan putih kabur. Di sini
   untuk urutan kerja — dan urutan memang satu-satunya hal yang perlu
   dibaca lebih dulu daripada judul langkahnya. */
.jual-gundukan{position:relative;text-align:center;padding:2.3rem 1.2rem 2rem}
.jual-gundukan::before{
  content:"";position:absolute;inset:0;
  border-radius:50%/38%;
  background:var(--j-kartu);
  box-shadow:0 18px 44px -24px rgba(18,22,26,.32);
}
.jual-gundukan>*{position:relative}
.jual-nomor-bulat{
  display:inline-grid;place-items:center;
  width:2.3rem;height:2.3rem;border-radius:99px;
  background:linear-gradient(135deg,#C85804,var(--j-aksen));
  color:#fff;font-size:12.5px;font-weight:800;
  font-variant-numeric:tabular-nums;
  box-shadow:0 6px 16px -5px rgba(245,124,0,.75);
}

/* ── bilah aksi jingga pada kartu bergambar ──
   Pada acuannya, harga dan tautan duduk di dalam satu bilah jingga di
   kaki kartu, bukan sebagai tautan telanjang. Bilah itu yang membuat
   kartunya terbaca sebagai sesuatu yang dapat ditekan. */
/* Memeluk isinya. Semula `display:flex;width:100%`, dan hasilnya bilah
   jingga selebar kartu di kaki tiap foto — enam batang mencolok yang
   menarik pandangan lebih kuat daripada foto yang seharusnya mereka
   layani, dan pada kartu berketerangan satu baris, tepinya tidak
   sejajar dengan kartu di sebelahnya.

   Yang dipesan dari tombol ini menonjol dibanding tautan bergaris
   bawah, bukan mendominasi kartunya. Pil sepanjang tulisannya sudah
   cukup untuk itu. */
.jual-bilah-aksi{
  display:inline-flex;align-items:center;gap:.6rem;
  width:auto;max-width:100%;margin-top:.85rem;padding:.45rem .5rem .45rem .95rem;
  border:0;border-radius:99px;
  background:linear-gradient(135deg,#C85804,var(--j-aksen));
  color:#fff;font-size:12.5px;font-weight:700;
  text-decoration:none;cursor:pointer;
  box-shadow:0 8px 20px -9px rgba(245,124,0,.8);
  transition:filter .16s,box-shadow .16s;
}
.jual-bilah-aksi:hover{filter:brightness(1.07);box-shadow:0 10px 24px -9px rgba(245,124,0,.9)}
.jual-bilah-aksi i{
  display:grid;place-items:center;flex:none;
  width:1.7rem;height:1.7rem;border-radius:99px;
  background:rgba(255,255,255,.22);
}
.jual-bilah-aksi svg{width:.85rem;height:.85rem;transition:transform .16s}
.jual-bilah-aksi:hover svg{transform:translateX(.12rem)}

/* ── sudut media yang lebih membulat ──
   Sudut siku membuat foto tambang terbaca seperti lampiran dokumen,
   bukan seperti bagian dari halaman. */
.jual-galeri-bingkai{border-radius:20px}
.jual-modul{border-radius:18px}


/* ═══════════════════════════════════════════════════════════════
   BAGIAN BARU MENGIKUTI PRD — masalah, peta SMKP, angka, tanya
   jawab, dan jalur WhatsApp.
   ═══════════════════════════════════════════════════════════════ */

/* ── masalah lapangan ──
   Lima kartu, dan yang PERTAMA melebar dua kolom pada layar lebar.
   Lima kartu seukuran di kisi tiga kolom meninggalkan dua lubang di
   baris kedua — dan lubang di tengah halaman terbaca sebagai sesuatu
   yang gagal dimuat, bukan sebagai ruang yang disengaja. */
.jual-masalah{display:grid;gap:.75rem;grid-template-columns:1fr}
@media (min-width:640px){.jual-masalah{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (min-width:1024px){
  .jual-masalah{grid-template-columns:repeat(3,minmax(0,1fr))}
  .jual-masalah-kartu:first-child{grid-column:span 2}
}
.jual-masalah-kartu{
  background:var(--j-kartu);border:1px solid var(--j-garis);border-radius:18px;
  padding:1.65rem;display:flex;flex-direction:column;
  transition:border-color .3s var(--j-lengkung),box-shadow .3s var(--j-lengkung),
             transform .3s var(--j-lengkung);
}
.jual-masalah-kartu:hover{border-color:var(--j-garis-tebal);transform:translateY(-2px);
  box-shadow:0 14px 32px -22px rgba(18,22,26,.4)}
.jual-masalah-nomor{
  display:inline-grid;place-items:center;width:2.3rem;height:2.3rem;border-radius:12px;
  background:var(--j-aksen-lembut);color:#8F3D07;
  font-size:12.5px;font-weight:800;letter-spacing:.02em;
}
/* Sumber duduk di KAKI kartu, dipisah garis — bukan menyambung
   langsung ke kalimatnya. Menyambung, ia terbaca sebagai bagian dari
   klaimnya; dipisah, ia terbaca sebagai tempat memeriksanya. */
.jual-sumber{
  margin-top:auto;padding-top:.85rem;border-top:1px dashed var(--j-garis-tebal);
  font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  font-size:10.5px;line-height:1.5;letter-spacing:.01em;color:var(--j-samar);
}
.jual-masalah-kartu .jual-sumber{margin-top:1.1rem}

/* ── peta elemen SMKP ↔ modul ── */
.jual-peta{width:100%;border-collapse:collapse;font-size:13px}
.jual-peta thead th{
  text-align:left;padding:0 .85rem .7rem;
  font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  font-size:10.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
  color:var(--j-samar);border-bottom:1px solid var(--j-garis-tebal);
}
.jual-peta tbody td{padding:.85rem;border-bottom:1px solid var(--j-garis);vertical-align:top}
.jual-peta tbody tr:last-child td{border-bottom:0}
.jual-peta tbody tr{transition:background .2s}
.jual-peta tbody tr:hover{background:var(--j-kartu)}
.jual-peta tbody td:first-child{display:flex;align-items:flex-start;gap:.5rem}
.jual-peta-titik{flex:none;width:.55rem;height:.55rem;border-radius:99px;margin-top:.42rem}
.jual-peta-kode{flex:none;width:1.6rem;color:var(--j-samar);font-size:11px;font-weight:700;
  text-align:right;padding-top:.06rem}
.jual-peta-nama{font-weight:700;letter-spacing:-.008em}
.jual-peta-bobot{white-space:nowrap;font-weight:800;color:var(--j-tinta)}
.jual-peta-modul{color:var(--j-redup);line-height:1.55}

/* Di bawah 48rem tabelnya runtuh jadi kartu berlabel — TIDAK digeser
   ke samping. Tabel yang harus digeser di ponsel adalah tabel yang
   tidak dibaca, dan ini justru tabel yang dicari pembacanya. */
@media (max-width:48rem){
  .jual-peta thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
  .jual-peta tbody tr{
    display:block;background:var(--j-kartu);border:1px solid var(--j-garis);
    border-radius:14px;padding:.35rem .25rem;margin-bottom:.6rem;
  }
  .jual-peta tbody td,
  .jual-peta tbody td:first-child{display:flex;gap:.9rem;border-bottom:0;padding:.42rem .85rem;
    align-items:flex-start}
  .jual-peta tbody td:first-child .jual-peta-titik{margin-top:.38rem}
  .jual-peta tbody td::before{
    content:attr(data-kolom);flex:none;width:6.2rem;
    font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
    font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
    color:var(--j-samar);padding-top:.16rem;
  }
}

/* ── penyangkalan kepatuhan ──
   Tidak boleh terbaca sebagai catatan kaki yang boleh dilewati:
   berlatar, bertanda seru, dan selebar tabel di atasnya. */
.jual-sangkal{
  display:flex;gap:.85rem;margin-top:1.4rem;padding:1rem 1.15rem;
  background:var(--j-aksen-lembut);border:1px solid #F7D8BF;border-radius:14px;
  font-size:12.5px;line-height:1.6;color:#7A3B06;
}
.jual-sangkal strong{color:#5E2D04}
.jual-sangkal>span:first-child{
  flex:none;display:grid;place-items:center;width:1.35rem;height:1.35rem;border-radius:99px;
  background:var(--j-aksen-teks);color:#fff;font-size:12px;font-weight:900;line-height:1;
}

/* ── deret angka cakupan ── */
.jual-angka-deret{display:grid;gap:2rem 1.5rem;grid-template-columns:repeat(2,minmax(0,1fr))}
@media (min-width:900px){.jual-angka-deret{grid-template-columns:repeat(4,minmax(0,1fr))}}
.jual-angka-besar{font-size:clamp(2.4rem,4.6vw,3.5rem);font-weight:800;line-height:1;
  letter-spacing:-.035em;color:var(--j-tinta)}
.jual-angka-satuan{
  margin-top:.5rem;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  font-size:10.5px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;
  color:var(--j-aksen-teks);
}

/* ── tanya jawab ── */
.jual-tanya{border-top:1px solid var(--j-garis)}
.jual-tanya-butir{border-bottom:1px solid var(--j-garis)}
.jual-tanya-kepala{
  width:100%;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;
  padding:1.15rem 0;background:none;border:0;cursor:pointer;text-align:left;
  font-size:14.5px;font-weight:700;letter-spacing:-.012em;color:var(--j-tinta);
  transition:color .2s;
}
.jual-tanya-kepala:hover{color:var(--j-aksen)}
.jual-tanya-kepala i{
  flex:none;display:grid;place-items:center;width:1.8rem;height:1.8rem;border-radius:99px;
  border:1px solid var(--j-garis-tebal);color:var(--j-redup);
  transition:transform .3s var(--j-lengkung),background .2s,border-color .2s,color .2s;
}
.jual-tanya-kepala svg{width:.85rem;height:.85rem}
.jual-tanya-buka .jual-tanya-kepala i{
  transform:rotate(180deg);background:var(--j-aksen);border-color:var(--j-aksen);color:#fff;
}
/* grid-template-rows 0fr→1fr, bukan max-height bertebak.
   max-height menuntut angka yang harus lebih besar dari jawaban
   terpanjang — dan jawaban yang tumbuh sedikit melewatinya terpotong
   diam-diam, tanpa galat apa pun yang memberi tahu siapa pun. */
.jual-tanya-isi{
  display:grid;grid-template-rows:0fr;
  transition:grid-template-rows .32s var(--j-lengkung);
}
.jual-tanya-buka .jual-tanya-isi{grid-template-rows:1fr}
.jual-tanya-isi>p{
  overflow:hidden;font-size:13.5px;line-height:1.7;color:var(--j-redup);
}
.jual-tanya-buka .jual-tanya-isi>p{padding-bottom:1.2rem}

/* ── jalur WhatsApp ── */
.jual-tombol-wa{display:inline-flex;align-items:center;gap:.5rem}
.jual-tombol-wa svg{width:1.05rem;height:1.05rem;flex:none}

/* Kaki halaman memesan ruang untuk tombol mengambang di atasnya.
   Tanpa ini, tombolnya duduk tepat di atas baris terakhir — dan yang
   tertutup adalah nama platform serta tahunnya. */
.jual-kaki-apung{padding-bottom:4.5rem}

/* Tombol mengambang. z-index di bawah pemutar video (.jual-pemutar),
   supaya ia tidak menempel di atas rekaman yang sedang diputar. */
.jual-apung{
  position:fixed;right:1.1rem;bottom:1.1rem;z-index:60;
  display:inline-flex;align-items:center;gap:.55rem;
  padding:.72rem 1.05rem;border-radius:99px;
  background:#25D366;color:#0A2E17;
  font-size:13px;font-weight:800;letter-spacing:-.01em;text-decoration:none;
  box-shadow:0 12px 30px -10px rgba(37,211,102,.85);
  transition:transform .2s var(--j-lengkung),box-shadow .2s;
}
.jual-apung:hover{transform:translateY(-2px);box-shadow:0 16px 36px -10px rgba(37,211,102,.95)}
.jual-apung svg{width:1.25rem;height:1.25rem;flex:none}
/* Di ponsel tinggal bulatannya. Pil berteks di sudut kanan bawah
   menutupi tombol terakhir tiap kartu pada lebar sempit. */
@media (max-width:640px){
  .jual-apung{padding:.85rem;gap:0}
  .jual-apung span{display:none}
}
@media (prefers-reduced-motion:reduce){
  .jual-apung,.jual-masalah-kartu,.jual-tanya-isi,.jual-tanya-kepala i{transition:none}
}

</style>
