<script setup lang="ts">
/**
 * Hazard Report — evaluasi temuan.
 *
 * Menyatukan hazard report dan temuan inspeksi dalam satu pandangan.
 * Seluruh agregat dan skalanya datang jadi dari server; menghitung
 * ulang nilai terbesar di tiap blok berarti setiap blok baru harus
 * mengingat perhitungan itu, dan yang terlewat menggambar batang yang
 * panjangnya tidak berarti apa-apa.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanEvaluasiBahaya } from '../../types';

const props = defineProps<HalamanEvaluasiBahaya>();

const isi = ref({ ...props.saring });

function kirim() {
  const data: Record<string, string> = {};
  for (const [k, v] of Object.entries(isi.value)) if (v) data[k] = String(v);

  router.get('/hazard/evaluasi', data, { preserveState: true, preserveScroll: true, replace: true });
}

const kartu = [
  { k: 'totalTemuan'    as const, l: 'Total temuan',          c: 'text-cam-ink' },
  { k: 'hazard'         as const, l: 'Hazard report',         c: 'text-cam-ink' },
  { k: 'temuanInspeksi' as const, l: 'Temuan inspeksi',       c: 'text-cam-ink' },
  { k: 'belumTutup'     as const, l: 'Belum ditutup',         c: 'text-red-500' },
  { k: 'risikoTinggi'   as const, l: 'Risiko tinggi',         c: 'text-red-600' },
  { k: 'inspeksi'       as const, l: 'Inspeksi dijalankan',   c: 'text-stone-500' },
  { k: 'itemDiperiksa'  as const, l: 'Item diperiksa',        c: 'text-stone-500' },
  { k: 'naikJadiHazard' as const, l: 'Dinaikkan jadi hazard', c: 'text-amber-600' },
];

const pilihan =
  'ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Evaluasi Temuan" />

  <div class="max-w-6xl mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
      <span class="block text-[12.5px] font-semibold text-stone-500 px-1">Saring</span>

      <select v-model="isi.bulan" :class="pilihan" @change="kirim" aria-label="Bulan">
        <option :value="null">Semua bulan</option>
        <option v-for="b in opsi.bulan" :key="b.nilai" :value="b.nilai">{{ b.label }}</option>
      </select>

      <select v-model="isi.perusahaan" :class="pilihan" @change="kirim" aria-label="Perusahaan">
        <option :value="null">Semua perusahaan</option>
        <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
      </select>

      <span v-if="rerataHari !== null" class="text-[11.5px] text-stone-400 ml-auto">
        Rerata penutupan
        <span class="num font-bold text-stone-600">{{ rerataHari }}</span> hari
      </span>
    </div>

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
      <div v-for="k in kartu" :key="k.k"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
        <div class="stat stat-sm" :class="k.c">{{ ringkas[k.k] }}</div>
        <div class="text-[11px] text-stone-400 mt-1.5 leading-tight">{{ k.l }}</div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Tren 12 Bulan</h3>
      <p class="text-[11.5px] text-stone-400 mb-4">
        <span class="inline-block w-2.5 h-2.5 rounded-sm lime-gradient align-middle"></span> Hazard report ·
        <span class="inline-block w-2.5 h-2.5 rounded-sm bg-stone-300 align-middle"></span> Temuan inspeksi
      </p>

      <div class="flex items-end gap-2 h-40">
        <div v-for="(t, i) in tren" :key="i" class="flex-1 flex flex-col items-center gap-1.5">
          <div class="w-full flex items-end justify-center gap-0.5 h-[112px]">
            <div class="flex-1 rounded-t lime-gradient"
                 :style="{ height: t.hazard ? Math.max(4, (t.hazard / t.maks) * 108) + 'px' : '2px' }"
                 :title="`Hazard: ${t.hazard}`"></div>
            <div class="flex-1 rounded-t bg-stone-300"
                 :style="{ height: t.inspeksi ? Math.max(4, (t.inspeksi / t.maks) * 108) + 'px' : '2px' }"
                 :title="`Inspeksi: ${t.inspeksi}`"></div>
          </div>
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
              <span class="text-stone-600 truncate pr-2" :title="b.label">{{ b.label }}</span>
              <span class="num font-bold text-stone-500 shrink-0">{{ b.nilai }}</span>
            </div>
            <div class="h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient" :style="{ width: (b.nilai / s.maks) * 100 + '%' }"></div>
            </div>
          </div>
          <p v-if="!s.baris.length" class="text-[12px] text-stone-300">Belum ada data.</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-4 border-b border-stone-100">
        <h3 class="text-[14px] font-bold text-cam-ink">Temuan per Perusahaan Terlapor</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="bg-stone-50 border-b border-stone-100">
              <th class="text-left  font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Perusahaan</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Total</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Ditutup</th>
              <th class="text-right font-bold text-stone-500 uppercase tracking-wide text-[10px] px-4 py-2.5">Risiko tinggi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in perPerusahaan" :key="p.nama" class="border-b border-stone-50 last:border-0">
              <td class="px-4 py-3 font-semibold text-cam-ink">{{ p.nama }}</td>
              <td class="px-4 py-3 num text-right text-stone-600">{{ p.total }}</td>
              <td class="px-4 py-3 num text-right text-emerald-600">{{ p.tutup }}</td>
              <td class="px-4 py-3 num text-right" :class="p.tinggi ? 'text-red-600 font-bold' : 'text-stone-400'">
                {{ p.tinggi }}
              </td>
            </tr>
            <tr v-if="!perPerusahaan.length">
              <td colspan="4" class="px-4 py-10 text-center text-stone-400">Belum ada data.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
