<script setup lang="ts">
/**
 * Cincin berputar untuk di dalam tombol yang sedang bekerja.
 *
 * Dipisahkan menjadi komponen karena ia dipakai di empat tempat yang
 * tidak saling tahu — masuk, verifikasi kode, simpan catatan, tandai
 * materi selesai — dan empat salinan SVG berputar akan berbeda
 * ukurannya pada perubahan pertama.
 *
 * `currentColor`, bukan warna tetap. Tombol utama berlatar oranye
 * dengan teks putih, tombol kedua berlatar putih dengan teks tinta;
 * cincin yang warnanya ditulis mati akan hilang sama sekali di salah
 * satunya.
 *
 * aria-hidden: yang perlu dibacakan pembaca layar adalah teks tombolnya
 * — "Memeriksa kredensial…" — bukan grafiknya. Tombol yang membacakan
 * keduanya mengucapkan hal yang sama dua kali.
 */
withDefaults(defineProps<{ ukuran?: number }>(), { ukuran: 15 });
</script>

<template>
  <span class="eq-putaran" aria-hidden="true"
        :style="{ width: ukuran + 'px', height: ukuran + 'px' }" />
</template>

<style scoped>
.eq-putaran {
  display: inline-block;
  flex: none;
  border-radius: 99px;

  /* Dari currentColor, dengan lingkar yang diredupkan dan satu sisi
     penuh. Dua warna berbeda dari satu warna teks: yang redup menjadi
     jalurnya, yang penuh menjadi yang berlari di atasnya. */
  border: 2px solid color-mix(in srgb, currentColor 30%, transparent);
  border-top-color: currentColor;
  animation: eq-putaran-putar .62s linear infinite;
}

@keyframes eq-putaran-putar {
  to { transform: rotate(360deg); }
}

/* Yang meminta gerakan dikurangi tetap mendapat cincinnya, hanya diam.
   Menghapusnya sama sekali membuat tombol yang sedang bekerja tampak
   persis seperti tombol yang tidak bereaksi. */
@media (prefers-reduced-motion: reduce) {
  .eq-putaran {
    animation: none;
    border-color: color-mix(in srgb, currentColor 55%, transparent);
    border-top-color: currentColor;
  }
}
</style>
