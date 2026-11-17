<script setup lang="ts">
/**
 * Lencana keadaan satu berkas.
 *
 * SELALU BERLABEL, tidak pernah warna saja. Empat keadaan dibedakan
 * empat warna, dan pembaca yang tidak dapat membedakan merah dari
 * jingga — sekitar satu dari dua belas laki-laki — hanya melihat dua.
 * Pada layar yang memutuskan boleh tidaknya seseorang masuk tambang,
 * itu bukan ketidaknyamanan.
 */
import { computed } from 'vue';
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  keadaan?: string | null;
  label?: string | null;
  nada?: Record<string, string> | null;
  sisa?: number | null;
}>();

const warna = computed(() => {
  const n = props.nada?.[String(props.keadaan)] ?? 'netral';
  return KEADAAN[n] ?? KEADAAN.netral;
});

/**
 * Sisa hari ditulis apa adanya, termasuk yang negatif.
 *
 * "Lewat 14 hari" memberi tahu seberapa mendesak; "habis" tidak.
 * Perbedaannya menentukan mana yang diurus lebih dahulu ketika ada dua
 * puluh baris merah di layar.
 */
const sisaTeks = computed(() => {
  const s = props.sisa;
  if (s === null || s === undefined) return '';
  if (s < 0) return `lewat ${Math.abs(s)} hari`;
  if (s === 0) return 'habis hari ini';
  return `${s} hari lagi`;
});
</script>

<template>
  <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
        :style="{ backgroundColor: warna + '1A', color: warna }">
    <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: warna }" />
    {{ label ?? keadaan }}
    <span v-if="sisaTeks" class="opacity-75">· {{ sisaTeks }}</span>
  </span>
</template>
