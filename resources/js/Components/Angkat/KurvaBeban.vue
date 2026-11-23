<script setup lang="ts">
/*
  Kurva beban (load chart) crane terpilih pada panjang boom ini.

  Tiga garis: kapasitas terpasang, batas utilisasi klasifikasinya (85%,
  75% tandem, 50% man-basket), dan beban kotor di kait. Selama titik
  beban berada di bawah garis batas, radius itu aman; tempat garis beban
  memotong garis batas adalah radius maksimum aman. Arahkan kursor untuk
  membaca kapasitas di setiap radius.
*/
import { computed, ref } from 'vue';
import { n } from '../../energi';

const props = withDefaults(defineProps<{
  kurva: { r: number; kap: number }[];
  radius: number; kotor: number; batas: number; radiusAman?: number | null; tinggi?: number;
}>(), { radiusAman: null, tinggi: 220 });

const W = 560, KI = 46, KA = 14, AT = 24, BA = 30;
const H = computed(() => props.tinggi);
const rMin = computed(() => props.kurva[0]?.r ?? 3);
const rMaks = computed(() => Math.max(props.radius + 1, props.kurva[props.kurva.length - 1]?.r ?? 20));
const yMaks = computed(() => {
  const m = Math.max(props.kotor * 1.15, ...props.kurva.map(k => k.kap)) * 1.08;
  const langkah = m > 200 ? 50 : m > 80 ? 20 : m > 30 ? 10 : 5;
  return Math.ceil(m / langkah) * langkah;
});
const x = (r: number) => KI + ((r - rMin.value) / Math.max(1, rMaks.value - rMin.value)) * (W - KI - KA);
const y = (t: number) => AT + (1 - t / yMaks.value) * (H.value - AT - BA);

const garisKap = computed(() => props.kurva.map((k, i) => `${i ? 'L' : 'M'}${x(k.r).toFixed(1)} ${y(k.kap).toFixed(1)}`).join(''));
const garisBatas = computed(() => props.kurva.map((k, i) => `${i ? 'L' : 'M'}${x(k.r).toFixed(1)} ${y(k.kap * props.batas / 100).toFixed(1)}`).join(''));
const bidangLewat = computed(() => {
  const titik = props.kurva.filter(k => k.kap * props.batas / 100 < props.kotor);
  if (!titik.length) return '';
  const atas = titik.map(k => `${x(k.r).toFixed(1)} ${y(props.kotor).toFixed(1)}`);
  const bawah = [...titik].reverse().map(k => `${x(k.r).toFixed(1)} ${y(k.kap * props.batas / 100).toFixed(1)}`);
  return 'M' + atas.join('L') + 'L' + bawah.join('L') + 'Z';
});
const sumbuY = computed(() => Array.from({ length: 5 }, (_, i) => (yMaks.value / 4) * i));
const sumbuX = computed(() => {
  const l = rMaks.value - rMin.value > 30 ? 10 : 5;
  const out: number[] = [];
  for (let r = Math.ceil(rMin.value / l) * l; r <= rMaks.value; r += l) out.push(r);
  return out;
});
const kapDiRadius = computed(() => {
  const k = props.kurva.reduce((a, b) => (Math.abs(b.r - props.radius) < Math.abs(a.r - props.radius) ? b : a), props.kurva[0] ?? { r: 0, kap: 0 });
  return k.kap;
});
const aman = computed(() => props.kotor <= kapDiRadius.value * props.batas / 100);

const sorot = ref<{ r: number; kap: number } | null>(null);
function gerak(e: MouseEvent) {
  const svg = e.currentTarget as SVGSVGElement;
  const b = svg.getBoundingClientRect();
  const px = ((e.clientX - b.left) / b.width) * W;
  const r = rMin.value + ((px - KI) / (W - KI - KA)) * (rMaks.value - rMin.value);
  sorot.value = props.kurva.reduce((a, k) => (Math.abs(k.r - r) < Math.abs(a.r - r) ? k : a), props.kurva[0]);
}
</script>

<template>
  <div class="kb">
    <svg :viewBox="`0 0 ${W} ${H}`" class="kb-svg" role="img" :aria-label="`Kurva beban: beban ${n(kotor, 1)} t pada radius ${n(radius, 1)} m`"
         @mousemove="gerak" @mouseleave="sorot = null">
      <g v-for="t in sumbuY" :key="'y' + t">
        <line :x1="KI" :y1="y(t)" :x2="W - KA" :y2="y(t)" class="kb-kisi" />
        <text :x="KI - 6" :y="y(t) + 3.5" text-anchor="end" class="kb-sumbu">{{ n(t, 0) }}</text>
      </g>
      <text :x="KI - 6" :y="10" text-anchor="end" class="kb-sumbu">ton</text>
      <g v-for="r in sumbuX" :key="'x' + r">
        <text :x="x(r)" :y="H - BA + 15" text-anchor="middle" class="kb-sumbu">{{ r }}</text>
      </g>
      <text :x="W - KA" :y="H - 4" text-anchor="end" class="kb-sumbu">radius (m)</text>

      <path v-if="bidangLewat" :d="bidangLewat" class="kb-lewat" />
      <path :d="garisKap" class="kb-kap" />
      <path :d="garisBatas" class="kb-batas" />
      <line :x1="KI" :y1="y(kotor)" :x2="W - KA" :y2="y(kotor)" class="kb-beban" />
      <text :x="KI + 6" :y="y(kotor) - 6" class="kb-label kb-label-beban">beban kotor {{ n(kotor, 1) }} t</text>

      <line v-if="radiusAman" :x1="x(radiusAman)" :y1="AT" :x2="x(radiusAman)" :y2="H - BA" class="kb-aman" />
      <text v-if="radiusAman" :x="x(radiusAman) + (x(radiusAman) > W - 140 ? -4 : 4)" :y="AT - 8" :text-anchor="x(radiusAman) > W - 140 ? 'end' : 'start'" class="kb-label">maks aman {{ n(radiusAman, 1) }} m</text>

      <circle :cx="x(radius)" :cy="y(kotor)" r="7" :class="aman ? 'kb-titik-aman' : 'kb-titik-lewat'" />
      <circle :cx="x(radius)" :cy="y(kotor)" r="12" :class="aman ? 'kb-cincin-aman' : 'kb-cincin-lewat'" />

      <g v-if="sorot">
        <line :x1="x(sorot.r)" :y1="AT" :x2="x(sorot.r)" :y2="H - BA" class="kb-sorot" />
        <circle :cx="x(sorot.r)" :cy="y(sorot.kap)" r="4" class="kb-titik-kap" />
      </g>
    </svg>
    <div class="kb-legenda">
      <span><i class="kb-l-kap" />Kapasitas terpasang</span>
      <span><i class="kb-l-batas" />Batas {{ n(batas, 0) }}%</span>
      <span><i class="kb-l-beban" />Beban kotor</span>
      <span v-if="sorot" class="kb-baca num">R {{ sorot.r }} m · kapasitas {{ n(sorot.kap, 1) }} t · batas {{ n(sorot.kap * batas / 100, 1) }} t · utilisasi {{ n(kotor / sorot.kap * 100, 1) }}%</span>
    </div>
  </div>
</template>

<style scoped>
.kb-svg { width: 100%; height: auto; display: block; cursor: crosshair; }
.kb-kisi { stroke: #F0EFED; }
.kb-sumbu { font-size: 10.5px; fill: #A8A29E; font-weight: 600; }
.kb-kap { fill: none; stroke: #2A78D6; stroke-width: 2.6; }
.kb-batas { fill: none; stroke: #D97706; stroke-width: 2; stroke-dasharray: 6 4; }
.kb-beban { stroke: #1C1917; stroke-width: 1.4; stroke-dasharray: 2 3; }
.kb-lewat { fill: rgba(220, 38, 38, .14); }
.kb-aman { stroke: #15803D; stroke-width: 1.2; stroke-dasharray: 3 3; }
.kb-label { font-size: 10.5px; font-weight: 700; fill: #15803D; }
.kb-label-beban { fill: #44403C; }
.kb-titik-aman { fill: #15803D; } .kb-titik-lewat { fill: #DC2626; }
.kb-cincin-aman { fill: none; stroke: #15803D; stroke-width: 2; opacity: .35; }
.kb-cincin-lewat { fill: none; stroke: #DC2626; stroke-width: 2; opacity: .45; }
.kb-sorot { stroke: #A8A29E; stroke-width: 1; }
.kb-titik-kap { fill: #2A78D6; }
.kb-legenda { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 6px; font-size: 11px; font-weight: 600; color: #78716C; }
.kb-legenda i { display: inline-block; width: 16px; height: 3px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }
.kb-l-kap { background: #2A78D6; } .kb-l-batas { background: #D97706; } .kb-l-beban { background: #44403C; }
.kb-baca { color: #2A78D6; font-weight: 800; }
:root[data-tema="gelap"] .kb-kisi { stroke: #2E373C; }
:root[data-tema="gelap"] .kb-beban { stroke: #D5DDE1; }
:root[data-tema="gelap"] .kb-label-beban { fill: #D5DDE1; }
:root[data-tema="gelap"] .kb-legenda { color: #93A1A8; }
:root[data-tema="gelap"] .kb-l-beban { background: #D5DDE1; }
</style>
