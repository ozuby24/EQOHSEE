<script setup lang="ts">
/**
 * Inspeksi — capaian KPI.
 *
 * Persentase dihitung server; pembagian terhadap target bernilai nol
 * hanya perlu dijaga di satu tempat.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanKpiInspeksi } from '../../types';

const props = defineProps<HalamanKpiInspeksi>();

const bulan = ref<string | null>(props.bulan);

function pilihBulan() {
  router.get('/inspeksi/kpi', bulan.value ? { bulan: bulan.value } : {}, {
    preserveState: true, preserveScroll: true, replace: true,
  });
}

const kartu = [
  { n: props.total,          l: 'Inspeksi dijalankan', c: 'text-cam-ink' },
  { n: props.temuan.total,   l: 'Item diperiksa',      c: 'text-stone-500' },
  { n: props.temuan.sesuai,  l: 'Sesuai',              c: 'text-emerald-600' },
  { n: props.temuan.tidak,   l: 'Tidak sesuai',        c: 'text-red-500' },
  { n: props.temuan.naik,    l: 'Naik jadi hazard',    c: 'text-amber-600' },
];
</script>

<template>
  <Head title="KPI Inspeksi" />

  <div class="max-w-6xl mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
      <span class="block text-[12.5px] font-semibold text-stone-500 px-1">Periode</span>
      <select v-model="bulan" @change="pilihBulan"
              class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px]
                     font-semibold text-stone-600" aria-label="Bulan">
        <option :value="null">Semua bulan (akumulasi)</option>
        <option v-for="b in opsiBulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
      </select>
      <span class="text-[11.5px] text-stone-400 ml-auto">
        Target dihitung atas <span class="num font-bold text-stone-600">{{ bulanAktif }}</span> bulan
      </span>
    </div>

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-5">
      <div v-for="(k, i) in kartu" :key="i"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
        <div class="stat stat-sm" :class="k.c">{{ k.n }}</div>
        <div class="text-[11px] text-stone-400 mt-1.5 leading-tight">{{ k.l }}</div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Capaian per Golongan Jabatan</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th class="text-left  font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Golongan</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Orang</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Target</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Aktual</th>
              <th class="text-left  font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Capaian</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Tercapai</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in golongan" :key="g.nama" class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-3 font-bold text-cam-ink">{{ g.nama }}</td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ g.orang }}</td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ g.target }}</td>
              <td class="px-4 py-3 num text-right font-semibold text-cam-ink">{{ g.aktual }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-1.5 rounded-full bg-stone-100 overflow-hidden min-w-[60px]">
                    <div class="h-full rounded-full"
                         :class="g.pct >= 100 ? 'lime-gradient' : 'bg-amber-400'"
                         :style="{ width: Math.min(100, g.pct) + '%' }"></div>
                  </div>
                  <span class="num font-bold" :class="g.pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600'">
                    {{ g.pct }}%
                  </span>
                </div>
              </td>
              <td class="px-4 py-3 num text-right text-stone-500">{{ g.tercapai }}/{{ g.orang }}</td>
            </tr>
            <tr v-if="!golongan.length">
              <td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Capaian per Petugas</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th class="text-left  font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Nama</th>
              <th class="text-left  font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Jabatan</th>
              <th class="text-left  font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Golongan</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Target</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Aktual</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Capaian</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, i) in petugas" :key="i" class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ o.nama }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ o.jabatan ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <span class="text-[10px] font-bold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">{{ o.gol }}</span>
              </td>
              <td class="px-4 py-2.5 num text-right text-stone-500">{{ o.target }}</td>
              <td class="px-4 py-2.5 num text-right font-bold text-cam-ink">{{ o.aktual }}</td>
              <td class="px-4 py-2.5 num text-right font-bold"
                  :class="o.pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600'">{{ o.pct }}%</td>
            </tr>
            <tr v-if="!petugas.length">
              <td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
