<script setup lang="ts">
/**
 * Inspeksi — daftar jenis (template) dan parameternya.
 */
import { Head, router } from '@inertiajs/vue3';
import type { HalamanJenisInspeksi } from '../../types';

defineProps<HalamanJenisInspeksi>();

function salin(url: string, nama: string) {
  if (!confirm(`Salin "${nama}" beserta seluruh parameternya?`)) return;
  router.post(url);
}

function hapus(url: string, nama: string, dipakai: number) {
  const pesan = dipakai
    ? `"${nama}" sudah dipakai ${dipakai} inspeksi. Inspeksi yang sudah ada tidak ikut terhapus. Lanjutkan?`
    : `Hapus jenis "${nama}"?`;

  if (!confirm(pesan)) return;
  router.delete(url);
}
</script>

<template>
  <Head title="Jenis & Parameter Inspeksi" />

  <div class="max-w-5xl mx-auto space-y-5">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex items-center gap-2">
      <p class="text-[12px] text-stone-500 px-1">
        Parameter pada tiap jenis disalin ke inspeksi yang memakainya saat inspeksi itu dibuat.
      </p>
      <a :href="tautan.buat"
         class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px]
                font-bold hover:brightness-105 transition ml-auto shrink-0">+ Jenis Baru</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <div v-for="j in jenis" :key="j.id"
           class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="text-[14px] font-bold text-cam-ink truncate">{{ j.nama }}</h3>
              <span v-if="!j.aktif"
                    class="text-[10px] font-bold bg-stone-100 text-stone-400 px-2 py-0.5 rounded-full">nonaktif</span>
            </div>
            <p v-if="j.keterangan" class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">{{ j.keterangan }}</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2 mt-3">
          <span class="text-[11px] font-bold bg-stone-100 text-stone-600 px-2.5 py-1 rounded-full num">
            {{ j.jumlahItem }} parameter
          </span>
          <span class="text-[11px] font-bold bg-stone-100 text-stone-600 px-2.5 py-1 rounded-full num">
            dipakai {{ j.dipakai }}×
          </span>
        </div>

        <div class="flex flex-wrap gap-2 mt-4 pt-3.5 border-t border-stone-100">
          <a :href="j.urlUbah"
             class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600
                    hover:bg-stone-50 transition">Ubah parameter</a>
          <button type="button" @click="salin(j.urlSalin, j.nama)"
                  class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600
                         hover:bg-stone-50 transition">Salin</button>
          <button type="button" @click="hapus(j.urlHapus, j.nama, j.dipakai)"
                  class="ml-auto rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-[11.5px]
                         font-bold text-red-700 hover:bg-red-100 transition">Hapus</button>
        </div>
      </div>
    </div>

    <div v-if="!jenis.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
      <p class="text-[13px] text-stone-400">Belum ada jenis inspeksi.</p>
      <a :href="tautan.buat" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
        Buat yang pertama →
      </a>
    </div>
  </div>
</template>
