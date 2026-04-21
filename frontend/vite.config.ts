import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import path from 'node:path';

const defaultSharedAuthRoot = fileURLToPath(
  new URL('../../maya_infra/packages/maya-shared-auth-react', import.meta.url),
);

const sharedAuthRoot = process.env.SHARED_AUTH_PACKAGE_ROOT
  ? path.resolve(process.env.SHARED_AUTH_PACKAGE_ROOT)
  : defaultSharedAuthRoot;

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    allowedHosts: true,
    fs: {
      allow: ['..', sharedAuthRoot],
    }, 
    watch: {
      usePolling: true,
    }
  },
  optimizeDeps: {
    include: ['keycloak-js', 'axios', '@blocknote/core', '@blocknote/react', '@blocknote/ariakit'],
    exclude: ['@maya/shared-auth-react'],
  },
  resolve: {
    alias: {
      '@maya/shared-auth-react': path.join(sharedAuthRoot, 'src/index.ts'),
    },
  },
});
