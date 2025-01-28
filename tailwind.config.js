/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/**/*.blade.php',
    ],
    safelist: [
        'bg-red-300', 'bg-green-300', 'bg-blue-300', 'bg-gray-200'
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}

