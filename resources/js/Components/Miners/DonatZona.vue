<script setup lang="ts">
/**
 * Donat sebaran zona masa berlaku. Memakai donat SVG rumah EQOHSEE
 * (Grafik/Donat.vue, keterangan berangka + persen) dengan warna zona
 * Miners — bukan Chart.js: donat rumah sudah menangani mode gelap,
 * sorotan, dan keterangan yang tidak mengandalkan warna.
 */
import { computed } from 'vue';
import Donat from '@/Grafik/Donat.vue';
import { ZONA, URUTAN_ZONA, type KunciZona } from './zona';
import { WARNA_ZONA } from './ikon';

const props = withDefaults(defineProps<{
  n: Partial<Record<KunciZona, number>>;
  tengahLabel?: string;
}>(), { tengahLabel: 'manpower' });

const bagian = computed(() => URUTAN_ZONA
  .map((z) => ({ label: ZONA[z].pendek, nilai: props.n[z] ?? 0, warna: WARNA_ZONA[z] }))
  .filter((b) => b.nilai > 0));
</script>

<template>
  <Donat :bagian="bagian" :tengah-label="tengahLabel" />
</template>
