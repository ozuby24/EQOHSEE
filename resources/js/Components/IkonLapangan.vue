<script setup lang="ts">
import { computed } from 'vue';
import { IKON, type NamaIkon } from '../lapangan/ikon';

/* `jalur` untuk ikon modul yang jalurnya datang dari Menu.php. */
const p = withDefaults(defineProps<{ nama?: NamaIkon; jalur?: string | null; tebal?: number; ukuran?: number }>(), {
  tebal: 1.9,
  ukuran: undefined,
});

const d = computed(() => p.jalur ?? (p.nama ? IKON[p.nama] : ''));
</script>

<template>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="tebal"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
       :width="ukuran" :height="ukuran"><path :d="d" /></svg>
</template>
