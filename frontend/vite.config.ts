import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import path from 'node:path';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const _require = createRequire(import.meta.url);

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

// The app root dir — used to resolve bare imports from shared package sources.
const appRoot = fileURLToPath(new URL('.', import.meta.url));
const sharedAuthSource = path.resolve(
  appRoot,
  '../../maya_infra/packages/maya-shared-auth-react/src/index.ts',
);

// Recursively unwrap nested package export condition objects until a string path is found.
// e.g. exports['.'].import can be { types: '...', default: './dist/esm/foo.js' }
function resolveExportCondition(entry: unknown): string | undefined {
  if (typeof entry === 'string') return entry;
  if (entry !== null && typeof entry === 'object') {
    const obj = entry as Record<string, unknown>;
    return resolveExportCondition(obj.default) ?? resolveExportCondition(obj.import);
  }
  return undefined;
}

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    // resolve.modules does NOT apply to the dev-server transform pipeline.
    // This plugin intercepts bare imports (e.g. "i18next") that originate from
    // /maya_infra/packages/... source files and resolves them from the app's
    // own node_modules, since those packages have no local node_modules inside
    // the container.
    {
      name: 'shared-packages-resolver',
      resolveId(source: string, importer: string | undefined) {
        if (
          importer?.includes('/maya_infra/packages/') &&
          !source.startsWith('.') &&
          !source.startsWith('/') &&
          !source.startsWith('\0')
        ) {
          // Extract bare package name (handles scoped packages like @scope/pkg)
          const pkgName = source.startsWith('@')
            ? source.split('/').slice(0, 2).join('/')
            : source.split('/')[0];
          // Try to resolve ESM entry from package.json exports map
          if (pkgName === source) {
            try {
              const pkgJsonPath = _require.resolve(`${pkgName}/package.json`, { paths: [appRoot] });
              const pkgJson = JSON.parse(readFileSync(pkgJsonPath, 'utf-8')) as Record<string, unknown>;
              const pkgDir = path.dirname(pkgJsonPath);
              const dotExport = (pkgJson.exports as Record<string, unknown> | undefined)?.['.'];
              const esmEntry =
                resolveExportCondition((dotExport as Record<string, unknown> | undefined)?.import) ??
                resolveExportCondition((dotExport as Record<string, unknown> | undefined)?.module) ??
                (typeof pkgJson.module === 'string' ? pkgJson.module : undefined);
              if (esmEntry) {
                return path.join(pkgDir, esmEntry);
              }
            } catch {
              // package.json not accessible; fall through to direct resolve
            }
          }
          try {
            return _require.resolve(source, { paths: [appRoot] });
          } catch {
            return null;
          }
        }
      },
    },
  ],
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
    include: ['keycloak-js', 'axios', '@blocknote/core', '@blocknote/react', '@blocknote/ariakit', 'html-parse-stringify', 'void-elements', 'use-sync-external-store', 'use-sync-external-store/shim'],
    exclude: ['@maya/shared-auth-react', '@maya/shared-i18n-react', '@maya/shared-layout-react', '@maya/shared-sidebar-react'],
  },
  resolve: {
    dedupe: ['react', 'react-dom', 'react-router-dom'],
    alias: {
      '@maya/shared-auth-react': sharedAuthSource,
      '@maya/shared-i18n-react': path.resolve(appRoot, '../../maya_infra/packages/maya-shared-i18n-react/src/index.ts'),
      '@maya/shared-layout-react': path.resolve(appRoot, '../../maya_infra/packages/maya-shared-layout-react/src/index.ts'),
      '@maya/shared-sidebar-react': path.resolve(appRoot, '../../maya_infra/packages/maya-shared-sidebar-react/src/index.ts'),
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
