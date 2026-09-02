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
            },
            colors: {
                brand: {
                    50: '#fbf8ec',
                    100: '#f5ebce',
                    200: '#ecd99d',
                    300: '#e1c274',
                    400: '#d0a64a',
                    500: '#b1862a',
                    600: '#8c6513',
                    700: '#73500d',
                    800: '#5d4212',
                    900: '#4a3512',
                    950: '#291c08',
                },
            },
        },
    },

    plugins: [forms],
};
