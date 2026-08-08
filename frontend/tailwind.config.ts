import containerQueries from '@tailwindcss/container-queries';
import type { Config } from 'tailwindcss';

export default {
  content: ['./src/**/*.{ts,tsx}'],
  darkMode: 'class',
  plugins: [containerQueries],
  theme: {
    spacing: {
      0: '0', 1: '0.25rem', 2: '0.5rem', 3: '0.75rem', 4: '1rem',
      5: '1.25rem', 6: '1.5rem', 8: '2rem', 10: '2.5rem',
      12: '3rem', 16: '4rem', 20: '5rem', 24: '6rem',
    },
    containers: {
      xs: '20rem', sm: '24rem', md: '28rem', lg: '32rem',
      xl: '36rem', '2xl': '42rem', '3xl': '48rem', '4xl': '56rem',
      '5xl': '64rem', '6xl': '72rem', '7xl': '80rem',
    },
    fontSize: {
      '2xs': ['clamp(0.625rem, 0.6rem + 0.1cqi, 0.75rem)', { lineHeight: '1.5' }],
      'xs':  ['clamp(0.6875rem, 0.65rem + 0.15cqi, 0.8125rem)', { lineHeight: '1.5' }],
      'sm':  ['clamp(0.75rem, 0.7rem + 0.2cqi, 0.875rem)', { lineHeight: '1.5' }],
      'base':['clamp(0.875rem, 0.82rem + 0.25cqi, 1rem)', { lineHeight: '1.5' }],
      'lg':  ['clamp(1rem, 0.92rem + 0.35cqi, 1.25rem)', { lineHeight: '1.4' }],
      'xl':  ['clamp(1.125rem, 1rem + 0.5cqi, 1.5rem)', { lineHeight: '1.35' }],
      '2xl': ['clamp(1.25rem, 1.1rem + 0.7cqi, 1.75rem)', { lineHeight: '1.3' }],
      '3xl': ['clamp(1.5rem, 1.25rem + 1cqi, 2.25rem)', { lineHeight: '1.25' }],
      '4xl': ['clamp(1.75rem, 1.4rem + 1.4cqi, 3rem)', { lineHeight: '1.2' }],
      '5xl': ['clamp(2rem, 1.5rem + 2cqi, 4rem)', { lineHeight: '1.15' }],
    },
    fontFamily: {
      sans: ['Inter', 'system-ui', 'sans-serif'],
      mono: ['JetBrains Mono', 'monospace'],
    },
    borderRadius: {
      none: '0', sm: '0.25rem', DEFAULT: '0.5rem', md: '0.625rem',
      lg: '0.75rem', xl: '1rem', '2xl': '1.5rem', full: '9999rem',
    },
    screens: {
      sm: '20rem', md: '48rem', lg: '64rem', xl: '80rem', '2xl': '96rem',
    },
  },
} satisfies Config;
