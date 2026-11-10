<script setup lang="ts">
/** Keadaan kosong: ikon, satu kalimat, dan langkah berikutnya bila ada. */
import '../../../css/miners.css';
import Ikon from './Ikon.vue';
import { IKON } from './ikon';

withDefaults(defineProps<{
  judul?: string | null;
  teks?: string | null;
  ikon?: string;
}>(), { judul: null, teks: null, ikon: IKON.kotak });
</script>

<template>
  <div class="mn-hampa">
    <span class="mn-hampa-ikon"><Ikon :d="ikon" /></span>
    <span v-if="judul" class="mn-hampa-judul">{{ judul }}</span>
    <span v-if="teks" class="mn-hampa-teks">{{ teks }}</span>
    <div v-if="$slots.default" class="mn-hampa-aksi"><slot /></div>
  </div>
</template>
