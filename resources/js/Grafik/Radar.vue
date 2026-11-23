<script setup lang="ts">
/**
 * Radar profil dampak — satu sumbu per kategori, nilai 0–100.
 *
 * Kategori dampak LCA berbeda satuan dan berbeda orde besaran, jadi yang
 * digambar BUKAN nilai mentah melainkan nilai yang sudah dinormalisasi
 * pemanggilnya (mis. persen terhadap kategori terbesar). Angka aslinya
 * tetap terbaca lewat `teks` pada keterangan saat sumbu diarahkan.
 */
import { computed, ref } from 'vue';
import { BINGKAI, warnaDeret } from './warna';

const props = withDefaults(defineProps<{
  sumbu: { label: string; sub?: string }[];
  deret: { nama: string; nilai: number[]; teks?: string[]; warna?: string }[];
  ukuran?: number;
}>(), { ukuran: 300 });

const U = computed(() => props.ukuran);
const pusat = computed(() => U.value / 2);
const R = computed(() => U.value / 2 - 46);
const n = computed(() => props.sumbu.length);
const sudut = (i: number) => -Math.PI / 2 + (i / n.value) * Math.PI * 2;
const titik = (i: number, v: number) => {
  const r = (Math.max(0, Math.min(100, v)) / 100) * R.value;
  return [pusat.value + r * Math.cos(sudut(i)), pusat.value + r * Math.sin(sudut(i))];
};
const cincin = computed(() => [25, 50, 75, 100].map(p => props.sumbu.map((_, i) => titik(i, p).join(',')).join(' ')));
const warna = (i: number) => props.deret[i]?.warna ?? warnaDeret(i);
const poligon = (d: { nilai: number[] }) => d.nilai.map((v, i) => titik(i, v).join(',')).join(' ');
const sorot = ref<number | null>(null);
const label = (i: number) => {
  const [x, y] = titik(i, 118);
  const c = Math.cos(sudut(i));
  return { x, y, anchor: Math.abs(c) < 0.2 ? 'middle' : c > 0 ? 'start' : 'end' };
};
</script>

<template>
  <div class="relative">
    <svg :viewBox="`-70 0 ${U + 140} ${U}`" class="w-full h-auto block mx-auto" :style="{ maxWidth: U + 140 + 'px' }" role="img" aria-label="Radar profil dampak">
      <polygon v-for="(c, i) in cincin" :key="'c' + i" :points="c" fill="none" :stroke="i === 3 ? BINGKAI.sumbu : BINGKAI.bantu" />
      <line v-for="(_, i) in sumbu" :key="'s' + i" :x1="pusat" :y1="pusat" :x2="titik(i, 100)[0]" :y2="titik(i, 100)[1]" :stroke="BINGKAI.bantu" />
      <g v-for="(d, di) in deret" :key="d.nama">
        <polygon :points="poligon(d)" :fill="warna(di)" fill-opacity=".18" :stroke="warna(di)" stroke-width="2" stroke-linejoin="round" />
        <circle v-for="(v, i) in d.nilai" :key="'p' + i" :cx="titik(i, v)[0]" :cy="titik(i, v)[1]" r="3.5" :fill="warna(di)" :stroke="BINGKAI.latar" stroke-width="1.5" />
      </g>
      <g v-for="(s, i) in sumbu" :key="'l' + i" class="cursor-default" @pointerenter="sorot = i" @pointerleave="sorot = null">
        <circle :cx="titik(i, 100)[0]" :cy="titik(i, 100)[1]" r="14" fill="transparent" />
        <text :x="label(i).x" :y="label(i).y" :text-anchor="label(i).anchor" font-size="11" font-weight="800" :fill="BINGKAI.tinta">{{ s.label }}</text>
        <text v-if="s.sub" :x="label(i).x" :y="label(i).y + 12" :text-anchor="label(i).anchor" font-size="9.5" :fill="BINGKAI.redupTinta">{{ s.sub }}</text>
      </g>
    </svg>
    <div v-if="sorot !== null" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 pointer-events-none bg-white border border-stone-200 rounded-lg shadow-lg px-2.5 py-2 min-w-[150px] z-10">
      <p class="text-[10.5px] font-bold text-stone-500 mb-1">{{ sumbu[sorot].label }} <span v-if="sumbu[sorot].sub">· {{ sumbu[sorot].sub }}</span></p>
      <p v-for="(d, di) in deret" :key="'t' + d.nama" class="flex items-baseline gap-1.5 text-[11px]">
        <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: warna(di) }"></span>
        <span class="font-bold text-cam-ink num">{{ d.teks?.[sorot] ?? Math.round(d.nilai[sorot]) + '%' }}</span>
        <span class="text-stone-500 truncate">{{ d.nama }}</span>
      </p>
    </div>
    <div v-if="deret.length > 1" class="flex flex-wrap justify-center gap-3 mt-1 text-[11px] text-stone-500">
      <span v-for="(d, di) in deret" :key="'lg' + d.nama" class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm" :style="{ background: warna(di) }"></span>{{ d.nama }}</span>
    </div>
  </div>
</template>
