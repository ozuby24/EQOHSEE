<script setup lang="ts">
/** Ikon garis modul Energi & Karbon — isinya dari ikon.ts (Lucide, ISC). */
import { IKON } from './ikon';

withDefaults(defineProps<{ nama: string; ukuran?: number; tebal?: number }>(), { ukuran: 20, tebal: 2 });
</script>

<template>
  <!-- eslint-disable-next-line vue/no-v-html — isinya konstanta ikon.ts, bukan masukan pengguna -->
  <svg :width="ukuran" :height="ukuran" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="tebal"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="IKON[nama] ?? IKON.activity" />
</template>
