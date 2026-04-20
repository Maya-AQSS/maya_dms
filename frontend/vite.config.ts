import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    allowedHosts: true,
    fs: {
      allow: ['..', '/maya_infra/packages/maya-shared-auth-react']
    },
    watch: {
      usePolling: true,
    }
  },
  optimizeDeps: {
    include: ['keycloak-js', 'axios', '@blocknote/core', '@blocknote/react'],
    exclude: ['@maya/shared-auth-react']
  },
  resolve: {
    alias: {
      '@maya/shared-auth-react': fileURLToPath(
        new URL('../../maya_infra/packages/maya-shared-auth-react/src/index.ts', import.meta.url)
      )
    }
  }
})
