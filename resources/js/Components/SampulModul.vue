<script setup lang="ts">
import { computed } from 'vue';

/**
 * Sampul halaman awal modul: foto, geo tag, dan kondisi cuaca.
 *
 * Foto dipilih menurut isi modulnya (lihat App\Support\SampulModul) dan
 * keterangan situsnya dibaca dari data yang sudah dicatat sendiri (lihat
 * App\Support\KondisiSitus) — bukan dari layanan ramalan di luar.
 *
 * Yang tidak ada datanya tidak digambar sama sekali. Sebuah lencana
 * "Cerah" yang muncul karena tidak ada catatan hujan lebih buruk
 * daripada bidang kosong: ia terbaca sebagai bacaan alat, dan orang
 * mengambil keputusan lapangan dari bacaan alat.
 *
 * Yang tertulis besar di sini adalah NAMA MODUL, bukan judul halaman.
 * Judul halaman sudah tergambar dua kali — pada bilah atas dan pada
 * kepala halaman itu sendiri — dan menuliskannya sekali lagi membuatnya
 * muncul tiga kali dalam satu layar. Lebih buruk lagi, sebagian halaman
 * awal modul berjudul "Dashboard": sampul setinggi ini yang hanya
 * berbunyi "Dashboard" tidak menerangkan modul apa yang sedang dibuka,
 * padahal justru itu satu-satunya hal yang perlu dijawabnya.
 */
const props = defineProps<{
  sampul: {
    gambar: string;
    webp: string | null;
    keterangan: string;
    label: string | null;
    kondisi: {
      lokasi: { nama: string | null; perusahaan: string; koordinat: string | null } | null;
      cuaca: { kunci: string; label: string; hujanMm: number; tanggal: string | null; hariIni: boolean } | null;
      waktu: { jam: string; zona: string; tanggal: string };
    } | null;
  } | null;
}>();

const kondisi = computed(() => props.sampul?.kondisi ?? null);

/**
 * Warna lencana cuaca mengikuti beratnya hujan, bukan satu warna netral.
 * Hujan sangat lebat menghentikan pekerjaan di lereng dan jalan angkut;
 * yang menyamakan warnanya dengan gerimis menghapus perbedaan itu tepat
 * di tempat orang membacanya sekilas.
 */
const WARNA_CUACA: Record<string, string> = {
  cerah:              'bg-amber-400/90 text-amber-950',
  hujan_ringan:       'bg-sky-300/90 text-sky-950',
  hujan_sedang:       'bg-sky-500/90 text-white',
  hujan_lebat:        'bg-blue-600/90 text-white',
  hujan_sangat_lebat: 'bg-red-600/90 text-white',
};

const warnaCuaca = computed(() =>
  WARNA_CUACA[kondisi.value?.cuaca?.kunci ?? ''] ?? 'bg-stone-500/90 text-white');
</script>

<template>
  <section v-if="props.sampul"
           class="relative overflow-hidden rounded-2xl border border-stone-200/60 shadow-card">
    <!--
      Tinggi dikunci lewat aspect-ratio bertingkat, bukan tinggi tetap.
      Foto 16:9 yang dipaksa setinggi 220 piksel pada layar ponsel
      menyisakan sepotong langit tanpa satu pun isi fotonya, dan sampul
      yang isinya hanya langit tidak menerangkan apa pun.
    -->
    <picture>
      <source v-if="props.sampul.webp" :srcset="props.sampul.webp" type="image/webp">
      <img :src="props.sampul.gambar" :alt="props.sampul.keterangan"
           class="h-[190px] w-full object-cover sm:h-[230px]"
           width="1600" height="900" decoding="async">
    </picture>

    <!-- Lapisan gelap dari bawah supaya teks putih tetap terbaca di atas
         langit senja yang terang maupun tebing yang gelap. -->
    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-black/10"></div>

    <div class="absolute inset-0 flex flex-col justify-between p-4 sm:p-5">
      <div class="flex flex-wrap items-start justify-end gap-2">
        <div class="flex flex-wrap items-center gap-2">
          <!-- Cuaca dari catatan hujan situs sendiri. Tanggalnya ikut
               disebut: hujan pagi tadi dan hujan kemarin menuntut
               keputusan yang berbeda. -->
          <span v-if="kondisi?.cuaca"
                class="flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold backdrop-blur-sm"
                :class="warnaCuaca"
                :title="`Curah hujan tercatat ${kondisi.cuaca.hujanMm} mm`">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <template v-if="kondisi.cuaca.kunci === 'cerah'">
                <circle cx="12" cy="12" r="4.2"/>
                <path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>
              </template>
              <template v-else>
                <path d="M7 16.5a4.2 4.2 0 0 1 .6-8.4 5.6 5.6 0 0 1 10.7 1.6 3.4 3.4 0 0 1-.8 6.8Z"/>
                <path d="M9 19.5l-.8 2M13 19.5l-.8 2M17 19.5l-.8 2"/>
              </template>
            </svg>
            {{ kondisi.cuaca.label }}
            <small class="font-semibold opacity-80">
              {{ kondisi.cuaca.hujanMm }} mm<template v-if="!kondisi.cuaca.hariIni && kondisi.cuaca.tanggal">
                · {{ kondisi.cuaca.tanggal }}</template>
            </small>
          </span>

          <span v-if="kondisi?.waktu"
                class="rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold text-white backdrop-blur-sm">
            {{ kondisi.waktu.jam }} {{ kondisi.waktu.zona }}
          </span>
        </div>
      </div>

      <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/70">Modul</p>
        <h2 v-if="props.sampul.label"
            class="text-lg font-extrabold tracking-tight text-white drop-shadow sm:text-2xl">
          {{ props.sampul.label }}
        </h2>

        <!-- Geo tag. Koordinatnya datang dari titik tengah layer peta
             tambang yang sudah digambar, bukan diketik terpisah — yang
             diketik terpisah akan berselisih dengan petanya sendiri. -->
        <div v-if="kondisi?.lokasi" class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-white/85">
          <span class="flex items-center gap-1.5">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/>
              <circle cx="12" cy="10" r="2.6"/>
            </svg>
            <b class="font-bold">{{ kondisi.lokasi.nama ?? kondisi.lokasi.perusahaan }}</b>
          </span>

          <span v-if="kondisi.lokasi.koordinat" class="font-mono text-[10.5px] text-white/70">
            {{ kondisi.lokasi.koordinat }}
          </span>

          <span class="text-white/60">{{ kondisi.waktu.tanggal }}</span>
        </div>
      </div>
    </div>

    <!-- Keterangan foto. Tanpa ini, rekaman umum di halaman resmi mudah
         disangka foto situs perusahaan yang sedang membukanya. -->
    <span class="absolute bottom-1.5 right-2.5 text-[9px] text-white/45">
      {{ props.sampul.keterangan }}
    </span>
  </section>
</template>
