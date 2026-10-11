import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// The app is pages are served by the PHP site at /app/...; the built files live in public/ui/.
export default defineConfig({
  base: '/ui/',
  plugins: [react()],
  build: { outDir: '../public/ui', emptyOutDir: true, sourcemap: false },
  server: { port: 5173, proxy: { '/api': 'http://127.0.0.1:8099', '/assets': 'http://127.0.0.1:8099', '/company': 'http://127.0.0.1:8099' } },
});
