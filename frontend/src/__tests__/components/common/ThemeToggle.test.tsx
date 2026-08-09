import { screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { ThemeToggle } from '@/components/common/ThemeToggle';
import { renderWithProviders } from '@/test/testUtils';

describe('ThemeToggle', () => {
  beforeEach(() => {
    localStorage.removeItem('wpm-theme');
    document.documentElement.classList.remove('light');
  });

  afterEach(() => {
    document.documentElement.classList.remove('light');
    localStorage.removeItem('wpm-theme');
  });

  it('renders a button (toggle) with accessible name', () => {
    renderWithProviders(<ThemeToggle />);
    const button = screen.getByRole('button');
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('aria-label');
  });

  it('shows Sun icon in dark mode (target = light)', () => {
    renderWithProviders(<ThemeToggle />, { theme: 'dark' });
    // Sun icon is rendered as an SVG; we verify via aria-pressed=true (dark active)
    const button = screen.getByRole('button');
    expect(button).toHaveAttribute('aria-pressed', 'true');
  });

  it('switches to light mode on click and updates aria-pressed', async () => {
    const { user } = renderWithProviders(<ThemeToggle />, { theme: 'dark' });
    const button = screen.getByRole('button');
    expect(button).toHaveAttribute('aria-pressed', 'true');

    await user.click(button);

    // After click: light mode active → aria-pressed=false, html gets .light class
    expect(button).toHaveAttribute('aria-pressed', 'false');
    expect(document.documentElement.classList.contains('light')).toBe(true);
  });

  it('switches back to dark on second click', async () => {
    const { user } = renderWithProviders(<ThemeToggle />, { theme: 'dark' });
    const button = screen.getByRole('button');

    await user.click(button); // dark → light
    expect(document.documentElement.classList.contains('light')).toBe(true);

    await user.click(button); // light → dark
    expect(document.documentElement.classList.contains('light')).toBe(false);
    expect(button).toHaveAttribute('aria-pressed', 'true');
  });

  it('has a title attribute (tooltip)', () => {
    renderWithProviders(<ThemeToggle />);
    const button = screen.getByRole('button');
    expect(button).toHaveAttribute('title');
    expect(button.getAttribute('title')).not.toBe('');
  });
});
