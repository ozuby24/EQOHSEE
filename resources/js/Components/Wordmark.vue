<script setup lang="ts">
/**
 * Lambang dan nama EQOHSEE, satu bentuk untuk seluruh halaman.
 *
 * Halaman masuk sudah memakai lambangnya lewat GuestLayout, tetapi
 * halaman depan dan halaman /pilar menuliskan namanya sebagai teks
 * biasa — sehingga pengunjung yang datang dari halaman depan lalu
 * masuk melihat dua identitas yang berbeda pada dua layar berurutan.
 *
 * Berkas lambangnya sudah ada di public/brand sejak lama; yang belum
 * ada adalah satu tempat yang memakainya secara seragam.
 *
 * `gelap` menandai latar gelap, dan memilih berkas putih. Dipilih lewat
 * prop, bukan lewat kelas CSS: berkasnya memang dua, dan menyembunyikan
 * salah satunya dengan CSS berarti peramban tetap mengunduh keduanya.
 */
withDefaults(defineProps<{ gelap?: boolean; tinggi?: number; teks?: boolean }>(), {
  gelap: true,
  tinggi: 28,
  teks: true,
});
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <img
      :src="gelap ? '/brand/eqohsee-mark-white.svg' : '/brand/eqohsee-mark.svg'"
      alt="" aria-hidden="true"
      :style="{ height: `${tinggi}px`, width: 'auto' }"
      class="block shrink-0"
    >
    <!--
      Namanya tetap berupa teks, bukan ikut ke dalam gambar. Teks dapat
      dibaca pembaca layar, ikut tersalin ketika halaman disalin, dan
      tidak buram pada layar berkerapatan tinggi. Huruf Q diberi warna
      aksen, sama seperti pada lambangnya — jingga terang di atas latar
      gelap, jingga penuh di atas latar terang, mengikuti komponen Blade
      x-brand supaya kedua sisi aplikasi menuliskan merek yang sama.
    -->
    <span v-if="teks" class="font-extrabold tracking-wide leading-none"
          :style="{ fontSize: `${Math.round(tinggi * 0.62)}px` }">
      E<span :class="gelap ? 'text-cam-orange-light' : 'text-cam-orange'">Q</span>OHSEE
    </span>
  </span>
</template>
