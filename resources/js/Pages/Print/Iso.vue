<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';
defineOptions({ layout: BlankLayout });
const props = defineProps<{ standar: any; kode: string; perBab: Record<string, any>; peta: Record<string, any[]>; cakupan: any; kembali?: string; dok?: any }>();
const bab = computed(() => Object.entries(props.perBab || {}));
const tanggal = (v: unknown) => v ? new Date(String(v)).toLocaleDateString('id-ID') : '—';
</script>
<template>
  <Head :title="`Matriks Pemenuhan ${props.standar.nama}`" /><PrintShell :title="`Matriks Pemenuhan ${props.standar.nama}`" :kembali="props.kembali"><div class="space-y-5"><section v-for="([nomor, isi], index) in bab" :key="nomor" class="lembar bg-white rounded-2xl border border-stone-100 p-6 print:border-0 print:rounded-none print:p-0" :class="index < bab.length - 1 ? 'lembar-putus' : ''"><PrintHeader :dok="props.dok" :halaman="index + 1" :dari="bab.length" /><header v-if="index === 0" class="text-center border-b border-stone-200 pb-4 mb-5"><h1 class="font-bold text-[16px] uppercase">Matriks Pemenuhan Klausul</h1><h2 class="font-bold text-[14px] uppercase">{{ props.standar.nama }} — {{ props.standar.judul }}</h2><p class="text-[11px] text-stone-500 mt-2"><b>{{ props.cakupan.tercakup }}</b> dari <b>{{ props.cakupan.butir }}</b> klausul sudah memiliki dokumen terkendali · {{ props.cakupan.celah }} belum tercakup</p></header><h3 class="font-bold text-[13px] mb-2">Bab {{ nomor }} — {{ isi.judul }}</h3><table class="w-full text-[11px]"><thead><tr class="border-b border-stone-300 text-left text-stone-500"><th class="p-2 w-16">Klausul</th><th class="p-2">Judul</th><th class="p-2">Dokumen Pemenuh</th></tr></thead><tbody><tr v-for="k in isi.klausul" :key="k.no" class="border-b border-stone-100 align-top"><td class="p-2 font-semibold">{{ k.no }}</td><td class="p-2">{{ k.judul }}</td><td class="p-2"><template v-if="props.peta[k.no]?.length"><div v-for="doc in props.peta[k.no]" :key="doc.kode"><b>{{ doc.kode }}</b> — {{ doc.judul }}</div></template><span v-else class="text-amber-700 font-semibold">Belum tercakup</span></td></tr></tbody></table><section v-if="index === bab.length - 1" class="pt-8 grid gap-8 sm:grid-cols-2 text-center text-[11px]"><div>Disusun oleh<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b></div><div>Disetujui oleh<div class="h-14"></div><b class="block border-t border-stone-400 pt-1">&nbsp;</b></div></section></section></div></PrintShell>
</template>

<script lang="ts">
import { h } from 'vue';
/* Kop ringkas diganti kop penuh: yang lama hanya menyebut nama
   perusahaan, judul, dan nomor halaman — tanpa nomor dokumen,
   tanggal penerbitan, tanggal persetujuan, maupun revisi.
   Keempatnya justru yang membuat sebuah lembar disebut
   dokumen terkendali. */
const PrintHeader = (props: any) => h(KopCetak, {
  dok: { ...(props.dok ?? {}), judul: props.dok?.judul || 'Dokumen Terkendali' },
  halaman: props.halaman, dari: props.dari,
});
export default { components: { PrintHeader } };
</script>
