import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
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
})
