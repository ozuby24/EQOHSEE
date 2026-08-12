<script setup lang="ts">
/**
 * Satu berita.
 *
 * Isinya ditulis sebagai teks biasa, bukan HTML: apa pun yang diketik
 * penulis tergambar apa adanya lewat whitespace-pre-line, sehingga tidak
 * ada jalan menyelipkan markah ke halaman orang lain.
 */
import { Head, Link } from '@inertiajs/vue3';
import type { HalamanDetailBerita } from '../../types';

defineProps<HalamanDetailBerita>();
</script>

<template>
  <Head :title="berita.judul" />

  <article class="max-w-3xl mx-auto bg-white rounded-2xl shadow-card border border-stone-100 p-7 md:p-9">
    <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-cam-lime-dark">{{ berita.tanggal }}</div>
    <h1 class="font-display text-[28px] md:text-[32px] font-black text-cam-ink mt-2.5 leading-tight">
      {{ berita.judul }}
    </h1>
    <div class="text-[13.5px] text-stone-600 leading-[1.75] mt-5 whitespace-pre-line">{{ berita.isi }}</div>

    <Link :href="tautan.daftar"
          class="inline-block mt-8 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">
      ← Semua berita
    </Link>
  </article>
</template>
