<script setup lang="ts">
import Ikon from '../Ikon.vue';

/*
 * Kepala satu komponen Rencana Audit: nomor komponen (hijau bila sudah
 * terisi), judul, keterangan, dan status. Dipakai di kolom label baris
 * isian maupun di kepala kartu tabel agar seluruh halaman seragam.
 */
withDefaults(defineProps<{ nomor?: number | string; ikon?: string; judul: string; ket?: string; terisi?: boolean | null }>(), {
  terisi: null,
});
</script>

<template>
  <div class="flex items-start gap-3 min-w-0">
    <span class="grid place-items-center w-8 h-8 shrink-0 rounded-lg text-[12.5px] font-bold tabular-nums transition-colors"
          :class="terisi ? 'bg-hijau-50 text-hijau-700 ring-1 ring-hijau-100' : terisi === false ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-100' : 'bg-navy-50 text-navy-800'">
      <Ikon v-if="ikon" :nama="ikon" class="w-4 h-4" />
      <template v-else>{{ nomor }}</template>
    </span>
    <div class="min-w-0 flex-1">
      <h2 class="kartu-judul">{{ judul }}</h2>
      <p v-if="ket" class="kartu-sub">{{ ket }}</p>
      <p v-if="terisi !== null" class="mt-1.5 inline-flex items-center gap-1 text-[11.5px] font-semibold" :class="terisi ? 'text-hijau-700' : 'text-amber-700'">
        <Ikon :nama="terisi ? 'centang' : 'kurang'" class="w-3 h-3" :tebal="3" /> {{ terisi ? 'Terisi' : 'Belum diisi' }}
      </p>
    </div>
    <slot />
  </div>
</template>
