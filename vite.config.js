import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  publicDir: false,
  build: {
    outDir: 'public/assets/calendar-dist',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    target: 'es2022',
    rollupOptions: {
      input: {
        calendar: 'assets/react/calendar/main.jsx',
      },
    },
  },
});
