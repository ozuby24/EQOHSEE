<script setup lang="ts">
/**
 * Ikon untuk kartu statistik dan kartu modul.
 *
 * Sebelumnya seluruh kartu di dashboard menggambar satu karakter yang
 * sama — belah ketupat "◈" yang ditulis harfiah di dalam templat.
 * Lima kartu ringkasan dan enam kartu modul semuanya memakai lambang
 * itu, sehingga tidak satu pun ikon di halaman itu membedakan apa pun.
 *
 * App\Support\Ikon sudah lama mengirimkan nama ikon lewat prop `ikon`
 * pada tiap kartu modul, dan tipe ModuleItem bahkan mencantumkannya —
 * tetapi tidak ada yang membacanya. Pola yang sama persis dengan ikon
 * pilar di halaman depan.
 *
 * Digambar sebagai path inline, bukan pustaka ikon: repo ini sudah
 * sekali melepas pustaka dari CDN, dan ikon yang gagal dimuat pada
 * jaringan site tambang meninggalkan kotak kosong.
 */
const props = withDefaults(defineProps<{ nama: string; ukuran?: number }>(), { ukuran: 18 });

const jalur: Record<string, string[]> = {
  /* ---- ringkasan pembelajaran ---- */
  // Total kursus — tumpukan buku
  pustaka: ['M4 5.5A1.5 1.5 0 0 1 5.5 4H19v13H5.5A1.5 1.5 0 0 0 4 18.5Z', 'M4 18.5A1.5 1.5 0 0 0 5.5 20H19', 'M8 8h7'],
  // Selesai — centang dalam lingkaran
  tuntas: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  // Kemajuan — grafik menanjak
  laju: ['M4 19h16', 'M6.5 15.5 10 11l3 2.6 4.5-6'],
  // Sertifikat — medali berpita
  medali: ['M12 3.5a4.6 4.6 0 1 0 0 9.2 4.6 4.6 0 0 0 0-9.2Z', 'm8.6 12-1.4 8.5 4.8-2.5 4.8 2.5-1.4-8.5'],
  // Belum diikuti — jam
  menunggu: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7.5v5l3.2 1.9'],

  /* ---- kartu modul ---- */
  hazard:   ['M12 9.4v4.2', 'M12 17h.01', 'M10.4 4.1 2.6 17.8a1.8 1.8 0 0 0 1.6 2.7h15.6a1.8 1.8 0 0 0 1.6-2.7L13.6 4.1a1.8 1.8 0 0 0-3.2 0Z'],
  inspeksi: ['M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Z', 'M20.5 20.5 16 16'],
  objek:    ['M12 3.2 4 7.4v9.2l8 4.2 8-4.2V7.4Z', 'M4 7.4l8 4.2 8-4.2', 'M12 11.6v9.2'],
  audit:    ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'm8.5 12.2 2.4 2.4 4.6-4.9'],
  materi:   ['M7 3.5h6.5L18 8v12.5H7a1 1 0 0 1-1-1v-15a1 1 0 0 1 1-1Z', 'M13.5 3.5V8H18', 'M9 13h6', 'M9 16.5h4'],
  penilaian:['M13 7h8', 'M21 7v8', 'm21 7-8 8-4-4-6 6'],

  /* ---- kategori kursus ---- */
  kursus:   ['M12 3.6 2.8 8 12 12.4 21.2 8Z', 'M6.4 10v4.4c0 1.6 2.5 2.9 5.6 2.9s5.6-1.3 5.6-2.9V10'],
};

/** Bentuk cadangan: kotak bertitik, jelas bahwa ikonnya belum dipetakan. */
const cadangan = ['M5 5h14v14H5z', 'M12 11.5v.01'];
</script>

<template>
  <svg :width="props.ukuran" :height="props.ukuran" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
       aria-hidden="true" focusable="false">
    <path v-for="(d, i) in (jalur[props.nama] || cadangan)" :key="i" :d="d" />
  </svg>
</template>
