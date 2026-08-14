<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Wordmark from '../Components/Wordmark.vue';
</script>

<template>
  <div class="min-h-screen min-h-[100dvh] grid lg:grid-cols-[1.15fr_.85fr] bg-[#FBFAF7] text-[#1B2024]">
    <section class="relative overflow-hidden bg-[#0B1117] text-white p-8 sm:p-12 lg:p-14 flex flex-col justify-between min-h-[330px] lg:min-h-screen">
      <img src="/brand/tambang.jpg" alt="" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover opacity-60">
      <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(11,17,23,.58),rgba(11,17,23,.18)_36%,rgba(11,17,23,.95))]"></div>

      <Link href="/" class="relative z-10 w-fit"><Wordmark :tinggi="28" /></Link>

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
