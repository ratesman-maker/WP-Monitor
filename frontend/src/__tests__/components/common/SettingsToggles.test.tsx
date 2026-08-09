import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { SettingsToggles } from '@/components/common/SettingsToggles';
import { renderWithProviders } from '@/test/testUtils';

describe('SettingsToggles', () => {
  it('renders both toggle buttons', () => {
    renderWithProviders(<SettingsToggles />);
    const buttons = screen.getAllByRole('button');
    // LanguageToggle + ThemeToggle = 2 buttons
    expect(buttons).toHaveLength(2);
  });

  it('contains a separator between the toggles', () => {
    const { container } = renderWithProviders(<SettingsToggles />);
    // Radix Separator with decorative=true (default) renders as a div with
    // data-orientation="vertical" (no role attribute when decorative).
    const separator = container.querySelector('[data-orientation="vertical"]');
    expect(separator).toBeInTheDocument();
  });

  it('both toggles are clickable (no errors)', async () => {
    const { user } = renderWithProviders(<SettingsToggles />);
    const buttons = screen.getAllByRole('button');
    expect(buttons).toHaveLength(2);
    const [languageToggle, themeToggle] = buttons;
    if (!languageToggle || !themeToggle) {
      throw new Error('Expected 2 toggle buttons');
    }
    // Click language toggle
    await user.click(languageToggle);
    // Click theme toggle
    await user.click(themeToggle);
    // No throw = pass
    expect(true).toBe(true);
  });
});
