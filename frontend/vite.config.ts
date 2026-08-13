import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/api': {
        target: 'http://php-blog-backend-project.test',
        changeOrigin: true,
      },
      '/admin': {
        target: 'http://php-blog-backend-project.test',
        changeOrigin: true,
      },
    },
  },
})
