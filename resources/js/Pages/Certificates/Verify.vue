<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{
  kode: string;
  c: {
    penerima: string;
    kursus: string;
    perusahaan: string;
    nomor: string;
    kodeVerifikasi: string;
    nilai: string | number;
    terbit?: string | null;
    ditandatangani: string;
  } | null;
}>();
</script>

<template>
  <Head title="Verifikasi Sertifikat" />

  <div v-if="c" class="bg-white rounded-2xl shadow-card border border-cam-lime/30 p-8 text-center animate-pop">
    <div class="w-16 h-16 mx-auto rounded-full lime-gradient shadow-glow grid place-items-center text-white text-[28px]">✓</div>
    <h1 class="font-display text-[22px] font-black text-cam-ink mt-4">Sertifikat Sah</h1>
    <p class="text-[12.5px] text-stone-500 mt-1">Data berikut tercatat pada sistem EQOHSEE.</p>

    <div class="text-left mt-6 space-y-2.5">
      <div v-for="row in [
        ['Nama penerima', c.penerima],
        ['Pelatihan', c.kursus],
        ['Perusahaan', c.perusahaan],
        ['Nomor', c.nomor],
        ['Kode verifikasi', c.kodeVerifikasi],
        ['Nilai akhir', c.nilai],
        ['Diterbitkan', c.terbit ?? '—'],
        ['Ditandatangani', c.ditandatangani],
      ]" :key="row[0]" class="flex items-start justify-between gap-4 border-b border-stone-100 pb-2 last:border-0">
        <span class="text-[11.5px] text-stone-400 shrink-0">{{ row[0] }}</span>
        <span class="text-[12.5px] font-semibold text-cam-ink text-right num">{{ row[1] }}</span>
      </div>
    </div>
  </div>

  <div v-else class="bg-white rounded-2xl shadow-card border border-stone-200 p-10 text-center">
    <div class="w-16 h-16 mx-auto rounded-full bg-red-50 grid place-items-center text-red-500 text-[28px]">×</div>
    <h1 class="font-display text-[22px] font-black text-cam-ink mt-4">Tidak Ditemukan</h1>
    <p class="text-[12.5px] text-stone-500 mt-2">Kode <span class="font-mono font-bold">{{ kode }}</span> tidak terdaftar pada sistem.</p>
  </div>
</template>
