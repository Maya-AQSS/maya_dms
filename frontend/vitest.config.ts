import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

const _require = createRequire(import.meta.url);
const configDir = path.dirname(fileURLToPath(import.meta.url));

const sharedAuthShim = path.resolve(configDir, 'src/test/shims/shared-auth-react.tsx');
const infra = path.resolve(configDir, '../../maya_infra/packages');

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
    {
      name: 'shared-packages-resolver',
      resolveId(source: string, importer: string | undefined) {
        if (
          importer?.includes('/maya_infra/packages/') &&
          !source.startsWith('.') &&
          !source.startsWith('/') &&
          !source.startsWith('\0')
        ) {
          const pkgName = source.startsWith('@')
            ? source.split('/').slice(0, 2).join('/')
            : source.split('/')[0];
          if (pkgName === source) {
            try {
              const pkgJsonPath = _require.resolve(`${pkgName}/package.json`, { paths: [configDir] });
              const pkgJson = JSON.parse(readFileSync(pkgJsonPath, 'utf-8')) as Record<string, unknown>;
              const pkgDir = path.dirname(pkgJsonPath);
              const dotExport = (pkgJson.exports as Record<string, unknown> | undefined)?.['.'];
              const esmEntry =
                resolveExportCondition((dotExport as Record<string, unknown> | undefined)?.import) ??
                resolveExportCondition((dotExport as Record<string, unknown> | undefined)?.module) ??
                (typeof pkgJson.module === 'string' ? pkgJson.module : undefined);
              if (esmEntry) return path.join(pkgDir, esmEntry);
            } catch { /* fall through */ }
          }
          try {
            return _require.resolve(source, { paths: [configDir] });
          } catch {
            return null;
          }
        }
      },
    },
  ],
  resolve: {
    alias: {
      '@maya/shared-auth-react': sharedAuthShim,
      '@maya/shared-layout-react': path.resolve(infra, 'maya-shared-layout-react/src/index.ts'),
      '@maya/shared-ui-react': path.resolve(infra, 'maya-shared-ui-react/src/index.ts'),
      '@maya/shared-sidebar-react': path.resolve(infra, 'maya-shared-sidebar-react/src/index.ts'),
      '@maya/shared-i18n-react': path.resolve(infra, 'maya-shared-i18n-react/src/index.ts'),
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
