<script setup lang="ts">
/**
 * PTPKKP — Rubrik.
 *
 * Rubrik acuan Kepdirjen, lima tingkat per item. Item satu parameter
 * dikirim seluruhnya lalu dicari di peramban; versi Blade menyaringnya di
 * server dan memuat ulang halaman tiap kali kata kuncinya berubah,
 * padahal isinya sudah ada di memori.
 *
 * Berpindah parameter tetap ke server — itu memang memuat kumpulan item
 * yang berbeda, bukan menyaring yang sudah ada.
 */
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PickerTpkkp from '../../Components/PickerTpkkp.vue';
import type { HalamanRubrik } from '../../types';

const props = defineProps<HalamanRubrik>();

const cari = ref('');

const tersaring = computed(() => {
  const q = cari.value.trim().toLowerCase();
  if (!q) return props.items;

  return props.items.filter(
    (it) => it.kode.toLowerCase().includes(q) || it.nama.toLowerCase().includes(q),
  );
});
</script>

<template>
  <Head title="PTPKKP — Rubrik" />

  <div class="max-w-5xl mx-auto space-y-5">

    <PickerTpkkp v-bind="picker" />

    <div class="bg-white rounded-2xl border border-stone-200 p-4">
      <div class="flex flex-wrap items-center gap-2">
        <input v-model="cari" placeholder="Cari kode atau item dalam parameter ini…"
               class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] w-72">
        <span class="text-[11.5px] text-stone-500 num">{{ tersaring.length }} item</span>
        <span class="text-[11px] text-stone-400 ml-auto">
          Rubrik acuan Kepdirjen · 5 tingkat per item
        </span>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-stone-200 p-4">
      <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-stone-400 mb-2.5">Parameter</div>
      <div class="flex flex-wrap gap-1.5">
        <Link v-for="p in parameter" :key="p.kode" :href="p.url"
              class="text-[11.5px] px-2.5 py-1 rounded-lg border transition"
              :class="p.kode === paramAktif
                ? 'bg-cam-lime-soft border-cam-lime/40 text-cam-lime-deep font-bold'
                : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400'">
          <span class="num font-semibold">{{ p.kode }}</span>
          <span class="opacity-60">({{ p.jumlah }})</span>
        </Link>
      </div>
    </div>

    <div v-for="it in tersaring" :key="it.kode"
         class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100 bg-stone-50">
        <span class="num text-[11px] font-bold text-stone-400">{{ it.kode }}</span>
        <div class="text-[12.5px] font-semibold text-cam-ink leading-snug">{{ it.nama }}</div>
        <div class="text-[10.5px] text-stone-400 mt-1">
          metode: {{ it.metode.join(' · ') }} · maks {{ it.maks }}
        </div>
      </div>

      <div v-if="it.acuan" class="px-5 py-4">
        <div class="text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Rubrik acuan</div>
        <div class="space-y-1.5">
          <div v-for="t in it.acuan" :key="t.tingkat" class="flex gap-2.5 text-[11.5px] leading-snug">
            <span class="flex-none w-5 h-5 rounded-md text-white text-[10px] font-bold grid place-items-center"
                  :style="{ background: t.warna }">{{ t.tingkat }}</span>
            <span class="text-stone-600">{{ t.teks }}</span>
          </div>
        </div>
      </div>

      <div v-for="r in it.rubrik" :key="r.metode" class="px-5 py-4 border-t border-stone-100">
        <div class="text-[10px] font-bold uppercase tracking-wider text-cam-lime-deep mb-2">
          Rubrik metode {{ r.metode }}
        </div>
        <div class="space-y-1.5">
          <div v-for="t in r.tingkat" :key="t.tingkat" class="flex gap-2.5 text-[11.5px] leading-snug">
            <span class="flex-none w-5 h-5 rounded-md text-white text-[10px] font-bold grid place-items-center"
                  :style="{ background: t.warna }">{{ t.tingkat }}</span>
            <span class="text-stone-600">{{ t.teks }}</span>
          </div>
        </div>
      </div>

      <div v-if="it.target.length" class="px-5 py-4 border-t border-stone-100 bg-stone-50">
        <div class="text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1.5">
          Target sampel / dokumen
        </div>
        <div v-for="t in it.target" :key="t.metode" class="text-[11.5px] text-stone-600 mb-1">
          <b class="num">{{ t.metode }}</b> — {{ t.teks }}
        </div>
      </div>
    </div>

    <div v-if="!tersaring.length"
         class="bg-white rounded-2xl border border-stone-200 px-5 py-10 text-center text-[12.5px] text-stone-400">
      Tidak ada item yang cocok.
    </div>
  </div>
</template>
