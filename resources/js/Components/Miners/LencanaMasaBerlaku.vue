<script setup lang="ts">
/**
 * Lencana masa berlaku — badge.blade.php Safe Track: tanggal dalam pil
 * lembut berwarna zona beserta ikonnya (ikon juga untuk "Belum diisi"), lalu "376 hari lagi" / "Lewat 8 hari"
 * di bawahnya, dan sumber tanggalnya (mis. "ikut MCU") bila disebut.
 */
import '../../../css/miners.css';
import { computed } from 'vue';
import { tglId, zonaDari } from './zona';
import { IKON_ZONA } from './ikon';
import Ikon from './Ikon.vue';

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
      <Ikon :d="IKON_ZONA[zona]" />
      {{ tanggal ? tglId(tanggal) : 'Belum diisi' }}
    </span>
    <span v-if="teks" class="mn-sisa" :class="{ 'mn-sisa-lewat': zona === 'expired' }">{{ teks }}</span>
    <span v-if="sumber" class="mn-sumber">{{ sumber }}</span>
  </span>
</template>
