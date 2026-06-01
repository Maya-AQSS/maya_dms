import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const _require = createRequire(import.meta.url);
const appRoot = fileURLToPath(new URL('.', import.meta.url));

// Dev override: si MAYA_DEV_OVERRIDE_DIR está set, los paquetes @ceedcv-maya/shared-*
// se resuelven desde el monorepo en disco en lugar de node_modules. Vite los carga
// vía resolve.alias (no requiere bind mount sobre node_modules — funciona limpio).
const _sharedOverrideDir = process.env.MAYA_DEV_OVERRIDE_DIR
const _sharedPackageAliases: Record<string, string> = _sharedOverrideDir
  ? Object.fromEntries(
      [
        'shared-auth-react', 'shared-dashboard-react', 'shared-editor-react',
        'shared-hooks-react', 'shared-i18n-react', 'shared-layout-react',
        'shared-profile-react', 'shared-realtime-react', 'shared-sidebar-react',
        'shared-styles', 'shared-ui-react',
      ].map((pkg) => [`@ceedcv-maya/${pkg}`, path.resolve(_sharedOverrideDir!, pkg, 'src')])
    )
  : {}

// When shared packages are resolved from `MAYA_DEV_OVERRIDE_DIR` (outside the
// consumer's node_modules), their transitive `import` calls (eg. `@tiptap/core`
// from inside `shared-editor-react`) start their lookup from that location and
// never reach the consumer's `node_modules`. Alias each consumer-installed
// dependency to the resolved path under `/app/node_modules` so the imports
// land in the same module instance the consumer already loaded.
// Resolve to the package DIRECTORY (not the entry file) so that subpath imports
// like `@tiptap/core/jsx-runtime` resolve via the package's `exports` map.
function _resolvePkgDir(pkg: string): string | null {
  try {
    const entry = _require.resolve(pkg, { paths: [appRoot] })
    const marker = `/node_modules/${pkg}/`
    const idx = entry.indexOf(marker)
    if (idx < 0) return null
    return entry.substring(0, idx + marker.length - 1)
  } catch { return null }
}

// Symlink the consumer's `node_modules` into every shared package directory so
// that transitive `import` calls from inside the shared sources walk up to the
// consumer's installs naturally. Done once at config eval (idempotent).
import { existsSync, symlinkSync, mkdirSync } from 'node:fs'
function _ensureSharedNodeModulesSymlink(): void {
  if (!_sharedOverrideDir) return
  const consumerNodeModules = path.join(appRoot, 'node_modules')
  const sharedPackages = [
    'shared-auth-react', 'shared-dashboard-react', 'shared-editor-react',
    'shared-hooks-react', 'shared-i18n-react', 'shared-layout-react',
    'shared-profile-react', 'shared-realtime-react', 'shared-sidebar-react',
    'shared-styles', 'shared-ui-react',
  ]
  for (const pkg of sharedPackages) {
    const pkgDir = path.join(_sharedOverrideDir, pkg)
    if (!existsSync(pkgDir)) continue
    const linkPath = path.join(pkgDir, 'node_modules')
    if (existsSync(linkPath)) continue
    try {
      mkdirSync(path.dirname(linkPath), { recursive: true })
      symlinkSync(consumerNodeModules, linkPath, 'dir')
    } catch { /* readonly fs or already-linked from another consumer — ignore */ }
  }
}
_ensureSharedNodeModulesSymlink()


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
      '@ceedcv-maya/shared-editor-react',
      '@ceedcv-maya/shared-hooks-react',
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
      ..._sharedPackageAliases,
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
