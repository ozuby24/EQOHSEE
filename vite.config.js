import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'node:fs';
import path from 'node:path';

/*
  Pengurai gambar pdf.js (JBIG2, JPEG 2000) untuk Unggah & Rangkum.

  PDF hasil pindaian lazimnya menyimpan halamannya sebagai JBIG2 atau
  JPEG 2000, dan pdf.js memuat penguraiannya dari satu folder yang
  disebut `wasmUrl` — nama berkasnya tetap, tidak dapat diberi hash oleh
  Vite. Tanpa folder itu halaman pindaiannya tergambar KOSONG, dan yang
  dikirim untuk dibaca hanyalah kertas putih.

  Folder itu ditaruh di samping pekerja pdf.js, bernama menurut versinya:
  berkas statis di sini disimpan peramban setahun (lihat nginx), jadi
  nama yang sama untuk isi yang berbeda tidak boleh terjadi.
*/
function penguraiPdfjs() {
    const asal = path.resolve('node_modules/pdfjs-dist/wasm');
    const versi = JSON.parse(fs.readFileSync(path.resolve('node_modules/pdfjs-dist/package.json'), 'utf-8')).version;
    const berkas = ['jbig2.wasm', 'jbig2_nowasm_fallback.js', 'openjpeg.wasm', 'openjpeg_nowasm_fallback.js', 'qcms_bg.wasm'];

    return {
        name: 'eqohsee-pengurai-pdfjs',
        apply: 'build',
        generateBundle() {
            for (const b of berkas) {
                this.emitFile({ type: 'asset', fileName: `assets/pdfjs-${versi}/${b}`, source: fs.readFileSync(path.join(asal, b)) });
            }
        },
    };
}

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
    build: {
        rollupOptions: {
            output: {
                /* Pekerja pdf.js berakhiran .mjs. nginx yang lebih tua tidak
                   mengenal .mjs dan menyajikannya sebagai octet-stream, dan
                   pekerja modul dengan jenis itu ditolak peramban (ditambah
                   nosniff) — PDF-nya lalu tidak terbaca sama sekali. */
                assetFileNames: (a) => ((a.names?.[0] ?? a.name ?? '').endsWith('.mjs')
                    ? 'assets/[name]-[hash].js'
                    : 'assets/[name]-[hash][extname]'),
            },
        },
    },
    plugins: [
        penguraiPdfjs(),
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
