<script setup lang="ts">
/** Paginasi "‹ 1 2 3 ›" Safe Track, dari tautan paginator Laravel. */
import '../../../css/miners.css';
import { Link } from '@inertiajs/vue3';
import type { TautanHalaman } from './zona';

defineProps<{ tautan: TautanHalaman[] }>();

/* Laravel menulis "&laquo; Previous" dan "Next &raquo;"; yang dipakai
   Safe Track hanya panah tunggal. */
const label = (l: string) => (l.includes('laquo') || l.includes('Previous') || l.includes('Sebelum'))
  ? '‹' : (l.includes('raquo') || l.includes('Next') || l.includes('Berikut')) ? '›' : l;
</script>

<template>
  <nav class="mn-halaman" aria-label="Halaman">
    <template v-for="(t, i) in tautan" :key="i">
      <Link v-if="t.url && !t.active" :href="t.url" preserve-scroll>{{ label(t.label) }}</Link>
      <span v-else :class="t.active ? 'mn-kini' : 'mn-mati'" :aria-current="t.active ? 'page' : undefined">{{ label(t.label) }}</span>
    </template>
  </nav>
</template>
