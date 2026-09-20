<script setup lang="ts">
/**
 * Lingkaran bermedia dengan tulisan melingkar di tepinya.
 *
 * Bentuk ini yang paling menandai rancangan acuan: sebuah foto bulat
 * dikelilingi kalimat yang mengikuti lengkungnya. Di sini ia memuat
 * semboyan platform, bukan sekadar hiasan — bagian yang paling menarik
 * mata pada sebuah halaman sebaiknya juga yang menyebut untuk apa
 * situs ini ada.
 *
 * Tulisannya digambar SVG `textPath`, bukan huruf yang diputar satu per
 * satu dengan transform. Diputar sendiri-sendiri, jarak antarhuruf
 * meleset pada tiap ukuran layar yang berbeda dan kalimatnya terbaca
 * bergelombang; `textPath` membiarkan peramban yang menghitungnya, dan
 * hasilnya sama rata di lebar berapa pun.
 */
withDefaults(defineProps<{
  /** Kalimat yang melingkar. Diulang sendiri bila pendek. */
  teks: string;

  /** Foto di dalam lingkaran. Null menggambar lingkaran kosong berpola. */
  gambar?: string | null;

  /** Detik untuk satu putaran penuh. */
  putaran?: number;

  /** Warna tulisannya — terang di atas latar gelap, gelap di atas krem. */
  nada?: 'terang' | 'gelap';
}>(), { gambar: null, putaran: 26, nada: 'terang' });
</script>

<template>
  <div class="eq-cincin" :class="`is-${nada}`">
    <!-- Tulisan melingkar. aria-hidden: kalimatnya sudah tertulis utuh
         di badan halaman, dan dibacakan dua kali hanya mengulang. -->
    <svg class="eq-cincin-teks" viewBox="0 0 200 200" aria-hidden="true"
         :style="{ animationDuration: putaran + 's' }">
      <defs>
        <path id="eq-cincin-jalur"
              d="M 100,100 m -76,0 a 76,76 0 1,1 152,0 a 76,76 0 1,1 -152,0" />
      </defs>
      <text>
        <textPath href="#eq-cincin-jalur" startOffset="0">{{ teks }}</textPath>
      </text>
    </svg>

    <div class="eq-cincin-isi">
      <img v-if="gambar" :src="gambar" alt="" loading="lazy" decoding="async">
      <slot v-else />
    </div>
  </div>
</template>

<style scoped>
.eq-cincin {
  position: relative;
  width: 100%;
  max-width: 21rem;
  aspect-ratio: 1;
  display: grid;
  place-items: center;
}

.eq-cincin-teks {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  animation: eq-cincin-putar linear infinite;
}

.eq-cincin-teks text {
  font-size: 9.4px;
  font-weight: 600;
  letter-spacing: .34em;
  text-transform: uppercase;
  fill: currentColor;
}

.is-terang { color: rgba(255, 255, 255, .62); }
.is-gelap  { color: rgba(18, 22, 26, .55); }

@keyframes eq-cincin-putar { to { transform: rotate(360deg); } }

.eq-cincin-isi {
  width: 78%;
  height: 78%;
  border-radius: 50%;
  overflow: hidden;
  display: grid;
  place-items: center;
  background: rgba(255, 255, 255, .06);
}

.eq-cincin-isi img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* Yang meminta gerakan seminimal mungkin: tulisannya BERHENTI, tidak
   hilang. Dihapus, semboyan yang jadi alasan bentuk ini ada ikut
   hilang bagi orang yang paling terganggu gerakan. */
@media (prefers-reduced-motion: reduce) {
  .eq-cincin-teks { animation: none; }
}
</style>
