<script setup lang="ts">
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps<{
  monitor: Record<string, number>;
  alerts: Array<Record<string, string>>;
  unit: Array<Record<string, any>>;
  trenMonitor: Array<Record<string, any>>;
  baseline?: Record<string, any>;
  dari: string;
  sampai: string;
  tautan: Record<string, string>;
}>();

const filter = reactive({ dari: String(props.dari).slice(0, 10), sampai: String(props.sampai).slice(0, 10) });
const angka = (value: unknown, digits = 2) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: digits });
const tanggal = (value: unknown) => value ? new Date(String(value)).toLocaleDateString('id-ID') : '-';
function terapkan() { router.get(window.location.pathname, filter, { preserveState: true, preserveScroll: true }); }
</script>

<template>
  <div class="space-y-5">
    <section class="rounded-2xl bg-cam-ink text-white p-5"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-[10px] uppercase tracking-[.18em] text-cam-lime-light font-bold">Operational intelligence</p><h3 class="text-xl font-extrabold mt-1">Control Tower Engineering</h3><p class="text-[12px] text-white/60 mt-1">Membaca produksi, fuel, listrik, dan anomali unit dalam satu rentang.</p></div><Link :href="props.tautan.input" class="rounded-lg bg-cam-orange px-4 py-2 text-[11px] font-bold text-white">Buka input lapangan</Link></div><form class="flex flex-wrap items-end gap-2 mt-5" @submit.prevent="terapkan"><label class="text-[11px] text-white/60">Dari<input v-model="filter.dari" type="date" class="block mt-1 rounded-lg border-0 text-[12px] text-stone-800"></label><label class="text-[11px] text-white/60">Sampai<input v-model="filter.sampai" type="date" class="block mt-1 rounded-lg border-0 text-[12px] text-stone-800"></label><button class="rounded-lg bg-white/10 px-4 py-2 text-[11px] font-bold hover:bg-white/20">Terapkan</button></form></section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><article v-for="item in [{l:'Produksi',v:angka(props.monitor.ton),s:'ton'},{l:'Energi',v:angka(props.monitor.gj),s:'GJ'},{l:'Intensitas',v:angka(props.monitor.intensitas,4),s:'GJ/ton'},{l:'Hari data',v:`${props.monitor.hari_produksi}/${props.monitor.hari_fuel}/${props.monitor.hari_listrik}`,s:'prod / fuel / listrik'}]" :key="item.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4"><p class="text-[10px] uppercase font-bold text-stone-400">{{ item.l }}</p><b class="block text-xl text-cam-ink mt-1">{{ item.v }}</b><small class="text-[10px] text-stone-400">{{ item.s }}</small></article></section>

    <section class="grid gap-5 xl:grid-cols-[.8fr_1.2fr]"><div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5"><div class="flex items-center justify-between"><h3 class="font-bold text-[14px]">Alerts & Prioritas</h3><span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-600">{{ props.alerts.length }} temuan</span></div><div v-if="props.alerts.length" class="space-y-2 mt-4"><article v-for="item in props.alerts" :key="item.judul" class="rounded-xl border-l-4 p-3" :class="item.level === 'tinggi' ? 'border-red-500 bg-red-50' : 'border-amber-400 bg-amber-50'"><b class="block text-[12px]">{{ item.judul }}</b><p class="text-[11px] text-stone-600 mt-1">{{ item.ket }}</p></article></div><p v-else class="mt-5 rounded-xl bg-emerald-50 p-4 text-[12px] text-emerald-700">Tidak ada anomali utama pada rentang ini.</p><div v-if="props.baseline" class="mt-5 rounded-xl bg-stone-50 p-3 text-[11px] text-stone-500">Target intensitas {{ props.baseline.tahun }}: <b>{{ angka(props.baseline.target_gj_ton, 4) }} GJ/ton</b></div></div><div class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Unit yang Perlu Dilihat</h3><p class="text-[11px] text-stone-400">Urut dari fuel rate tertinggi terhadap acuan kelasnya.</p></div><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Unit</th><th class="px-5 py-3">Fuel rate</th><th class="px-5 py-3">Idle</th><th class="px-5 py-3">Tonase</th><th class="px-5 py-3">Status</th></tr></thead><tbody><tr v-for="item in props.unit.slice(0, 8)" :key="item.kode" class="border-b border-stone-50"><td class="px-5 py-3 font-semibold">{{ item.kode }}<small class="block text-[10px] text-stone-400">{{ item.nama }}</small></td><td class="px-5 py-3">{{ angka(item.fuel_rate) }} L/HM<small class="block text-[10px] text-stone-400">Acuan {{ angka(item.acuan) }}</small></td><td class="px-5 py-3">{{ angka(item.idle_persen, 1) }}%</td><td class="px-5 py-3">{{ angka(item.ton) }}</td><td class="px-5 py-3 font-semibold" :class="item.status === 'Normal' ? 'text-emerald-700' : 'text-red-600'">{{ item.status }}</td></tr><tr v-if="!props.unit.length"><td colspan="5" class="p-8 text-center text-stone-400">Belum ada input fuel alat.</td></tr></tbody></table></div></section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto"><div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Tren Harian</h3></div><table class="min-w-full text-left text-[12px]"><thead><tr class="border-b border-stone-100 text-stone-400"><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Ton</th><th class="px-5 py-3">Solar</th><th class="px-5 py-3">Listrik</th><th class="px-5 py-3">Intensitas</th></tr></thead><tbody><tr v-for="item in props.trenMonitor" :key="item.tanggal" class="border-b border-stone-50"><td class="px-5 py-3">{{ tanggal(item.tanggal) }}</td><td class="px-5 py-3">{{ angka(item.ton) }}</td><td class="px-5 py-3">{{ angka(item.liter) }} L</td><td class="px-5 py-3">{{ angka(item.kwh) }} kWh</td><td class="px-5 py-3 font-semibold">{{ angka(item.intensitas, 4) }} GJ/ton</td></tr><tr v-if="!props.trenMonitor.length"><td colspan="5" class="p-8 text-center text-stone-400">Belum ada tren data pada rentang ini.</td></tr></tbody></table></section>
  </div>
</template>
