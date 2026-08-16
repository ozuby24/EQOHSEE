<script setup lang="ts">
/**
 * Batang mendatar — membandingkan besaran antar hal yang dinamai.
 *
 * MENDATAR, bukan tegak, dan itu keputusan yang disengaja: nama di
 * modul ini panjang-panjang ("Dump Truck HD465-7", "Tanggul Kolam
 * Pengendap 3"). Pada batang tegak nama sepanjang itu harus dimiringkan
 * atau dipotong; mendatar ia terbaca mendatar seperti teks biasa.
 *
 * DIGAMBAR DENGAN HTML, BUKAN SVG. Versi pertamanya SVG ber-viewBox
 * tetap yang direntangkan selebar wadahnya — dan ukuran huruf di dalam
 * viewBox ikut terentang bersamanya. Akibatnya sama sekali tidak
 * kentara sampai jumlah barisnya sedikit: grafik tiga baris pada kartu
 * selebar 1100px menggambar namanya sebesar judul halaman. Ukuran
 * huruf pada HTML mutlak, jadi ia tidak dapat terjadi lagi.
 *
 * Yang dijaga:
 *
 * - Diurutkan dari yang TERBESAR, kecuali pemanggilnya menyatakan
 *   urutannya sendiri berarti (nomor sub-elemen Kepmen, misalnya).
 *   Batang yang urutannya acak menuntut pembacanya mencari sendiri
 *   yang tertinggi — pekerjaan yang justru seharusnya diambil alih
 *   grafiknya.
 *
 * - Satu deret memakai SATU warna. Mewarnai tiap batang berbeda-beda
 *   menghabiskan saluran identitas untuk mengulang apa yang sudah
 *   dikatakan panjangnya, dan membuat pembaca mencari makna warna yang
 *   tidak ada. Warna hanya berbeda ketika ia menyatakan KEADAAN.
 *
 * - Nilainya ditulis di UJUNG batang, bukan di dalamnya: batang pendek
 *   tidak muat memuat angkanya, dan angka yang terpotong lebih buruk
 *   daripada angka yang di luar.
 *
 * - Satu batang dapat DITONJOLKAN lewat `sorot`; sisanya menjadi abu.
 *   Itu bentuk yang tepat ketika ceritanya "yang ini yang bermasalah",
 *   dan hampir selalu lebih jelas daripada memberi delapan warna.
 */
import { computed } from 'vue';
import { AKSEN, KEADAAN, REDUP, ringkas } from './warna';

const props = withDefaults(defineProps<{
  baris: { label: string; nilai: number; keadaan?: keyof typeof KEADAAN }[];
  satuan?: string;
  /** Label batang yang ditonjolkan; sisanya diredupkan. */
  sorot?: string | null;
  /** Batas atas tetap — dipakai bila beberapa grafik harus sebanding. */
  maksTetap?: number | null;
  /** Sudah terurut dari pemanggilnya; jangan urutkan lagi. */
  apaAdanya?: boolean;
}>(), { satuan: '', sorot: null, maksTetap: null, apaAdanya: false });

const urut = computed(() =>
  props.apaAdanya ? props.baris : [...props.baris].sort((a, b) => b.nilai - a.nilai));

const maks = computed(() =>
  props.maksTetap ?? Math.max(1, ...urut.value.map(b => Math.abs(b.nilai))));

const lebar = (v: number) => `${Math.max(1.5, (Math.abs(v) / maks.value) * 100)}%`;

const warna = (b: { label: string; keadaan?: keyof typeof KEADAAN }) => {
  if (props.sorot && b.label !== props.sorot) return REDUP;

  return b.keadaan ? KEADAAN[b.keadaan] : AKSEN;
};
</script>

<template>
  <div v-if="!urut.length"
       class="h-full min-h-[140px] grid place-items-center text-[12px] text-stone-400">
    Belum ada data untuk dibandingkan.
  </div>

  <ul v-else class="space-y-1.5">
    <li v-for="b in urut" :key="b.label"
        tabindex="0"
        class="grafik-baris grid items-center gap-3 rounded-lg px-1 py-0.5
               hover:bg-stone-50 focus:outline-none focus-visible:ring-2
               focus-visible:ring-cam-lime"
        :style="{ gridTemplateColumns: 'minmax(0, 11rem) 1fr auto' }"
        :aria-label="`${b.label}: ${ringkas(b.nilai)} ${satuan}`">

      <span class="text-[11.5px] text-cam-ink truncate" :title="b.label">{{ b.label }}</span>

      <!-- Tebalnya dibatasi supaya sisa jalurnya menjadi udara, bukan
           batang setebal barisnya. Sudut kanan dibulatkan dan sudut
           kiri disikukan: batang tumbuh dari satu garis dasar, dan
           ujung bulat di pangkal membuatnya tampak melayang. -->
      <span class="block h-[18px] rounded-r-[4px] transition-[width] duration-300"
            :style="{ width: lebar(b.nilai), background: warna(b) }"></span>

      <span class="text-[11.5px] font-bold text-cam-ink num tabular-nums whitespace-nowrap">
        {{ ringkas(b.nilai) }}
      </span>
    </li>
  </ul>
</template>
