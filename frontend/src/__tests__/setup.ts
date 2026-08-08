import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// Set window.__ENV__ before any module imports read it
(window as unknown as { __ENV__: Record<string, string> }).__ENV__ = {
  VITE_API_URL: 'http://localhost:8080/api',
};

// Auto-cleanup RTL after each test
afterEach(() => {
  cleanup();
});
