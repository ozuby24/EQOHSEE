<script setup lang="ts">
/**
 * Ubin ringkas berikon — info-card Safe Track ("Manpower",
 * "Rujukan | Outstanding", "Open | Total"): ikon bertinta lembut di
 * kiri, label kecil dan angka besar di kanannya.
 */
import '../../../css/miners.css';
import Ikon from './Ikon.vue';
import { IKON } from './ikon';

withDefaults(defineProps<{
  judul: string;
  keterangan?: string | null;
  angka?: number | string | null;
  warna?: 'biru' | 'hijau' | 'oranye';
}>(), { keterangan: null, angka: null, warna: 'biru' });
</script>

<template>
  <div class="mn-info" :class="`mn-info-${warna}`">
    <span class="mn-info-ikon" aria-hidden="true"><Ikon :d="IKON.orang" /></span>
    <div class="min-w-0">
      <h3>{{ judul }}<small v-if="keterangan"> · {{ keterangan }}</small></h3>
      <span class="mn-info-angka">{{ angka ?? '' }}</span>
    </div>
  </div>
</template>
