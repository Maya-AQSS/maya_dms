import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { mergeConfig } from 'vite';
import { defineConfig } from 'vitest/config';
import viteConfig from './vite.config';

/** Vitest puede cargar vite.config antes de que exista `process.env.VITEST`; el shim debe aplicarse aquí. */
const dir = path.dirname(fileURLToPath(import.meta.url));
const sharedAuthShim = path.resolve(dir, 'src/test/shims/shared-auth-react.tsx');
const sharedProfileShim = path.resolve(dir, 'src/test/shims/shared-profile-react.tsx');

export default mergeConfig(
  viteConfig,
  defineConfig({
    resolve: {
      alias: {
        '@maya/shared-auth-react': sharedAuthShim,
        '@maya/shared-profile-react': sharedProfileShim,
      },
    },
    test: {
      // DMS frontend tiene grafos de imports muy grandes (DocumentWizard, etc.)
      // que en vitest 4 con worker_threads (default) provocan OOM. Forks + un
      // único worker serializan la suite y mantienen el heap estable.
      // Vitest 4: las opciones de pool se aplanaron — `maxWorkers` y
      // `minWorkers` son top-level, ya no `poolOptions.forks.singleFork`.
      pool: 'forks',
      maxWorkers: 1,
      minWorkers: 1,
      coverage: {
        provider: 'v8',
        reporter: ['text', 'json-summary', 'html'],
        include: ['src/**/*.{ts,tsx}'],
        exclude: [
          'src/**/*.test.{ts,tsx}',
          'src/**/*.d.ts',
          'src/test/**',
          'src/main.tsx',
          'src/vite-env.d.ts',
        ],
      },
    },
  }),
);
