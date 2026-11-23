<script setup lang="ts">
/**
 * Batang mendatar bertumpuk: satu baris per kelompok, dibagi menurut
 * keadaan atau tahap.
 *
 * Dua mode: `relatif` menggambar tiap baris selebar penuh (komposisi —
 * "dari mana emisi produk ini datang"), bawaannya menggambar panjang
 * sebanding jumlahnya (besaran — "zona mana yang paling banyak
 * perancahnya"). Mengarahkan penunjuk ke satu baris memperlihatkan
 * rinciannya; baris ber-url dapat diklik untuk menelusuri.
 */
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ringkas, warnaDeret } from './warna';

type Bagian = { nama: string; nilai: number; warna?: string };
const props = withDefaults(defineProps<{
  baris: { label: string; sub?: string | null; bagian: Bagian[]; url?: string }[];
  satuan?: string;
  relatif?: boolean;
}>(), { satuan: '', relatif: false });

const total = (b: Bagian[]) => b.reduce((a, x) => a + Math.max(0, x.nilai), 0);
const maks = computed(() => Math.max(1, ...props.baris.map(r => total(r.bagian))));
const nama = computed(() => {
  const s = new Map<string, string>();
  props.baris.forEach(r => r.bagian.forEach((b, i) => { if (!s.has(b.nama)) s.set(b.nama, b.warna ?? warnaDeret(i)); }));
  return [...s.entries()].map(([n, w]) => ({ n, w }));
});
const warnaDari = (b: Bagian, i: number) => b.warna ?? warnaDeret(i);
const lebarBagian = (r: { bagian: Bagian[] }, b: Bagian) => {
  const t = total(r.bagian);
  if (!t) return 0;
  return props.relatif ? (Math.max(0, b.nilai) / t) * 100 : (Math.max(0, b.nilai) / maks.value) * 100;
};
const sorot = ref<number | null>(null);
const daftar = computed(() => props.baris.map((r, urut) => ({ ...r, urut, jumlah: total(r.bagian) })));
</script>

<template>
  <div v-if="!baris.length" class="min-h-[120px] grid place-items-center text-[12px] text-stone-400">Belum ada data.</div>
  <div v-else>
    <ul class="space-y-2.5" @pointerleave="sorot = null">
      <li v-for="r in daftar" :key="r.label" class="relative" @pointerenter="sorot = r.urut">
        <component :is="r.url ? Link : 'div'" :href="r.url" class="block rounded-lg -mx-1.5 px-1.5 py-1" :class="r.url ? 'hover:bg-stone-50' : ''">
          <div class="flex items-baseline justify-between gap-3 text-[12px]">
            <span class="min-w-0"><span class="font-semibold text-cam-ink">{{ r.label }}</span>
              <span v-if="r.sub" class="text-stone-400 ml-1.5">{{ r.sub }}</span></span>
            <span class="font-bold text-cam-ink num shrink-0">{{ ringkas(r.jumlah) }}<small v-if="satuan" class="text-stone-400 font-semibold ml-1">{{ satuan }}</small></span>
          </div>
          <div class="mt-1.5 h-3 rounded-full bg-stone-100 overflow-hidden flex" :class="relatif ? '' : ''">
            <span v-for="(b, bi) in r.bagian" :key="b.nama" class="h-full transition-[filter] duration-150"
                  :style="{ width: lebarBagian(r, b) + '%', background: warnaDari(b, bi), filter: sorot !== null && sorot !== r.urut ? 'saturate(.35) opacity(.6)' : 'none' }" />
          </div>
        </component>
        <div v-if="sorot === r.urut" class="absolute right-0 -top-1 translate-y-[-100%] z-10 pointer-events-none bg-white border border-stone-200 rounded-lg shadow-lg px-2.5 py-2 min-w-[170px]">
          <p class="text-[10.5px] font-bold text-stone-500 mb-1">{{ r.label }}</p>
          <p v-for="(b, bi) in r.bagian" :key="'t' + b.nama" class="flex items-baseline gap-1.5 text-[11px] leading-snug">
            <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: warnaDari(b, bi) }"></span>
            <span class="font-bold text-cam-ink num">{{ ringkas(b.nilai) }}</span>
            <span class="text-stone-500 truncate">{{ b.nama }}</span>
            <span v-if="r.jumlah" class="ml-auto text-stone-400 num">{{ Math.round(Math.max(0, b.nilai) / r.jumlah * 100) }}%</span>
          </p>
        </div>
      </li>
    </ul>
    <div class="flex flex-wrap gap-x-3 gap-y-1 mt-3 text-[11px] text-stone-500">
      <span v-for="x in nama" :key="x.n" class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" :style="{ background: x.w }"></span>{{ x.n }}</span>
    </div>
  </div>
</template>
