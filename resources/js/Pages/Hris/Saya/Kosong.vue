<script setup lang="ts">
/**
 * Akun yang belum tertaut ke data pekerja.
 *
 * BUKAN GALAT, DAN TIDAK DIGAMBAR SEPERTI GALAT. Admin, auditor, dan
 * pengawas kantor memang bukan pekerja tambang mana pun; halaman yang
 * memerah atau berbunyi "akses ditolak" akan membuat mereka mengira ada
 * yang rusak dan melaporkannya. Yang dibutuhkan hanyalah satu kalimat
 * yang menyebutkan keadaannya, dan satu kalimat lagi yang menyebutkan
 * siapa yang dapat mengubahnya.
 */
import { Head } from '@inertiajs/vue3';
import { propHalaman } from '../../../halaman';

const props = propHalaman();
</script>

<template>
  <Head :title="props.judul as string" />

  <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-8 text-center">
    <span class="mx-auto grid place-items-center w-14 h-14 rounded-2xl bg-stone-50 text-stone-400">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
           stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7" aria-hidden="true">
        <path d="M19 20v-1.6a5 5 0 0 0-5-5h-4a5 5 0 0 0-5 5V20"/>
        <path d="M12 11.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/>
      </svg>
    </span>

    <h2 class="text-[15px] font-bold mt-4">Akun ini belum tertaut ke data pekerja</h2>

    <p class="text-[12.5px] text-stone-500 mt-2 max-w-md mx-auto leading-relaxed">
      Halaman "milik saya" menampilkan jadwal, kehadiran, cuti, dan slip gaji seseorang —
      dan untuk itu akun Anda perlu ditunjuk oleh satu baris pekerja. Administrator dapat
      menautkannya dari daftar pekerja.
    </p>
  </section>
</template>
