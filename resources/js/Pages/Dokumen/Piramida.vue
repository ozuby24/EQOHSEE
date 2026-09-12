<script setup lang="ts">
/**
 * Piramida dokumen — enam tingkat dari Kebijakan sampai Rekaman.
 *
 * Lebar tiap tingkat mengikuti urutannya, bukan jumlah dokumennya:
 * bentuknya harus tetap terbaca sebagai piramida walau isinya belum
 * merata, dan tingkat yang kosong justru yang paling berguna dilihat.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanPiramidaDokumen } from '../../types';

defineProps<HalamanPiramidaDokumen>();

const lebar = (urutan: number) => 46 + (urutan - 1) * 10.8;
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-4xl mx-auto space-y-5">

    <section class="kartu-lux rounded-2xl p-6">
      <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">
        Register mendaftar dokumen secara mendatar; piramida menunjukkan bentuk sistemnya.
        Tingkat yang kosong justru paling berguna dilihat — sistem tanpa Prosedur, misalnya,
        terbaca seketika.
      </p>
      <p class="text-[11.5px] text-stone-400 mt-3">
        <span class="num font-bold text-cam-ink">{{ total }}</span> dokumen terdaftar pada enam tingkat.
      </p>
    </section>

    <div class="space-y-2.5">
      <div v-for="t in tingkat" :key="t.jenis" class="mx-auto transition-all"
           :style="{ maxWidth: lebar(t.urutan) + '%' }">
        <Link :href="t.url" class="kartu-lux rounded-2xl px-5 py-4 block hover:-translate-y-0.5 transition">
          <div class="flex flex-wrap items-center gap-3">
            <span class="shrink-0 num text-[11px] font-black text-white w-6 h-6 rounded-lg
                         grid place-items-center bg-cam-lime">{{ t.urutan }}</span>
            <span class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">{{ t.jenis }}</span>
            <span class="shrink-0 text-right">
              <span class="num text-[17px] font-bold"
                    :class="t.total ? 'text-cam-ink' : 'text-cam-coral'">{{ t.total }}</span>
              <span class="text-[10.5px] text-stone-400 ml-1">dokumen</span>
            </span>
          </div>

          <p class="text-[11.5px] text-stone-500 mt-2 leading-relaxed">{{ t.ket }}</p>

          <div v-if="t.total" class="flex flex-wrap gap-2 mt-2.5">
            <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded
                         bg-cam-lime-soft text-cam-lime-deep">{{ t.berlaku }} berlaku</span>
            <span v-if="t.draft" class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5
                         rounded bg-stone-100 text-stone-500">{{ t.draft }} draft</span>
          </div>
          <div v-else class="text-[11px] font-semibold text-cam-coral mt-2.5">Tingkat ini masih kosong</div>
        </Link>
      </div>
    </div>
  </div>
</template>
