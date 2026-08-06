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
            colors: {
                cam: {
                    black: '#0E2B44',
                    dark:  '#14385A',
                    panel: '#1E5075',
                    lime:      '#158D99',
                    'lime-dark':  '#0E747E',
                    'lime-deep':  '#0B5E66',
                    'lime-light': '#2CB0BC',
                    'lime-soft':  '#E6F5F6',
                    ink:  '#123049',
                    bg:   '#FBFAF7',
                    amber:      '#E0A62C',
                    'amber-dark': '#C6911E',
                },
            },
            backgroundImage: {
                'lime-grad':  'linear-gradient(135deg,#0E747E 0%,#2CB0BC 100%)',
                'brand-grad': 'linear-gradient(120deg,#0E2B44 0%,#14385A 48%,#116570 78%,#158D99 100%)',
                'amber-grad': 'linear-gradient(135deg,#C6911E 0%,#E0A62C 100%)',
            },
            boxShadow: {
                soft: '0 1px 2px rgba(0,0,0,.04),0 8px 24px -12px rgba(0,0,0,.18)',
                card: '0 1px 3px rgba(14,43,68,.06),0 14px 40px -18px rgba(14,43,68,.22)',
                glow: '0 10px 40px -12px rgba(21,141,153,.45)',
            },
        },
    },
    plugins: [forms],
};
