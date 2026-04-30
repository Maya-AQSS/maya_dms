import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';
const configDir = path.dirname(fileURLToPath(import.meta.url));
const sharedAuthShim = path.resolve(configDir, 'src/test/shims/shared-auth-react.tsx');
export default defineConfig({
    resolve: {
        alias: {
            '@maya/shared-auth-react': sharedAuthShim,
        },
    },
    test: {
        environment: 'jsdom',
        include: ['src/**/*.test.{ts,tsx}'],
        setupFiles: ['./vitest.setup.ts'],
    },
});
