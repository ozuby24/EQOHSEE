<script setup lang="ts">
/**
 * Batang mendatar — membandingkan besaran antar hal yang dinamai.
 *
 * MENDATAR, bukan tegak, dan itu keputusan yang disengaja: nama di
 * modul ini panjang-panjang ("Dump Truck HD465-7", "Tanggul Kolam
 * Pengendap 3"). Pada batang tegak nama sepanjang itu harus dimiringkan
 * atau dipotong; mendatar ia terbaca mendatar seperti teks biasa.
 *
 * Yang dijaga:
 *
 * - Diurutkan dari yang TERBESAR. Batang yang urutannya acak menuntut
 *   pembacanya mencari sendiri yang tertinggi — pekerjaan yang justru
 *   seharusnya diambil alih grafiknya.
 *
 * - Satu deret memakai SATU warna. Mewarnai tiap batang berbeda-beda
 *   menghabiskan saluran identitas untuk mengulang apa yang sudah
 *   dikatakan panjangnya, dan membuat pembaca mencari makna warna yang
 *   tidak ada.
 *
 * - Nilainya ditulis di UJUNG batang, bukan di dalamnya: batang pendek
 *   tidak muat memuat angkanya, dan angka yang terpotong lebih buruk
 *   daripada angka yang di luar.
 *
 * - Satu batang dapat DITONJOLKAN lewat `sorot`; sisanya menjadi abu.
 *   Itu bentuk yang tepat ketika ceritanya "yang ini yang bermasalah",
 *   dan hampir selalu lebih jelas daripada memberi delapan warna.
 */
import { computed, ref } from 'vue';
import { AKSEN, BINGKAI, KEADAAN, REDUP, ringkas } from './warna';

const props = withDefaults(defineProps<{
  baris: { label: string; nilai: number; keadaan?: keyof typeof KEADAAN }[];
  satuan?: string;
  /** Label batang yang ditonjolkan; sisanya diredupkan. */
  sorot?: string | null;
  /** Batas atas tetap — dipakai bila beberapa grafik harus sebanding. */
  maksTetap?: number | null;
  /** Sudah terurut dari pemanggilnya; jangan urutkan lagi. */
  apaAdanya?: boolean;
}>(), { satuan: '', sorot: null, maksTetap: null, apaAdanya: false });

const TEBAL = 20;   // ≤ 24px; sisanya udara
const JEDA  = 10;

const urut = computed(() =>
  props.apaAdanya ? props.baris : [...props.baris].sort((a, b) => b.nilai - a.nilai));

const maks = computed(() =>
  props.maksTetap ?? Math.max(1, ...urut.value.map(b => Math.abs(b.nilai))));

const lebarNama = computed(() =>
  Math.min(180, Math.max(72, ...urut.value.map(b => b.label.length * 6.2))));

const L = 640;
const kananNilai = 56;

const lebar = (v: number) =>
  Math.max(2, (Math.abs(v) / maks.value) * (L - lebarNama.value - kananNilai - 8));

const warna = (b: { label: string; keadaan?: keyof typeof KEADAAN }) => {
  if (props.sorot && b.label !== props.sorot) return REDUP;

  return b.keadaan ? KEADAAN[b.keadaan] : AKSEN;
};

const disorot = ref<string | null>(null);
</script>

<template>
  <div v-if="!urut.length"
       class="h-full min-h-[140px] grid place-items-center text-[12px] text-stone-400">
    Belum ada data untuk dibandingkan.
  </div>

  <svg v-else :viewBox="`0 0 ${L} ${urut.length * (TEBAL + JEDA) + 6}`" class="w-full block"
       role="img" aria-label="Grafik batang" @pointerleave="disorot = null">

    <g v-for="(b, i) in urut" :key="b.label"
       :transform="`translate(0 ${i * (TEBAL + JEDA)})`"
       tabindex="0" class="grafik-batang focus:outline-none"
       :aria-label="`${b.label}: ${ringkas(b.nilai)} ${satuan}`"
       @pointerenter="disorot = b.label" @focus="disorot = b.label" @blur="disorot = null">

      <!-- Sasaran arahkan setinggi seluruh jalur, bukan setebal
           batangnya: batang setinggi 20px yang nilainya kecil hampir
           tidak dapat dikenai kursor. -->
      <rect x="0" y="0" :width="L" :height="TEBAL + JEDA" fill="transparent" />

      <rect v-if="disorot === b.label"
            x="0" y="-1" :width="L" :height="TEBAL + 2" rx="4" fill="#FAFAF9" />

      <text :x="0" :y="TEBAL / 2 + 4" font-size="11.5" :fill="BINGKAI.tinta"
            :textLength="b.label.length * 6.2 > lebarNama ? lebarNama - 8 : undefined"
            lengthAdjust="spacingAndGlyphs">{{ b.label }}</text>

      <rect :x="lebarNama" y="0" :width="lebar(b.nilai)" :height="TEBAL"
            :fill="warna(b)" rx="4"
            :style="{ opacity: disorot && disorot !== b.label ? 0.55 : 1 }" />

      <!-- Pangkalnya disikukan kembali: batang tumbuh dari satu garis
           dasar, dan ujung bulat di pangkal membuatnya tampak melayang. -->
      <rect :x="lebarNama" y="0" width="4" :height="TEBAL" :fill="warna(b)"
            :style="{ opacity: disorot && disorot !== b.label ? 0.55 : 1 }" />

      <text :x="lebarNama + lebar(b.nilai) + 8" :y="TEBAL / 2 + 4"
            font-size="11.5" font-weight="700" :fill="BINGKAI.tinta"
            class="num">{{ ringkas(b.nilai) }}</text>
    </g>
  </svg>
</template>

<style scoped>
.grafik-batang:focus-visible rect:first-of-type {
  outline: 2px solid #F57C00;
  outline-offset: 1px;
}
</style>
