<script setup lang="ts">
/* `mendatar`: lembar A4 mendatar (sertifikat). Wadah 850px memotong
   lembar 1050px-nya di layar lebar sehingga harus digeser; yang
   mendatar diberi wadah selebar lembarnya sendiri. */
const props = defineProps<{ title: string; kembali?: string; mendatar?: boolean }>();
const cetak = () => window.print();
</script>

<template>
  <div class="min-h-screen bg-stone-100 print:bg-white text-[#1b1817]">
    <div class="print:hidden sticky top-0 z-10 bg-white/90 backdrop-blur border-b border-stone-200">
      <div class="mx-auto flex items-center justify-between gap-3 px-4 py-2.5" :class="props.mendatar ? 'max-w-[1120px]' : 'max-w-[850px]'">
        <a :href="props.kembali || '/'" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Kembali</a>
        <div class="flex items-center gap-3"><span class="hidden sm:block text-[11px] text-stone-400">{{ props.title }}</span><button type="button" class="lime-gradient rounded-xl text-white px-4 py-2 text-[12px] font-bold" @click="cetak">Cetak / Simpan PDF</button></div>
      </div>
    </div>
    <!-- Di layar sempit lembarnya digeser di sini. Tanpa ini tabel lembar
         A4 terpotong di tepi kanan HP (overflow-x:clip pada body) dan
         kolom terakhirnya — status, paraf — tidak pernah terlihat. -->
    <main data-cetak :class="props.mendatar ? 'max-w-[1120px]' : 'max-w-[850px]'" class="mx-auto px-4 py-5 overflow-x-auto print:overflow-visible print:p-0 print:max-w-none"><slot /></main>
  </div>
</template>
