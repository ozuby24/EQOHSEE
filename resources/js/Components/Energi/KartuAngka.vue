<script setup lang="ts">
/*
  Angka utama modul energi, digambar dengan ubin bersama EQOHSEE
  (Components/UbinAngka) supaya serupa dengan dasbor modul lain. Warna
  ubin mengikuti status terhadap target; satuannya ikut di label.
*/
import { computed, h } from 'vue';
import UbinAngka from '../UbinAngka.vue';
import { n, rp } from '../../energi';

const props = withDefaults(defineProps<{
  label: string; nilai: unknown; satuan?: string; desimal?: number;
  rag?: string | null; catatan?: string | null;
  arah?: { persen: number; baik: boolean } | null;
  ikon?: string | null;
}>(), { satuan: '', desimal: 1, rag: null, catatan: null, arah: null, ikon: null });

const NADA: Record<string, 'baik' | 'ingat' | 'gawat' | 'netral'> = { hijau: 'baik', kuning: 'ingat', merah: 'gawat', netral: 'netral' };

const angka = computed(() => props.satuan === 'Rp' ? rp(props.nilai) : n(props.nilai, props.desimal));
const label = computed(() => props.satuan && props.satuan !== 'Rp' ? `${props.label} (${props.satuan})` : props.label);
const catatan = computed(() => {
  const bagian: string[] = [];
  if (props.arah) bagian.push(`${props.arah.persen >= 0 ? '+' : '−'}${n(Math.abs(props.arah.persen), 1)}% thn lalu`);
  if (props.catatan) bagian.push(props.catatan);
  return bagian.join(' · ') || null;
});

const IKON: Record<string, string[]> = {
  energi: ['M13 2 4 14h7l-1 8 10-13h-7l0-7Z'],
  emisi:  ['M7 18h10a4 4 0 0 0 .5-7.97A6 6 0 0 0 6.1 9.1 4.5 4.5 0 0 0 7 18Z'],
  uang:   ['M3.5 7h17v10h-17z', 'M12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z'],
  persen: ['m5 19 14-14', 'M7.5 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm9 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z'],
  tetes:  ['M12 3.5s6 6.4 6 10.5a6 6 0 0 1-12 0c0-4.1 6-10.5 6-10.5Z'],
  grafik: ['M4 19.5h16', 'M7 16v-5m5 5V7m5 9v-3'],
};
const pilihIkon = computed(() => props.ikon ?? (
  /GJ|TOE/.test(props.satuan) ? 'energi' : /CO/.test(props.satuan) ? 'emisi' : props.satuan === 'Rp' || /Rp/.test(props.satuan) ? 'uang'
  : /%/.test(props.satuan) ? 'persen' : /^L\b|L\//.test(props.satuan) ? 'tetes' : 'grafik'));
const Ikon = () => h('svg', { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 1.9,
  'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true' },
  (IKON[pilihIkon.value] ?? []).map(d => h('path', { d })));
</script>

<template>
  <UbinAngka :angka="angka" :label="label" :nada="NADA[props.rag ?? 'netral'] ?? 'netral'" :catatan="catatan">
    <template #ikon><Ikon /></template>
  </UbinAngka>
</template>
