<script setup lang="ts">
/*
  Mode layar besar Control Tower (BOD-15): tanpa kendali ubah, panel
  berganti tiap 30 detik, data disegarkan tiap 5 menit.
*/
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import { propHalaman } from '../../halaman';
import { RAG, n, persen, rp, tgl } from '../../energi';

defineOptions({ layout: BlankLayout });

const props = propHalaman();
const panel = ref(0);
const JUMLAH = 3;
let putar: number | undefined;
let segar: number | undefined;

onMounted(() => {
  putar = window.setInterval(() => { panel.value = (panel.value + 1) % JUMLAH; }, 30_000);
  segar = window.setInterval(() => router.reload({ only: ['kartu', 'mutu', 'tren', 'peringatan', 'portofolio'] }), 300_000);
});
onBeforeUnmount(() => { window.clearInterval(putar); window.clearInterval(segar); });

const maksTren = computed(() => Math.max(1, ...(props.tren?.deret ?? []).map((d: any) => d.tco2e)));
</script>

<template>
  <Head title="Control Tower — Layar" />
  <div class="min-h-screen bg-[#0f1a1e] text-white p-8 space-y-6">
    <header class="flex items-center justify-between">
      <div>
        <p class="text-[13px] uppercase tracking-[.2em] text-white/70">Energy &amp; Carbon Control Tower</p>
        <p class="text-[15px] text-white/80">{{ tgl(props.filter.dari) }} – {{ tgl(props.filter.sampai) }} · {{ persen(props.mutu.persen_terverifikasi, 0) }} data terverifikasi · data s.d. {{ tgl(props.mutu.terakhir) }}</p>
      </div>
      <a :href="props.kembali" class="text-[13px] text-white/70 underline">Keluar mode layar</a>
    </header>

    <section v-if="panel === 0" class="grid grid-cols-4 gap-5">
      <article v-for="k in props.kartu" :key="k.kode" class="rounded-2xl bg-white/5 p-6" :style="{ borderTop: `6px solid ${RAG[k.rag]?.garis ?? '#78716C'}` }">
        <p class="text-[16px] text-white/70">{{ k.label }}</p>
        <p class="text-[40px] font-bold leading-tight">{{ k.satuan === 'Rp' ? rp(k.nilai) : n(k.nilai, k.kode.includes('ton') ? 4 : 1) }} <span class="text-[18px] text-white/60">{{ k.satuan === 'Rp' ? '' : k.satuan }}</span></p>
        <p class="text-[14px] text-white/70">{{ RAG[k.rag]?.label }}<template v-if="k.arah"> · {{ k.arah.persen >= 0 ? '▲' : '▼' }} {{ n(Math.abs(k.arah.persen), 1) }}%</template></p>
      </article>
    </section>

    <section v-else-if="panel === 1" class="rounded-2xl bg-white/5 p-6">
      <p class="text-[20px] font-bold mb-4">Emisi bulanan (tCO₂e)</p>
      <div class="flex items-end gap-3 h-[360px]">
        <div v-for="d in props.tren.deret" :key="d.bulan" class="flex-1 flex flex-col items-center justify-end gap-2">
          <span class="text-[13px] text-white/80">{{ n(d.tco2e, 0) }}</span>
          <div class="w-full rounded-t-lg bg-[#eb6834]" :style="{ height: `${Math.max(2, d.tco2e / maksTren * 300)}px` }"></div>
          <span class="text-[13px] text-white/70">{{ d.label }}</span>
        </div>
      </div>
      <p v-if="props.tren.proyeksi" class="text-[16px] text-white/80 mt-4">Proyeksi akhir tahun: {{ n(props.tren.proyeksi.tco2e, 0) }} ± {{ n(props.tren.proyeksi.pita, 0) }} tCO₂e</p>
    </section>

    <section v-else class="grid grid-cols-2 gap-5">
      <div class="rounded-2xl bg-white/5 p-6">
        <p class="text-[20px] font-bold mb-3">Peringatan</p>
        <ul class="space-y-2 text-[16px]">
          <li v-for="(p, i) in props.peringatan.slice(0, 8)" :key="i" class="flex gap-3"><span class="mt-2 w-3 h-3 rounded-full shrink-0" :style="{ background: p.tingkat === 'merah' ? '#F04438' : '#F79009' }"></span>{{ p.judul }}</li>
        </ul>
        <p v-if="!props.peringatan.length" class="text-[16px] text-white/70">Tidak ada peringatan aktif.</p>
      </div>
      <div class="rounded-2xl bg-white/5 p-6">
        <p class="text-[20px] font-bold mb-3">Inisiatif hemat</p>
        <ul class="space-y-2 text-[16px]">
          <li v-for="s in props.portofolio.per_status.filter((x: any) => x.jumlah)" :key="s.status" class="flex justify-between"><span class="capitalize">{{ s.status }}</span><span>{{ s.jumlah }} · {{ n(s.rencana_tco2e, 0) }} tCO₂e/thn</span></li>
        </ul>
      </div>
    </section>

    <p class="text-[12px] text-white/50">Panel {{ panel + 1 }} dari {{ JUMLAH }} · berganti tiap 30 detik · disegarkan tiap 5 menit</p>
  </div>
</template>
