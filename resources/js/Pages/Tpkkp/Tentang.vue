<script setup lang="ts">
/**
 * PTPKKP — Instrumen.
 *
 * Halaman yang menjelaskan struktur instrumen dan cara nilai dihitung.
 * Seluruh angkanya diturunkan server dari acuan, tidak satu pun diketik
 * ulang: angka yang menyimpang di halaman ini lebih menyesatkan daripada
 * di halaman mana pun, sebab justru ini yang dipakai orang untuk
 * memeriksa apakah angka di halaman lain masuk akal.
 */
import { Head } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanTentang } from '../../types';

defineProps<HalamanTentang>();
</script>

<template>
  <Head title="PTPKKP — Instrumen" />

  <div class="max-w-5xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-6">
      <h2 class="text-[17px] font-bold text-cam-ink">{{ meta.judul }}</h2>
      <p class="text-[12.5px] text-stone-500 mt-1">{{ meta.basis }}</p>

      <div class="grid sm:grid-cols-4 gap-3 mt-5">
        <div v-for="r in ringkas" :key="r.label"
             class="rounded-xl bg-stone-50 border border-stone-200 px-4 py-3">
          <div class="stat text-[20px] leading-none">{{ r.nilai }}</div>
          <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold mt-1.5">
            {{ r.label }}
          </div>
        </div>
      </div>

      <h3 class="text-[13px] font-bold text-cam-ink mt-7 mb-2">Ambang kategori</h3>
      <div class="flex flex-wrap gap-2">
        <span v-for="t in ambang" :key="t.label"
              class="text-[11px] font-semibold px-2.5 py-1 rounded-lg text-white"
              :style="{ background: t.warna }">
          {{ t.label }} &lt; {{ t.batas }}
        </span>
      </div>
      <p class="text-[11.5px] text-stone-500 mt-2">
        Ambang yang sama dipakai untuk item, parameter, indikator, dan total.
      </p>

      <h3 class="text-[13px] font-bold text-cam-ink mt-6 mb-2">Cara nilai dihitung</h3>
      <ul class="text-[12px] text-stone-600 space-y-1 list-disc pl-4">
        <li>Skor satu metode = rerata seluruh entitas yang terisi.</li>
        <li>Nilai item = jumlah skor metodenya; maks item = 5 × jumlah metode.</li>
        <li>Metode yang belum diisi dihitung <b>nol</b>, bukan diabaikan.</li>
        <li>Nilai parameter = (Σ nilai item ÷ Σ maks item) × bobot parameter.</li>
        <li>Nilai total = jumlah nilai seluruh indikator, maksimum 1,000.</li>
      </ul>
    </div>

    <div v-for="ind in indikator" :key="ind.kode"
         class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 bg-stone-50 border-b border-stone-200 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">{{ ind.kode }}. {{ ind.nama }}</h3>
        <span class="num text-[12px] text-stone-500">bobot {{ ind.bobot.toFixed(2) }}</span>
      </div>

      <div class="tabel-scroll">
        <table class="w-full text-[12px]">
          <thead class="text-[10px] uppercase tracking-wider text-stone-400">
            <tr class="border-b border-stone-100">
              <th class="text-left px-5 py-2 font-bold">Parameter</th>
              <th class="text-right px-3 py-2 font-bold">Bobot</th>
              <th class="text-right px-3 py-2 font-bold">Target</th>
              <th class="text-right px-5 py-2 font-bold">Item</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in ind.parameter" :key="p.kode" class="border-b border-stone-50 last:border-0">
              <td class="px-5 py-2"><b class="num">{{ p.kode }}</b> · {{ p.nama }}</td>
              <td class="px-3 py-2 text-right num">{{ p.bobot.toFixed(2) }}</td>
              <td class="px-3 py-2 text-right num text-stone-500">{{ p.target.toFixed(2) }}</td>
              <td class="px-5 py-2 text-right num">{{ p.jumlahItem }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
