import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Space Grotesk', ...defaultTheme.fontFamily.sans],
            },
            // CREASY Community palette — orange with warm graphite neutrals.
            // Accessible dark orange for white button labels.
            // Source of truth: ./.branding
            colors: {
                surface: {
                    DEFAULT: '#fffaf5',
                    subtle: '#fff2e6',
                },
                secondary: {
                    50: '#fafaf9',
                    100: '#f5f5f4',
                    200: '#e7e5e4',
                    300: '#d6d3d1',
                    400: '#a8a29e',
                    500: '#78716c',
                    600: '#57534e',
                    700: '#44403c',
                    800: '#292524',
                    900: '#1c1917',
                    950: '#0c0a09',
                },
                brand: {
                    50: 'rgb(var(--brand-50, 255 247 237) / <alpha-value>)',
                    100: 'rgb(var(--brand-100, 255 237 213) / <alpha-value>)',
                    200: 'rgb(var(--brand-200, 254 215 170) / <alpha-value>)',
                    300: 'rgb(var(--brand-300, 253 186 116) / <alpha-value>)',
                    400: 'rgb(var(--brand-400, 251 146 60) / <alpha-value>)',
                    500: 'rgb(var(--brand-500, 255 122 0) / <alpha-value>)',
                    600: 'rgb(var(--brand-600, 196 81 0) / <alpha-value>)',
                    700: 'rgb(var(--brand-700, 168 67 0) / <alpha-value>)',
                    800: 'rgb(var(--brand-800, 135 55 0) / <alpha-value>)',
                    900: 'rgb(var(--brand-900, 111 47 0) / <alpha-value>)',
                    950: 'rgb(var(--brand-950, 53 25 0) / <alpha-value>)',
                },
                // Orange accents for the community identity.
                accent: {
                    50: '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#ff7a00',
                    600: '#c45100',
                    700: '#a84300',
                    800: '#873700',
                    900: '#6f2f00',
                    950: '#351900',
                },
                // Danger / destructive (coral-red)
                coral: {
                    50: '#fff3f1',
                    100: '#ffe4df',
                    200: '#ffcabf',
                    300: '#ffa593',
                    400: '#fb7355',
                    500: '#f04e2e',
                    600: '#d8331a',
                    700: '#b32512',
                    800: '#931f13',
                    900: '#7a1e16',
                    950: '#420a07',
                },
                neutral: {
                    50: '#fafafa',
                    100: '#f4f4f5',
                    200: '#e4e4e7',
                    300: '#d4d4d8',
                    400: '#a1a1aa',
                    500: '#71717a',
                    600: '#52525b',
                    700: '#3f3f46',
                    800: '#27272a',
                    900: '#18181b',
                    950: '#0a0a0b',
                },
            },
            // Soft borders
            borderWidth: {
                soft: '1px',
            },
            borderColor: {
                DEFAULT: 'rgb(228 228 231 / 0.8)',
                soft: 'rgb(228 228 231 / 0.6)',
                muted: 'rgb(228 228 231 / 0.4)',
            },
            borderRadius: {
                soft: '0.5rem',
                'soft-lg': '0.75rem',
                'soft-xl': '1rem',
            },
            // Subtle shadows
            boxShadow: {
                soft: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 2px -1px rgb(0 0 0 / 0.04)',
                'soft-md': '0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05)',
                'soft-lg': '0 10px 15px -3px rgb(0 0 0 / 0.06), 0 4px 6px -4px rgb(0 0 0 / 0.06)',
                'soft-xl': '0 20px 25px -5px rgb(0 0 0 / 0.06), 0 8px 10px -6px rgb(0 0 0 / 0.06)',
                inner: 'inset 0 1px 2px 0 rgb(0 0 0 / 0.04)',
            },
            // Spacing scale (align with design)
            spacing: {
                '4.5': '1.125rem',
                '13': '3.25rem',
                '15': '3.75rem',
                '18': '4.5rem',
                '22': '5.5rem',
                '30': '7.5rem',
            },
            transitionDuration: {
                150: '150ms',
                250: '250ms',
            },
            transitionTimingFunction: {
                smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
            },
        },
    },

    plugins: [forms],
};
