import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import path from 'node:path';

const defaultSharedAuthRoot = fileURLToPath(
  new URL('../../maya_infra/packages/maya-shared-auth-react', import.meta.url),
);
const defaultSharedLayoutRoot = fileURLToPath(
  new URL('../../maya_infra/packages/maya-shared-layout-react', import.meta.url),
);
const defaultSharedSidebarRoot = fileURLToPath(
  new URL('../../maya_infra/packages/maya-shared-sidebar-react', import.meta.url),
);
const sharedI18nRoot = fileURLToPath(
  new URL('../../maya_infra/packages/maya-shared-i18n-react', import.meta.url),
);

const sharedAuthRoot = process.env.SHARED_AUTH_PACKAGE_ROOT
  ? path.resolve(process.env.SHARED_AUTH_PACKAGE_ROOT)
  : defaultSharedAuthRoot;
const sharedLayoutRoot = defaultSharedLayoutRoot;
const sharedSidebarRoot = defaultSharedSidebarRoot;

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    allowedHosts: true,
    fs: {
      allow: ['..', sharedAuthRoot, sharedLayoutRoot, sharedSidebarRoot, sharedI18nRoot],
    },
    watch: {
      usePolling: true,
    }
  },
  optimizeDeps: {
    include: ['keycloak-js', 'axios', '@blocknote/core', '@blocknote/react', '@blocknote/ariakit'],
    exclude: ['@maya/shared-auth-react', '@maya/shared-i18n-react', '@maya/shared-layout-react', '@maya/shared-sidebar-react'],
  },
  resolve: {
    // When Vite processes source files from /maya_infra/packages/..., bare imports
    // (e.g. "i18next") are resolved against the app's node_modules, not the shared
    // package directory which has no local node_modules inside the container.
    modules: [path.resolve('node_modules'), 'node_modules'],
    alias: {
      '@maya/shared-auth-react': path.join(sharedAuthRoot, 'src/index.ts'),
      '@maya/shared-i18n-react': path.join(sharedI18nRoot, 'src/index.ts'),
      '@maya/shared-layout-react': path.join(sharedLayoutRoot, 'src/index.ts'),
      '@maya/shared-sidebar-react': path.join(sharedSidebarRoot, 'src/index.ts'),
    },
  },
});
