<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Wordmark from '../Components/Wordmark.vue';

/**
 * Catatan kaki datang dari SERVER, bukan dari slot bernama.
 *
 * Slot bernama tidak sampai ke sini: Inertia merender halamannya sebagai
 * anak bawaan kerangka ini, sehingga `<template #kaki>` pada halaman
 * tidak pernah terpasang — dan yang terjadi bukan galat melainkan
 * kalimat bawaan yang tetap tercetak. Terbukti begitu saat dicoba.
 *
 * Bawaannya kalimat PTPKKP karena tujuh dari sembilan halaman yang
 * memakai kerangka ini memang kuesioner dan pengujian PTPKKP. Dua
 * sisanya mengirimkan kalimatnya sendiri.
 */
const halaman = usePage<any>();

const kaki = computed(() => (halaman.props as any).catatanKaki
  ?? 'Jawaban bersifat anonim dan digunakan hanya untuk penilaian PTPKKP.');
</script>

<template>
  <div class="min-h-screen bg-cam-bg text-cam-ink">
    <header class="brand-gradient text-white">
      <div class="max-w-3xl mx-auto px-5 py-5 flex items-center gap-3">
        <Link href="/"><Wordmark :tinggi="26" /></Link>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-5 py-7">
      <slot />
    </main>

    <!-- Catatan kaki dapat diganti halamannya.

         Bawaannya kalimat PTPKKP karena tujuh dari sembilan halaman
         yang memakai kerangka ini memang kuesioner dan pengujian
         PTPKKP. Dua sisanya bukan — verifikasi sertifikat dan halaman
         pembayaran — dan pada keduanya kalimat itu menyesatkan: yang
         membaca "jawaban bersifat anonim" di halaman tagihan wajar
         menduga ia sedang berada di halaman yang keliru. -->
    <footer class="max-w-3xl mx-auto px-5 pb-8 text-center text-[11px] text-stone-400">
      {{ kaki }}
    </footer>
  </div>
</template>
