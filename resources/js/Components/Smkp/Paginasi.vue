<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ tautan: Array<{ url: string | null; label: string; active: boolean }>; dari?: number | null; sampai?: number | null; total?: number }>();

function label(l: string) {
  return l.replace('&laquo; Previous', '‹').replace('Next &raquo;', '›').replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>

<template>
  <div v-if="tautan.length > 3" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-t border-slate-100">
    <p class="text-[12px] text-slate-500"><template v-if="total">Menampilkan {{ dari }}–{{ sampai }} dari {{ total }}</template></p>
    <div class="flex flex-wrap gap-1">
      <template v-for="(l, i) in tautan" :key="i">
        <Link v-if="l.url" :href="l.url" preserve-scroll
              class="min-w-[34px] h-[34px] px-2.5 grid place-items-center rounded-lg text-[12.5px] font-semibold transition"
              :class="l.active ? 'bg-navy-900 text-white' : 'text-slate-600 hover:bg-slate-100'">{{ label(l.label) }}</Link>
        <span v-else class="min-w-[34px] h-[34px] px-2.5 grid place-items-center rounded-lg text-[12.5px] text-slate-300">{{ label(l.label) }}</span>
      </template>
    </div>
  </div>
</template>
