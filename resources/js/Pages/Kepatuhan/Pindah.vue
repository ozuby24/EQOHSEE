<script setup lang="ts">
/**
 * Bilah pindah antar halaman Pemenuhan.
 *
 * Keempatnya satu pekerjaan yang dikerjakan berurutan — memantau,
 * mendaftar, merekap, dan memasukkan naskah baru — jadi berpindah di
 * antaranya harus satu ketukan, bukan kembali ke menu samping.
 *
 * Ikonnya digambar sebagai path inline, bukan aksara hias seperti "◔".
 * Aksara hias tampil berbeda di tiap sistem — beberapa berwarna,
 * beberapa jatuh ke kotak kosong — dan tidak satu pun dapat diatur
 * ketebalan garisnya agar sepadan dengan huruf di sebelahnya.
 */
import { Link } from '@inertiajs/vue3';

defineProps<{
  tautan: { dasbor: string; register: string; rekap: string; unggah: string; pustaka: string };
  kini: 'dasbor' | 'register' | 'rekap' | 'unggah' | 'pustaka';
}>();

const BUTIR = [
  { kunci: 'dasbor',   label: 'Dasbor',
    jalur: ['M12 20a8 8 0 1 0-8-8', 'M12 12l4.5-4.5', 'M12 12h.01'] },
  { kunci: 'register', label: 'Register',
    jalur: ['M5 4.5h11l3 3V19.5H5z', 'M8.5 9.5h7', 'M8.5 13h7', 'M8.5 16.5h4'] },
  { kunci: 'rekap',    label: 'Rekap Bulanan',
    jalur: ['M4.5 6.5h15v13h-15z', 'M4.5 10.5h15', 'M9 6.5v-2', 'M15 6.5v-2', 'M9 14h2', 'M13 14h2'] },
  { kunci: 'pustaka',  label: 'Pustaka',
    jalur: ['M4 5.5A1.5 1.5 0 0 1 5.5 4H19v13H5.5A1.5 1.5 0 0 0 4 18.5Z',
            'M4 18.5A1.5 1.5 0 0 0 5.5 20H19', 'M8 8h7'] },
  { kunci: 'unggah',   label: 'Unggah & Rangkum',
    jalur: ['M6.5 16.5a3.5 3.5 0 0 1 .4-6.98 5 5 0 0 1 9.6-1.2 3.9 3.9 0 0 1 .5 7.68',
            'M12 12v7', 'm9.5 14.5 2.5-2.5 2.5 2.5'] },
] as const;
</script>

<template>
  <nav class="eq-pindah" aria-label="Halaman Pemenuhan">
    <Link v-for="b in BUTIR" :key="b.kunci"
          :href="tautan[b.kunci]"
          :class="['eq-pindah-pil', { aktif: kini === b.kunci }]"
          :aria-current="kini === b.kunci ? 'page' : undefined">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path v-for="(d, i) in b.jalur" :key="i" :d="d" />
      </svg>
      {{ b.label }}
    </Link>
  </nav>
</template>
