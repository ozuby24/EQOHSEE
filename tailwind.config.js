import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', 'serif'],
                sign: ['"Dancing Script"', 'cursive'],
            },
            /**
             * Palet: Teal + Coral + Sand di atas dasar Charcoal dan Soft Beige.
             *
             * Nama token sengaja dipertahankan — `cam-lime` dan kerabatnya
             * dipakai di ratusan tempat. Yang berubah nilainya, sehingga satu
             * berkas ini menentukan rasa seluruh antarmuka.
             */
            colors: {
                cam: {
                    black: '#1B2422',       // charcoal bersemu teal
                    dark:  '#25332F',
                    panel: '#33463F',

                    // Teal — warna utama
                    lime:      '#0F766E',
                    'lime-dark':  '#0C5F58',
                    'lime-deep':  '#094A45',
                    'lime-light': '#2A9D8F',
                    'lime-soft':  '#E3F1EE',

                    // Coral — aksen hangat, dipakai hemat agar tetap mahal
                    coral:      '#FF7F50',
                    'coral-dark': '#E2663A',
                    'coral-soft': '#FFEDE3',

                    // Sand & Sage — bidang tenang penyeimbang teal
                    sand:       '#F5E6CA',
                    'sand-dark':'#E0CBA4',
                    sage:       '#B5CBB7',
                    'sage-soft':'#E4EDE5',

                    ink:  '#22312F',        // charcoal, bukan hitam pekat
                    bg:   '#F3EFE6',        // soft beige
                    amber:      '#D9993A',
                    'amber-dark': '#BE8226',
                },
            },
            backgroundImage: {
                'lime-grad':  'linear-gradient(135deg,#0C5F58 0%,#2A9D8F 100%)',
                'brand-grad': 'linear-gradient(120deg,#1B2422 0%,#25332F 42%,#0C5F58 76%,#0F766E 100%)',
                'coral-grad': 'linear-gradient(135deg,#E2663A 0%,#FF7F50 100%)',
                'amber-grad': 'linear-gradient(135deg,#BE8226 0%,#D9993A 100%)',
                'sand-grad':  'linear-gradient(135deg,#F5E6CA 0%,#F3EFE6 100%)',
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
