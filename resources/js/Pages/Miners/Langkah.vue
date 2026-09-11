<script setup lang="ts">
/**
 * Langkah persetujuan satu dokumen, berurutan.
 *
 * SELURUH LANGKAH DITAMPILKAN SEKALIGUS, termasuk yang belum tiba
 * gilirannya. Yang mengajukan berhak tahu berapa langkah lagi berkasnya
 * harus dilewati sebelum terbit; menampilkan hanya langkah berjalan
 * membuat layar menjawab "satu langkah lagi" pada tiap langkah sampai
 * yang terakhir.
 */
import { KEADAAN } from '../../Grafik/warna';

defineProps<{ alur?: any[] | null }>();

const WARNA: Record<string, string> = {
  setuju:       KEADAAN.baik,
  menunggu:     KEADAAN.netral,
  tolak:        KEADAAN.gawat,
  dikembalikan: KEADAAN.ingat,
};

const LABEL: Record<string, string> = {
  setuju: 'disetujui', menunggu: 'menunggu',
  tolak: 'ditolak', dikembalikan: 'dikembalikan',
};
</script>

<template>
  <ol v-if="alur?.length" class="flex flex-wrap items-center gap-x-1 gap-y-2 text-xs">
    <li v-for="(l, i) in alur" :key="l.urutan" class="flex items-center gap-1">
      <span v-if="i > 0" class="text-stone-400" aria-hidden="true">→</span>

      <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1"
            :style="{ backgroundColor: (WARNA[l.keadaan] ?? KEADAAN.netral) + '14',
                      color: WARNA[l.keadaan] ?? KEADAAN.netral }">
        <span class="h-1.5 w-1.5 rounded-full"
              :style="{ backgroundColor: WARNA[l.keadaan] ?? KEADAAN.netral }" />
        <span class="font-medium">{{ l.label }}</span>
        <span class="opacity-70">{{ LABEL[l.keadaan] ?? l.keadaan }}</span>
      </span>
    </li>
  </ol>
  <span v-else class="text-xs text-stone-400">Belum diajukan</span>
</template>
