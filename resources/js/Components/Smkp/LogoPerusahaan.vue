<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ logo?: string | null; nama?: string | null; ukuran?: string }>(), {
  logo: null, nama: '', ukuran: 'w-10 h-10',
});

const inisial = computed(() => {
  const abaikan = ['pt', 'cv', 'tbk', 'persero'];
  return (props.nama ?? '').split(/\s+/)
    .map((k) => k.replace(/[^A-Za-z]/g, ''))
    .filter((k) => k && !abaikan.includes(k.toLowerCase()))
    .slice(0, 2).map((k) => k[0].toUpperCase()).join('') || 'PT';
});
</script>

<template>
  <span class="shrink-0 grid place-items-center rounded-xl bg-white border border-slate-200 overflow-hidden" :class="ukuran">
    <img v-if="logo" :src="logo" :alt="nama ?? ''" class="max-w-[86%] max-h-[86%] object-contain" loading="lazy">
    <span v-else class="font-bold text-[11px] tracking-wide text-navy-700">{{ inisial }}</span>
  </span>
</template>
