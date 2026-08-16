/// <reference types="vitest/config" />
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => ({
  plugins: [
    vue({
      template: {
        // Under vitest the absolute public-path assets resolve to a broken
        // file:// URL, so skip asset-url rewriting there (dev/build are untouched).
        transformAssetUrls: mode === 'test' ? false : undefined,
      },
    }),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      '@': `${import.meta.dirname}/src`,
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://php-blog-backend-project.test',
        changeOrigin: true,
      },
      '/admin/login.php': {
        target: 'http://php-blog-backend-project.test',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
  },
}))
