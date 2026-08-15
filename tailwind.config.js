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
                display: ['"Playfair Display"', 'serif'],
                sign: ['"Dancing Script"', 'cursive'],
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
                glow: '0 10px 40px -12px rgba(15,118,110,.42)',
                // Bayangan berlapis: satu garis rambut, satu jatuh dalam.
                // Inilah yang membuat kartu terbaca mahal, bukan blur tebal.
                lux:  '0 0 0 1px rgba(34,49,47,.05),0 2px 4px rgba(34,49,47,.04),0 24px 60px -28px rgba(34,49,47,.34)',
                'coral-glow': '0 10px 36px -14px rgba(255,127,80,.55)',
            },
        },
    },
    plugins: [forms],
};
