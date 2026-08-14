<script setup lang="ts">
/**
 * Lambang dan nama EQOHSEE, satu bentuk untuk seluruh halaman.
 *
 * Halaman masuk sudah memakai lambangnya lewat GuestLayout, tetapi
 * halaman depan dan halaman /pilar menuliskan namanya sebagai teks
 * biasa — sehingga pengunjung yang datang dari halaman depan lalu
 * masuk melihat dua identitas yang berbeda pada dua layar berurutan.
 *
 * Lambangnya adalah heksagon jingga-perak. Satu berkas melayani latar
 * gelap maupun terang: bidangnya jingga dan perak di atas transparan,
 * jadi tidak ada varian putih yang perlu dipilih. Berkas lambang gunung
 * navy-emas (eqohsee-mark.svg) adalah identitas lama dan tidak dipakai
 * lagi di mana pun.
 *
 * Yang dimuat varian 128 piksel, bukan berkas 512 piksel seberat 217 KB:
 * slot terbesar yang memakainya 40 piksel, jadi 128 sudah melebihi
 * kebutuhan layar berkerapatan ganda sekalipun.
 *
 * `gelap` kini hanya menentukan warna teksnya, bukan berkas lambangnya.
 */
withDefaults(defineProps<{ gelap?: boolean; tinggi?: number; teks?: boolean }>(), {
  gelap: true,
  tinggi: 28,
  teks: true,
});
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <!-- Lambangnya persegi (512×512), jadi lebar dan tingginya sama. -->
    <img
      src="/brand/eqohsee-mark-128.png"
      alt="" aria-hidden="true"
      :width="tinggi" :height="tinggi"
      :style="{ height: `${tinggi}px`, width: `${tinggi}px` }"
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
