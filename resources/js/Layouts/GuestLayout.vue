<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Wordmark from '../Components/Wordmark.vue';

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
 */
const media = usePage<any>().props.mediaMasuk ?? {};

const kurangiGerak = ref(
  typeof window !== 'undefined'
  && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true,
);

/** Waktu setempat, ditulis sekali saat halaman dibuka. */
const jam = new Intl.DateTimeFormat('id-ID', {
  hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short',
}).format(new Date());
</script>

<template>
  <div class="min-h-screen min-h-[100dvh] grid lg:grid-cols-[1.15fr_.85fr] bg-[#FBFAF7] text-[#1B2024]">
    <section class="relative overflow-hidden bg-[#0B1117] text-white p-8 sm:p-12 lg:p-14 flex flex-col justify-between min-h-[330px] lg:min-h-screen">
      <video
        v-if="media.video && !kurangiGerak"
        :src="media.video" :poster="media.poster ?? undefined"
        autoplay muted loop playsinline preload="metadata"
        aria-hidden="true"
        class="absolute inset-0 w-full h-full object-cover opacity-60"
      ></video>
      <img v-else :src="media.poster ?? '/brand/tambang.jpg'" alt="" aria-hidden="true"
           class="absolute inset-0 w-full h-full object-cover opacity-60">

      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(11,17,23,.58),rgba(11,17,23,.18)_36%,rgba(11,17,23,.95))]"></div>

      <!--
        Vinyet dan pendar hangat, bukan kisi.
        Kisi kotak yang saya pasang sebelumnya memang memberi pembanding
        gerakan, tetapi harganya terlalu mahal: polanya berulang rapi di
        atas rekaman dan membuat panelnya terbaca seperti kertas berpetak.
        Vinyet melakukan pekerjaan yang sama dengan cara berlawanan —
        menggelapkan tepi supaya mata jatuh ke tengah, tempat gerakannya
        berada — dan sekaligus menutupi bagian tepi rekaman yang paling
        banyak kehilangan detail saat dimampatkan.
        Pendarnya mengikuti arah cahaya pada rekamannya sendiri, jadi ia
        menyatu alih-alih menempel di atasnya.
      -->
      <div class="absolute inset-0 pointer-events-none"
           style="background-image:
             radial-gradient(58% 42% at 74% 12%, rgba(245,124,0,.20), transparent 68%),
             radial-gradient(120% 96% at 50% 50%, transparent 42%, rgba(11,17,23,.72) 100%)"></div>

      <div class="relative z-10 flex items-center justify-between gap-4">
        <Link href="/" class="w-fit"><Wordmark :tinggi="28" /></Link>

        <!-- Penanda sistem hidup: titik berdenyut dan jam setempat. -->
        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 backdrop-blur px-3 py-1.5
                     text-[10px] font-bold uppercase tracking-[.14em] text-white/70">
          <span class="relative flex w-1.5 h-1.5">
            <span class="absolute inline-flex w-full h-full rounded-full bg-[#22C55E] opacity-75 animate-ping"></span>
            <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-[#22C55E]"></span>
          </span>
          Sistem aktif · {{ jam }}
        </span>
      </div>

      <div class="relative z-10 max-w-xl mt-12 lg:mt-0">
        <p class="text-[10px] font-bold uppercase tracking-[.22em] text-white/50 mb-4">Delapan Aspek · Satu Sistem</p>
        <div class="w-14 h-0.5 bg-gradient-to-r from-[#F57C00] to-[#FF9800] mb-5"></div>
        <h1 class="font-serif text-4xl sm:text-5xl leading-tight max-w-md">Menjaga kinerja, membentuk <em class="not-italic text-[#FF9800]">masa depan</em>.</h1>
        <div class="mt-6 pl-4 border-l-[3px] border-[#F57C00] flex flex-col gap-1 font-extrabold uppercase tracking-wider text-sm">
          <span>Safe Today</span>
          <span>Sustainable Tomorrow</span>
          <span class="text-[#FF9800]">Innovation Always</span>
        </div>
        <p class="mt-6 text-[11px] leading-7 tracking-widest uppercase font-semibold text-white/45">Energy · Quality · Occupational Health · Hygiene · Safety · Environment · Engineering · Konservasi Minerba</p>
      </div>
    </section>

    <section class="flex items-center justify-center px-6 py-10 sm:px-12 lg:px-16">
      <div class="w-full max-w-md">
        <div class="font-extrabold text-2xl tracking-wide mb-8">E<span class="text-[#F57C00]">Q</span>OHSEE</div>
        <slot />
      </div>
    </section>
  </div>
</template>
