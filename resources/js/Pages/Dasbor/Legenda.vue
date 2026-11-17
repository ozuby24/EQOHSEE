<script setup lang="ts">
/**
 * Keterangan warna untuk grafik bergaris ganda.
 *
 * Grafik garis di modul ini menamai deretnya hanya saat kursor
 * membidiknya — cukup di layar sentuh yang dapat ditahan, tidak cukup
 * pada layar yang dibaca sekilas atau pada tangkapan layar yang
 * ditempel ke laporan. Dua garis tanpa keterangan warna adalah dua
 * garis yang artinya harus ditebak.
 */
defineProps<{ deret: { nama: string; warna?: string }[] }>();
</script>

<template>
  <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
    <span v-for="d in deret" :key="d.nama" class="inline-flex items-center gap-1.5 text-[11.5px] text-stone-500">
      <i class="inline-block w-3 h-[3px] rounded-full"
         :style="{ background: d.warna ?? '#C47000' }" aria-hidden="true"></i>
      {{ d.nama }}
    </span>
  </div>
</template>
