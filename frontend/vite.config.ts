import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const _require = createRequire(import.meta.url);
const appRoot = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    allowedHosts: true,
    hmr: { clientPort: 443, protocol: 'wss' },
    watch: {
      usePolling: true,
    },
  },
  optimizeDeps: {
    include: [
      'keycloak-js',
      'axios',
      'html-parse-stringify',
      'void-elements',
      'use-sync-external-store',
      'use-sync-external-store/shim',
    ],
    exclude: [
      '@ceedcv-maya/shared-auth-react',
      '@ceedcv-maya/shared-dashboard-react',
      '@ceedcv-maya/shared-i18n-react',
      '@ceedcv-maya/shared-layout-react',
      '@ceedcv-maya/shared-profile-react',
      '@ceedcv-maya/shared-sidebar-react',
      '@ceedcv-maya/shared-ui-react',
    ],
  },
  resolve: {
    dedupe: ['react', 'react-dom', 'react-router-dom'],
    alias: {
      '@tanstack/react-query': _require.resolve('@tanstack/react-query', { paths: [appRoot] }),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['src/**/*.test.{ts,tsx}'],
    setupFiles: ['./vitest.setup.ts'],
    server: {
      deps: {
        inline: [/@maya\/shared/],
      },
    },
  },
});
