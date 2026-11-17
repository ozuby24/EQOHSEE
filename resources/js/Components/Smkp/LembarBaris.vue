<script setup lang="ts" generic="T">
/**
 * Formulir bertabel yang barisnya dibagi ke lembar A4 dari tinggi terukur
 * (lib/susunLembar useBarisLembar). Isi lembar diberikan lewat slot dan
 * dirender dua kali: sekali di wadah ukur tersembunyi dengan seluruh baris,
 * lalu per lembar. Di dalam slot, badan tabel diberi `data-baris`, penutup
 * lembar terakhir `data-penutup`, dan judul & identitas yang hanya dicetak
 * di lembar pertama (`pertama`) `data-hanya-awal`.
 */
import { computed } from 'vue';
import Lembar from './Lembar.vue';
import { useBarisLembar } from '../../smkp/lib/susunLembar';
import type { KopDok } from '../../smkp/types';

const props = defineProps<{ dok: KopDok; baris: T[]; lanskap?: boolean }>();
// `ukur` = render di wadah ukur: satu baris data harus tetap satu baris tabel.
defineSlots<{ default(p: { baris: T[]; pertama: boolean; akhir: boolean; awal: number; ukur: boolean }): unknown }>();

const { ukurEl, halaman } = useBarisLembar(computed(() => props.baris), props.lanskap);
// Nomor urut baris pertama tiap lembar.
const awal = (i: number) => (halaman.value ?? []).slice(0, i).reduce((n, h) => n + h.length, 0);
</script>

<template>
  <div ref="ukurEl" class="smkp-ukur no-print" aria-hidden="true" inert>
    <Lembar :dok="dok" :halaman="1" :dari="1" :lanskap="lanskap"><slot :baris="baris" :pertama="true" :akhir="true" :awal="0" :ukur="true" /></Lembar>
  </div>
  <p v-if="!halaman" class="text-center text-[12.5px] text-slate-500 py-10">Menyusun halaman…</p>
  <template v-else>
    <Lembar v-for="(h, i) in halaman" :key="i" :dok="dok" :halaman="i + 1" :dari="halaman.length" :lanskap="lanskap">
      <slot :baris="h" :pertama="i === 0" :akhir="i === halaman.length - 1" :awal="awal(i)" :ukur="false" />
    </Lembar>
  </template>
</template>
