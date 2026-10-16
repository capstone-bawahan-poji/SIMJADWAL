/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', '-apple-system', 'sans-serif'],
        display: ['Quicksand', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono"', '"Fira Code"', 'monospace'],
      },
      colors: {
        primary: {
          DEFAULT: '#0f766e',
          hover: '#0d6b63',
          foreground: '#ffffff',
        },
      },
      boxShadow: {
        'primary-sm': '0 2px 8px 0 rgba(15,118,110,0.18)',
      },
    },
  },
  plugins: [],
}
