import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  server: {
    port: 5173,
    // Proxy API calls to Laravel so the browser sees a single origin in development.
    proxy: {
      '/api': { target: process.env.VITE_API_PROXY_TARGET ?? 'http://127.0.0.1:8000', changeOrigin: true },
      // Sanctum's CSRF cookie endpoint
      '/sanctum': { target: process.env.VITE_API_PROXY_TARGET ?? 'http://127.0.0.1:8000', changeOrigin: true },
    },
  },
})
