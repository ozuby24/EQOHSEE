import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';
import { singkap } from './singkap';

/*
  Chart.js ikut dibundel (tidak lagi dari CDN), tetapi ditarik saat
  dibutuhkan — lihat bagan.ts untuk kedua alasannya.

  Yang dipasang di sini hanyalah pintunya. `eqChartSiap` sudah sejak awal
  berbentuk callback karena dulu ia menunggu skrip dari jaringan; bentuk
  itulah yang membuat pemuatan tertunda ini tidak menuntut satu baris pun
  berubah di kedua halaman yang memakainya.

  Impornya dihafal supaya beberapa grafik pada satu halaman berbagi satu
  unduhan, dan supaya kegagalannya terjadi sekali — bukan sekali per
  grafik.
*/
let bagan: Promise<typeof import('./bagan')> | null = null;

window.eqChartSiap = (cb: () => void): void => {
  bagan ??= import('./bagan');

  bagan.then(cb).catch((e) => {
    /* Halaman pemakainya sudah menjaga diri (`if (!C) return`), jadi
       kegagalan di sini berarti kanvas kosong, bukan halaman rusak. Yang
       hilang tanpa baris ini adalah keterangannya: grafik yang tidak
       muncul tanpa sebab persis keluhan yang melahirkan bagan.ts. */
    bagan = null;
    console.error('Modul grafik gagal dimuat; grafik tidak digambar.', e);
  });
};

/*
  Titik masuk halaman Vue.

  Alpine sengaja TIDAK dimuat di sini — halaman Inertia tidak memakainya,
  dan dua kerangka yang sama-sama mengikat elemen mudah berebut atas
  elemen yang sama.
*/

createInertiaApp({
  title: (judul) => (judul ? `${judul} — EQOHSEE` : 'EQOHSEE'),

  /*
    Halaman dimuat SAAT DIBUTUHKAN, bukan seluruhnya di muka.

    Sebelumnya glob ini memakai `eager: true`, yang menarik seluruh 147
    halaman ke dalam satu berkas 1,5 MB. Artinya seorang pengawas yang
    membuka satu halaman laporan bahaya ikut mengunduh modul peledakan,
    konservasi, penirisan, gudang, dan seratus empat puluh dua halaman
    lain yang tidak akan ia buka hari itu.

    Yang membuatnya penting bukan angka melainkan tempat: aplikasi ini
    dipakai di site tambang, di ujung sambungan yang lambat dan sering
    terputus. Satu setengah megabita sebelum layar pertama muncul adalah
    perbedaan antara aplikasi yang terasa hidup dan aplikasi yang
    disangka rusak lalu dimuat ulang berkali-kali — yang justru
    mengunduhnya lagi dari awal.

    Tanpa `eager`, tiap halaman menjadi berkasnya sendiri dan hanya yang
    dibuka yang diunduh. Yang sudah pernah dibuka tersimpan di peramban,
    jadi ongkosnya dibayar sekali per halaman, bukan sekali per kunjungan.
  */
  resolve: async (nama) => {
    const halaman = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');
    const muat = halaman[`./Pages/${nama}.vue`];

    if (!muat) {
      throw new Error(`Halaman Inertia '${nama}' tidak ditemukan di resources/js/Pages.`);
    }

    const komponen = (await muat()).default;

    // Tata letak dipasang di sini, bukan diimpor tiap halaman: satu
    // halaman yang lupa membungkus dirinya akan tampil tanpa bilah
    // samping sama sekali, dan itu tidak menimbulkan galat apa pun.
    komponen.layout ??= AppLayout;

    /* Yang dikembalikan komponennya, bukan modulnya. Inertia menerima
       keduanya saat dijalankan, tetapi hanya salah satunya yang sah
       menurut tipe `resolve` — dan bentuk yang tidak sah lolos begitu saja
       lewat build, sebab Vite tidak memeriksa tipe. */
    return komponen;
  },

  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)

      /* v-singkap dipasang menyeluruh, bukan diimpor per halaman:
         gerakan masuk hanya terasa satu kesatuan bila lengkung waktu,
         jarak, dan jedanya sama di seluruh situs. Diimpor sendiri-sendiri,
         tiap halaman perlahan memilih angkanya masing-masing. */
      .directive('singkap', singkap)
      .mount(el);
  },

  progress: {
    color: '#FF9800',
    showSpinner: false,
  },
});
