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
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Lexend Variable"', 'Lexend', '"Noto Sans"', 'system-ui', 'sans-serif'],
                mono: ['"Noto Sans Mono"', 'monospace'],
                serif: ['"Lexend Variable"', 'Lexend', '"Noto Serif"', 'serif'],
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
                'aiwa-shake': {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '20%, 60%': { transform: 'translateX(-6px)' },
                    '40%, 80%': { transform: 'translateX(6px)' },
                },
            },
            animation: {
                'slide-up': 'slideUp 0.3s ease-out',
                shake: 'aiwa-shake 0.5s ease-in-out',
            },
            colors: {
                brand: {
                    pink: '#E91E8C',
                    purple: '#7B3FA0',
                },
                surface: {
                    light: '#F8F8F8',
                    dark: '#1A1A1A',
                },
                pos: {
                    noun:      { bg: '#FCE4F3', text: '#C2185B' },
                    verb:      { bg: '#E8F5E9', text: '#2E7D32' },
                    adjective: { bg: '#E3F2FD', text: '#1565C0' },
                    phrase:    { bg: '#E0F7FA', text: '#00796B' },
                    adverb:    { bg: '#FFF8E1', text: '#F57F17' },
                    other:     { bg: '#F3E5F5', text: '#6A1B9A' },
                },
            },
        },
    },
    plugins: [],
    corePlugins: {
        preflight: false, // Disable Tailwind's base reset to avoid conflicts with WordPress
    },
};
