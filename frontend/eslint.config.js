import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import reactPlugin from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import importPlugin from 'eslint-plugin-import';

export default tseslint.config(
  // Global ignores
  {
    ignores: ['dist', 'node_modules', '*.config.ts', '*.config.js', '*.cjs'],
  },

  // Base JS recommended
  js.configs.recommended,

  // TypeScript strict + stylistic
  ...tseslint.configs.strict,
  ...tseslint.configs.stylistic,

  // React recommended + jsx-runtime
  {
    ...reactPlugin.configs.flat.recommended,
    settings: {
      react: { version: 'detect' },
    },
  },
  {
    ...reactPlugin.configs.flat['jsx-runtime'],
  },

  // React hooks
  {
    plugins: {
      'react-hooks': reactHooks,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
    },
  },

  // React refresh
  {
    plugins: {
      'react-refresh': reactRefresh,
    },
    rules: {
      'react-refresh/only-export-components': [
        'warn',
        { allowConstantExport: true },
      ],
    },
  },

  // Import plugin
  {
    plugins: {
      import: importPlugin,
    },
    settings: {
      'import/resolver': {
        typescript: { project: './tsconfig.json' },
      },
    },
    rules: {
      'import/order': ['error', { 'newlines-between': 'always' }],
      'import/no-extraneous-dependencies': [
        'error',
        { devDependencies: true },
      ],
    },
  },

  // Global rules
  {
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        navigator: 'readonly',
        localStorage: 'readonly',
        sessionStorage: 'readonly',
        fetch: 'readonly',
        URL: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        queueMicrotask: 'readonly',
        AbortController: 'readonly',
        HTMLElement: 'readonly',
        HTMLInputElement: 'readonly',
        HTMLButtonElement: 'readonly',
        HTMLDivElement: 'readonly',
        HTMLSpanElement: 'readonly',
        HTMLAnchorElement: 'readonly',
        HTMLFormElement: 'readonly',
        HTMLSelectElement: 'readonly',
        HTMLTextAreaElement: 'readonly',
        HTMLLabelElement: 'readonly',
        HTMLDialogElement: 'readonly',
        HTMLTableElement: 'readonly',
        HTMLUListElement: 'readonly',
        HTMLOListElement: 'readonly',
        HTMLLIElement: 'readonly',
        HTMLParagraphElement: 'readonly',
        HTMLHeadingElement: 'readonly',
        HTMLCanvasElement: 'readonly',
        HTMLImageElement: 'readonly',
        Event: 'readonly',
        MouseEvent: 'readonly',
        KeyboardEvent: 'readonly',
        FocusEvent: 'readonly',
        SubmitEvent: 'readonly',
        CustomEvent: 'readonly',
        FormData: 'readonly',
        IntersectionObserver: 'readonly',
        MutationObserver: 'readonly',
        ResizeObserver: 'readonly',
        requestAnimationFrame: 'readonly',
        cancelAnimationFrame: 'readonly',
        matchMedia: 'readonly',
        getComputedStyle: 'readonly',
        scrollTo: 'readonly',
        alert: 'readonly',
        confirm: 'readonly',
        crypto: 'readonly',
        btoa: 'readonly',
        atob: 'readonly',
        location: 'readonly',
        history: 'readonly',
        performance: 'readonly',
        structuredClone: 'readonly',
      },
    },
    rules: {
      'react/react-in-jsx-scope': 'off',
      'react/prop-types': 'off',
      '@typescript-eslint/no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_' },
      ],
      '@typescript-eslint/no-misused-promises': 'off',
      '@typescript-eslint/no-floating-promises': 'off',
    },
  },

  // Override for shadcn/ui components
  {
    files: ['src/components/ui/**/*.tsx'],
    rules: {
      'react/no-unknown-property': 'off',
      '@typescript-eslint/consistent-type-definitions': 'off',
      'react-refresh/only-export-components': 'off',
    },
  },
);
