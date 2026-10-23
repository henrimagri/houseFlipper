import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { browserSecurityHeaders } from './security-headers';

export default defineConfig(({ command }) => {
  const development = command === 'serve';

  return {
    plugins: [react()],
    build: { manifest: true },
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    optimizeDeps: {
      // MapLibre 6 loads its worker as an ESM asset; pre-bundling rewrites the
      // worker URL to a transient dependency path that Vite cannot serve.
      exclude: ['maplibre-gl'],
    },
    server: {
      host: '127.0.0.1',
      port: 5173,
      strictPort: true,
      headers: browserSecurityHeaders(development),
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
        '/storage': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
        '/uploads': {
          target: 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
    preview: {
      headers: browserSecurityHeaders(false),
    },
  };
});
