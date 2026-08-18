import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'node:fs';

/*
  Satu titik masuk.

  Dulu ada dua: `app.js` melayani halaman Blade (Alpine, gerak halaman
  publik), `inertia.ts` melayani halaman Vue. Halaman Blade-nya sudah
  habis dipindahkan ke Vue, dan bersama halaman terakhirnya hilang pula
  satu-satunya pemuat `app.js` — sejak itu ia tetap dibangun 47 kB tiap
  kali, tanpa satu halaman pun yang menariknya.

  Keempat perilaku yang dibawanya juga sudah tidak berpijak pada apa pun:
  tidak ada lagi markup ber-`.reveal`, `[data-tilt]`, `[data-parallax]`,
  atau `[data-hero-video]`. Video hero dikerjakan langsung di Landing.vue
  dan GuestLayout.vue, lengkap dengan penghormatan pada
  `prefers-reduced-motion` yang dulu ditangani di sana.
*/
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/inertia.ts',
            ],
            refresh: true,
        }),
        vue({
            /*
              `defineProps<Tipe>()` dengan tipe yang diimpor dari berkas lain
              mengharuskan compiler SFC membaca berkas itu. Di lingkungan
              build ini akses berkasnya tidak tersedia secara bawaan, dan
              tanpa opsi ini build berhenti dengan "No fs option provided".

              Alternatifnya menyalin tiap antarmuka ke dalam komponennya
              masing-masing — yang berarti bentuk data ditulis dua kali dan
              salinannya berbeda diam-diam begitu server mengubah bentuknya.
            */
            script: {
                fs: {
                    fileExists: (berkas) => fs.existsSync(berkas),
                    readFile: (berkas) => fs.readFileSync(berkas, 'utf-8'),
                },
            },
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
