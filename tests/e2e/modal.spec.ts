import { test, expect } from '@playwright/test';

// Le contrôleur Stimulus du modal ne doit pas entrer en conflit avec le JS de Flowbite (attribut data-modal-target).
test('modal opens and closes without JS errors', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('console', (message) => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource')) {
      errors.push(message.text());
    }
  });

  await page.goto('/login');
  await page.fill('input[name="_username"]', 'admin@example.com');
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  await page.goto('/equipe');
  const row = page.locator('[data-email="lead@example.com"]');
  const dialog = row.locator('dialog', { has: page.locator('[data-test="member-deactivate-cancel"]') });
  await row.locator('[data-test="member-deactivate"]').click();
  await expect(dialog).toBeVisible();
  await row.locator('[data-test="member-deactivate-cancel"]').click();
  await expect(dialog).toBeHidden();

  expect(errors).toEqual([]);
});
