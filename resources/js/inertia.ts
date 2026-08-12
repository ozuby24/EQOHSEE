import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

/*
  Titik masuk halaman Vue.

  Alpine sengaja TIDAK dimuat di sini — halaman Inertia tidak memakainya,
  dan dua kerangka yang sama-sama mengikat elemen mudah berebut atas
  elemen yang sama.
*/

createInertiaApp({
  title: (judul) => (judul ? `${judul} — EQOHSEE` : 'EQOHSEE'),

  resolve: (nama) => {
    const halaman = import.meta.glob<DefineComponent>('./Pages/**/*.vue', { eager: true });
    const komponen = halaman[`./Pages/${nama}.vue`];

    if (!komponen) {
      throw new Error(`Halaman Inertia '${nama}' tidak ditemukan di resources/js/Pages.`);
    }

    // Tata letak dipasang di sini, bukan diimpor tiap halaman: satu
    // halaman yang lupa membungkus dirinya akan tampil tanpa bilah
    // samping sama sekali, dan itu tidak menimbulkan galat apa pun.
    komponen.default.layout ??= AppLayout;

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
