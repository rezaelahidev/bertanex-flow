import react from '@vitejs/plugin-react';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => {
  const isProduction = mode === 'production';

  return {
    plugins: [react({ jsxRuntime: 'classic' })],
    resolve: {
      alias: {
        react: path.resolve(__dirname, 'shims/react-shim.ts'),
        'react-dom/client': path.resolve(__dirname, 'shims/react-dom-client.ts'),
        'react-dom': path.resolve(__dirname, 'shims/react-shim.ts'),
      },
    },
    root: path.resolve(__dirname),
    define: {
      'process.env.NODE_ENV': JSON.stringify(isProduction ? 'production' : 'development'),
    },
    build: {
      outDir: path.resolve(__dirname, '../assets/admin'),
      emptyOutDir: false,
      minify: isProduction,
      sourcemap: !isProduction,
      target: 'es2020',
      rollupOptions: {
        input: path.resolve(__dirname, 'main.tsx'),
        output: {
          entryFileNames: 'rules-visual-editor.bundle.js',
          format: 'iife',
          inlineDynamicImports: true,
        },
      },
    },
  };
});
