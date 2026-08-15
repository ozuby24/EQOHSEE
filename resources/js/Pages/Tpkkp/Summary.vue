<script setup lang="ts">
/**
 * PTPKKP — Summary.
 *
 * Capaian lawan target per parameter. Seluruh selisih (gap) dihitung
 * server: capaian atau target yang belum ada bukan berarti selisihnya
 * nol, dan membedakan "belum ada" dari "pas nol" itu justru inti bacaan
 * halaman ini.
 */
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import LencanaKategori from '../../Components/LencanaKategori.vue';
import type { HalamanSummary } from '../../types';

defineProps<HalamanSummary>();

const angka = (n: number | null | undefined, d = 3) =>
  n === null || n === undefined
    ? '—'
    : n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });

const persen = (r: number | null | undefined) =>
  r === null || r === undefined ? '—' : (r * 100).toFixed(1) + '%';

/** Lebar bilah: capaian dan target dipersenkan terhadap bobot parameter. */
const lebar = (nilai: number | null, bobot: number) =>
  Math.max(0, Math.min(100, ((nilai ?? 0) / (bobot || 1)) * 100));
</script>

<template>
  <Head title="PTPKKP — Summary" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Rekap Nilai per Parameter — lawan target</h3>
        <p class="text-[11px] text-stone-500 mt-0.5">Gap negatif berarti capaian masih di bawah target.</p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[760px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-5 py-2.5 font-bold">No</th>
              <th class="text-left px-3 py-2.5 font-bold">Indikator / Parameter</th>
              <th class="text-right px-3 py-2.5 font-bold">Maks</th>
              <th class="text-right px-3 py-2.5 font-bold">Capaian</th>
              <th class="text-right px-3 py-2.5 font-bold">Ach</th>
              <th class="text-left px-3 py-2.5 font-bold">Kategori</th>
              <th class="text-right px-3 py-2.5 font-bold">Target</th>
              <th class="text-right px-5 py-2.5 font-bold">Gap</th>
            </tr>
          </thead>

          <tbody>
            <template v-for="I in indikator" :key="I.kode">
              <tr class="bg-stone-50/70 border-y border-stone-100 font-bold">
                <td class="px-5 py-2 num">{{ I.kode }}</td>
                <td class="px-3 py-2 text-cam-ink">{{ I.nama }}</td>
                <td class="px-3 py-2 text-right num">{{ I.bobot.toFixed(2) }}</td>
                <td class="px-3 py-2 text-right num">{{ angka(I.skor) }}</td>
                <td class="px-3 py-2 text-right num">{{ persen(I.rasio) }}</td>
                <td class="px-3 py-2"><LencanaKategori :kategori="I.kategori" :warna="I.warna" /></td>
                <td class="px-3 py-2 text-right num">{{ I.target.toFixed(2) }}</td>
                <td class="px-5 py-2 text-right num">
                  <span v-if="I.gap === null" class="text-stone-400">—</span>
                  <span v-else class="font-semibold"
                        :class="I.gap >= 0 ? 'text-emerald-600' : 'text-red-600'">
                    {{ I.gap >= 0 ? '+' : '' }}{{ angka(I.gap) }}
                  </span>
                </td>
              </tr>

              <tr v-for="P in I.parameter" :key="P.kode" class="border-b border-stone-50">
                <td class="px-5 py-2 num text-stone-500">{{ P.kode }}</td>
                <td class="px-3 py-2">{{ P.nama }}</td>
                <td class="px-3 py-2 text-right num text-stone-400">{{ P.bobot.toFixed(2) }}</td>
                <td class="px-3 py-2 text-right num font-semibold">{{ angka(P.skor) }}</td>
                <td class="px-3 py-2 text-right num">{{ persen(P.rasio) }}</td>
                <td class="px-3 py-2"><LencanaKategori :kategori="P.kategori" :warna="P.warna" /></td>
                <td class="px-3 py-2 text-right num text-stone-400">
                  {{ P.target === null ? '—' : P.target.toFixed(2) }}
                </td>
                <td class="px-5 py-2 text-right num">
                  <span v-if="P.gap === null" class="text-stone-400">—</span>
                  <span v-else class="font-semibold"
                        :class="P.gap >= 0 ? 'text-emerald-600' : 'text-red-600'">
                    {{ P.gap >= 0 ? '+' : '' }}{{ angka(P.gap) }}
                  </span>
                </td>
              </tr>
            </template>

            <tr class="bg-cam-ink text-white font-bold">
              <td class="px-5 py-2.5"></td>
              <td class="px-3 py-2.5">NILAI TOTAL PENCAPAIAN KINERJA</td>
              <td class="px-3 py-2.5 text-right num">1,00</td>
              <td class="px-3 py-2.5 text-right num text-cam-lime-light">{{ angka(total.skor) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ persen(total.rasio) }}</td>
              <td class="px-3 py-2.5"><LencanaKategori :kategori="total.kategori" :warna="total.warna" /></td>
              <td class="px-3 py-2.5 text-right num">{{ total.target.toFixed(2) }}</td>
              <td class="px-5 py-2.5 text-right num">
                <span v-if="total.gap === null" class="text-white/50">—</span>
                <span v-else :class="total.gap >= 0 ? 'text-cam-lime-light' : 'text-red-300'">
                  {{ total.gap >= 0 ? '+' : '' }}{{ angka(total.gap) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-6">
      <h3 class="text-[13px] font-bold text-cam-ink mb-4">Capaian vs Target per Parameter</h3>
      <div class="space-y-2.5">
        <template v-for="I in indikator" :key="I.kode">
          <div v-for="P in I.parameter" :key="P.kode">
            <div class="flex justify-between text-[11px] mb-1">
              <span class="text-stone-600"><b class="num">{{ P.kode }}</b> {{ P.nama.slice(0, 50) }}</span>
              <span class="num text-stone-500">
                {{ angka(P.skor) }} / {{ P.target === null ? '—' : P.target.toFixed(2) }}
              </span>
            </div>
            <div class="relative h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full"
                   :style="{ width: Math.round(lebar(P.skor, P.bobot)) + '%', background: P.warna }"></div>
              <div class="absolute top-0 bottom-0 w-0.5 bg-cam-ink/70"
                   :style="{ left: Math.round(lebar(P.target, P.bobot)) + '%' }"></div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
