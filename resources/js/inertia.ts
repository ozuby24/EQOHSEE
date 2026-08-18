import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

/* Chart.js ikut dibundel, tidak lagi ditarik dari CDN. Diimpor demi
   efek sampingnya — ia memasang window.eqChartSiap yang dipanggil
   halaman-halaman bergrafik. Lihat bagan.ts untuk alasannya. */
import './bagan';

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
      .mount(el);
  },

  progress: {
    color: '#FF9800',
    showSpinner: false,
  },
});
