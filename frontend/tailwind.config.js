/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
      },
      colors: {
        canvas: '#F7F8FA',
        surface: '#FFFFFF',
        ink: '#171A21',
        muted: '#667085',
        line: '#E6E8EC',
        brand: {
          DEFAULT: '#334E85',
          hover: '#283E6B',
          subtle: '#EEF2F9',
        },
        success: {
          DEFAULT: '#16845B',
          subtle: '#ECFDF5',
        },
        warning: {
          DEFAULT: '#B7791F',
          subtle: '#FEF9C3',
        },
        danger: {
          DEFAULT: '#C2413B',
          subtle: '#FEF2F2',
        },
      },
    },
  },
  plugins: [],
};
