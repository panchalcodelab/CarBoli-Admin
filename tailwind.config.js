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
                poppins: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                carboli: {
                    50: '#e6f5fc',
                    100: '#cceafa',
                    200: '#99d5f4',
                    300: '#5cbcea',
                    400: '#1fa6e2',
                    500: '#0098db',
                    600: '#0072a8',
                    700: '#005b86',
                    800: '#004d73',
                    900: '#003a56',
                    950: '#00273a',
                },
            },
        },
    },

    plugins: [forms],
};
