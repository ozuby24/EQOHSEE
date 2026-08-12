<script setup lang="ts">
/**
 * Hasil evaluasi SOP.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanHasilSop } from '../../types';

defineProps<HalamanHasilSop>();
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-md mx-auto">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-9 text-center animate-pop">
      <div class="w-24 h-24 mx-auto rounded-full grid place-items-center"
           :class="hasil.lulus ? 'lime-gradient shadow-glow' : 'bg-stone-100'">
        <span class="stat stat-lg" :class="hasil.lulus ? 'text-white' : 'text-stone-400'">{{ hasil.nilai }}</span>
      </div>

      <h2 class="text-[19px] font-extrabold mt-5 text-cam-ink">
        {{ hasil.lulus ? 'Lulus!' : 'Belum lulus' }}
      </h2>
      <p class="text-[12.5px] text-stone-400 mt-1.5">
        Benar {{ hasil.benar }} dari {{ hasil.total }} soal · batas lulus {{ hasil.nilaiLulus }}
      </p>
      <p class="text-[11px] text-stone-300 mt-3">{{ hasil.evaluasi }}</p>

      <div class="flex gap-2.5 justify-center mt-7">
        <Link :href="tautan.ulangi"
              class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold
                     text-stone-600 hover:bg-stone-50 transition">Ulangi</Link>
        <Link :href="tautan.daftar"
              class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                     font-bold hover:brightness-105 transition">Daftar Evaluasi</Link>
      </div>
    </div>
  </div>
</template>
