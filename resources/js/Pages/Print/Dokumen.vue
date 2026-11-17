<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';
defineOptions({ layout: BlankLayout });
const props = defineProps<{ documents: any[]; dok?: any; kembali?: string }>();
const chunks = computed(() => { const out: any[][] = []; for (let i = 0; i < props.documents.length; i += 18) out.push(props.documents.slice(i, i + 18)); return out.length ? out : [[]]; });
const tanggal = (v: unknown) => v ? new Date(String(v)).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const warna = (v: string) => v === 'berlaku' ? '#84cc16' : v === 'draft' ? '#f59e0b' : '#a8a29e';
</script>
<template>
  <Head title="Daftar Induk Dokumen" /><PrintShell title="Daftar Induk Dokumen" :kembali="props.kembali"><div class="space-y-5"><section v-for="(bagian, page) in chunks" :key="page" class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0" :class="page < chunks.length - 1 ? 'lembar-putus' : ''"><KopCetak :dok="props.dok" :halaman="page + 1" :dari="chunks.length" /><header v-if="page === 0" class="text-center border-b border-stone-200 pb-4 mb-5"><h1 class="font-bold text-[16px] uppercase">Daftar Induk Dokumen Terkendali</h1><p class="text-[11px] text-stone-500 mt-2"><b>{{ props.documents.length }}</b> dokumen terdaftar</p></header><table class="w-full text-[11px]"><thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-2">No</th><th class="p-2">Nomor</th><th class="p-2">Judul</th><th class="p-2">Jenis</th><th class="p-2">Rev</th><th class="p-2">Berlaku</th><th class="p-2">Tinjau</th><th class="p-2">Status</th></tr></thead><tbody><tr v-for="(d, index) in bagian" :key="d.id" class="border-b border-stone-100"><td class="p-2">{{ page * 18 + index + 1 }}</td><td class="p-2 font-semibold">{{ d.kode }}</td><td class="p-2">{{ d.judul }}</td><td class="p-2">{{ d.jenis }}</td><td class="p-2">{{ d.revisi }}</td><td class="p-2">{{ tanggal(d.tanggal_berlaku) }}</td><td class="p-2">{{ tanggal(d.tanggal_tinjau) }}</td><td class="p-2 font-semibold" :style="{ color: warna(d.status) }">{{ d.status }}</td></tr><tr v-if="!bagian.length"><td colspan="8" class="p-8 text-center text-stone-400">Belum ada dokumen terdaftar.</td></tr></tbody></table><section v-if="page === chunks.length - 1" class="pt-8 grid gap-8 sm:grid-cols-2 text-center text-[11px]"><div>Pengendali Dokumen<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b></div><div>Disetujui oleh<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b></div></section></section></div></PrintShell>
</template>
