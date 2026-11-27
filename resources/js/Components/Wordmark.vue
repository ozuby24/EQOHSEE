<script setup lang="ts">
import { computed } from 'vue';

/**
 * Lambang dan nama EQOHSEE, satu bentuk untuk seluruh halaman.
 *
 * Merek EQOHSEE berupa pita Deep Teal–Olive Green–Warm Grey dengan
 * wordmark beraerodinamis dan ekor Q hijau zaitun. Wordmark-nya huruf
 * buatan, bukan fon: menuliskannya sebagai teks biasa selalu meleset dari
 * bentuk resminya, jadi yang dimuat adalah vektor resminya.
 *
 * Satu berkas tiap latar: varian `-dark` bernama putih dan berpita lebih
 * terang untuk latar gelap, varian `-light` bernama #072420 untuk latar
 * terang. Lambang heksagon jingga-perak dan lambang gunung navy-emas
 * adalah identitas lama dan tidak dipakai lagi di mana pun.
 *
 * `tinggi` adalah tinggi gambar keseluruhan; lebarnya mengikuti
 * perbandingan vektornya sendiri. `teks=false` menampilkan lambangnya
 * saja (persegi).
 */
const props = withDefaults(defineProps<{ gelap?: boolean; tinggi?: number; teks?: boolean }>(), {
  gelap: true,
  tinggi: 28,
  teks: true,
});

/* Perbandingan lebar∶tinggi viewBox lockup navbar (917,3 × 164,8). */
const RASIO_LOCKUP = 917.3 / 164.8;

const sumber = computed(() => props.teks
  ? (props.gelap ? '/brand/eqohsee-navbar-dark.svg' : '/brand/eqohsee-navbar-light.svg')
  : (props.gelap ? '/brand/eqohsee-icon-dark.svg' : '/brand/eqohsee-icon.svg'));

const lebar = computed(() => props.teks ? Math.round(props.tinggi * RASIO_LOCKUP) : props.tinggi);
</script>

<template>
  <!-- Lebar dan tinggi ditulis supaya ruangnya sudah dipesan sebelum
       berkasnya tiba: tanpa itu kepala halaman melompat sekali saat
       logonya muncul. -->
  <img
    :src="sumber"
    alt="EQOHSEE"
    :width="lebar" :height="tinggi"
    :style="{ height: `${tinggi}px`, width: `${lebar}px` }"
    class="block shrink-0 max-w-none"
    decoding="async"
  >
</template>
