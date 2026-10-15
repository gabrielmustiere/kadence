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

  await login(page, 'thomas.cerqueus@anyti.me', 'password');
  await page.click('[data-test="nav-people"]');
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
  await expect(page.locator(`[data-email="${email}"]`)).toBeVisible();
  await expect(page.locator(`[data-email="${email}"] [data-test="member-status"]`)).toHaveCount(0);

  await page.goto('/logout');
  await login(page, email, temporaryPassword);
  await expect(page).toHaveURL(/\/mon-compte\/mot-de-passe$/);

  await page.fill('[data-test="new-password"]', newPassword);
  await page.fill('[data-test="new-password-confirm"]', newPassword);
  await page.click('[data-test="change-password-submit"]');

  await expect(page.locator('h1')).toContainText('Ma semaine');
  await expect(page.locator('[data-test="nav-admin"]')).toHaveCount(0);
});

test('un membre de prod lit la liste des personnes sans pouvoir la gérer', async ({ page }) => {
  await login(page, 'antoine.leroy@anyti.me', 'password');
  await expect(page.locator('h1')).toContainText('Ma semaine');
  await page.click('[data-test="nav-people"]');

  await expect(page).toHaveURL(/\/personnes$/);
  await expect(page.locator('[data-test="team-member-row"]').first()).toBeVisible();
  await expect(page.locator('[data-test="team-new"], [data-test="team-filter"], [data-test="member-edit"], [data-test="member-deactivate"], [data-test="member-status"]')).toHaveCount(0);

  const response = await page.goto('/personnes/nouveau');
  expect(response?.status()).toBe(403);
});

test('la direction filtre l\'équipe dès qu\'elle choisit un tag, et désactive puis réactive sans perdre ses filtres', async ({ page }) => {
  await login(page, 'thomas.cerqueus@anyti.me', 'password');
  await expect(page).not.toHaveURL(/\/login/);
  await page.goto('/personnes');
  const rows = page.locator('[data-test="team-member-row"]');
  const all = await rows.count();

  await page.selectOption('[data-test="team-filter-competence"]', { label: 'Symfony' });
  await expect(page).toHaveURL(/[?&]competence=\d+/);
  await expect(rows).toHaveCount(4);
  expect(all).toBeGreaterThan(4);

  const maxime = page.locator('[data-email="maxime.roux@anyti.me"]');
  await maxime.locator('[data-test="member-deactivate"]').click();
  await maxime.locator('[data-test="member-deactivate-confirm"]').click();
  await expect(page.locator('[role="alert"]')).toContainText('est désactivé');
  await expect(maxime.locator('[data-test="member-status"]')).toHaveText('Désactivée');
  await expect(page).toHaveURL(/[?&]competence=\d+/);
  await expect(rows).toHaveCount(4);

  await maxime.locator('[data-test="member-reactivate"]').click();
  await expect(maxime.locator('[data-test="member-status"]')).toHaveCount(0);

  await page.click('[data-test="team-filter-reset"]');
  await expect(rows).toHaveCount(all);
  await expect(page).not.toHaveURL(/competence=/);
});
