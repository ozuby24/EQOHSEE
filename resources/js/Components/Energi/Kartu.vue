<script setup lang="ts">
/** Kartu bagian halaman energi: judul, keterangan, aksi di kanan, isi. */
defineProps<{ judul: string; ket?: string | null }>();
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
      <div class="min-w-0">
        <h3 class="font-bold text-[14px] text-cam-ink">{{ judul }}</h3>
        <p v-if="ket" class="text-[11.5px] text-stone-500 mt-0.5">{{ ket }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2"><slot name="aksi" /></div>
    </div>
    <slot />
  </section>
</template>
