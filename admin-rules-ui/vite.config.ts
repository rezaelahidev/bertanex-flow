import react from '@vitejs/plugin-react';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [react()],
  root: path.resolve(__dirname),
  build: {
    outDir: path.resolve(__dirname, '../assets/admin'),
    emptyOutDir: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'main.tsx'),
      output: {
        entryFileNames: 'rules-visual-editor.bundle.js',
        format: 'iife',
        inlineDynamicImports: true,
      },
    },
  },
});
