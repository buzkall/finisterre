/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
    ],
    safelist: [
        'bg-red-300', 'bg-blue-300', 'bg-gray-200'
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}
