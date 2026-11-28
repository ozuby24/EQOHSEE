<script setup lang="ts">
/**
 * Kolom tegak — tunggal atau bertumpuk — dengan garis pendamping
 * opsional pada sumbu kanan.
 *
 * Dipakai untuk jumlah per periode yang terdiri dari beberapa keadaan
 * (inspeksi aman / bersyarat / bahaya per minggu) dan untuk Pareto
 * (kolom terurut + garis kumulatif %). Garis pendamping selalu persen
 * 0–100 pada sumbu kanan, sehingga dua besaran yang berbeda satuannya
 * tidak dipaksa berbagi satu sumbu.
 *
 * Mengarahkan penunjuk ke satu kolom memperlihatkan seluruh isinya
 * sekaligus — bagian tumpukan dan garisnya — dalam satu keterangan.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { BINGKAI, ringkas, ringkasSumbu, skalaRapi, warnaDeret } from './warna';

const props = withDefaults(defineProps<{
  label: string[];
  deret: { nama: string; nilai: (number | null)[]; warna?: string }[];
  garis?: { nama: string; nilai: (number | null)[]; warna?: string } | null;
  satuan?: string;
  tinggi?: number;
  /** Label sumbu datar dimiringkan — untuk label panjang seperti kode butir. */
  miring?: boolean;
}>(), { garis: null, satuan: '', tinggi: 200, miring: false });

/* Bantalan kiri selebar label sumbu terpanjang (lihat Garis.vue). */
const P = computed(() => ({
  atas: 14, kanan: props.garis ? 40 : 12, bawah: props.miring ? 44 : 24,
  kiri: Math.max(30, Math.ceil(Math.max(...tanda.value.map(t => t.teks.length)) * 5.9) + 13),
}));

const wadah = ref<HTMLElement | null>(null);
const lebar = ref(640);
let pengukur: ResizeObserver | null = null;
onMounted(() => {
  const ukur = () => { const w = Math.round(wadah.value?.getBoundingClientRect().width ?? 0); if (w > 0) lebar.value = w; };
  ukur();
  if (typeof ResizeObserver !== 'undefined' && wadah.value) { pengukur = new ResizeObserver(ukur); pengukur.observe(wadah.value as unknown as Element); }
});
onBeforeUnmount(() => pengukur?.disconnect());

const L = computed(() => Math.max(240, lebar.value));
const T = computed(() => props.tinggi);
const n = computed(() => props.label.length);
const lajur = computed(() => (L.value - P.value.kiri - P.value.kanan) / Math.max(1, n.value));
const tebal = computed(() => Math.max(6, Math.min(38, lajur.value * 0.62)));
const warna = (i: number) => props.deret[i]?.warna ?? warnaDeret(i);

const jumlah = (i: number) => props.deret.reduce((a, d) => a + (d.nilai[i] ?? 0), 0);
/* Langkah sumbu bulat (skalaRapi), bulat-bilangan untuk cacahan. */
const skala = computed(() => skalaRapi(
  Math.max(0, ...props.label.map((_, i) => jumlah(i))) || 1,
  props.deret.every(d => d.nilai.every(v => v === null || v === undefined || Number.isInteger(v)))));
const maks = computed(() => skala.value.batas);
const tanda = computed(() => {
  const { batas, langkah } = skala.value;
  return Array.from({ length: Math.round(batas / langkah) + 1 },
    (_, i) => ({ nilai: i * langkah, teks: ringkasSumbu(i * langkah, batas) }));
});

const cx = (i: number) => P.value.kiri + lajur.value * (i + 0.5);
const y = (v: number) => P.value.atas + (1 - v / maks.value) * (T.value - P.value.atas - P.value.bawah);
const yKanan = (v: number) => P.value.atas + (1 - v / 100) * (T.value - P.value.atas - P.value.bawah);

const tumpuk = computed(() => props.label.map((_, i) => {
  let dasar = 0;
  return props.deret.map((d, di) => {
    const v = d.nilai[i] ?? 0;
    const k = { y: y(dasar + v), h: Math.max(0, y(dasar) - y(dasar + v)), warna: warna(di), v };
    dasar += v;
    return k;
  });
}));

const jalurGaris = computed(() => {
  if (!props.garis) return '';
  let d = '', mulai = true;
  props.garis.nilai.forEach((v, i) => {
    if (v === null || v === undefined) { mulai = true; return; }
    d += `${mulai ? 'M' : 'L'}${cx(i).toFixed(1)} ${yKanan(v).toFixed(1)} `;
    mulai = false;
  });
  return d;
});

const bantu = computed(() => tanda.value.map((t, i) => ({ r: i, nilai: t.nilai, teks: t.teks, py: y(t.nilai) })));
const langkah = computed(() => { const muat = Math.max(2, Math.floor((L.value - 60) / (props.miring ? 26 : 46))); return n.value <= muat ? 1 : Math.ceil(n.value / muat); });

const bidik = ref<number | null>(null);
const svgEl = ref<SVGSVGElement | null>(null);
function arahkan(e: PointerEvent) {
  const k = svgEl.value?.getBoundingClientRect(); if (!k) return;
  const px = ((e.clientX - k.left) / k.width) * L.value;
  const i = Math.floor((px - P.value.kiri) / lajur.value);
  bidik.value = i >= 0 && i < n.value ? i : null;
}
const kosong = computed(() => !n.value || props.label.every((_, i) => jumlah(i) === 0));
</script>

<template>
  <div v-if="kosong" class="h-full min-h-[140px] grid place-items-center text-[12px] text-stone-400">Belum ada data pada rentang ini.</div>
  <div v-else ref="wadah" class="relative">
    <svg ref="svgEl" :viewBox="`0 0 ${L} ${T}`" class="w-full block" :style="{ height: T + 'px' }" role="img"
         :aria-label="`Grafik kolom: ${deret.map(d => d.nama).join(', ')}`" @pointermove="arahkan" @pointerleave="bidik = null">
      <line v-for="g in bantu" :key="g.r" :x1="P.kiri" :x2="L - P.kanan" :y1="g.py" :y2="g.py" :stroke="g.r === 0 ? BINGKAI.sumbu : BINGKAI.bantu" />
      <text v-for="g in bantu" :key="'t' + g.r" :x="P.kiri - 7" :y="g.py + 3.5" text-anchor="end" font-size="10" :fill="BINGKAI.redupTinta">{{ g.teks }}</text>
      <template v-if="garis">
        <text v-for="r in [0, 50, 100]" :key="'k' + r" :x="L - P.kanan + 6" :y="yKanan(r) + 3.5" font-size="10" :fill="BINGKAI.redupTinta">{{ r }}%</text>
      </template>

      <rect v-if="bidik !== null" :x="P.kiri + lajur * bidik" :y="P.atas" :width="lajur" :height="T - P.atas - P.bawah" fill="#F5F5F4" />

      <g v-for="(kol, i) in tumpuk" :key="i">
        <rect v-for="(b, bi) in kol" :key="bi" v-show="b.h > 0" :x="cx(i) - tebal / 2" :y="b.y" :width="tebal" :height="b.h"
              :fill="b.warna" :rx="bi === kol.length - 1 || kol.slice(bi + 1).every(x => x.h === 0) ? 3 : 0" />
      </g>

      <path v-if="jalurGaris" :d="jalurGaris" fill="none" :stroke="garis?.warna ?? '#78716C'" stroke-width="2" stroke-linejoin="round" />
      <template v-if="garis">
        <circle v-for="(v, i) in garis.nilai" :key="'g' + i" v-show="v !== null && v !== undefined" :cx="cx(i)" :cy="yKanan(v ?? 0)" r="3.2"
                :fill="garis.warna ?? '#78716C'" :stroke="BINGKAI.latar" stroke-width="1.5" />
      </template>

      <text v-for="(l, i) in label" :key="'x' + i" v-show="i % langkah === 0"
            :x="cx(i)" :y="miring ? T - P.bawah + 12 : T - 7" :text-anchor="miring ? 'end' : 'middle'" font-size="10" :fill="BINGKAI.redupTinta"
            :transform="miring ? `rotate(-35 ${cx(i)} ${T - P.bawah + 12})` : undefined">{{ l }}</text>
    </svg>

    <div v-if="bidik !== null" class="absolute top-1 pointer-events-none bg-white border border-stone-200 rounded-lg shadow-lg px-2.5 py-2 min-w-[150px] z-10"
         :class="bidik > n * 0.55 ? 'left-1' : 'right-1'">
      <p class="text-[10.5px] font-bold text-stone-500 mb-1">{{ label[bidik] }}</p>
      <p v-for="(d, di) in deret" :key="d.nama" class="flex items-baseline gap-1.5 text-[11px] leading-snug">
        <span class="inline-block w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: warna(di) }"></span>
        <span class="font-bold text-cam-ink num">{{ ringkas(d.nilai[bidik] ?? 0) }}{{ satuan ? ' ' + satuan : '' }}</span>
        <span class="text-stone-500 truncate">{{ d.nama }}</span>
      </p>
      <p v-if="garis && garis.nilai[bidik] !== null && garis.nilai[bidik] !== undefined" class="flex items-baseline gap-1.5 text-[11px] leading-snug mt-0.5">
        <span class="inline-block w-2.5 h-0.5 rounded-full shrink-0" :style="{ background: garis.warna ?? '#78716C' }"></span>
        <span class="font-bold text-cam-ink num">{{ ringkas(garis.nilai[bidik] as number) }}%</span>
        <span class="text-stone-500 truncate">{{ garis.nama }}</span>
      </p>
    </div>

    <div class="flex flex-wrap gap-x-3 gap-y-1 mt-2 text-[11px] text-stone-500">
      <span v-for="(d, di) in deret" :key="'lg' + d.nama" class="inline-flex items-center gap-1.5">
        <span class="w-2.5 h-2.5 rounded-sm" :style="{ background: warna(di) }"></span>{{ d.nama }}
      </span>
      <span v-if="garis" class="inline-flex items-center gap-1.5">
        <span class="w-3 h-0.5 rounded-full" :style="{ background: garis.warna ?? '#78716C' }"></span>{{ garis.nama }}
      </span>
    </div>
  </div>
</template>
