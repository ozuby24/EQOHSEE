<script setup lang="ts">
/*
  Sebaran rencana pengangkatan: radius (datar) terhadap beban kotor
  (tegak), warna menurut putusan simulasi, ukuran menurut utilisasi.
  Titik di kanan atas adalah pengangkatan terberat pada jangkauan
  terjauh — yang paling layak ditinjau ulang. Klik titik untuk membuka
  rencananya.
*/
import { computed, ref } from 'vue';
import { n } from '../../energi';
import { simulasi, klas } from '../../angkat';

const props = withDefaults(defineProps<{ titik: { id: number; kode: string; judul: string; x: number; y: number; util: number; putusan: string; klasifikasi: string }[]; tinggi?: number }>(), { tinggi: 260 });
const emit = defineEmits<{ (e: 'pilih', id: number): void }>();

const W = 560, KI = 44, KA = 14, AT = 12, BA = 30;
const xMaks = computed(() => Math.ceil(Math.max(10, ...props.titik.map(t => t.x)) / 5) * 5 + 5);
const yMaks = computed(() => {
  const m = Math.max(5, ...props.titik.map(t => t.y)) * 1.1;
  const l = m > 100 ? 20 : m > 40 ? 10 : 5;
  return Math.ceil(m / l) * l;
});
const x = (v: number) => KI + (v / xMaks.value) * (W - KI - KA);
const y = (v: number) => AT + (1 - v / yMaks.value) * (props.tinggi - AT - BA);
const jari = (u: number) => 4 + Math.min(10, Math.max(0, u) / 10);
const sumbuY = computed(() => Array.from({ length: 5 }, (_, i) => (yMaks.value / 4) * i));
const sumbuX = computed(() => { const o: number[] = []; for (let v = 0; v <= xMaks.value; v += xMaks.value > 40 ? 10 : 5) o.push(v); return o; });
const sorot = ref<any>(null);
</script>

<template>
  <div class="sr">
    <svg :viewBox="`0 0 ${W} ${tinggi}`" class="sr-svg" role="img" aria-label="Sebaran rencana pengangkatan menurut radius dan beban kotor">
      <g v-for="t in sumbuY" :key="'y' + t">
        <line :x1="KI" :y1="y(t)" :x2="W - KA" :y2="y(t)" class="sr-kisi" />
        <text :x="KI - 6" :y="y(t) + 3.5" text-anchor="end" class="sr-sumbu">{{ n(t, 0) }}</text>
      </g>
      <text v-for="v in sumbuX" :key="'x' + v" :x="x(v)" :y="tinggi - BA + 15" text-anchor="middle" class="sr-sumbu">{{ v }}</text>
      <text :x="KI - 6" :y="AT - 2" text-anchor="end" class="sr-sumbu">ton</text>
      <text :x="W - KA" :y="tinggi - 4" text-anchor="end" class="sr-sumbu">radius (m)</text>
      <circle v-for="t in titik" :key="t.id" :cx="x(t.x)" :cy="y(t.y)" :r="jari(t.util)" :fill="simulasi(t.putusan).warna"
              class="sr-titik" :class="{ 'sr-redup': sorot && sorot.id !== t.id }" tabindex="0"
              @mouseenter="sorot = t" @mouseleave="sorot = null" @focus="sorot = t" @blur="sorot = null"
              @click="emit('pilih', t.id)" @keydown.enter="emit('pilih', t.id)" />
    </svg>
    <div class="sr-kaki">
      <span v-for="k in ['go', 'bersyarat', 'nogo']" :key="k"><i :style="{ background: simulasi(k).warna }" />{{ simulasi(k).pendek }}</span>
      <span class="sr-catatan">ukuran titik = utilisasi crane</span>
    </div>
    <p v-if="sorot" class="sr-baca">
      <b>{{ sorot.kode }}</b> · {{ sorot.judul }} — {{ n(sorot.y, 1) }} t pada {{ n(sorot.x, 1) }} m, utilisasi {{ n(sorot.util, 1) }}% · {{ klas(sorot.klasifikasi).pendek }}
    </p>
  </div>
</template>

<style scoped>
.sr-svg { width: 100%; height: auto; display: block; }
.sr-kisi { stroke: #F0EFED; }
.sr-sumbu { font-size: 10.5px; fill: #A8A29E; font-weight: 600; }
.sr-titik { fill-opacity: .78; stroke: #fff; stroke-width: 1.5; cursor: pointer; transition: opacity .15s; }
.sr-titik:hover, .sr-titik:focus { fill-opacity: 1; outline: none; }
.sr-redup { opacity: .3; }
.sr-kaki { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 6px; font-size: 11px; font-weight: 700; color: #57534E; }
.sr-kaki i { display: inline-block; width: 10px; height: 10px; border-radius: 999px; margin-right: 6px; vertical-align: -1px; }
.sr-catatan { font-weight: 500; color: #A8A29E; }
.sr-baca { margin-top: 6px; font-size: 12px; color: #44403C; }
:root[data-tema="gelap"] .sr-kisi { stroke: #2E373C; }
:root[data-tema="gelap"] .sr-titik { stroke: #1B2124; }
:root[data-tema="gelap"] .sr-kaki, :root[data-tema="gelap"] .sr-baca { color: #B4BEC3; }
</style>
