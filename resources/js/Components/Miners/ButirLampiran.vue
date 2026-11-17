<script setup lang="ts">
/**
 * Satu butir daftar periksa lampiran (<ul class="mn-periksa">): ikon
 * keadaan (ada / wajib belum ada / opsional kosong), nama lampiran dengan
 * tanda wajib/opsional, dan isi kanan (chip berkas, tombol) lewat slot.
 */
import Ikon from './Ikon.vue';
import { IKON } from './ikon';

withDefaults(defineProps<{
  label: string;
  ada: boolean;
  wajib?: boolean;
  sub?: string | null;
}>(), { wajib: false, sub: null });
</script>

<template>
  <li :class="ada ? 'mn-periksa-ada' : (wajib ? 'mn-periksa-kurang' : '')">
    <span class="mn-periksa-ikon"><Ikon :d="ada ? IKON.cek : (wajib ? IKON.awas : IKON.titik)" /></span>
    <span class="mn-periksa-teks">
      <span class="mn-periksa-label">{{ label }}</span>
      <span class="mn-periksa-sub">{{ ada ? 'Sudah diunggah' : (wajib ? 'Wajib · belum diunggah' : 'Opsional · belum diunggah') }}<template v-if="sub"> · {{ sub }}</template></span>
    </span>
    <span v-if="$slots.default" class="mn-periksa-kanan"><slot /></span>
  </li>
</template>
