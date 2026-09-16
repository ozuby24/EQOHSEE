<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const BANDS = [
  { min: 80, fill: '#5EAE38', label: 'Baik (≥80)' },
  { min: 60, fill: '#F0B429', label: 'Perlu Perhatian (60-79)' },
  { min: 40, fill: '#F0834A', label: 'Perlu Tindak Lanjut (40-59)' },
  { min: 0, fill: '#D03B3B', label: 'Kritis (<40)' },
];
function bandFor(v: number) {
  return BANDS.find((b) => v >= b.min) ?? BANDS[BANDS.length - 1];
}

const props = defineProps<{
  title: string;
  emptyMessage: string;
  noDataLabel: string;
  items: { id: number; label: string; value: number | null }[];
  hrefFor?: (item: { id: number }) => string;
}>();

const rows = computed(() => {
  const scored = props.items.filter((i) => i.value !== null).sort((a, b) => (a.value as number) - (b.value as number));
  const unscored = props.items.filter((i) => i.value === null).sort((a, b) => a.label.localeCompare(b.label));
  return [...scored, ...unscored];
});

function hrefOf(item: { id: number }) {
  return props.hrefFor ? props.hrefFor(item) : `/pjp/${item.id}`;
}
</script>

<template>
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1 mb-3">
      <h3 class="text-[13px] font-bold text-cam-ink">{{ title }}</h3>
      <span class="text-[11px] text-stone-400">Urut dari yang paling perlu ditindaklanjuti</span>
    </div>

    <p v-if="!rows.length" class="py-4 text-center text-[12.5px] text-stone-500">{{ emptyMessage }}</p>
    <div v-else class="space-y-2">
      <Link v-for="item in rows" :key="item.id" :href="hrefOf(item)"
            class="flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-3 rounded-xl px-2 py-2 sm:py-1.5 hover:bg-stone-50 transition"
            :title="item.value !== null ? `${item.label}: ${item.value}%` : `${item.label}: ${noDataLabel}`">
        <div class="flex items-center justify-between gap-2 sm:w-64 sm:shrink-0">
          <span class="text-[12.5px] text-stone-700">{{ item.label }}</span>
          <span class="sm:hidden shrink-0 text-[11px] font-semibold text-stone-600">
            {{ item.value !== null ? `${item.value}%` : noDataLabel }}
          </span>
        </div>
        <span class="h-3 w-full sm:flex-1 rounded-full bg-stone-100 overflow-hidden">
          <span v-if="item.value !== null" class="block h-full rounded-full transition-all duration-700"
                :style="{ width: `${Math.max(item.value, 2)}%`, background: bandFor(item.value).fill }"></span>
        </span>
        <span class="hidden sm:block shrink-0 sm:w-28 text-right text-[11px] font-semibold text-stone-600">
          {{ item.value !== null ? `${item.value}%` : noDataLabel }}
        </span>
      </Link>
    </div>

    <div class="flex flex-wrap gap-x-4 gap-y-1 mt-4 pt-3 border-t border-stone-100">
      <span v-for="band in BANDS" :key="band.label" class="flex items-center gap-1.5 text-[11px] text-stone-500">
        <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: band.fill }"></span>
        {{ band.label }}
      </span>
    </div>
  </div>
</template>
