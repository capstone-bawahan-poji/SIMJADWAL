/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        // IBM Plex: institutional sans for UI, serif for page titles, mono only for codes (HC1, IF101).
        sans: ['"IBM Plex Sans Variable"', 'system-ui', '-apple-system', 'sans-serif'],
        serif: ['"IBM Plex Serif"', 'Georgia', 'serif'],
        mono: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
      },
      // Type scale on the 8-point grid: size / line-height.
      fontSize: {
        xs: ['12px', '16px'],
        sm: ['14px', '20px'],
        base: ['16px', '24px'],
        lg: ['20px', '28px'],
        xl: ['24px', '32px'],
      },
      colors: {
        primary: {
          DEFAULT: '#1e3a8a',
          hover: '#1e40af',
          foreground: '#ffffff',
          soft: '#eff3fb',
          tint: '#dbe4f5',
        },
        ink: {
          DEFAULT: '#101828',
          secondary: '#344054',
          tertiary: '#475467',
          muted: '#5d6679',
          faint: '#98a2b3',
        },
        line: {
          DEFAULT: '#e4e7ec',
          soft: '#eef0f3',
          strong: '#d0d5dd',
        },
        canvas: '#f5f6f8',
        disabled: '#eaecf0',
        danger: {
          DEFAULT: '#d92d20',
          hover: '#b42318',
          ink: '#b42318',
          bg: '#fef3f2',
          border: '#fda29b',
        },
        warning: {
          ink: '#93370d',
          bg: '#fff4e5',
          border: '#fedf89',
          dot: '#f79009',
        },
        success: {
          ink: '#05603a',
          bg: '#ecfdf3',
          border: '#a6f4c5',
          dot: '#12b76a',
          tint: '#d1fadf',
        },
      },
      borderRadius: {
        badge: '4px',
        control: '8px',
        panel: '12px',
      },
    },
  },
  plugins: [],
}
