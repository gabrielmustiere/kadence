import { test, expect } from '@playwright/test';

// Turbo 8 précharge les liens au survol : survoler « Déconnexion » ne doit pas déconnecter.
test('hovering the logout link keeps the user logged in', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="_username"]', 'admin@example.com');
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  await page.click('[data-test="user-menu-toggle"]');
  await page.hover('[data-test="logout-link"]');
  await page.waitForTimeout(500);

  await page.reload();
  await expect(page).not.toHaveURL(/\/login/);
  await expect(page.locator('body')).toContainText('Tableau de bord');
});
