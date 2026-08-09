import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// Set window.__ENV__ before any module imports read it
(window as unknown as { __ENV__: Record<string, string> }).__ENV__ = {
  VITE_API_URL: 'http://localhost:8080/api',
};

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
