import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'node:fs';

/*
  Dua titik masuk yang berdiri sendiri.

  `app.js` melayani halaman Blade: Alpine dan gerak halaman publik.
  `inertia.ts` melayani halaman Vue. Keduanya sengaja tidak digabung —
  halaman Blade tidak perlu memuat Vue, dan halaman Inertia tidak perlu
  memuat Alpine. Menggabungnya berarti setiap halaman menanggung berat
  kerangka yang tidak dipakainya, dan dua kerangka yang sama-sama
  mengikat elemen mudah berebut atas elemen yang sama.
*/
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
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
