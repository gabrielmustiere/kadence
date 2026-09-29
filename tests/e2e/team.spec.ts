import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// L'application n'a pas de suppression de compte (seulement la désactivation) : on purge en base les comptes créés ici.
test.afterAll(() => {
  execFileSync('symfony', ['console', 'dbal:run-sql', `DELETE FROM "user" WHERE email LIKE 'e2e-%@example.com'`]);
});

async function login(page: Page, email: string, password: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', password);
  await page.click('button[type="submit"]');
}

test('la direction inscrit une personne qui choisit son mot de passe à la première connexion', async ({ page }) => {
  const email = `e2e-${Date.now().toString(36)}@example.com`;
  const newPassword = 'Une phrase de passe assez longue 2026';

  await login(page, 'admin@example.com', 'password');
  await page.click('[data-test="nav-team"]');
  await page.click('[data-test="team-new"]');

  await page.fill('[data-test="member-first-name"]', 'Emma');
  await page.fill('[data-test="member-last-name"]', 'Test');
  await page.fill('[data-test="member-email"]', email);
  await expect(page.locator('[data-test="member-role-prod"]')).toBeChecked();
  await page.click('[data-test="team-member-submit"]');

  await expect(page.locator('[data-test="temporary-password-email"]')).toHaveText(email);
  const temporaryPassword = (await page.locator('[data-test="temporary-password"]').textContent())?.trim() ?? '';
  expect(temporaryPassword).toHaveLength(12);

  await page.click('[data-test="temporary-password-done"]');
  await expect(page.locator(`[data-email="${email}"] [data-test="member-status"]`)).toHaveText('Active');

  await page.goto('/logout');
  await login(page, email, temporaryPassword);
  await expect(page).toHaveURL(/\/mon-compte\/mot-de-passe$/);

  await page.fill('[data-test="new-password"]', newPassword);
  await page.fill('[data-test="new-password-confirm"]', newPassword);
  await page.click('[data-test="change-password-submit"]');

  await expect(page.locator('h1')).toContainText('Ma semaine');
  await expect(page.locator('[data-test="nav-team"]')).toHaveCount(0);
});

test('un membre de prod ne voit pas la gestion d\'équipe', async ({ page }) => {
  await login(page, 'prod@example.com', 'password');
  await expect(page.locator('h1')).toContainText('Ma semaine');
  await expect(page.locator('[data-test="nav-team"]')).toHaveCount(0);

  const response = await page.goto('/equipe');
  expect(response?.status()).toBe(403);
});
