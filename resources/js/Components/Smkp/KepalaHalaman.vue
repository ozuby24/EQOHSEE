<script setup lang="ts">
/*
 * Kepala halaman audit SMKP (porting Aurorasys). Kop kerangka EQOHSEE
 * ditolak untuk rute smkp.* dengan `kop: false` (HandleInertiaRequests),
 * karena kepala inilah yang membawa judul, jejak, dan tombol aksinya.
 */
import { Link } from '@inertiajs/vue3';
import Ikon from './Ikon.vue';
import Kilat from './Kilat.vue';

defineProps<{
  judul: string;
  sub?: string | null;
  eyebrow?: string | null;
  jejak?: Array<{ label: string; url?: string }>;
}>();
</script>

<template>
  <header class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3 mb-5">
    <div class="min-w-0">
      <nav v-if="jejak?.length" class="flex flex-wrap items-center gap-1 text-[12px] text-slate-400 mb-2" aria-label="Jejak">
        <template v-for="(j, i) in jejak" :key="i">
          <Link v-if="j.url" :href="j.url" class="hover:text-brand-600 transition">{{ j.label }}</Link>
          <span v-else class="text-slate-500">{{ j.label }}</span>
          <Ikon v-if="i < jejak.length - 1" nama="chevron-right" class="w-3.5 h-3.5" />
        </template>
      </nav>
      <p v-if="eyebrow" class="eyebrow mb-1.5">{{ eyebrow }}</p>
      <h1 class="text-[20px] sm:text-[24px] font-bold tracking-tight text-navy-900 leading-tight [text-wrap:balance]">{{ judul }}</h1>
      <p v-if="sub" class="text-[13px] text-slate-500 mt-1 max-w-3xl leading-relaxed">{{ sub }}</p>
    </div>
    <div v-if="$slots.default" class="flex flex-wrap items-center gap-2"><slot /></div>
    <Kilat />
  </header>
</template>
