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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Archivo', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    navy: '#0B1F3A',
                    'navy-light': '#1B3A66',
                    emerald: '#0F9D58',
                    'emerald-dark': '#0C7E46',
                    gold: '#F4B400',
                },
            },
        },
    },

    plugins: [forms],
};
