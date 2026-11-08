import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',

        /* Halaman Inertia hidup di .vue, bukan .blade.php. Tanpa baris ini
           Tailwind memindai hanya berkas Blade untuk memutuskan kelas mana
           yang dipakai, dan kelas yang HANYA muncul di sebuah komponen Vue
           dibuang saat build produksi — tanpa galat apa pun, kelasnya
           sekadar hilang dari CSS. Sudah terjadi sekali: `disabled:cursor-
           not-allowed` pada tombol Simpan PTPKKP hilang total dari berkas
           terkompilasi karena kelas itu tidak muncul di satu pun .blade.php. */
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                /* Archivo: muka huruf merek — judul halaman depan, kop, dan
                   halaman tamu. Dimuat lewat huruf-archivo.css. */
                brand: ['Archivo', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
                /* Playfair hanya untuk lembar sertifikat — dokumen resmi
                   memang pantas berhuruf serif. */
                display: ['"Playfair Display"', 'serif'],
            },
            /**
             * Palet: Dark Navy + Orange + Silver — mengikuti logo EQOHSEE.
             *
             * Perbandingannya kira-kira 70% navy gelap, 20% putih/abu terang,
             * 10% jingga. Jingga hanya untuk aksen dan tombol utama: dipakai
             * pada bidang lebar ia berhenti menarik perhatian, padahal
             * menarik perhatian satu-satunya alasan ia ada.
             *
             * Nama token sengaja dipertahankan — `cam-lime` dan kerabatnya
             * dipakai di ratusan tempat. Yang berubah nilainya, sehingga satu
             * berkas ini menentukan rasa seluruh antarmuka.
             */
            colors: {
                cam: {
                    black: '#0B1117',       // navy black — chrome utama
                    dark:  '#151D26',
                    panel: '#1E2835',

                    // Jingga EQOHSEE — aksen dan tombol utama
                    lime:      '#F57C00',
                    'lime-dark':  '#DC6E00',
                    'lime-deep':  '#A85400',
                    'lime-light': '#FF9800',
                    'lime-soft':  '#FFF2E2',

                    /*
                     * Nama jujurnya, sebagai alias nilai yang sama.
                     *
                     * `cam-lime` dipertahankan karena dipakai di ratusan
                     * tempat, tetapi warnanya sudah lama bukan hijau — dan
                     * penulisan berikutnya wajar menebak `cam-orange`. Tebakan
                     * itu sudah terjadi di delapan berkas: `text-cam-orange`
                     * pada logotype, `ring-cam-orange` pada kartu pilar
                     * terpilih, dan sepuluh tempat lain. Karena tokennya tidak
                     * pernah ada, Tailwind tidak menghasilkan kelasnya sama
                     * sekali — huruf Q tampil putih dan cincin pilihannya
                     * tidak pernah muncul.
                     *
                     * Didaftarkan, bukan ditulis ulang menjadi `cam-lime`:
                     * nama ini yang benar untuk warnanya, dan tebakan yang
                     * sama pasti terulang.
                     */
                    orange:         '#F57C00',
                    'orange-light': '#FF9800',
                    'orange-dark':  '#DC6E00',
                    'orange-soft':  '#FFF2E2',

                    // Merah — bahaya, penolakan, penghapusan
                    coral:      '#EF4444',
                    'coral-dark': '#DC2626',
                    'coral-soft': '#FEE9E9',

                    // Perak & hijau — bidang tenang dan penanda berhasil
                    sand:       '#E8ECF0',   // light gray
                    'sand-dark':'#B8BEC5',   // silver
                    sage:       '#22C55E',   // success
                    'sage-soft':'#E7F8ED',

                    ink:  '#0F1720',        // teks gelap, bukan hitam pekat
                    bg:   '#F5F7F9',        // off white
                    amber:      '#FACC15',  // warning
                    'amber-dark': '#CA9A04',
                },

                /*
                 * Palet modul Audit SMKP — dibawa utuh dari Aurorasys, sebab
                 * halaman dan lembar cetak SMKP adalah porting persis Aurorasys
                 * (navy · biru · teal · hijau di atas abu slate).
                 *
                 * Aman ditambahkan: di luar halaman SMKP tidak ada satu pun
                 * kelas navy-, brand-, hijau-, teal-, atau cyan- di EQOHSEE
                 * (diperiksa saat porting). `teal` dan `cyan` menimpa tangga
                 * bawaan Tailwind dengan nilai Aurorasys. Pasangan mode gelap
                 * tinggal di resources/views/partials/eq-visual.blade.php (bagian SMKP).
                 */
                navy: {
                    50: '#F0F5FA', 100: '#DCE7F2', 200: '#B9CFE4', 300: '#8AAFCF', 400: '#5786B3',
                    500: '#356695', 600: '#235079', 700: '#173F63', 800: '#103556', 900: '#0B2D4D', 950: '#071D33',
                },
                teal: {
                    50: '#E8FBF9', 100: '#C5F5F0', 200: '#8EECE4', 300: '#50DFD4', 400: '#1FD1C5',
                    500: '#00C6BB', 600: '#00A49C', 700: '#00837E', 800: '#066765', 900: '#0B5453',
                },
                cyan: {
                    50: '#E8F8FF', 100: '#C6EEFF', 200: '#8DDDFF', 300: '#4FCBFF', 400: '#1CC2FF',
                    500: '#00BFFF', 600: '#009AD6', 700: '#0079AB', 800: '#086189', 900: '#0C5070',
                },
                brand: {
                    50: '#EEF8FE', 100: '#D8EEFC', 200: '#B3DDF9', 300: '#7CC5F4', 400: '#3DA8EA',
                    500: '#1592D6', 600: '#0284C7', 700: '#036AA1', 800: '#075885', 900: '#0C496D',
                },
                hijau: {
                    50: '#ECFDF5', 100: '#D1FAE5', 200: '#A7F3D0', 300: '#6EE7B7', 400: '#34D399',
                    500: '#10B981', 600: '#059669', 700: '#047857', 800: '#065F46', 900: '#064E3B',
                },
            },
            /* Token bentuk modul Audit SMKP (Aurorasys). */
            fontSize: {
                mikro: ['11px', { lineHeight: '16px' }],
            },
            borderRadius: {
                kartu: '14px',
            },
            keyframes: {
                muncul: { '0%': { opacity: 0, transform: 'translateY(6px)' }, '100%': { opacity: 1, transform: 'none' } },
            },
            animation: {
                muncul: 'muncul .35s cubic-bezier(.2,.8,.2,1) both',
            },
            backgroundImage: {
                'lime-grad':  'linear-gradient(135deg,#DC6E00 0%,#FF9800 100%)',
                'brand-grad': 'linear-gradient(160deg,#0B1117 0%,#141C25 55%,#1B2530 100%)',
                'coral-grad': 'linear-gradient(135deg,#DC2626 0%,#EF4444 100%)',
                'amber-grad': 'linear-gradient(135deg,#CA9A04 0%,#FACC15 100%)',
                'sand-grad':  'linear-gradient(135deg,#E8ECF0 0%,#F5F7F9 100%)',
            },
            boxShadow: {
                soft: '0 1px 2px rgba(34,49,47,.04),0 8px 24px -12px rgba(34,49,47,.18)',
                card: '0 1px 3px rgba(34,49,47,.05),0 14px 40px -18px rgba(34,49,47,.20)',
                glow: '0 10px 40px -12px rgba(245,124,0,.42)',
                // Bayangan berlapis: satu garis rambut, satu jatuh dalam.
                // Inilah yang membuat kartu terbaca mahal, bukan blur tebal.
                lux:  '0 0 0 1px rgba(34,49,47,.05),0 2px 4px rgba(34,49,47,.04),0 24px 60px -28px rgba(34,49,47,.34)',
                'coral-glow': '0 10px 36px -14px rgba(255,127,80,.55)',
                /* Modul Audit SMKP (Aurorasys). */
                kartu:  '0 1px 2px rgba(11,45,77,.05), 0 1px 1px rgba(11,45,77,.03)',
                angkat: '0 16px 40px -16px rgba(11,45,77,.28), 0 2px 6px -2px rgba(11,45,77,.08)',
                cahaya: '0 1px 2px rgba(11,45,77,.12)',
            },
        },
    },
    plugins: [forms],
};
