<script setup lang="ts">
import { computed } from 'vue';
import { bolehLatarVideo } from '../latarVideo';
import { Link } from '@inertiajs/vue3';
import { propHalaman } from '../halaman';
import Wordmark from '../Components/Wordmark.vue';
import '../../css/masuk.css';

/**
 * Latar halaman masuk.
 *
 * Sebelumnya sebuah foto 900 piksel diregangkan memenuhi panel setinggi
 * layar. Pada layar berkerapatan ganda ia diperbesar sekitar tiga kali
 * lipat, dan pecahnya terlihat jelas — pada layar pertama yang dilihat
 * pengguna baru.
 *
 * Rekaman dipakai bila berkasnya ada; bila tidak, posternya, dan bila
 * itu pun belum ada, foto lama tetap menjadi jaring pengaman supaya
 * halaman masuk tidak pernah tampil tanpa latar sama sekali.
 *
 * Rekamannya dipakai di SEMUA ukuran layar, ponsel termasuk. Sempat
 * dibatasi ke layar lebar demi berkasnya yang hampir lima megabita,
 * tetapi gerakan itu memang yang diminta ada di halaman ini — dan
 * poster diam pada panel yang di sebelahnya penuh gerak terbaca sebagai
 * rekaman yang gagal dimuat, bukan sebagai penghematan.
 *
 * Posternya tetap bingkai pertama rekaman yang sama, jadi tidak ada
 * lompatan gambar saat rekamannya mulai berjalan.
 *
 * ── BAHASA TAMPILANNYA MENGIKUTI HALAMAN DEPAN ──
 *
 * Judul Archivo yang dipersempit, label IBM Plex Mono, garis rambut,
 * tombol jingga berteks ink (masuk.css). Sebelumnya halaman ini berjudul
 * serif, bersemboyan bahasa Inggris, dan menulis merek EQOHSEE dua kali
 * di layar lebar — orang yang datang dari "Masuk ke Platform" di halaman
 * depan melihat identitas yang berbeda pada dua layar berurutan.
 */
const prop = propHalaman();
const media = computed<any>(() => prop.mediaMasuk ?? {});

/**
 * Pesan kilat pada halaman yang belum masuk.
 *
 * Sebelumnya bilah ini HANYA ada di AppLayout — yaitu di balik login.
 * Akibatnya setiap pesan yang ditujukan kepada orang yang belum masuk
 * hilang tanpa jejak, dan yang paling merugikan adalah pesan sesudah
 * verifikasi email berhasil: sesinya ditutup, orangnya dipulangkan ke
 * halaman masuk, dan halaman itu menyambutnya persis seperti kalau
 * kodenya salah.
 */
const kilat = computed<any>(() => prop.kilat ?? {});


/* Syaratnya tidak lagi hanya prefers-reduced-motion: masuk.mp4
   berukuran 5,6 MB, dan halaman masuk adalah halaman yang dibuka setiap
   orang setiap hari — termasuk dari ponsel di site. Lihat latarVideo.ts.

   Dibaca sekali, bukan lewat `cocok()` yang reaktif: nilai yang
   berbalik saat jendela diperlebar justru memulai unduhan yang baru
   saja berhasil dihindari. */
const pakaiRekaman = computed(() =>
  Boolean(media.value.video) && bolehLatarVideo());

/** Jam setempat, ditulis sekali saat halaman dibuka. Tanpa tanggal:
 *  di ponsel, "27 SEP, 15.58" mematahkan penandanya menjadi dua baris. */
const jam = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(new Date());

/** Delapan aspek, huruf dan nama pendeknya — urutan akronim EQOHSEE + K. */
const ASPEK: [string, string][] = [
  ['E', 'Energy'], ['Q', 'Quality'], ['O', 'Occ. Health'], ['H', 'Hygiene'],
  ['S', 'Safety'], ['E', 'Environment'], ['E', 'Engineering'], ['+K', 'Konservasi'],
];
</script>

<template>
  <div class="ms min-h-screen min-h-[100dvh] grid grid-rows-[auto_1fr] lg:grid-rows-1 lg:grid-cols-[1.12fr_.88fr] bg-white">
    <!--
      `pendar-rekaman` memasang dua lapisan: pendar hangat yang bergerak
      perlahan dan vinyet yang diam. Keduanya di CSS, bukan inline, supaya
      halaman depan dan halaman masuk memakai bahasa gerak yang sama —
      dan supaya keduanya ikut berhenti pada satu tempat ketika pengguna
      meminta gerakan dikurangi.
    -->
    <!-- Layar lebar: panelnya menempel setinggi layar. Tanpa itu, pada
         formulir panjang (Daftar) panelnya ikut memanjang dan judulnya
         jatuh jauh di bawah lipatan, menyisakan foto kosong. -->
    <!-- Di ponsel panelnya menjadi foto setinggi sepertiga layar dengan
         lembar putih yang menumpuk di bawahnya (rancangan seluler 1a):
         formulirnya di jangkauan ibu jari, bukan di bawah satu layar
         penuh kalimat pembuka. -->
    <section class="pendar-rekaman bg-[#0B1117] text-white px-5 pt-5 pb-12 sm:px-10 sm:pt-8 sm:pb-12 lg:px-14 lg:py-12 flex flex-col justify-between min-h-[290px] sm:min-h-[360px] lg:sticky lg:top-0 lg:self-start lg:h-screen lg:h-[100dvh]">
      <video
        v-if="pakaiRekaman"
        :src="media.video" :poster="media.poster ?? undefined"
        autoplay muted loop playsinline preload="metadata"
        aria-hidden="true"
        class="absolute inset-0 w-full h-full object-cover opacity-55"
      ></video>
      <img v-else :src="media.poster ?? '/brand/tambang.jpg'" alt="" aria-hidden="true"
           class="absolute inset-0 w-full h-full object-cover opacity-55">

      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(11,17,23,.62),rgba(11,17,23,.2)_34%,rgba(11,17,23,.96))]"></div>

      <div class="relative z-10 flex items-center justify-between gap-3">
        <Link href="/" class="w-fit" aria-label="EQOHSEE — ke halaman depan"><Wordmark :tinggi="28" /></Link>

        <!-- Penanda sistem hidup: titik hijau dan jam setempat, bergaya
             chip status di foto hero halaman depan. -->
        <span class="ms-mono inline-flex items-center gap-2 whitespace-nowrap rounded-md border border-white/15 bg-[#0B1117]/80 px-2.5 py-1.5 text-[11px] tracking-[.06em] text-white/80">
          <span class="relative flex w-1.5 h-1.5">
            <span class="absolute inline-flex w-full h-full rounded-full bg-[#22C55E] opacity-75 motion-safe:animate-ping"></span>
            <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
          </span>
          <span class="hidden sm:inline">SISTEM AKTIF ·</span><span class="sm:hidden">AKTIF ·</span> {{ jam }}
        </span>
      </div>

      <div class="relative z-10 mt-10 lg:mt-0 max-w-[560px]">
        <p class="ms-mata text-white/75">Platform keselamatan pertambangan</p>
        <h1 class="ms-h1">Satu akun untuk seluruh modul <em>dan site.</em></h1>
        <p class="hidden sm:block mt-5 max-w-[460px] text-[15.5px] leading-relaxed text-white/75">
          Laporan bahaya, inspeksi, izin kerja, audit SMKP, dan kinerja tenaga kerja dalam satu
          basis data — dengan akun yang diberikan admin perusahaan Anda.
        </p>
        <div class="hidden sm:grid ms-huruf mt-8" role="img"
             :aria-label="'Delapan aspek EQOHSEE: ' + ASPEK.map(([, n]) => n).join(', ')">
          <b v-for="([h], i) in ASPEK" :key="i" :class="{ ini: h === 'S' }" aria-hidden="true">{{ h }}</b>
        </div>
        <p class="hidden sm:block mt-3 ms-mono text-[11px] tracking-[.06em] text-white/50">DELAPAN ASPEK · SATU SISTEM</p>
      </div>
    </section>

    <section class="relative z-10 -mt-6 sm:mt-0 rounded-t-[20px] sm:rounded-none bg-white flex items-start sm:items-center justify-center px-5 pt-7 pb-10 sm:py-12 sm:px-10 lg:px-14">
      <div class="w-full max-w-[420px]">
        <!-- role=status, bukan alert: kabarnya baik, dan alert merebut
             pembacaan di tengah orang membaca judul halamannya. -->
        <div v-if="kilat.sukses" role="status"
             class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3
                    flex items-start gap-2.5 text-emerald-800">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
               stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 mt-0.5 shrink-0"
               aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
          <p class="text-[13px] font-semibold leading-relaxed">{{ kilat.sukses }}</p>
        </div>

        <div v-if="kilat.galat" role="alert"
             class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
          <p class="text-[13px] font-semibold leading-relaxed">{{ kilat.galat }}</p>
        </div>

        <slot />

        <p class="mt-8 pt-5 border-t border-[#E2E6EB] ms-mono text-[11.5px] tracking-[.06em] text-[#5E6875] flex flex-wrap justify-between gap-x-4 gap-y-2">
          <Link href="/" class="hover:text-[#0B1117]">← EQOHSEE.ID</Link>
          <a href="/kebijakan-privasi" class="hover:text-[#0B1117]">KEBIJAKAN PRIVASI</a>
        </p>
      </div>
    </section>
  </div>
</template>
