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
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Quicksand', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand navy from docs/ui. Use `primary` in components, never a raw hex.
                primary: {
                    DEFAULT: '#1e3a8a',
                    hover: '#1e40af',
                    foreground: '#ffffff',
                },
            },
        },
    },

    plugins: [forms],
};
