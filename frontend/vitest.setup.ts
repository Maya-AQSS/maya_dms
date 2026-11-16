import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

// Configuración en ejecución (D26 R6): en el navegador la pone `/config.js`
// (window.__MAYA_CONFIG__, generado a partir de MAYA_PUBLIC_*). En Vitest no hay
// `/config.js` (y en CI tampoco `.env`), y `auth/oidcAdapter.ts` exige las claves KEYCLOAK_* al
// importarse (lo arrastra `api/http.ts`), así que se las damos aquí con valores
// de prueba. El cliente OIDC es el shim de tests: estos valores no salen a la red.
(window as { __MAYA_CONFIG__?: Record<string, string> }).__MAYA_CONFIG__ = {
  KEYCLOAK_URL: 'https://keycloak.vitest.local',
  KEYCLOAK_REALM: 'vitest',
  KEYCLOAK_CLIENT_ID: 'maya-dms',
};

const localStorageMock = {
  _store: {} as Record<string, string>,
  getItem(key: string) { return this._store[key] ?? null; },
  setItem(key: string, value: string) { this._store[key] = value; },
  removeItem(key: string) { delete this._store[key]; },
  clear() { this._store = {}; },
  get length() { return Object.keys(this._store).length; },
  key(index: number) { return Object.keys(this._store)[index] ?? null; },
};

Object.defineProperty(window, 'localStorage', {
  writable: true,
  value: localStorageMock,
});

Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

afterEach(() => {
  cleanup();
});
