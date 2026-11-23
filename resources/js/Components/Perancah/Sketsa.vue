<script setup lang="ts">
/*
  Sketsa tampak depan perancah, digambar ulang setiap dimensi berubah.

  Bukan gambar kerja — bentuknya mengikuti hasil kalkulator (jumlah bay,
  lift, dek, ikatan) supaya yang mengisi form langsung melihat apa yang
  sedang ia rancang: tiang di setiap batas bay, ledger di setiap lift,
  bracing selang-seling, plank dan guardrail di dek kerja, tangga di
  sisi, ikatan putus-putus ke dinding, dan satu orang setinggi 1,7 m
  sebagai pembanding skala.
*/
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  panjang: number; tinggi: number; bay: number; lift: number; dek?: number; ikatan?: number;
  warna?: string; guardrail?: boolean; toeboard?: boolean; outrigger?: boolean;
}>(), { dek: 1, ikatan: 0, warna: '#D97706', guardrail: true, toeboard: true, outrigger: false });

const W = 420, H = 300, M = { kiri: 54, kanan: 40, atas: 22, bawah: 34 };

const skala = computed(() => {
  const p = Math.max(1, props.panjang), t = Math.max(1.5, props.tinggi + 1.1);
  return Math.min((W - M.kiri - M.kanan) / p, (H - M.atas - M.bawah) / t);
});
const tanah = H - M.bawah;
const x0 = computed(() => M.kiri + ((W - M.kiri - M.kanan) - Math.max(1, props.panjang) * skala.value) / 2);
const lebarBay = computed(() => (Math.max(1, props.panjang) * skala.value) / Math.max(1, props.bay));
const tinggiLift = computed(() => (props.tinggi * skala.value) / Math.max(1, props.lift));
const xs = computed(() => Array.from({ length: props.bay + 1 }, (_, i) => x0.value + i * lebarBay.value));
const ys = computed(() => Array.from({ length: props.lift + 1 }, (_, i) => tanah - i * tinggiLift.value));
const atas = computed(() => tanah - props.tinggi * skala.value);
const rel = computed(() => 1.0 * skala.value);
const dekLevel = computed(() => {
  const n = Math.min(props.lift, Math.max(1, props.dek));
  return Array.from({ length: n }, (_, i) => props.lift - i);
});
const ikatanY = computed(() => {
  const n = Math.max(0, Math.min(props.ikatan, props.lift * 2));
  if (!n) return [];
  return Array.from({ length: Math.min(n, props.lift) }, (_, i) => tanah - (i + 1) * tinggiLift.value + tinggiLift.value * 0.3);
});
const orang = computed(() => ({ x: x0.value + lebarBay.value * 0.5, kaki: ys.value[props.lift] ?? atas.value, h: 1.7 * skala.value }));
const fmt = (v: number) => v.toLocaleString('id-ID', { maximumFractionDigits: 2 });
</script>

<template>
  <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-auto block" role="img"
       :aria-label="`Sketsa perancah ${fmt(panjang)} m × ${fmt(tinggi)} m, ${bay} bay, ${lift} lift`">
    <defs>
      <pattern id="sk-tanah" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
        <rect width="8" height="8" fill="#E7E5E4" /><rect width="3" height="8" fill="#D6D3D1" />
      </pattern>
    </defs>

    <!-- dinding struktur permanen (tempat ikatan) -->
    <rect :x="x0 - 26" :y="atas - 6" width="10" :height="tanah - atas + 6" fill="#E7E5E4" stroke="#D6D3D1" />
    <line v-for="(y, i) in ikatanY" :key="'ik' + i" :x1="x0 - 16" :x2="x0" :y1="y" :y2="y" stroke="#0EA5E9" stroke-width="2" stroke-dasharray="4 3" />

    <!-- tanah & sole board -->
    <rect :x="M.kiri - 30" :y="tanah" :width="W - M.kiri - M.kanan + 60" height="10" fill="url(#sk-tanah)" />
    <rect v-for="(x, i) in xs" :key="'sb' + i" :x="x - 7" :y="tanah - 3" width="14" height="4" fill="#78716C" rx="1" />
    <template v-if="outrigger">
      <line :x1="xs[0]" :y1="tanah - tinggiLift * 0.9" :x2="xs[0] - 22" :y2="tanah" stroke="#57534E" stroke-width="2.5" />
      <line :x1="xs[xs.length - 1]" :y1="tanah - tinggiLift * 0.9" :x2="xs[xs.length - 1] + 22" :y2="tanah" stroke="#57534E" stroke-width="2.5" />
    </template>

    <!-- bracing selang-seling -->
    <template v-for="l in lift" :key="'br' + l">
      <template v-for="(x, i) in xs.slice(0, -1)" :key="'b' + l + '-' + i">
        <line v-if="(i + l) % 2 === 0" :x1="x" :y1="ys[l - 1]" :x2="x + lebarBay" :y2="ys[l]" stroke="#A8A29E" stroke-width="1.6" />
      </template>
    </template>

    <!-- tiang (standard) & ledger -->
    <line v-for="(x, i) in xs" :key="'st' + i" :x1="x" :x2="x" :y1="tanah" :y2="atas - (guardrail ? rel : 0)" :stroke="warna" stroke-width="3" stroke-linecap="round" />
    <line v-for="(y, i) in ys.slice(1)" :key="'ld' + i" :x1="xs[0]" :x2="xs[xs.length - 1]" :y1="y" :y2="y" :stroke="warna" stroke-width="2.4" />

    <!-- dek kerja: plank, guardrail, toe board -->
    <template v-for="lv in dekLevel" :key="'dk' + lv">
      <rect :x="xs[0] - 2" :y="ys[lv] - 5" :width="xs[xs.length - 1] - xs[0] + 4" height="5" fill="#57534E" rx="1" />
      <template v-if="guardrail">
        <line :x1="xs[0]" :x2="xs[xs.length - 1]" :y1="ys[lv] - rel" :y2="ys[lv] - rel" stroke="#16A34A" stroke-width="2" />
        <line :x1="xs[0]" :x2="xs[xs.length - 1]" :y1="ys[lv] - rel * 0.5" :y2="ys[lv] - rel * 0.5" stroke="#16A34A" stroke-width="1.4" />
      </template>
      <rect v-if="toeboard" :x="xs[0]" :y="ys[lv] - 5 - Math.max(3, 0.15 * skala)" :width="xs[xs.length - 1] - xs[0]" :height="Math.max(3, 0.15 * skala)" fill="#EAB308" opacity=".85" />
    </template>

    <!-- tangga di sisi kanan -->
    <line :x1="xs[xs.length - 1] + 8" :x2="xs[xs.length - 1] + 8" :y1="tanah" :y2="atas - rel" stroke="#57534E" stroke-width="1.6" />
    <line :x1="xs[xs.length - 1] + 18" :x2="xs[xs.length - 1] + 18" :y1="tanah" :y2="atas - rel" stroke="#57534E" stroke-width="1.6" />
    <line v-for="k in Math.max(2, Math.floor((tanah - atas + rel) / 9))" :key="'tg' + k"
          :x1="xs[xs.length - 1] + 8" :x2="xs[xs.length - 1] + 18" :y1="tanah - k * 9" :y2="tanah - k * 9" stroke="#57534E" stroke-width="1.2" />

    <!-- orang 1,7 m sebagai pembanding skala -->
    <g :transform="`translate(${orang.x} ${orang.kaki - 5})`" fill="#0F172A" opacity=".75">
      <circle cx="0" :cy="-orang.h + orang.h * 0.09" :r="orang.h * 0.09" />
      <rect :x="-orang.h * 0.09" :y="-orang.h * 0.8" :width="orang.h * 0.18" :height="orang.h * 0.42" rx="2" />
      <rect :x="-orang.h * 0.085" :y="-orang.h * 0.4" :width="orang.h * 0.07" :height="orang.h * 0.4" />
      <rect :x="orang.h * 0.015" :y="-orang.h * 0.4" :width="orang.h * 0.07" :height="orang.h * 0.4" />
      <path :d="`M${-orang.h * 0.12} ${-orang.h * 0.92} h${orang.h * 0.24} l${-orang.h * 0.03} ${-orang.h * 0.06} h${-orang.h * 0.18}Z`" fill="#EAB308" />
    </g>

    <!-- ukuran -->
    <g font-size="10.5" fill="#78716C" font-weight="700">
      <line :x1="xs[0]" :x2="xs[xs.length - 1]" :y1="tanah + 22" :y2="tanah + 22" stroke="#A8A29E" />
      <text :x="(xs[0] + xs[xs.length - 1]) / 2" :y="tanah + 19" text-anchor="middle">P {{ fmt(panjang) }} m · {{ bay }} bay</text>
      <line :x1="M.kiri - 38" :x2="M.kiri - 38" :y1="tanah" :y2="atas" stroke="#A8A29E" />
      <text :x="M.kiri - 42" :y="(tanah + atas) / 2" text-anchor="middle" :transform="`rotate(-90 ${M.kiri - 42} ${(tanah + atas) / 2})`">T {{ fmt(tinggi) }} m · {{ lift }} lift</text>
    </g>
  </svg>
</template>
