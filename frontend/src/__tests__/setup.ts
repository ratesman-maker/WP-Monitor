import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// Set window.__ENV__ before any module imports read it
(window as unknown as { __ENV__: Record<string, string> }).__ENV__ = {
  VITE_API_URL: 'http://localhost:8080/api',
};

// Node 26+ has a native experimental localStorage global that is undefined
// without --localstorage-file flag. It shadows jsdom's window.localStorage.
// Provide a manual localStorage mock if jsdom's isn't available.
const localStorageMock = (() => {
  let store: Record<string, string> = {};
  return {
    getItem: (key: string) => store[key] ?? null,
    setItem: (key: string, value: string) => { store[key] = String(value); },
    removeItem: (key: string) => { store = Object.fromEntries(Object.entries(store).filter(([k]) => k !== key)); },
    clear: () => { store = {}; },
    key: (index: number) => Object.keys(store)[index] ?? null,
    get length() { return Object.keys(store).length; },
  };
})();

const sessionStorageMock = (() => {
  let store: Record<string, string> = {};
  return {
    getItem: (key: string) => store[key] ?? null,
    setItem: (key: string, value: string) => { store[key] = String(value); },
    removeItem: (key: string) => { store = Object.fromEntries(Object.entries(store).filter(([k]) => k !== key)); },
    clear: () => { store = {}; },
    key: (index: number) => Object.keys(store)[index] ?? null,
    get length() { return Object.keys(store).length; },
  };
})();

// Override globalThis localStorage/sessionStorage (Node 26 native is undefined)
try {
  Object.defineProperty(globalThis, 'localStorage', {
    value: window.localStorage ?? localStorageMock,
    writable: true,
    configurable: true,
  });
  Object.defineProperty(globalThis, 'sessionStorage', {
    value: window.sessionStorage ?? sessionStorageMock,
    writable: true,
    configurable: true,
  });
} catch {
  // Fallback: direct assignment
  (globalThis as Record<string, unknown>).localStorage = localStorageMock;
  (globalThis as Record<string, unknown>).sessionStorage = sessionStorageMock;
}

// Mock window.matchMedia — required by next-themes (and other libraries that
// check prefers-color-scheme). jsdom does not implement it natively.
if (!window.matchMedia) {
  window.matchMedia = (query: string): MediaQueryList => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => undefined,
    removeListener: () => undefined,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    dispatchEvent: () => false,
  });
}

// Auto-cleanup RTL after each test
afterEach(() => {
  cleanup();
});
