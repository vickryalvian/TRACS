import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

const fromFrontendRoot = (path) => fileURLToPath(new URL(path, import.meta.url));

export default defineConfig({
  plugins: [react(), tailwindcss()],
  publicDir: false,
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    sourcemap: true,
    target: 'es2022',
    rollupOptions: {
      input: {
        sandbox: fromFrontendRoot('./src/modules/_sandbox/main.jsx'),
        shiftAssignment: fromFrontendRoot('./src/modules/shift-assignment/main.jsx'),
      },
    },
  },
});
