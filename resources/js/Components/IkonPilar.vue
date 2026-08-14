<script setup lang="ts">
/**
 * Ikon untuk kedelapan aspek EQOHSEE.
 *
 * Nama ikonnya sudah lama dikirim oleh App\Support\Pillars — 'bolt',
 * 'droplet', 'gear', dan seterusnya — tetapi tidak pernah ada yang
 * menggambarnya. Halaman depan dan halaman /pilar sama-sama menampilkan
 * huruf pertama nama aspeknya sebagai pengganti, sehingga Occupational
 * Health dan Hygiene keduanya tampil sebagai "O" dan "H" yang tidak
 * membedakan apa pun, dan Environment serta Engineering sama-sama "E".
 *
 * Digambar sebagai path inline, bukan berkas terpisah maupun pustaka
 * ikon: repo ini sudah sekali melepas pustaka dari CDN, dan ikon yang
 * gagal dimuat pada jaringan site tambang meninggalkan kotak kosong
 * persis di tempat yang paling menjelaskan.
 *
 * Semuanya digambar pada kanvas 24×24 dengan tebal garis yang sama,
 * supaya berdampingan tidak ada yang tampak lebih berat daripada yang
 * lain.
 */
const props = withDefaults(defineProps<{ nama: string; ukuran?: number }>(), { ukuran: 22 });

const jalur: Record<string, string[]> = {
  // Energy — kilat
  bolt: ['M13 2 4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5Z'],

  // Quality — permata bersudut
  gem: ['M6 3h12l3 6-9 12L3 9l3-6Z', 'M3 9h18', 'M9.5 3 12 9l2.5-6', 'M12 9v12'],

  // Occupational Health — hati dengan denyut
  health: [
    'M12 20.5s-7.5-4.7-7.5-10a4.5 4.5 0 0 1 8.1-2.7l-.6.8-.6-.8A4.5 4.5 0 0 1 19.5 10.5c0 5.3-7.5 10-7.5 10Z',
    'M4.8 12.2h3l1.6-2.6 1.9 4.4 1.7-3.1 1.1 1.3h3.1',
  ],

  // Hygiene — tetes air
  droplet: ['M12 3.2s5.6 6.1 5.6 9.9a5.6 5.6 0 1 1-11.2 0C6.4 9.3 12 3.2 12 3.2Z', 'M9.4 13.6a2.6 2.6 0 0 0 2.6 2.6'],

  // Safety — perisai bertanda centang
  shield: ['M12 2.8 4.6 5.7v5.6c0 4.5 3.1 8.6 7.4 9.9 4.3-1.3 7.4-5.4 7.4-9.9V5.7L12 2.8Z', 'm8.9 11.9 2.2 2.2 4-4.4'],

  // Environment — daun
  leaf: ['M20 4c0 9-5.5 13-11 13a5 5 0 0 1-1.6-.3C6 15.6 5 13.4 5 11 5 6.6 10 4 20 4Z', 'M4 20c2.5-4.5 6-7.5 11-9.5'],

  // Konservasi Minerba — lapisan endapan
  layers: [
    'M12.8 2.6a2 2 0 0 0-1.6 0L2.6 6.5a1 1 0 0 0 0 1.8l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 0-1.8Z',
    'M2 12.4a1 1 0 0 0 .6.9l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 .6-.9',
    'M2 17.2a1 1 0 0 0 .6.9l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 .6-.9',
  ],

  // Engineering — roda gigi
  gear: [
    'M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Z',
    'M19.4 14.3a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1h.2a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z',
  ],
};

/** Bentuk cadangan: lingkaran bertitik, jelas bahwa ikonnya belum ada. */
const cadangan = ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 8.5v4', 'M12 15.6h.01'];
</script>

<template>
  <svg :width="props.ukuran" :height="props.ukuran" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"
       aria-hidden="true" focusable="false">
    <path v-for="(d, i) in (jalur[props.nama] || cadangan)" :key="i" :d="d" />
  </svg>
</template>
