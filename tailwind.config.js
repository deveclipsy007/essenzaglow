/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./*.php"],
    theme: {
        extend: {
            colors: {
                ivory: { DEFAULT: '#F8F4EA', dark: '#F5EFE3' },
                sand: { DEFAULT: '#EEE9DC', dark: '#E3DECF' },
                sage: { DEFAULT: '#A0A892', dark: '#818A74' },
                charcoal: { DEFAULT: '#433C30', light: '#5C5446' },
                gold: { DEFAULT: '#DAC38F', dark: '#B49C73', light: '#E5D4B3' },
            },
            fontFamily: {
                serif: ['"Playfair Display"', 'serif'],
                sans: ['"Inter"', 'sans-serif'],
            },
            animation: {
                'fade-in': 'fadeIn 0.5s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                }
            }
        },
    },
    plugins: [],
}
