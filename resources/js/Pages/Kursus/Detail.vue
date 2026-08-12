<script setup lang="ts">
/**
 * Rincian kursus: modul dan materinya.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanDetailKursus } from '../../types';

defineProps<HalamanDetailKursus>();
</script>

<template>
  <Head :title="kursus.judul" />

  <div class="max-w-4xl mx-auto space-y-5">
    <div class="brand-gradient rounded-2xl overflow-hidden text-white shadow-card">
      <div class="md:flex">
        <div class="md:w-52 h-36 md:h-auto bg-black/25 shrink-0">
          <img v-if="kursus.gambar" :src="kursus.gambar" class="w-full h-full object-cover" alt="">
        </div>
        <div class="p-6 flex-1">
          <span v-if="kursus.kategori" class="eq-lencana-kat" :class="`k-${kursus.nadaKategori}`">
            {{ kursus.kategori }}
          </span>
          <h2 class="stat mt-2.5 leading-tight">{{ kursus.judul }}</h2>
          <p class="text-[13px] text-white/50 mt-2 leading-relaxed">{{ kursus.keterangan ?? '—' }}</p>
          <div class="flex items-center gap-3 mt-5">
            <Link :href="tautan.belajar"
                  class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px]
                         font-bold hover:brightness-105 transition">Mulai Belajar</Link>
            <a v-if="bolehUbah" :href="tautan.ubah"
               class="text-[12.5px] font-bold text-cam-lime-light hover:underline">Edit kursus</a>
          </div>
        </div>
      </div>
    </div>

    <div>
      <h3 class="text-[15px] font-bold text-cam-ink mb-3">Modul &amp; Materi</h3>
      <div class="space-y-2.5">
        <div v-for="m in modul" :key="m.id"
             class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
          <div class="text-[14px] font-bold text-cam-ink">{{ m.urutan }}. {{ m.judul }}</div>
          <p v-if="m.keterangan" class="text-[12.5px] text-stone-400 mt-1 leading-relaxed">{{ m.keterangan }}</p>

          <ul v-if="m.materi.length" class="mt-3 space-y-1.5">
            <li v-for="x in m.materi" :key="x.id" class="flex items-center gap-2 text-[12.5px] text-stone-500">
              <span class="text-[9.5px] uppercase font-bold bg-cam-lime-soft text-cam-lime-deep
                           px-1.5 py-0.5 rounded tracking-wide">{{ x.jenis }}</span>
              {{ x.judul }}
            </li>
          </ul>
        </div>

        <div v-if="!modul.length"
             class="bg-white rounded-2xl border border-dashed border-stone-200 p-10
                    text-center text-[13px] text-stone-400">
          Belum ada modul.
        </div>
      </div>
    </div>

    <Link :href="tautan.daftar" class="inline-block text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
      ← Kembali ke daftar
    </Link>
  </div>
</template>
