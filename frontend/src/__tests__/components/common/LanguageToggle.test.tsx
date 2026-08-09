import { screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { LanguageToggle } from '@/components/common/LanguageToggle';
import { renderWithProviders } from '@/test/testUtils';
import i18n, { LANGUAGE_STORAGE_KEY } from '@/i18n';

describe('LanguageToggle', () => {
  beforeEach(() => {
    localStorage.removeItem(LANGUAGE_STORAGE_KEY);
    void i18n.changeLanguage('en');
  });

  afterEach(() => {
    localStorage.removeItem(LANGUAGE_STORAGE_KEY);
    void i18n.changeLanguage('en');
  });

  it('renders a button (toggle) with accessible name', () => {
    renderWithProviders(<LanguageToggle />);
    const button = screen.getByRole('button');
    expect(button).toBeInTheDocument();
    expect(button).toHaveAttribute('aria-label');
  });

  it('shows target language code "CS" when in EN mode', () => {
    renderWithProviders(<LanguageToggle />, { locale: 'en' });
    const button = screen.getByRole('button');
    // EN active → target is CS → button shows "CS"
    expect(button.textContent).toContain('CS');
    expect(button).toHaveAttribute('aria-pressed', 'false');
  });

  it('switches to CS on click and updates aria-pressed + localStorage', async () => {
    const { user } = renderWithProviders(<LanguageToggle />, { locale: 'en' });
    const button = screen.getByRole('button');

    await user.click(button);

    // After click: CS active → aria-pressed=true, localStorage updated, i18n.language=cs
    expect(button).toHaveAttribute('aria-pressed', 'true');
    expect(i18n.language).toBe('cs');
    expect(localStorage.getItem(LANGUAGE_STORAGE_KEY)).toBe('cs');
    // CS active → target is EN → button shows "EN"
    expect(button.textContent).toContain('EN');
  });

  it('switches back to EN on second click', async () => {
    const { user } = renderWithProviders(<LanguageToggle />, { locale: 'en' });
    const button = screen.getByRole('button');

    await user.click(button); // EN → CS
    expect(i18n.language).toBe('cs');

    await user.click(button); // CS → EN
    expect(i18n.language).toBe('en');
    expect(localStorage.getItem(LANGUAGE_STORAGE_KEY)).toBe('en');
    expect(button).toHaveAttribute('aria-pressed', 'false');
  });

  it('has a title attribute (tooltip)', () => {
    renderWithProviders(<LanguageToggle />);
    const button = screen.getByRole('button');
    expect(button).toHaveAttribute('title');
    expect(button.getAttribute('title')).not.toBe('');
  });
});
