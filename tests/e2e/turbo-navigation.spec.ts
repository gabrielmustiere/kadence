import { test, expect } from '@playwright/test';

// Flowbite doit rester fonctionnel après une navigation Turbo (ici la redirection qui suit la connexion).
test('Flowbite dropdown works after a Turbo navigation', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="_username"]', 'admin@example.com');
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  await page.click('[data-test="user-menu-toggle"]');
  await expect(page.locator('[data-test="logout-link"]')).toBeVisible();
});
