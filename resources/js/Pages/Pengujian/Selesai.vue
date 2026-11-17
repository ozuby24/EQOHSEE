<script setup lang="ts">
/**
 * Pengujian (metode PJ) — layar penutup.
 *
 * Nilainya sengaja TIDAK ada di sini, dan halaman ini menyebutkan
 * ketiadaannya. Layar penutup yang diam soal nilai membuat peserta
 * mengira halamannya belum selesai memuat, lalu memuat ulang — dan
 * memuat ulang halaman penutup adalah cara paling umum orang mengira
 * pekerjaannya hilang.
 */
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{
  token: string;
  company: { name: string };
  catatan: string | null;
}>();
</script>

<template>
  <Head title="Pengujian selesai" />

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-10 text-center animate-pop">
    <div class="w-20 h-20 mx-auto rounded-full lime-gradient shadow-glow grid place-items-center text-white text-[34px]">✓</div>
    <h1 class="stat text-cam-ink mt-5">Terima kasih!</h1>
    <p class="text-[13px] text-stone-500 mt-2 leading-relaxed">
      Jawaban Anda sudah tersimpan dan akan dipakai untuk penilaian PTPKKP di {{ company.name }}.
    </p>
    <p v-if="catatan" class="text-[12px] text-stone-400 mt-3 max-w-md mx-auto leading-relaxed">
      {{ catatan }}
    </p>
    <Link :href="`/q/${token}`"
          class="inline-block mt-6 text-[12.5px] font-bold text-cam-lime-deep hover:underline">
      Isi kuesioner budaya keselamatan →
    </Link>
  </div>
</template>
