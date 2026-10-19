import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { browserSecurityHeaders } from './security-headers';

export default defineConfig(({ command }) => {
  const development = command === 'serve';

  return {
    plugins: [react()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
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
      },
    },
    preview: {
      headers: browserSecurityHeaders(false),
    },
  };
});
