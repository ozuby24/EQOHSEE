<script setup lang="ts">
import { computed } from 'vue';

const STATUS_OPTIONS: Record<string, string> = {
  aktif: 'Aktif Dipantau',
  perlu_tindak_lanjut: 'Perlu Tindak Lanjut',
  tidak_aktif: 'Tidak Aktif',
};
const STATUS_FILL: Record<string, string> = {
  aktif: '#5EAE38',
  perlu_tindak_lanjut: '#F0B429',
  tidak_aktif: '#A8B0B8',
};
const ORDER = ['aktif', 'perlu_tindak_lanjut', 'tidak_aktif'];

const props = defineProps<{ title: string; counts: Record<string, number> }>();
const total = computed(() => ORDER.reduce((sum, k) => sum + (props.counts[k] ?? 0), 0));
</script>

<template>
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex items-baseline justify-between mb-3">
      <h3 class="text-[13px] font-bold text-cam-ink">{{ title }}</h3>
      <span class="text-[11.5px] text-stone-400">{{ total }} PJP</span>
    </div>
    <div v-if="total > 0" class="flex h-5 w-full gap-[3px] overflow-hidden rounded-full bg-stone-100">
      <div v-for="key in ORDER" :key="key" v-show="(counts[key] ?? 0) > 0"
           class="h-full first:rounded-l-full last:rounded-r-full"
           :style="{ flexGrow: counts[key] ?? 0, flexBasis: 0, background: STATUS_FILL[key] }"></div>
    </div>
    <div v-else class="h-5 rounded-full bg-stone-100"></div>
    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-4">
      <div v-for="key in ORDER" :key="key" class="flex items-center gap-2 text-[12.5px]">
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: STATUS_FILL[key] }"></span>
        <span class="text-stone-600">{{ STATUS_OPTIONS[key] }}</span>
        <span class="ml-auto font-bold text-cam-ink">{{ counts[key] ?? 0 }}</span>
      </div>
    </dl>
  </div>
</template>
