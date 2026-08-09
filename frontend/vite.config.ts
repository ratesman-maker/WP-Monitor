import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { visualizer } from 'rollup-plugin-visualizer';
import path from 'path';

export default defineConfig({
  plugins: [
    react(),
    visualizer({
      filename: 'stats.html',
      gzipSize: true,
      brotliSize: true,
      emitFile: true,
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://web',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    manualChunks: {
      'react-vendor': ['react', 'react-dom', 'react-router-dom'],
      'query-vendor': ['@tanstack/react-query'],
      'ui-vendor': ['lucide-react', 'class-variance-authority', 'clsx', 'tailwind-merge'],
      'chart-vendor': ['recharts'],
      'form-vendor': ['react-hook-form', 'zod'],
    },
  },
});
