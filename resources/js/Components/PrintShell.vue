<script setup lang="ts">
defineProps<{ title: string; kembali?: string }>();
const cetak = () => window.print();
</script>

<template>
  <div class="min-h-screen bg-stone-100 print:bg-white text-[#1b1817]">
    <div class="print:hidden sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-stone-200">
      <div class="max-w-[850px] mx-auto flex items-center justify-between gap-3 px-4 py-2.5">
        <a :href="kembali || '/'" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Kembali</a>
        <div class="flex items-center gap-3"><span class="hidden sm:block text-[11px] text-stone-400">{{ title }}</span><button type="button" class="lime-gradient rounded-xl text-white px-4 py-2 text-[12px] font-bold" @click="cetak">Cetak / Simpan PDF</button></div>
      </div>
    </div>
    <main class="max-w-[850px] mx-auto px-4 py-5 print:p-0 print:max-w-none"><slot /></main>
  </div>
</template>
