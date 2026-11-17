<script setup lang="ts">
/** Ubin KPI dasbor: label, angka besar, keterangan; ikon bertinta lembut sesuai nada. */
import '../../../css/miners.css';
import { Link } from '@inertiajs/vue3';
import Ikon from './Ikon.vue';

withDefaults(defineProps<{
  label: string;
  angka: number | string;
  sub?: string | null;
  ikon: string;
  nada?: 'bahaya' | 'kritis' | 'aman' | 'aksen' | 'info';
  href?: string | null;
}>(), { sub: null, nada: 'info', href: null });
</script>

<template>
  <component :is="href ? Link : 'div'" :href="href ?? undefined" class="mn-kpi" :class="`mn-kpi-${nada}`">
    <span class="mn-kpi-atas">
      <span class="mn-kpi-label">{{ label }}</span>
      <span class="mn-zona-ikon"><Ikon :d="ikon" /></span>
    </span>
    <span class="mn-kpi-angka">{{ typeof angka === 'number' ? angka.toLocaleString('id-ID') : angka }}</span>
    <span v-if="sub" class="mn-kpi-sub">{{ sub }}</span>
  </component>
</template>
