import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                studio: {
                    950: '#08080b',
                    900: '#0e0e13',
                    800: '#15151c',
                    700: '#1e1e27',
                    600: '#2a2a36',
                    500: '#3b3b4a',
                },
                ember: {
                    300: '#f8dba6',
                    400: '#f2c879',
                    500: '#e8b258',
                    600: '#d09434',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                glow: '0 0 40px -12px rgba(232, 178, 88, 0.45)',
            },
        },
    },

    plugins: [forms],
};
