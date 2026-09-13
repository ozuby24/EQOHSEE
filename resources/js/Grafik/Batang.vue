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
import { Link } from '@inertiajs/vue3';
import { AKSEN, KEADAAN, REDUP, ringkas } from './warna';

const props = withDefaults(defineProps<{
  /**
   * `url` membuat barisnya DAPAT DITELUSURI: grafik menjawab "mana yang
   * bermasalah", dan pertanyaan berikutnya selalu "yang mana persisnya".
   * Tanpanya orang membaca nama pada grafik, lalu mencarinya sendiri di
   * daftar — pekerjaan yang diulang tiap kali.
   *
   * Pilihan, bukan keharusan: baris yang tidak menunjuk ke mana-mana
   * (pita nilai, misalnya) tidak dibuat seolah dapat diklik.
   */
  baris: { label: string; nilai: number; keadaan?: keyof typeof KEADAAN; url?: string }[];
  satuan?: string;
  /**
   * Cetak satuannya di samping angka, bukan hanya pada aria-label.
   *
   * OPSI, dan bawaannya mati. "60" pada grafik kemajuan kursus dapat
   * berarti 60 persen atau 60 modul; di sana satuannya harus terbaca.
   * Pada "12 laporan" ia hanya mengulang judul kartunya sendiri dan
   * melebarkan kolom angka sampai membungkus pada kartu sempit —
   * karena itu sebelas grafik yang sudah ada tidak ikut berubah.
   */
  satuanTampak?: boolean;
  /** Label batang yang ditonjolkan; sisanya diredupkan. */
  sorot?: string | null;
  /** Batas atas tetap — dipakai bila beberapa grafik harus sebanding. */
  maksTetap?: number | null;
  /** Sudah terurut dari pemanggilnya; jangan urutkan lagi. */
  apaAdanya?: boolean;
}>(), { satuan: '', satuanTampak: false, sorot: null, maksTetap: null, apaAdanya: false });

const urut = computed(() =>
  props.apaAdanya ? props.baris : [...props.baris].sort((a, b) => b.nilai - a.nilai));

const maks = computed(() =>
  props.maksTetap ?? Math.max(1, ...urut.value.map(b => Math.abs(b.nilai))));

/**
 * NOL DIGAMBAR SEBAGAI NOL.
 *
 * Lantai 1,5% dulu berlaku untuk semua nilai, termasuk nol — dan pada
 * grafik yang seluruh nilainya nol, keenam batangnya tampil sebagai
 * puntung berwarna yang terbaca sebagai "sedikit", bukan "tidak ada".
 * Terlihat pada Sertifikat terbit per bulan: enam bulan tanpa satu pun
 * sertifikat menggambar enam batang jingga.
 *
 * Lantainya tetap ada untuk nilai yang BUKAN nol: batang setipis
 * setengah piksel tidak dapat dibedakan dari yang tidak digambar, dan
 * di sana bedanya justru yang penting.
 */
const lebar = (v: number) =>
  v === 0 ? '0%' : `${Math.max(1.5, (Math.abs(v) / maks.value) * 100)}%`;

const warna = (b: { label: string; keadaan?: keyof typeof KEADAAN }) => {
  if (props.sorot && b.label !== props.sorot) return REDUP;

  return b.keadaan ? KEADAAN[b.keadaan] : AKSEN;
};

/**
 * Isian batang: landaian dari warnanya ke warna yang sedikit lebih
 * terang.
 *
 * Bukan hiasan. Batang datar setinggi 18px yang berderet enam baris
 * membaur menjadi satu blok warna, dan mata kehilangan batas antar
 * baris justru pada grafik yang barisnya paling banyak. Landaian tipis
 * memberi tiap batang tepi yang terbaca tanpa menambah garis.
 *
 * Landaiannya SELALU ke arah yang sama dan selalu setipis ini: landaian
 * yang mencolok membuat batang panjang tampak berubah warna di
 * tengahnya, dan warna di grafik ini punya arti sendiri.
 */
const isian = (b: { label: string; keadaan?: keyof typeof KEADAAN }) => {
  const w = warna(b);

  return `linear-gradient(90deg, ${w} 0%, ${w}D8 55%, ${w}B0 100%)`;
};
</script>

<template>
  <div v-if="!urut.length"
       class="h-full min-h-[140px] grid place-items-center text-[12px] text-stone-400">
    Belum ada data untuk dibandingkan.
  </div>

  <ul v-else class="space-y-1.5">
    <li v-for="b in urut" :key="b.label">
      <!-- Baris bertujuan menjadi tautan; yang tidak tetap sebuah div
           yang dapat difokus. Keduanya memakai kelas yang sama persis,
           jadi yang berubah hanya dapat-tidaknya ditelusuri — bukan
           rupanya. -->
      <component :is="b.url ? Link : 'div'" :href="b.url"
                 :tabindex="b.url ? undefined : 0"
                 class="grafik-baris grid items-center gap-3 rounded-lg px-1 py-0.5
                        hover:bg-stone-50 focus:outline-none focus-visible:ring-2
                        focus-visible:ring-cam-lime"
                 :style="{ gridTemplateColumns: 'minmax(0, 11rem) 1fr auto' }"
                 :aria-label="`${b.label}: ${ringkas(b.nilai)} ${satuan}`">

      <span class="text-[11.5px] text-cam-ink truncate" :title="b.label">{{ b.label }}</span>

      <!-- Tebalnya dibatasi supaya sisa jalurnya menjadi udara, bukan
           batang setebal barisnya. Sudut kanan dibulatkan penuh dan
           sudut kiri disikukan: batang tumbuh dari satu garis dasar,
           dan ujung bulat di pangkal membuatnya tampak melayang.

           Jalur redup di belakangnya menunjukkan sisa ruang menuju
           nilai terbesar — tanpa itu, batang terpendek tidak dapat
           dibedakan dari batang yang datanya belum masuk. -->
      <span class="grafik-jalur block h-[18px] rounded-[9px] bg-stone-100/70 overflow-hidden">
        <span class="grafik-batang block h-full rounded-r-[9px] transition-[width] duration-300"
              :style="{ width: lebar(b.nilai), background: isian(b) }"></span>
      </span>

      <span class="text-[11.5px] font-bold text-cam-ink num tabular-nums whitespace-nowrap">
        {{ ringkas(b.nilai) }}<template v-if="satuanTampak && satuan">{{ satuan }}</template>
      </span>
      </component>
    </li>
  </ul>
</template>
