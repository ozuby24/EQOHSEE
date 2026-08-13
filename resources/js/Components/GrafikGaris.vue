<script setup lang="ts">
/**
 * Grafik garis sederhana.
 *
 * Digambar sebagai SVG dari data, bukan lewat pustaka grafik: satu
 * berkas JavaScript tambahan tidak sepadan untuk satu garis, dan
 * halaman ini harus tetap terbaca di jaringan site tambang.
 *
 * Skala ditulis di tepi kiri. Tanpa angka acuan, garis hanya
 * memperlihatkan naik-turun tanpa memberi tahu naik-turun berapa — dan
 * itu belum cukup untuk mengambil keputusan.
 */
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  titik: number[];
  warna?: string;
  target?: number | null;
  tinggi?: number;
  satuan?: string | null;
  desimal?: number;
}>(), {
  warna: '#F57C00',
  target: null,
  tinggi: 150,
  satuan: null,
  desimal: 2,
});

const L = 600;

const nilai = computed(() => props.titik.map((v) => Number(v) || 0));
const n = computed(() => nilai.value.length);

const puncak = computed(() => (n.value ? Math.max(...nilai.value, props.target ?? 0) : 0));
const skala = computed(() => (puncak.value > 0 ? puncak.value * 1.12 : 1));

const T = computed(() => props.tinggi);

const koordinat = computed(() =>
  nilai.value.map((v, i) => {
    const x = n.value > 1 ? (i / (n.value - 1)) * L : L / 2;
    const y = T.value - (v / skala.value) * T.value;
    return `${x.toFixed(1)} ${y.toFixed(1)}`;
  }),
);

const garis = computed(() => (koordinat.value.length ? 'M' + koordinat.value.join(' L') : ''));
const isi = computed(() => (koordinat.value.length ? garis.value + ` L${L} ${T.value} L0 ${T.value} Z` : ''));
const uid = computed(() => 'g' + Math.abs(hash(garis.value + props.warna + T.value)).toString(36).slice(0, 8));

const yTarget = computed(() =>
  props.target !== null && skala.value > 0
    ? Number((T.value - (props.target / skala.value) * T.value).toFixed(1))
    : null,
);

function hash(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h << 5) - h + s.charCodeAt(i);
  return h;
}

function tulis(v: number): string {
  const t = v.toLocaleString('id-ID', { minimumFractionDigits: props.desimal, maximumFractionDigits: props.desimal });
  return props.satuan ? `${t} ${props.satuan}` : t;
}

const labelTarget = computed(() => {
  if (yTarget.value === null) return null;
  return Math.max(0, Math.min(T.value - 16, yTarget.value - 17));
});
</script>

<template>
  <div v-if="n" class="flex gap-3">
    <div class="shrink-0 flex flex-col justify-between text-[10px] num text-stone-400 text-right"
         :style="{ height: T + 'px' }">
      <span>{{ tulis(skala) }}</span>
      <span>{{ tulis(skala / 2) }}</span>
      <span>0</span>
    </div>

    <div class="flex-1 min-w-0 relative">
      <svg :viewBox="`0 0 ${L} ${T}`" preserveAspectRatio="none" class="w-full block" :style="{ height: T + 'px' }">
        <defs>
          <linearGradient :id="uid" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" :stop-color="warna" stop-opacity=".22" />
            <stop offset="1" :stop-color="warna" stop-opacity="0" />
          </linearGradient>
        </defs>

        <line v-for="y in [0, T / 2]" :key="y" x1="0" :y1="y" :x2="L" :y2="y"
              stroke="#E7E2D8" stroke-width="1" vector-effect="non-scaling-stroke" />
        <line x1="0" :y1="T" :x2="L" :y2="T" stroke="#D8D2C6" stroke-width="1" vector-effect="non-scaling-stroke" />

        <path :d="isi" :fill="`url(#${uid})`" />

        <line v-if="yTarget !== null" x1="0" :y1="yTarget" :x2="L" :y2="yTarget"
              stroke="#E2663A" stroke-width="1.5" stroke-dasharray="6 5" vector-effect="non-scaling-stroke" />

        <path :d="garis" fill="none" :stroke="warna" stroke-width="2.2"
              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
      </svg>

      <span v-if="yTarget !== null"
            class="absolute right-0 text-[10px] num font-bold text-cam-coral bg-white px-1 rounded
                   shadow-sm pointer-events-none"
            :style="{ top: labelTarget + 'px' }">Target {{ tulis(target!) }}</span>
    </div>
  </div>
  <div v-else class="grid place-items-center text-[12px] text-stone-400" :style="{ height: tinggi + 'px' }">
    Belum ada data pada rentang ini.
  </div>
</template>
