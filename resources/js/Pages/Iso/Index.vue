<script setup lang="ts">
/**
 * Ringkasan pemenuhan klausul seluruh standar.
 *
 * Celah ditampilkan sejelas cakupannya. Persentase saja menyembunyikan
 * pertanyaan yang sebenarnya diajukan auditor — klausul mana yang belum
 * punya dokumen.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanIso } from '../../types';

const props = defineProps<HalamanIso>();

const ringkas: Array<[string, number, boolean]> = [
  ['Standar diacu', props.standar.length, false],
  ['Dokumen terdaftar', props.dokumen, false],
  ['Sudah dipetakan', props.dipetakan, false],
  ['Belum dipetakan', Math.max(0, props.dokumen - props.dipetakan), true],
];
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-4xl mx-auto space-y-5">

    <section class="kartu-lux rounded-2xl p-6">
      <h2 class="font-display text-[20px] font-black text-cam-ink leading-tight">
        Pemenuhan Klausul Standar
      </h2>
      <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">
        Register dokumen menjawab dokumen apa saja yang dipunya. Halaman ini menjawab
        pertanyaan sebaliknya — yang justru ditanyakan auditor: klausul mana yang belum
        punya dokumen.
      </p>

      <div class="grid gap-3 grid-cols-2 sm:grid-cols-4 mt-5 pt-5 hairline border-b-0">
        <div v-for="[l, v, sorot] in ringkas" :key="l">
          <div class="num text-[20px] font-bold"
               :class="sorot && v > 0 ? 'text-cam-coral' : 'text-cam-lime-deep'">{{ v }}</div>
          <div class="text-[11px] text-stone-400 mt-1">{{ l }}</div>
        </div>
      </div>
    </section>

    <div class="grid gap-4 md:grid-cols-2">
      <Link v-for="s in standar" :key="s.kode" :href="s.url"
            class="kartu-lux rounded-2xl p-5 block hover:-translate-y-0.5 transition">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink">{{ s.nama }}</div>
            <div class="text-[12px] text-stone-500 mt-0.5">{{ s.judul }}</div>
          </div>
          <span v-if="s.aspek"
                class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                :style="{ background: s.warna }">{{ s.aspek }}</span>
        </div>

        <p class="text-[11.5px] text-stone-500 mt-2.5 leading-relaxed">{{ s.ket }}</p>

        <div class="flex items-end justify-between gap-3 mt-4">
          <div>
            <span class="num text-[19px] font-bold" :style="{ color: s.warna }">{{ s.tercakup }}</span>
            <span class="num text-[13px] text-stone-400">/{{ s.butir }}</span>
            <span class="text-[11px] text-stone-400 ml-1">klausul tercakup</span>
          </div>
          <span v-if="s.celah" class="text-[11px] font-bold text-cam-coral shrink-0">{{ s.celah }} celah</span>
          <span v-else class="text-[11px] font-bold text-cam-lime-deep shrink-0">Lengkap</span>
        </div>

        <div class="mt-2.5 h-1.5 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full transition-all duration-700"
               :style="{ width: s.rasio * 100 + '%', background: s.warna }"></div>
        </div>
      </Link>
    </div>

    <p v-if="catatan" class="text-[11.5px] text-stone-400 leading-relaxed px-1">{{ catatan }}</p>
  </div>
</template>
