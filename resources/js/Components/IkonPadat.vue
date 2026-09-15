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
type Jalur = {
  d: string;
  evenodd?: boolean;
  /** Tebal garis, bila bagian ini memang digambar dengan garis. */
  garis?: number;
  /** Nilai transform SVG, mis. 'rotate(45 17.4 6.6)'. */
  putar?: string;
};

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
    <!-- Sebagian besar bagian diisi. Yang membawa `garis` digambar
         dengan garis — batang beliung pada ikon Mine Operations
         misalnya; diisi, ia berubah jadi coretan. evenodd menahan
         lubang di dalam bentuk tetap berlubang: gerigi roda gigi,
         jendela gedung, kaca pembesar. -->
    <path v-for="(j, i) in props.jalur" :key="i" :d="j.d"
          :transform="j.putar"
          :fill="j.garis ? 'none' : undefined"
          :stroke="j.garis ? 'currentColor' : undefined"
          :stroke-width="j.garis"
          :stroke-linecap="j.garis ? 'round' : undefined"
          :stroke-linejoin="j.garis ? 'round' : undefined"
          :fill-rule="j.evenodd ? 'evenodd' : undefined"
          :clip-rule="j.evenodd ? 'evenodd' : undefined" />
  </svg>
</template>
