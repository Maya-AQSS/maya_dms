import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { mergeConfig } from 'vite';
import { defineConfig } from 'vitest/config';
import viteConfig from './vite.config';

/** Vitest puede cargar vite.config antes de que exista `process.env.VITEST`; el shim debe aplicarse aquí. */
const dir = path.dirname(fileURLToPath(import.meta.url));
const sharedAuthShim = path.resolve(dir, 'src/test/shims/shared-auth-react.tsx');

export default mergeConfig(
  viteConfig,
  defineConfig({
    resolve: {
      alias: {
        '@maya/shared-auth-react': sharedAuthShim,
      },
    },
  }),
);
