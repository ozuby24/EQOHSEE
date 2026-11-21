<script setup lang="ts">
/**
 * Panel bagian halaman energi. Bentuknya sama dengan KartuGrafik dan
 * panel HRIS supaya kartu teks dan kartu grafik pada satu halaman
 * tidak terlihat berasal dari dua aplikasi.
 *
 * `rapat` untuk panel yang isinya tabel selebar kartu: judulnya tetap
 * berjarak, tabelnya menempel ke tepi.
 */
withDefaults(defineProps<{ judul: string; ket?: string | null; rapat?: boolean }>(), { ket: null, rapat: false });
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card" :class="rapat ? 'overflow-hidden' : 'p-5'">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2" :class="rapat ? 'px-5 pt-4 pb-3' : 'mb-3'">
      <div class="min-w-0">
        <h3 class="text-[13.5px] font-bold text-cam-ink">{{ judul }}</h3>
        <p v-if="ket" class="text-[11.5px] text-stone-500 mt-0.5">{{ ket }}</p>
      </div>
      <div v-if="$slots.aksi" class="flex flex-wrap items-center gap-2"><slot name="aksi" /></div>
    </div>
    <slot />
  </section>
</template>
