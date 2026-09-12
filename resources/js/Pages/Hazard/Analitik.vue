<script setup lang="ts">
/**
 * Hazard Report — analitik dan capaian KPI.
 *
 * Seluruh angka datang jadi dari server, termasuk persentase capaian:
 * pembagian terhadap target bernilai nol hanya perlu dijaga di satu
 * tempat, dan tabel yang menghitung sendiri cepat atau lambat ada yang
 * lupa menjaganya.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanAnalitikBahaya } from '../../types';

const props = defineProps<HalamanAnalitikBahaya>();

const bulan = ref<string | null>(props.bulan);

function pilihBulan() {
  router.get('/hazard/analitik', bulan.value ? { bulan: bulan.value } : {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

/** Tinggi batang tren — murni penataan, jadi dihitung di sini. */
function tinggi(nilai: number, maks: number): string {
  return nilai ? `${Math.max(6, (nilai / maks) * 112)}px` : '2px';
}

const kepalaGolongan = ['Golongan', 'Orang', 'Target', 'Aktual', 'Capaian', 'Tercapai'];
const kananGolongan  = ['Orang', 'Target', 'Aktual', 'Tercapai'];

const kepalaPelapor = ['Nama', 'Jabatan', 'Golongan', 'Target', 'Aktual', 'Capaian'];
const kananPelapor  = ['Target', 'Aktual', 'Capaian'];
</script>

<template>
  <Head title="Analitik & KPI" />

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
        <span class="num font-bold text-stone-600">{{ total }}</span> laporan ·
        target dihitung atas <span class="num font-bold text-stone-600">{{ bulanAktif }}</span> bulan
      </span>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Capaian KPI per Golongan Jabatan</h3>
        <p class="text-[11.5px] text-stone-400 mt-0.5">
          Target: General Manager &amp; Manager 1 · Superintendent 3 · lainnya 4 laporan per bulan.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th v-for="h in kepalaGolongan" :key="h"
                  class="font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5"
                  :class="kananGolongan.includes(h) ? 'text-right' : 'text-left'">{{ h }}</th>
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

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-4">Tren Laporan 12 Bulan</h3>
      <div class="flex items-end gap-1.5 h-40">
        <div v-for="(t, i) in tren" :key="i" class="flex-1 flex flex-col items-center gap-1.5">
          <span class="num text-[10.5px] font-bold text-stone-500">{{ t.nilai || '' }}</span>
          <div class="w-full rounded-t-lg transition-all"
               :class="t.nilai ? 'lime-gradient' : ''"
               :style="{ height: tinggi(t.nilai, t.maks), background: t.nilai ? undefined : '#e7e5e4' }"></div>
          <span class="text-[9px] text-stone-400">{{ t.label }}</span>
        </div>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="s in sebaran" :key="s.judul"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">{{ s.judul }}</h3>
        <div class="space-y-2">
          <div v-for="(b, i) in s.baris" :key="i">
            <div class="flex items-center justify-between text-[11.5px] mb-1">
              <span class="text-stone-600 truncate pr-2">{{ b.label }}</span>
              <span class="num font-bold text-stone-500">{{ b.nilai }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient"
                   :style="{ width: (b.nilai / s.maks) * 100 + '%' }"></div>
            </div>
          </div>
          <p v-if="!s.baris.length" class="text-[12px] text-stone-300">Belum ada data.</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Capaian per Pelapor</h3>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th v-for="h in kepalaPelapor" :key="h"
                  class="font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5"
                  :class="kananPelapor.includes(h) ? 'text-right' : 'text-left'">{{ h }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(o, i) in pelapor" :key="i" class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ o.nama }}</td>
              <td class="px-4 py-2.5 text-stone-500">{{ o.jabatan ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <span class="text-[10px] font-bold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">
                  {{ o.gol }}
                </span>
              </td>
              <td class="px-4 py-2.5 num text-right text-stone-500">{{ o.target }}</td>
              <td class="px-4 py-2.5 num text-right font-bold text-cam-ink">{{ o.aktual }}</td>
              <td class="px-4 py-2.5 num text-right font-bold"
                  :class="o.pct >= 100 ? 'text-cam-lime-deep' : 'text-amber-600'">{{ o.pct }}%</td>
            </tr>
            <tr v-if="!pelapor.length">
              <td colspan="6" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
