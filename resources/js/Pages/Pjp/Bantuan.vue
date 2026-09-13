<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PjpNav from '../../Components/PjpNav.vue';

/**
 * Tanya jawab modul.
 *
 * Isinya datang dari server, bukan ditulis di sini: penjelasannya
 * menyebut tanggal batas, bulan triwulan, dan ambang perhatian yang
 * hidup sebagai tetapan di model. Menyalinnya ke sisi peramban membuat
 * penjelasan berselisih dengan aturan yang dijelaskannya begitu salah
 * satunya diubah — dan penjelasan yang salah lebih buruk daripada tidak
 * ada penjelasan sama sekali.
 */
defineProps<{
  judul: string;
  subjudul: string;
  faq: Array<{ tanya: string; jawab: string }>;
  tautan: Record<string, string>;
}>();
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-[900px] mx-auto space-y-5">
    <div>
      <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-cam-orange">Minerba · Safety</p>
      <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul }}</h2>
      <p class="text-[12px] text-stone-500 mt-1">{{ subjudul }}</p>
    </div>

    <PjpNav :tautan="tautan" aktif="bantuan" />

    <div class="space-y-3">
      <details
        v-for="(item, i) in faq"
        :key="item.tanya"
        class="rounded-2xl bg-white border border-stone-100 shadow-card group"
        :open="i === 0"
      >
        <summary class="flex cursor-pointer items-center justify-between gap-4 p-4 text-[13px] font-bold text-stone-800">
          {{ item.tanya }}
          <svg class="h-4 w-4 shrink-0 text-stone-400 transition-transform duration-200 group-open:rotate-180"
               viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </summary>
        <p class="border-t border-stone-100 px-4 pb-4 pt-3 text-[12px] leading-relaxed text-stone-600">
          {{ item.jawab }}
        </p>
      </details>
    </div>
  </div>
</template>
