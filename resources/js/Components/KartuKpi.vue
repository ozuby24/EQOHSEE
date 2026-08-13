<script setup lang="ts">
/**
 * Kartu angka tunggal.
 *
 * Bilah di bawahnya hanya muncul bila ada rasio yang benar-benar
 * bermakna — bilah kosong memberi kesan data hilang.
 */
withDefaults(defineProps<{
  label: string;
  nilai: string | number;
  satuan?: string | null;
  ket?: string | null;
  warna?: string;
  rasio?: number | null;
}>(), {
  satuan: null,
  ket: null,
  warna: '#F57C00',
  rasio: null,
});
</script>

<template>
  <div class="kartu-lux rounded-2xl p-4">
    <div class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500">{{ label }}</div>
    <div class="mt-2 leading-none">
      <span class="stat stat-sm" :style="{ color: warna }">{{ nilai }}</span>
      <span v-if="satuan" class="text-[11px] font-semibold text-stone-400 ml-1">{{ satuan }}</span>
    </div>
    <div v-if="ket" class="text-[10.5px] text-stone-400 mt-1.5 leading-relaxed">{{ ket }}</div>
    <div v-if="rasio !== null" class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
      <div class="h-full rounded-full transition-all duration-700"
           :style="{ width: Math.max(0, Math.min(100, rasio * 100)) + '%', background: warna }"></div>
    </div>
  </div>
</template>
