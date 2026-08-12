<script setup lang="ts">
/**
 * Satu baris riwayat mutasi gudang.
 *
 * Warna dan kata kerja mengikuti jenisnya. Opname sengaja tidak diberi
 * panah arah — ia menetapkan saldo, bukan menambah atau mengurangi, dan
 * panah akan membuatnya terbaca sebagai penerimaan.
 */
import { computed } from 'vue';
import { angka } from '../angka';
import type { BarisMutasiGudang } from '../types';

const props = defineProps<{ m: BarisMutasiGudang }>();

const RUPA: Record<string, [string, string]> = {
  masuk:  ['t-hijau',  'M12 19V5M6 11l6-6 6 6'],
  keluar: ['t-biru',   'M12 5v14M6 13l6 6 6-6'],
  rusak:  ['t-merah',  'M12 9.3v4.2m0 3.3h.01M10.4 4 2.5 17.8A1.8 1.8 0 0 0 4.1 20.5h15.8a1.8 1.8 0 0 0 1.6-2.7L13.6 4a1.8 1.8 0 0 0-3.2 0Z'],
  opname: ['t-kuning', 'M9 11l3 3 7-7M4 12h.01M4 17h.01M4 7h.01'],
};

const rupa = computed(() => RUPA[props.m.jenis] ?? ['t-toska', 'M5 12h14']);
</script>

<template>
  <li class="flex items-center gap-3 py-2 border-b border-stone-50 last:border-0">
    <span class="w-9 h-9 rounded-xl grid place-items-center shrink-0" :class="rupa[0]">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
           stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
        <path :d="rupa[1]" /></svg>
    </span>

    <div class="min-w-0 flex-1">
      <p class="text-[12.5px] font-semibold text-cam-ink truncate">{{ m.barang ?? '—' }}</p>
      <p class="text-[11px] text-stone-400 truncate">
        {{ m.nomor }} · {{ m.tanggal }}<template v-if="m.pihak"> · {{ m.pihak }}</template>
      </p>
    </div>

    <span class="text-[12.5px] font-bold tabular-nums shrink-0">
      <span v-if="m.jenis === 'opname'" class="text-stone-500">jadi {{ angka(m.stokFisik) }}</span>
      <span v-else :class="m.jenis === 'masuk' ? 'text-[#4A8E2C]' : 'text-[#C03A3A]'">
        {{ m.jenis === 'masuk' ? '+' : '−' }}{{ angka(m.jumlah) }}
      </span>
      <span class="text-stone-400 font-normal">{{ m.satuan }}</span>
    </span>
  </li>
</template>
