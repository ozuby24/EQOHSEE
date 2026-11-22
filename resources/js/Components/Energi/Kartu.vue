<script setup lang="ts">
/**
 * Panel bagian halaman energi & karbon. Bentuknya sama dengan
 * KartuGrafik dan panel HRIS supaya kartu teks dan kartu grafik pada
 * satu halaman tidak terlihat berasal dari dua aplikasi.
 *
 * `ikon` + `warna` memberi judul sebuah ubin ikon bergradasi — penanda
 * bagian yang cepat ditemukan mata saat halamannya panjang.
 * `rapat` untuk panel yang isinya tabel selebar kartu.
 */
import Ikon from './Ikon.vue';

withDefaults(defineProps<{
  judul: string; ket?: string | null; rapat?: boolean;
  ikon?: string | null; warna?: 'oranye' | 'amber' | 'toska' | 'biru' | 'ungu' | 'hijau' | 'merah' | 'langit' | 'mawar' | null;
}>(), { ket: null, rapat: false, ikon: null, warna: null });
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card" :class="rapat ? 'overflow-hidden' : 'p-5'">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2" :class="rapat ? 'px-5 pt-4 pb-3' : 'mb-3'">
      <div class="min-w-0 flex items-center gap-2.5">
        <span v-if="ikon" class="kt-ikon" :class="`kt-${warna ?? 'oranye'}`"><Ikon :nama="ikon" :ukuran="16" :tebal="2.2" /></span>
        <div class="min-w-0">
          <h3 class="text-[13.5px] font-bold text-cam-ink">{{ judul }}</h3>
          <p v-if="ket" class="text-[11.5px] text-stone-500 mt-0.5">{{ ket }}</p>
        </div>
      </div>
      <div v-if="$slots.aksi" class="flex flex-wrap items-center gap-2"><slot name="aksi" /></div>
    </div>
    <slot />
  </section>
</template>

<style scoped>
.kt-ikon { width: 30px; height: 30px; flex: none; border-radius: 9px; display: grid; place-items: center; color: #fff;
  background: linear-gradient(135deg, var(--k1), var(--k2)); }
.kt-oranye { --k1: #E25C00; --k2: #FF9A1F; }
.kt-amber  { --k1: #C2410C; --k2: #F59E0B; }
.kt-toska  { --k1: #0B5F57; --k2: #14B8A6; }
.kt-biru   { --k1: #1D4ED8; --k2: #60A5FA; }
.kt-ungu   { --k1: #5B21B6; --k2: #A78BFA; }
.kt-hijau  { --k1: #15803D; --k2: #4ADE80; }
.kt-merah  { --k1: #B91C1C; --k2: #F87171; }
.kt-langit { --k1: #0369A1; --k2: #38BDF8; }
.kt-mawar  { --k1: #BE123C; --k2: #FB7185; }
</style>
