import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';
const localStorageMock = {
    _store: {},
    getItem(key) { return this._store[key] ?? null; },
    setItem(key, value) { this._store[key] = value; },
    removeItem(key) { delete this._store[key]; },
    clear() { this._store = {}; },
    get length() { return Object.keys(this._store).length; },
    key(index) { return Object.keys(this._store)[index] ?? null; },
};
Object.defineProperty(window, 'localStorage', {
    writable: true,
    value: localStorageMock,
});
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation((query) => ({
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
