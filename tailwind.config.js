import defaultTheme from 'tailwindcss/defaultTheme'
import colors from 'tailwindcss/colors'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // context.md §7: teal is the only accent. amber warns, red destroys,
            // emerald is reserved for the "Terbit" badge.
            colors: {
                primary: colors.teal,
                warning: colors.amber,
                danger: colors.red,
                success: colors.emerald,
                gray: colors.slate,
            },

            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },

            borderRadius: {
                DEFAULT: '0.375rem',
                card: '0.75rem',
            },

            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                raised: '0 4px 12px -2px rgb(15 23 42 / 0.10), 0 2px 6px -2px rgb(15 23 42 / 0.06)',
                overlay: '0 20px 40px -12px rgb(15 23 42 / 0.25)',
            },

            spacing: {
                sidebar: '16rem',
                'sidebar-collapsed': '4.5rem',
                topbar: '4rem',
            },

            zIndex: {
                sidebar: '30',
                topbar: '40',
                overlay: '50',
                modal: '60',
                toast: '70',
            },

            transitionDuration: {
                DEFAULT: '150ms',
            },

            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'slide-up': {
                    '0%': { opacity: '0', transform: 'translateY(0.5rem)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },

            animation: {
                'fade-in': 'fade-in 150ms ease-out',
                'slide-up': 'slide-up 200ms ease-out',
            },
        },
    },

    plugins: [forms, typography],
}
