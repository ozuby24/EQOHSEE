<script setup lang="ts">
/**
 * PTPKKP — Metode.
 *
 * Daftar tujuh metode pengukuran beserta keterisiannya. Tautan "Isi nilai
 * metode ini" menuju Formulir Nilai yang sudah Inertia, jadi berpindah
 * ke sana tidak memuat ulang.
 */
import { Head, Link } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import LencanaKategori from '../../Components/LencanaKategori.vue';
import type { HalamanMetode } from '../../types';

defineProps<HalamanMetode>();
</script>

<template>
  <Head title="PTPKKP — Metode" />

  <div class="max-w-5xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Tujuh Metode Pengukuran</h3>
      </div>

      <div class="divide-y divide-stone-100">
        <div v-for="m in metode" :key="m.kode" class="px-5 py-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <span class="text-[12px] font-bold text-cam-ink">{{ m.kode }} · {{ m.nama }}</span>
              <div class="text-[11px] text-stone-500 mt-0.5">
                {{ m.labelEntitas || 'Tanpa entitas' }} ·
                {{ m.entitas.length }} entitas ·
                {{ m.items }} item
              </div>
            </div>
            <div class="text-right">
              <LencanaKategori :kategori="m.kategori" :warna="m.warna" />
              <div class="num text-[11px] text-stone-400 mt-1">{{ m.terisi }}/{{ m.items }} terisi</div>
            </div>
          </div>

          <div v-if="m.entitas.length" class="flex flex-wrap gap-1.5 mt-2.5">
            <span v-for="e in m.entitas" :key="e"
                  class="text-[10.5px] px-2 py-0.5 rounded-md bg-stone-50 border border-stone-200 text-stone-600">
              {{ e }}
            </span>
          </div>

          <Link :href="m.url"
                class="inline-block mt-3 text-[11.5px] font-bold text-cam-lime-deep hover:underline">
            Isi nilai metode ini →
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
