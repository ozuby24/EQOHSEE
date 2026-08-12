<script setup lang="ts">
/**
 * Berita — pengumuman untuk seluruh pengguna.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import type { HalamanDaftarBerita } from '../../types';

defineProps<HalamanDaftarBerita>();

function hapus(url: string, judul: string) {
  if (!confirm(`Hapus berita "${judul}"?`)) return;
  router.delete(url, { preserveScroll: true });
}
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-3xl mx-auto">

    <div v-if="bolehUbah" class="flex justify-end mb-5">
      <a :href="tautan.buat" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5
                                    text-[12.5px] font-bold hover:brightness-105 transition">
        + Tulis Berita
      </a>
    </div>

    <div class="space-y-3">
      <article v-for="b in berita" :key="b.id"
               class="bg-white rounded-2xl shadow-card border border-stone-100 p-6
                      hover:border-cam-lime/30 transition">
        <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-dark">{{ b.tanggal }}</div>
        <h2 class="text-[17px] font-bold text-cam-ink mt-1.5 leading-snug">
          <Link :href="b.url" class="hover:text-cam-lime-deep transition">{{ b.judul }}</Link>
        </h2>
        <p class="text-[12.5px] text-stone-400 mt-2 clamp-3 leading-relaxed">{{ b.cuplikan }}</p>

        <div v-if="bolehUbah" class="flex gap-1 mt-3.5 pt-3 border-t border-stone-100">
          <a :href="b.urlUbah" class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                                      text-cam-lime-deep hover:bg-cam-lime-soft">Edit</a>
          <button type="button" @click="hapus(b.urlHapus, b.judul)"
                  class="px-3 py-1.5 text-[11.5px] font-semibold rounded-lg
                         text-red-500 hover:bg-red-50">Hapus</button>
        </div>
      </article>

      <div v-if="!berita.length"
           class="bg-white rounded-2xl border border-dashed border-stone-200 p-14
                  text-center text-[13px] text-stone-400">
        Belum ada berita.
      </div>
    </div>

    <nav v-if="halaman.akhir > 1" class="mt-6 flex flex-wrap gap-1.5">
      <component v-for="(t, i) in halaman.tautan" :key="i"
                 :is="t.url ? Link : 'span'" :href="t.url ?? undefined"
                 class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                 :class="t.aktif ? 'border-transparent bg-cam-ink text-white'
                       : t.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50'
                               : 'border-stone-100 text-stone-300'"
                 v-html="t.label" />
    </nav>
  </div>
</template>
