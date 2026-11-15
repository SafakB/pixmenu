import { defineConfig, loadEnv, type Plugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

/**
 * VITE_API_URL başka bir origin'deyse (ör. https://pixmenu.com) index.html'e preconnect ekler:
 * ilk API isteğinin DNS + TLS süresi sayfa yüklenirken ödenir.
 */
function apiPreconnect(apiUrl: string): Plugin {
  return {
    name: 'pixmenu-api-preconnect',
    transformIndexHtml() {
      let origin: string

      try {
        origin = new URL(apiUrl).origin
      } catch {
        return [] // göreli adres (/api/v1) → aynı origin, gerek yok
      }

      return [{ tag: 'link', attrs: { rel: 'preconnect', href: origin, crossorigin: '' }, injectTo: 'head' }]
    },
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '.', 'VITE_')

  return {
    plugins: [vue(), tailwindcss(), apiPreconnect(env.VITE_API_URL ?? '')],
    server: {
      port: 5173,
      strictPort: true,
    },
    build: {
      outDir: 'dist',
      emptyOutDir: true,
    },
  }
})
