<script setup lang="ts">
/** Bilah sebaran zona satu perusahaan — .mb-bar Safe Track. */
import '../../../css/miners.css';
import { computed } from 'vue';
import { ZONA } from './zona';

const props = defineProps<{
  n: { expired?: number; kritis?: number; waspada?: number; aman?: number; kosong?: number; nonaktif?: number };
}>();

const ruas = computed(() => {
  const urut = ['expired', 'kritis', 'waspada', 'aman', 'kosong', 'nonaktif'] as const;
  const total = urut.reduce((t, k) => t + (props.n[k] ?? 0), 0) || 1;

  return urut
    .filter((k) => (props.n[k] ?? 0) > 0)
    .map((k) => ({
      kunci: k,
      lebar: `${((props.n[k] ?? 0) / total) * 100}%`,
      judul: `${k === 'nonaktif' ? 'Non-aktif' : ZONA[k].pendek}: ${props.n[k]}`,
    }));
});
</script>

<template>
  <div class="mn-bar" role="img" :aria-label="ruas.map((r) => r.judul).join(', ') || 'Tidak ada data'">
    <span v-for="r in ruas" :key="r.kunci" :class="`mn-bg-${r.kunci}`" :style="{ width: r.lebar }" :title="r.judul" />
  </div>
</template>
