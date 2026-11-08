<script setup lang="ts">
/**
 * Lencana masa berlaku — badge.blade.php Safe Track: tanggal dalam pil
 * berwarna zona beserta ikonnya, lalu "376 hari lagi" / "Lewat 8 hari"
 * di bawahnya, dan sumber tanggalnya (mis. "ikut MCU") bila disebut.
 */
import '../../../css/miners.css';
import { computed } from 'vue';
import { tglId, zonaDari } from './zona';

const props = withDefaults(defineProps<{
  tanggal?: string | null;
  sisa?: number | null;
  sumber?: string | null;
}>(), { tanggal: null, sisa: null, sumber: null });

const sisaHari = computed<number | null>(() => {
  if (props.sisa !== null && props.sisa !== undefined) return props.sisa;
  if (!props.tanggal) return null;
  const hari = new Date(); hari.setHours(0, 0, 0, 0);
  const t = new Date(props.tanggal.slice(0, 10) + 'T00:00:00');
  return Math.round((t.getTime() - hari.getTime()) / 86400000);
});

const zona = computed(() => props.tanggal ? zonaDari(sisaHari.value) : 'kosong');

const kelas = computed(() => ({
  expired: 'mn-l-bahaya', kritis: 'mn-l-oranye', waspada: 'mn-l-kuning', aman: 'mn-l-sukses', kosong: 'mn-l-abu',
})[zona.value]);

const teks = computed(() => {
  const s = sisaHari.value;
  if (s === null) return null;
  return s < 0 ? `Lewat ${Math.abs(s)} hari` : `${s} hari lagi`;
});
</script>

<template>
  <span class="mn-berlaku">
    <span class="mn-lencana" :class="kelas">
      <svg v-if="zona === 'aman'" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg>
      <svg v-else-if="zona === 'kritis'" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/><path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg>
      <svg v-else-if="zona === 'waspada'" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zM8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/><path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/></svg>
      <svg v-else-if="zona === 'expired'" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/></svg>
      {{ tanggal ? tglId(tanggal) : 'Belum diisi' }}
    </span>
    <span v-if="teks" class="mn-sisa">{{ teks }}</span>
    <span v-if="sumber" class="mn-sumber">{{ sumber }}</span>
  </span>
</template>
