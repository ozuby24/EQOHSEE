<script setup lang="ts">
/**
 * Kepala halaman Energy: judul, penjelasan singkat, dan penyaring
 * rentang bila halamannya memang bergantung pada periode.
 */
import RentangTanggal from './RentangTanggal.vue';

withDefaults(defineProps<{
  judul: string;
  ket?: string | null;
  dari?: string | null;
  sampai?: string | null;
  rute?: string | null;
}>(), {
  ket: null,
  dari: null,
  sampai: null,
  rute: null,
});
</script>

<template>
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <h2 class="font-display text-[20px] font-black text-cam-ink leading-tight">{{ judul }}</h2>
        <p v-if="ket" class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-2xl">{{ ket }}</p>
      </div>

      <RentangTanggal v-if="rute && dari && sampai" :dari="dari" :sampai="sampai" :rute="rute" />
    </div>

    <slot />
  </section>
</template>
