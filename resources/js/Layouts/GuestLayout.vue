<script setup lang="ts">
import { computed } from 'vue';
import { bolehLatarVideo } from '../latarVideo';
import { Link } from '@inertiajs/vue3';
import { propHalaman } from '../halaman';
import Wordmark from '../Components/Wordmark.vue';

/**
 * Kerangka halaman tamu: masuk, daftar, lupa sandi, dua faktor.
 *
 * Dua bidang. Kiri: rekaman tambang dengan judul berhuruf Archivo —
 * muka huruf yang sama dengan halaman depan, sehingga orang yang datang
 * dari sana tidak merasa pindah situs. Kanan: formulirnya, di atas
 * bidang putih tanpa hiasan; yang dikerjakan di sini satu hal saja, dan
 * halaman yang menjual dirinya di samping kotak sandi terbaca sebagai
 * halaman yang belum yakin akan dipakai.
 *
 * Nama merek digambar SEKALI, pada panel kiri. Sebelumnya ia dicetak lagi
 * di atas formulir, dan dua wordmark yang sama pada satu layar terbaca
 * sebagai templat yang belum dirapikan.
 *
 * Latar halaman masuk: rekaman bila berkasnya ada, posternya bila tidak,
 * dan foto merek sebagai jaring pengaman terakhir — halaman ini tidak
 * pernah tampil tanpa latar. Lihat latarVideo.ts untuk syarat pemutaran
 * rekaman 5,6 MB itu di jaringan site.
 */
const prop = propHalaman();
const media = computed<any>(() => prop.mediaMasuk ?? {});

/**
 * Pesan kilat pada halaman yang belum masuk — mis. sesudah verifikasi
 * email berhasil, orangnya dipulangkan ke sini dan harus membaca bahwa
 * yang barusan terjadi adalah keberhasilan, bukan kesalahan kode.
 */
const kilat = computed<any>(() => prop.kilat ?? {});

/* Dibaca sekali, bukan reaktif: nilai yang berbalik saat jendela
   diperlebar justru memulai unduhan yang baru saja dihindari. */
const pakaiRekaman = computed(() => Boolean(media.value.video) && bolehLatarVideo());

/** Jam setempat, ditulis sekali saat halaman dibuka. */
const jam = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date());
const tanggal = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short' }).format(new Date());

const ASPEK = ['Energy', 'Quality', 'Occupational Health', 'Hygiene', 'Safety', 'Environment', 'Engineering', 'Konservasi Minerba'];
</script>

<template>
  <div class="tamu min-h-screen min-h-[100dvh] grid lg:grid-cols-[1.1fr_.9fr] bg-white text-[#0F1720]">
    <!-- ══════════ PANEL KIRI: rekaman + pernyataan merek ══════════
         Di ponsel menjadi foto setinggi sepertiga layar dengan lembar
         formulir yang menumpuk di bawahnya: formulirnya di jangkauan ibu
         jari, bukan di bawah satu layar penuh kalimat pembuka. -->
    <section class="tamu-panel pendar-rekaman bg-[#0B1117] text-white px-6 pt-5 pb-14 sm:p-12 lg:p-14 flex flex-col justify-between min-h-[300px] sm:min-h-[340px] lg:min-h-screen">
      <video v-if="pakaiRekaman" :src="media.video" :poster="media.poster ?? undefined"
             autoplay muted loop playsinline preload="metadata" aria-hidden="true"
             class="absolute inset-0 w-full h-full object-cover opacity-60"></video>
      <img v-else :src="media.poster ?? '/brand/tambang.jpg'" alt="" aria-hidden="true"
           class="absolute inset-0 w-full h-full object-cover opacity-60">

      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(11,17,23,.62),rgba(11,17,23,.22)_36%,rgba(11,17,23,.96))]"></div>

      <div class="relative z-10 flex items-center justify-between gap-3">
        <Link href="/" class="w-fit" aria-label="EQOHSEE — ke halaman depan"><Wordmark :tinggi="28" /></Link>

        <!-- Penanda sistem hidup. Satu baris, tidak pernah patah: di
             ponsel bagian tanggalnya disembunyikan supaya kepingnya tetap
             sekecil kalimatnya. -->
        <span class="tamu-status" aria-label="Sistem aktif">
          <span class="relative flex w-1.5 h-1.5">
            <span class="absolute inline-flex w-full h-full rounded-full bg-[#22C55E] opacity-75 animate-ping"></span>
            <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
          </span>
          <span>Sistem aktif</span>
          <span class="hidden sm:inline">· {{ tanggal }}, {{ jam }}</span>
        </span>
      </div>

      <!-- Ponsel: satu kalimat, bukan manifesto. -->
      <div class="relative z-10 sm:hidden mt-10">
        <p class="tamu-mata">Platform keselamatan tambang</p>
        <h1 class="huruf-merek mt-2 text-[34px] text-white">Satu akun untuk seluruh modul dan site.</h1>
      </div>

      <div class="relative z-10 max-w-xl mt-12 lg:mt-0 hidden sm:block">
        <p class="tamu-mata">Platform keselamatan pertambangan terpadu</p>
        <h1 class="huruf-merek mt-5 text-[clamp(34px,min(4.2vw,7.6vh),62px)] text-white max-w-[13ch]">
          Keselamatan tambang, <span class="text-[#FF9800]">terukur</span> dan terbukti.
        </h1>
        <p class="mt-6 max-w-md text-[15px] leading-relaxed text-white/72">
          Pembelajaran, inspeksi, izin kerja, audit SMKP, hingga sertifikasi —
          satu akun, satu basis data, mengikuti regulasi pertambangan Indonesia.
        </p>

        <div class="mt-9 pl-4 border-l-2 border-[#F57C00] flex flex-col gap-1">
          <span class="tamu-semboyan">Safe Today</span>
          <span class="tamu-semboyan">Sustainable Tomorrow</span>
          <span class="tamu-semboyan text-[#FF9800]">Innovation Always</span>
        </div>

        <ul class="tamu-aspek" aria-label="Delapan aspek EQOHSEE">
          <li v-for="a in ASPEK" :key="a">{{ a }}</li>
        </ul>
      </div>
    </section>

    <!-- ══════════ PANEL KANAN: formulir ══════════ -->
    <section class="relative z-10 -mt-7 sm:mt-0 rounded-t-[26px] sm:rounded-none bg-white flex items-start sm:items-center justify-center px-6 pt-7 pb-10 sm:py-12 sm:px-12 lg:px-16">
      <div class="w-full max-w-md">
        <!-- role=status, bukan alert: kabarnya baik, dan alert merebut
             pembacaan di tengah orang membaca judul halamannya. -->
        <div v-if="kilat.sukses" role="status"
             class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 flex items-start gap-2.5 text-emerald-800">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
          <p class="text-[12.5px] font-semibold leading-relaxed">{{ kilat.sukses }}</p>
        </div>

        <div v-if="kilat.galat" role="alert"
             class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
          <p class="text-[12.5px] font-semibold leading-relaxed">{{ kilat.galat }}</p>
        </div>

        <slot />

        <p class="tamu-kaki">
          <span>© {{ new Date().getFullYear() }} EQOHSEE</span>
          <a href="/kebijakan-privasi">Kebijakan privasi</a>
        </p>
      </div>
    </section>
  </div>
</template>

<style>
/* Gaya kerangka tamu — TIDAK scoped, supaya halaman anak (masuk, daftar,
   lupa sandi) dapat memakai kelas yang sama: satu bentuk label, satu
   bentuk isian, satu bentuk tombol di seluruh pintu masuk. */
.tamu-panel{ position:relative; isolation:isolate; overflow:hidden; }
/* Layar lebar: panelnya menempel setinggi layar. Tanpa itu, pada
   formulir panjang (Daftar) panelnya ikut memanjang setinggi formulir,
   judulnya jatuh jauh di bawah lipatan, dan yang tersisa di layar
   pertama hanya foto kosong. Ditulis di sini, bukan lewat lg:sticky:
   aturan di atas dimuat sesudah utilitas Tailwind dan akan menimpanya. */
@media (min-width:1024px){
  .tamu-panel{ position:sticky; top:0; align-self:start; height:100vh; height:100dvh; min-height:0; }
}
.tamu-mata{
  display:flex; align-items:center; gap:10px;
  font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:11.5px; letter-spacing:.1em;
  text-transform:uppercase; color:rgba(255,255,255,.78);
}
.tamu-mata::before{ content:''; width:22px; height:2px; background:#F57C00; flex:none; }
.tamu-status{
  display:inline-flex; align-items:center; gap:8px; white-space:nowrap; flex:none;
  border-radius:999px; border:1px solid rgba(255,255,255,.16); background:rgba(255,255,255,.06);
  backdrop-filter:blur(6px); padding:6px 12px;
  font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:10.5px; letter-spacing:.08em;
  text-transform:uppercase; color:rgba(255,255,255,.78);
}
.tamu-semboyan{
  font-family:'Archivo',system-ui,sans-serif; font-weight:700; font-stretch:88%;
  font-size:14px; letter-spacing:.12em; text-transform:uppercase;
}
/* Jarak atasnya ditulis di sini, bukan lewat kelas mt-*: gaya komponen
   dimuat SESUDAH utilitas Tailwind, jadi `margin:0` di sini akan
   mengalahkan mt-8 pada markup — dan daftarnya menempel ke semboyan. */
.tamu-aspek{
  display:flex; flex-wrap:wrap; gap:6px 10px; margin:36px 0 0; padding:0; list-style:none;
  font-family:'IBM Plex Mono',ui-monospace,monospace; font-size:10.5px; letter-spacing:.06em;
  text-transform:uppercase; color:rgba(255,255,255,.5);
}
/* Pemisah di UJUNG tiap butir, bukan di awal: bila barisnya patah, titiknya
   menutup baris sebelumnya alih-alih membuka baris baru. */
.tamu-aspek li:not(:last-child)::after{ content:'·'; margin-left:10px; color:rgba(255,255,255,.3); }
.tamu-kaki{
  display:flex; justify-content:space-between; gap:12px; margin-top:44px;
  font-size:11.5px; color:#8A93A0;
}
.tamu-kaki a{ color:inherit; text-decoration:underline; text-underline-offset:3px; }
.tamu-kaki a:hover{ color:#0B1117; }
@media (max-width:639.98px){ .tamu-kaki{ margin-top:32px; } }
/* Layar lebar yang pendek (tablet mendatar 1176×620): isi panelnya lebih
   tinggi daripada ruang di antara logo dan tepi bawah, dan label judulnya
   menempel ke baris logo. Daftar aspek — yang juga tertulis di halaman
   depan — dilepas di sini lebih dulu. Ditulis PALING AKHIR: di atas
   aturan dasar .tamu-aspek ia kalah urutan dan tidak berlaku. */
@media (min-width:1024px) and (max-height:760px){
  .tamu-aspek{ display:none; }
}
</style>
