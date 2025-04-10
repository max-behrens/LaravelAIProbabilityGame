import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  
  return {
    base: mode === 'production' 
      ? (env.ASSET_URL || env.APP_URL || '/') 
      : '/',
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'resources/js'),
      }
    },
    plugins: [
      laravel({
        input: 'resources/js/app.js',
        refresh: true,
        // Add explicit build options
        buildDirectory: 'public/build',
      }),
      vue({
        template: {
          transformAssetUrls: {
            base: null,
            includeAbsolute: false,
          },
        },
      }),
      react(),
    ],
    build: {
      outDir: 'public/build',
      rollupOptions: {
        output: {
          manualChunks: {
            vendor: ['vue', 'react', 'react-dom'],
          },
        },
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      hmr: {
        host: 'localhost',
      },
    },
  };
});