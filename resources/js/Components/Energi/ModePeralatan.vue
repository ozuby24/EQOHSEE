<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import { n, rp } from '../../energi';

const props = defineProps<{ peringkat: any[]; perKategori: Record<string, any>; kategori: string | null }>();

/* Kategori yang sama diklik lagi melepas saringannya; parameter lain
   (rentang tanggal) dibawa apa adanya. */
function saring(kode: string | null) {
  const q = Object.fromEntries([...new URLSearchParams(window.location.search)].filter(([k]) => k !== 'kategori'));
  router.get(window.location.pathname, kode ? { ...q, kategori: kode } : q, { preserveScroll: true });
}
</script>

<template>
  <div class="space-y-4">
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <button v-for="(k, kode) in perKategori" :key="kode" type="button" @click="saring(props.kategori === String(kode) ? null : String(kode))"
              class="text-left rounded-2xl bg-white border shadow-card p-4" :class="props.kategori === String(kode) ? 'border-cam-orange' : 'border-stone-100'">
        <p class="text-[11px] font-semibold text-stone-500">{{ k.nama }}</p>
        <p class="text-[20px] font-bold text-cam-ink num">{{ n(k.l_hm, 2) }}<span class="text-[11px] text-stone-400 ml-1">L/HM</span></p>
        <p class="text-[10.5px] text-stone-400">{{ n(k.liter, 0) }} L · {{ n(k.hm, 1) }} HM</p>
      </button>
    </section>

    <Kartu judul="Peringkat unit" :ket="props.kategori ? 'Satu kategori' : null">
      <table class="w-full text-[12px]">
        <thead><tr class="text-left text-stone-400 border-b border-stone-100"><th class="py-2">Unit</th><th class="py-2">Kategori</th><th class="py-2 text-right">Liter</th><th class="py-2 text-right">HM</th><th class="py-2 text-right">L/HM</th><th class="py-2 text-right">L/ton</th><th class="py-2 text-right">Idle</th><th class="py-2 text-right">Biaya</th><th class="py-2">Status</th></tr></thead>
        <tbody>
          <tr v-for="u in peringkat" :key="u.unit?.id" class="border-b border-stone-50">
            <td class="py-2"><Link :href="`/energi/alat/${u.unit?.id}`" class="font-semibold text-cam-lime-deep">{{ u.unit?.kode }}</Link><span class="block text-[10.5px] text-stone-400">{{ u.unit?.nama }}</span></td>
            <td class="py-2">{{ u.kategori }}</td><td class="py-2 text-right num">{{ n(u.liter, 0) }}</td><td class="py-2 text-right num">{{ n(u.hm, 1) }}</td>
            <td class="py-2 text-right num font-bold">{{ n(u.l_hm, 2) }}</td><td class="py-2 text-right num">{{ n(u.l_ton, 3) }}</td>
            <td class="py-2 text-right num">{{ n(u.idle, 1) }} j</td><td class="py-2 text-right num">{{ rp(u.rupiah) }}</td>
            <td class="py-2"><span class="text-[11px] font-bold" :style="{ color: u.status?.warna }">{{ u.status?.label }}</span></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!peringkat.length" class="text-[12px] text-stone-400 py-4 text-center">Belum ada catatan solar per unit pada rentang ini.</p>
    </Kartu>
  </div>
</template>
