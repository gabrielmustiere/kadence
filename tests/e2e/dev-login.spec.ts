import { test, expect } from '@playwright/test';

// Le serveur E2E tourne en environnement dev : le raccourci de connexion y est actif.
test('le raccourci de connexion préremplit les identifiants du compte choisi', async ({ page }) => {
  await page.goto('/login');

  await expect(page.locator('input[name="_username"]')).toHaveValue('admin@example.com');
  await expect(page.locator('input[name="_password"]')).toHaveValue('password');

  await page.selectOption('[data-test="dev-login-user"]', 'lead@example.com');
  await expect(page.locator('input[name="_username"]')).toHaveValue('lead@example.com');
  await expect(page.locator('input[name="_password"]')).toHaveValue('password');

  await page.click('button[type="submit"]');
  await expect(page.locator('[data-test="user-menu-name"]')).toHaveText('Louis Bernard');
});
