<script setup lang="ts">
/*
  Laporan Kinerja Energi bulanan (yang lama) — kini dihitung langsung dan
  ditautkan ke Pusat Pelaporan untuk versi yang disetujui & dibekukan.
*/
import { Link } from '@inertiajs/vue3';
import KopCetak from '../KopCetak.vue';
import { n, rp, tgl } from '../../energi';

defineProps<{ dok: any; r: Record<string, any>; baseline: Record<string, any> | null; dari: any; sampai: any;
              perKategori: Record<string, any>; peringkat: any[]; peluang: any[]; hemat: Record<string, any> }>();
const cetak = () => window.print();
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2 print:hidden">
      <p class="text-[12px] text-stone-500">Pratinjau langsung. Untuk laporan bernomor yang dibekukan dan berparaf, susun "Laporan Kinerja Energi Bulanan" di Pusat Pelaporan.</p>
      <div class="flex gap-2"><Link href="/energi/pelaporan" class="eq-btn-lain">Pusat Pelaporan</Link><button type="button" class="eq-btn-utama" @click="cetak">Cetak</button></div>
    </div>
    <div class="bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:p-0 text-[11.5px]">
      <KopCetak :dok="dok" />
      <h1 class="text-center font-bold text-[15px] uppercase mt-4">{{ dok?.judul ?? 'Laporan Kinerja Energi dan Emisi Karbon' }}</h1>
      <p class="text-center text-stone-500 mb-4">Periode {{ tgl(dari) }} — {{ tgl(sampai) }}</p>

      <h3 class="font-bold text-[13px] mb-2">A. Ringkasan</h3>
      <table class="w-full border-collapse mb-5"><tbody>
        <tr class="border-b border-stone-200"><td class="p-1.5 w-1/3">Total energi</td><td class="p-1.5 font-semibold">{{ n(r.gj, 1) }} GJ</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Solar</td><td class="p-1.5">{{ n(r.liter, 0) }} L (alat {{ n(r.liter_alat, 0) }} · genset {{ n(r.liter_genset, 0) }})</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Listrik</td><td class="p-1.5">{{ n(r.kwh, 0) }} kWh</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Produksi</td><td class="p-1.5">{{ n(r.ton, 0) }} ton · {{ n(r.bcm, 0) }} BCM</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Intensitas energi</td><td class="p-1.5">{{ n(r.intensitas, 4) }} GJ/ton<template v-if="baseline"> (target {{ n(baseline.target_gj_ton, 4) }})</template></td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Emisi</td><td class="p-1.5">{{ n(r.tco2e, 2) }} tCO₂e</td></tr>
        <tr class="border-b border-stone-200"><td class="p-1.5">Biaya energi</td><td class="p-1.5">{{ rp(r.rupiah) }}</td></tr>
      </tbody></table>

      <h3 class="font-bold text-[13px] mb-2">B. Konsumsi per kategori alat</h3>
      <table class="w-full border-collapse mb-5">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1.5">Kategori</th><th class="p-1.5 text-right">Liter</th><th class="p-1.5 text-right">HM</th><th class="p-1.5 text-right">L/HM</th></tr></thead>
        <tbody><tr v-for="(k, kode) in perKategori" :key="kode" class="border-b border-stone-100"><td class="p-1.5">{{ k.nama }}</td><td class="p-1.5 text-right">{{ n(k.liter, 0) }}</td><td class="p-1.5 text-right">{{ n(k.hm, 1) }}</td><td class="p-1.5 text-right">{{ n(k.l_hm, 2) }}</td></tr></tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">C. Peringkat unit</h3>
      <table class="w-full border-collapse mb-5">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1.5">Unit</th><th class="p-1.5 text-right">L/HM</th><th class="p-1.5 text-right">Acuan</th><th class="p-1.5">Status</th></tr></thead>
        <tbody><tr v-for="u in peringkat" :key="u.unit?.id" class="border-b border-stone-100"><td class="p-1.5">{{ u.unit?.kode }} — {{ u.unit?.nama }}</td><td class="p-1.5 text-right">{{ n(u.l_hm, 2) }}</td><td class="p-1.5 text-right">{{ n(u.acuan, 2) }}</td><td class="p-1.5">{{ u.status?.label }}</td></tr></tbody>
      </table>

      <h3 class="font-bold text-[13px] mb-2">D. Peluang penghematan</h3>
      <table class="w-full border-collapse">
        <thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-1.5">Peluang</th><th class="p-1.5">Status</th><th class="p-1.5 text-right">Hemat/bulan</th></tr></thead>
        <tbody><tr v-for="p in peluang" :key="p.id" class="border-b border-stone-100"><td class="p-1.5">{{ p.judul }}</td><td class="p-1.5">{{ p.status }}</td><td class="p-1.5 text-right">{{ n(p.hemat_liter, 0) }} L · {{ n(p.hemat_kwh, 0) }} kWh</td></tr></tbody>
      </table>
      <p class="mt-3 text-stone-500">Penghematan berjalan: {{ hemat.jumlah ?? 0 }} peluang, {{ n(hemat.gj, 1) }} GJ/bulan, {{ n(hemat.tco2e, 2) }} tCO₂e/bulan.</p>
    </div>
  </div>
</template>
