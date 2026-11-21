<script setup lang="ts">
/**
 * Pemilih perusahaan bagi administrator pada halaman tata kelola energi.
 * Pengguna biasa tidak menerima daftarnya dan tidak melihat apa pun di sini:
 * perusahaannya selalu perusahaannya sendiri.
 */
import { router } from '@inertiajs/vue3';

const props = defineProps<{ daftar?: Array<{ id: number; nama: string }>; terpilih?: number | null; nama?: string | null }>();

function pilih(e: Event) {
  const v = (e.target as HTMLSelectElement).value;
  const q = Object.fromEntries([...new URLSearchParams(window.location.search)].filter(([k]) => k !== 'perusahaan'));
  router.get(window.location.pathname, v ? { ...q, perusahaan: v } : q, { preserveScroll: true });
}
</script>

<template>
  <label v-if="props.daftar?.length" class="text-[11px] font-semibold text-stone-500 flex items-center gap-2">
    Perusahaan
    <select class="rounded-lg border-stone-200 text-[12px]" :value="props.terpilih ?? ''" @change="pilih">
      <option value="">— tanpa perusahaan —</option>
      <option v-for="c in props.daftar" :key="c.id" :value="c.id">{{ c.nama }}</option>
    </select>
  </label>
  <span v-else-if="props.nama" class="text-[11px] text-stone-500">{{ props.nama }}</span>
</template>
