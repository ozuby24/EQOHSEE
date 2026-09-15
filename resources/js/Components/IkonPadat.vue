<script setup lang="ts">
/**
 * Glyph PADAT di atas ubin berdimensi.
 *
 * Bedanya dengan IkonStat bukan gaya melainkan cara menggambar:
 * yang ini diisi (fill), yang itu digaris (stroke). Di atas bidang
 * berwarna, glyph garis menyusut jadi beberapa benang putih dan
 * ubinnya berhenti terbaca sebagai ikon — jadi keduanya dipakai di
 * tempat yang berbeda, bukan saling menggantikan.
 *
 * Jalurnya datang dari server (App\Support\IkonPadat), bukan dipetakan
 * ulang di sini. Satu modul karena itu tidak pernah dapat punya dua
 * ikon padat yang berbeda di dua halaman.
 */
type Jalur = { d: string; evenodd?: boolean };

const props = withDefaults(defineProps<{
  jalur?: Jalur[] | null;
  ukuran?: number;
}>(), { jalur: () => [], ukuran: 20 });
</script>

<template>
  <!-- Tanpa jalur, tidak digambar apa pun. Ubin berwarna yang kosong
       masih terbaca sebagai penanda; bentuk cadangan yang salah justru
       memberi tahu hal yang keliru tentang modulnya. -->
  <svg v-if="props.jalur && props.jalur.length" :width="props.ukuran" :height="props.ukuran"
       viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
    <!-- evenodd menahan lubang di dalam bentuk tetap berlubang:
         gerigi roda gigi, jendela gedung, kaca pembesar. -->
    <path v-for="(j, i) in props.jalur" :key="i" :d="j.d"
          :fill-rule="j.evenodd ? 'evenodd' : undefined"
          :clip-rule="j.evenodd ? 'evenodd' : undefined" />
  </svg>
</template>
