<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';
defineOptions({ layout: BlankLayout });
const props = defineProps<{ 
  dok?: Record<string, any> | null;data: any[]; kembali?: string }>();
const tanggal = (v: unknown) => v ? new Date(String(v)).toLocaleDateString('id-ID') : '—';
const warna = (v: string) => v === 'Sesuai' ? '#84cc16' : v === 'Tidak Sesuai' ? '#ef4444' : '#a8a29e';
</script>
<template>
  <Head title="Laporan Inspeksi" /><PrintShell title="Laporan Inspeksi" :kembali="props.kembali"><section class="bg-white p-5 print:p-0"><KopCetak :dok="props.dok" /><header class="flex justify-between items-end border-b-[3px] border-cam-lime-deep pb-3 mb-4"><div class="flex items-center gap-3"><div class="w-8 h-8 rounded-lg lime-gradient text-white grid place-items-center font-black">E</div><div><h1 class="text-lg font-bold">Laporan Inspeksi</h1><p class="text-[11px] text-stone-500">EQOHSEE · HSE Platform</p></div></div><div class="text-right text-[10px] text-stone-500">Dicetak {{ new Date().toLocaleString('id-ID') }}<br>Total <b>{{ props.data.length }}</b> inspeksi</div></header><article v-for="i in props.data" :key="i.id" class="mb-5 break-inside-avoid"><h2 class="font-bold text-[13px]">{{ i.kode }} — {{ i.judul }}</h2><p class="text-[10px] text-stone-500 mb-2">{{ i.template?.nama || '—' }} · {{ tanggal(i.tanggal) }} · {{ i.lokasi || '—' }} · {{ i.company?.name || '—' }} · Status: <b>{{ i.status }}</b><br>Inspektur: {{ i.inspectors?.map((p: any) => p.nama + ' (' + p.peran + ')').join(', ') || '—' }}</p><table class="w-full text-[10.5px]"><thead><tr class="bg-stone-100 text-left text-stone-500"><th class="p-1.5">Kelompok</th><th class="p-1.5">Parameter</th><th class="p-1.5">Kondisi</th><th class="p-1.5">Risiko</th><th class="p-1.5">Temuan</th><th class="p-1.5">Tindakan</th></tr></thead><tbody><tr v-for="it in i.items || []" :key="it.id" class="border-b border-stone-100 align-top"><td class="p-1.5">{{ it.kelompok || '—' }}</td><td class="p-1.5">{{ it.uraian }}</td><td class="p-1.5"><span v-if="it.kondisi" class="rounded-full px-2 py-0.5 text-white text-[9px]" :style="{ background: warna(it.kondisi) }">{{ it.kondisi }}</span><span v-else>—</span></td><td class="p-1.5">{{ it.risiko || '—' }}</td><td class="p-1.5">{{ it.temuan || '—' }}</td><td class="p-1.5">{{ it.tindakan || '—' }}</td></tr><tr v-if="!i.items?.length"><td colspan="6" class="p-4 text-center text-stone-400">Belum ada item.</td></tr></tbody></table></article><p v-if="!props.data.length" class="p-8 text-center text-stone-400">Tidak ada data inspeksi.</p></section></PrintShell>
</template>
