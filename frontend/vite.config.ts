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
// walk up. Only `packages/js` is bind-mounted into the container, so any
// per-package `node_modules` left there by a host pnpm install dangles and
// can't satisfy them. Point the first walk-up stop outside the bind mount —
// `<override>/../../node_modules` (eg. `/maya_platform/node_modules`,
// container-local) — at the consumer's installs. Re-created on every config
// eval, so it survives container recreation.
import { lstatSync, readlinkSync, rmSync, symlinkSync } from 'node:fs'
function _ensureSharedNodeModulesSymlink(): void {
  if (!_sharedOverrideDir) return
  const consumerNodeModules = path.join(appRoot, 'node_modules')
  const linkPath = path.resolve(_sharedOverrideDir, '..', '..', 'node_modules')
  try {
    const current = lstatSync(linkPath, { throwIfNoEntry: false })
    if (current && !current.isSymbolicLink()) return // real install (host run) — leave it
    if (current?.isSymbolicLink()) {
      if (readlinkSync(linkPath) === consumerNodeModules) return
      rmSync(linkPath)
    }
    symlinkSync(consumerNodeModules, linkPath, 'dir')
  } catch (err) {
    console.warn(`[vite] Failed to symlink ${linkPath}:`, (err as Error).message)
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
