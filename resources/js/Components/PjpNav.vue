<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * Bilah pindah antar halaman modul PJP.
 *
 * Digambar dari tautan yang dikirim server, bukan dari jalur yang
 * ditulis tangan di sini. Jalur tulisan tangan kebetulan benar pada hari
 * ia ditulis dan tidak ikut berubah ketika rutenya berubah — yang rusak
 * karenanya adalah seluruh navigasi modul, tanpa satu pun galat.
 */
defineProps<{
  tautan: Record<string, string>;
  aktif: 'beranda' | 'persyaratan' | 'pelaporan' | 'evaluasi' | 'daftar' | 'bantuan';
}>();

const BUTIR: Array<{ kunci: string; label: string }> = [
  { kunci: 'beranda',     label: 'Beranda' },
  { kunci: 'persyaratan', label: 'Persyaratan' },
  { kunci: 'pelaporan',   label: 'Pelaporan' },
  { kunci: 'evaluasi',    label: 'Evaluasi' },
  { kunci: 'daftar',      label: 'Data PJP' },
  { kunci: 'bantuan',     label: 'Bantuan' },
];
</script>

<template>
  <nav class="flex flex-wrap gap-2">
    <Link
      v-for="butir in BUTIR"
      :key="butir.kunci"
      :href="tautan[butir.kunci]"
      class="rounded-full px-4 py-2 text-[11px] font-bold"
      :class="aktif === butir.kunci ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'"
    >{{ butir.label }}</Link>
  </nav>
</template>
