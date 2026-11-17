<script setup lang="ts">
/**
 * Garis kecil di dalam kartu angka.
 *
 * Bukan grafik: tidak bersumbu, tidak berangka, tidak dapat dibaca
 * nilainya. Tugasnya satu — mengatakan apakah angka besar di
 * sebelahnya sedang naik, turun, atau diam. Itu pertanyaan yang
 * hampir selalu menyusul angkanya, dan yang tanpa ini menuntut orang
 * membuka halaman lain.
 *
 * Karena tidak dapat dibaca nilainya, ia TIDAK pernah menjadi
 * satu-satunya tempat sebuah nilai muncul.
 */
import { computed } from 'vue';
import { AKSEN, REDUP } from './warna';

const props = withDefaults(defineProps<{
  nilai: (number | null)[];
  warna?: string;
  tinggi?: number;
}>(), { warna: AKSEN, tinggi: 28 });

const L = 96;

const ada = computed(() => props.nilai.filter((v): v is number => v !== null));

const jalur = computed(() => {
  if (ada.value.length < 2) return '';

  const min = Math.min(...ada.value);
  const maks = Math.max(...ada.value);
  const rentang = maks - min || 1;
  const n = Math.max(1, props.nilai.length - 1);

  let d = '';
  let mulai = true;

  props.nilai.forEach((v, i) => {
    if (v === null) { mulai = true; return; }

    const x = (i / n) * (L - 4) + 2;
    const y = props.tinggi - 3 - ((v - min) / rentang) * (props.tinggi - 6);

    d += `${mulai ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)} `;
    mulai = false;
  });

  return d.trim();
});

const ujung = computed(() => {
  if (ada.value.length < 2) return null;

  const min = Math.min(...ada.value);
  const maks = Math.max(...ada.value);
  const rentang = maks - min || 1;
  const n = Math.max(1, props.nilai.length - 1);

  for (let i = props.nilai.length - 1; i >= 0; i--) {
    const v = props.nilai[i];
    if (v === null) continue;

    return {
      x: (i / n) * (L - 4) + 2,
      y: props.tinggi - 3 - ((v - min) / rentang) * (props.tinggi - 6),
    };
  }

  return null;
});
</script>

<template>
  <svg :viewBox="`0 0 ${L} ${tinggi}`" class="w-[96px] shrink-0"
       :style="{ height: tinggi + 'px' }" aria-hidden="true" focusable="false">
    <path v-if="jalur" :d="jalur" fill="none" :stroke="warna" stroke-width="2"
          stroke-linejoin="round" stroke-linecap="round" />

    <circle v-if="ujung" :cx="ujung.x" :cy="ujung.y" r="2.5" :fill="warna" />

    <line v-if="!jalur" x1="2" :y1="tinggi / 2" :x2="L - 2" :y2="tinggi / 2"
          :stroke="REDUP" stroke-width="2" stroke-linecap="round" />
  </svg>
</template>
