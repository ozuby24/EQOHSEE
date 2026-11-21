<script setup lang="ts">
import { RAG, n } from '../../energi';

const props = withDefaults(defineProps<{
  label: string; nilai: unknown; satuan?: string; desimal?: number;
  rag?: string | null; catatan?: string | null;
  arah?: { persen: number; baik: boolean } | null;
}>(), { satuan: '', desimal: 1, rag: null, catatan: null, arah: null });
</script>

<template>
  <article class="rounded-2xl bg-white border border-stone-100 shadow-card p-4 flex flex-col gap-1"
           :style="props.rag ? { borderTop: `3px solid ${RAG[props.rag]?.garis ?? '#78716C'}` } : {}">
    <div class="flex items-start justify-between gap-2">
      <p class="text-[11px] font-semibold text-stone-500">{{ props.label }}</p>
      <span v-if="props.rag" class="rounded-full px-1.5 py-0.5 text-[9.5px] font-bold whitespace-nowrap" :class="RAG[props.rag]?.kelas">
        {{ RAG[props.rag]?.label }}
      </span>
    </div>
    <p class="text-[20px] font-bold text-cam-ink leading-tight num">
      {{ n(props.nilai, props.desimal) }}<span class="text-[11.5px] font-semibold text-stone-400 ml-1">{{ props.satuan }}</span>
    </p>
    <p v-if="props.arah" class="text-[10.5px] font-semibold" :class="props.arah.baik ? 'text-emerald-700' : 'text-red-700'">
      {{ props.arah.persen >= 0 ? '▲' : '▼' }} {{ n(Math.abs(props.arah.persen), 1) }}% vs periode sama tahun lalu
    </p>
    <p v-if="props.catatan" class="text-[10.5px] text-stone-400">{{ props.catatan }}</p>
    <slot />
  </article>
</template>
