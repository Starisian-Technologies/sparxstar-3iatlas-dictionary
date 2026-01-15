/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './src/**/*.php',
        './src/**/*.js',
        './src/**/*.jsx',
        './src/**/*.ts',
        './src/**/*.tsx',
        './src/templates/**/*.php',
        './*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Noto Sans"', 'system-ui', 'sans-serif'],
                mono: ['"Noto Sans Mono"', 'monospace'],
                serif: ['"Noto Serif"', 'serif'],
            },
            fontSize: {
                base: ['1.05rem', { lineHeight: '1.6' }], // Improves legibility for dense orthography
                lg: ['1.15rem', { lineHeight: '1.7' }],
                xl: ['1.35rem', { lineHeight: '1.6' }],
            },
            letterSpacing: {
                wide: '0.015em', // Helps distinguish digraphs (ŋ, ny, gb, kp)
            },
            keyframes: {
                slideUp: {
                    '0%': { transform: 'translateY(100%)' },
                    '100%': { transform: 'translateY(0)' },
                },
            },
            animation: {
                'slide-up': 'slideUp 0.3s ease-out',
            },
            colors: {
                primary: {
                    50: '#f0f9ff',
                    100: '#e0f2fe',
                    200: '#bae6fd',
                    300: '#7dd3fc',
                    400: '#38bdf8',
                    500: '#0ea5e9',
                    600: '#0284c7',
                    700: '#0369a1',
                    800: '#075985',
                    900: '#0c4a6e',
                    950: '#082f49',
                },
            },
        },
    },
    plugins: [],
    corePlugins: {
        preflight: false, // Disable Tailwind's base reset to avoid conflicts with WordPress
    },
};
