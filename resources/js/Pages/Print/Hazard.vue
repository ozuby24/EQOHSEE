<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';
import KopCetak from '../../Components/KopCetak.vue';
defineOptions({ layout: BlankLayout });
const props = defineProps<{ 
  dok?: Record<string, any> | null;data: any[]; kembali?: string }>();
const warnaRisiko: Record<string, string> = { Tinggi: '#ef4444', Sedang: '#f59e0b', Rendah: '#84cc16' };
const warnaStatus: Record<string, string> = { Open: '#f59e0b', Closed: '#84cc16', 'In Progress': '#0ea5e9' };
const tanggal = (v: unknown) => v ? new Date(String(v)).toLocaleDateString('id-ID') : '—';
</script>
<template>
  <Head title="Register Hazard Report" /><PrintShell title="Register Hazard Report" :kembali="props.kembali">
    <section class="bg-white p-5 print:p-0">
      <KopCetak :dok="props.dok" />
      <header class="flex justify-between items-end border-b-[3px] border-cam-lime-deep pb-3 mb-4"><div class="flex items-center gap-3"><div class="w-8 h-8 rounded-lg lime-gradient text-white grid place-items-center font-black">E</div><div><h1 class="text-lg font-bold">Register Hazard Report</h1><p class="text-[11px] text-stone-500">EQOHSEE · HSE Platform</p></div></div><div class="text-right text-[10px] text-stone-500">Dicetak {{ new Date().toLocaleString('id-ID') }}<br>Total <b>{{ props.data.length }}</b> laporan</div></header>
      <div class="overflow-x-auto"><table class="w-full text-[11px] min-w-[780px]"><thead><tr class="bg-stone-100 text-left text-[9px] uppercase text-stone-500"><th class="p-2">Kode</th><th class="p-2">Tanggal</th><th class="p-2">Lokasi</th><th class="p-2">Risiko</th><th class="p-2">Kategori</th><th class="p-2">Deskripsi</th><th class="p-2">Pelapor</th><th class="p-2">Ditujukan</th><th class="p-2">Status</th></tr></thead><tbody><tr v-for="h in props.data" :key="h.id" class="border-b border-stone-100 align-top"><td class="p-2 font-bold">{{ h.kode }}</td><td class="p-2">{{ tanggal(h.tanggal) }}</td><td class="p-2">{{ h.lokasi || '—' }}</td><td class="p-2"><span class="rounded-full px-2 py-0.5 text-white text-[9px] font-bold" :style="{ background: warnaRisiko[h.risiko] || '#a8a29e' }">{{ h.risiko }}</span></td><td class="p-2">{{ h.kategori || '—' }}</td><td class="p-2 max-w-[220px]">{{ h.deskripsi }}<small v-if="h.unsafe_action_list?.length" class="block text-stone-500">UA: {{ h.unsafe_action_list.join(', ') }}</small><small v-if="h.unsafe_condition_list?.length" class="block text-stone-500">UC: {{ h.unsafe_condition_list.join(', ') }}</small></td><td class="p-2">{{ h.pelapor_nama }}<small class="block text-stone-500">{{ h.pelapor_jabatan }}</small></td><td class="p-2">{{ h.company?.name || h.terlapor || '—' }}</td><td class="p-2"><span class="rounded-full px-2 py-0.5 text-white text-[9px] font-bold" :style="{ background: warnaStatus[h.status] || '#a8a29e' }">{{ h.status }}</span></td></tr><tr v-if="!props.data.length"><td colspan="9" class="p-8 text-center text-stone-400">Tidak ada data.</td></tr></tbody></table></div>
    </section>
  </PrintShell>
</template>
