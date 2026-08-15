<script setup lang="ts">
/**
 * PTPKKP — Hasil.
 *
 * Ringkasan pencapaian per indikator dan per metode pengukuran.
 *
 * Rentang kategori di kiri diturunkan server dari ambang acuan, bukan
 * diketik sebagai teks tetap seperti versi Blade. Teks tetap seperti itu
 * diam saja ketika ambangnya berubah — tabel keterangannya lalu menyebut
 * batas yang tidak lagi dipakai perhitungan di sebelahnya.
 */
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import LencanaKategori from '../../Components/LencanaKategori.vue';
import type { HalamanHasil } from '../../types';

defineProps<HalamanHasil>();

const angka = (n: number | null | undefined, d = 3) =>
  n === null || n === undefined
    ? '—'
    : n.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });

const persen = (r: number | null | undefined) =>
  r === null || r === undefined ? '—' : (r * 100).toFixed(1) + '%';
</script>

<template>
  <Head title="PTPKKP — Hasil" />

  <div class="max-w-6xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="grid lg:grid-cols-3 gap-5">
      <!-- ══════════ KATEGORI & TOTAL ══════════ -->
      <div class="bg-white rounded-2xl border border-stone-200 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink mb-3">Kategori Tingkat Pencapaian</h3>

        <table class="w-full text-[12px]">
          <tbody>
            <tr v-for="r in rentang" :key="r.kategori" class="border-b border-stone-50 last:border-0">
              <td class="py-1.5 num text-stone-600">{{ r.teks }}</td>
              <td class="py-1.5 text-right"><LencanaKategori :kategori="r.kategori" :warna="r.warna" /></td>
            </tr>
          </tbody>
        </table>

        <div class="mt-5 pt-4 border-t border-stone-100">
          <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold">
            Tingkat Pencapaian Kinerja KP
          </div>
          <div class="stat stat-xl leading-none mt-1.5">{{ angka(total.skor) }}</div>
          <div class="mt-2"><LencanaKategori :kategori="total.kategori" :warna="total.warna" /></div>

          <div class="h-2 rounded-full bg-stone-100 mt-3 overflow-hidden relative">
            <div class="h-full rounded-full bg-cam-lime"
                 :style="{ width: Math.round((total.skor ?? 0) * 100) + '%' }"></div>
            <div class="absolute top-0 bottom-0 w-0.5 bg-cam-ink"
                 :style="{ left: Math.round(total.target * 100) + '%' }"></div>
          </div>
          <div class="text-[11px] text-stone-500 mt-1.5 num">target {{ total.target.toFixed(2) }}</div>
        </div>
      </div>

      <!-- ══════════ PER INDIKATOR ══════════ -->
      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden lg:col-span-2">
        <div class="px-5 py-3 border-b border-stone-100">
          <h3 class="text-[13px] font-bold text-cam-ink">Pencapaian per Indikator</h3>
        </div>

        <table class="w-full text-[12px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-5 py-2.5 font-bold">Indikator</th>
              <th class="text-right px-3 py-2.5 font-bold">Nilai Maks</th>
              <th class="text-right px-3 py-2.5 font-bold">Pencapaian</th>
              <th class="text-right px-3 py-2.5 font-bold">% Terpenuhi</th>
              <th class="text-left px-5 py-2.5 font-bold">Tingkat</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="I in indikator" :key="I.kode" class="border-b border-stone-50">
              <td class="px-5 py-2.5"><b class="num">{{ I.kode }}</b> {{ I.nama }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-400">{{ I.bobot.toFixed(2) }}</td>
              <td class="px-3 py-2.5 text-right num font-semibold">{{ angka(I.skor) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ persen(I.rasio) }}</td>
              <td class="px-5 py-2.5"><LencanaKategori :kategori="I.kategori" :warna="I.warna" /></td>
            </tr>
            <tr class="bg-stone-50 font-bold">
              <td class="px-5 py-2.5">Total</td>
              <td class="px-3 py-2.5 text-right num">1,00</td>
              <td class="px-3 py-2.5 text-right num">{{ angka(total.skor) }}</td>
              <td class="px-3 py-2.5 text-right num">{{ persen(total.skor) }}</td>
              <td class="px-5 py-2.5"><LencanaKategori :kategori="total.kategori" :warna="total.warna" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════ PER METODE ══════════ -->
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">Pencapaian per Metode Pengukuran</h3>
        <span class="text-[11px] text-stone-400">maks mengikuti pemetaan metode pada instrumen</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12px] min-w-[720px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400 bg-stone-50">
            <tr>
              <th class="text-left px-5 py-2.5 font-bold">Metode</th>
              <th class="text-right px-3 py-2.5 font-bold">Item</th>
              <th class="text-right px-3 py-2.5 font-bold">Terisi</th>
              <th class="text-right px-3 py-2.5 font-bold">Maks</th>
              <th class="text-right px-3 py-2.5 font-bold">Pencapaian</th>
              <th class="text-right px-3 py-2.5 font-bold">% Terpenuhi</th>
              <th class="text-left px-3 py-2.5 font-bold">Tingkat</th>
              <th class="text-left px-5 py-2.5 font-bold" style="width:180px">Progres</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in metode" :key="m.kode" class="border-b border-stone-50">
              <td class="px-5 py-2.5"><b class="num text-cam-lime-deep">{{ m.kode }}</b> {{ m.nama }}</td>
              <td class="px-3 py-2.5 text-right num">{{ m.items }}</td>
              <td class="px-3 py-2.5 text-right num">{{ m.terisi }}</td>
              <td class="px-3 py-2.5 text-right num text-stone-400">{{ m.maks }}</td>
              <td class="px-3 py-2.5 text-right num font-semibold">
                {{ m.jumlah ? m.jumlah.toFixed(1) : '—' }}
              </td>
              <td class="px-3 py-2.5 text-right num">{{ persen(m.rasio) }}</td>
              <td class="px-3 py-2.5"><LencanaKategori :kategori="m.kategori" :warna="m.warna" /></td>
              <td class="px-5 py-2.5">
                <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
                  <div class="h-full rounded-full"
                       :style="{ width: Math.round((m.rasio ?? 0) * 100) + '%', background: m.warna }"></div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
