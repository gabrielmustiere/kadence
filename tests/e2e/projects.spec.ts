import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// SQLite n'applique pas les clés étrangères ici : on purge les lots avant leurs projets.
test.afterAll(() => {
  execFileSync('symfony', ['console', 'dbal:run-sql', `DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'E2E %')`]);
  execFileSync('symfony', ['console', 'dbal:run-sql', `DELETE FROM project WHERE title LIKE 'E2E %'`]);
});

test.describe.configure({ mode: 'serial' });

const title = `E2E ${Date.now().toString(36)}`;

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

async function fillLot(page: Page, lotTitle: string, days: string, owner: string) {
  await page.fill('[data-test="lot-title"]', lotTitle);
  await page.fill('[data-test="lot-estimate"]', days);
  await page.selectOption('[data-test="lot-owner"]', { label: owner });
}

test('un lead crée un projet et enchaîne trois lots estimés et confiés', async ({ page }) => {
  await login(page, 'lead@example.com');
  await page.click('[data-test="nav-projects"]');
  await page.click('[data-test="project-new"]');
  await page.fill('[data-test="project-title"]', title);
  await page.click('[data-test="project-submit"]');
  await expect(page.locator('[data-test="project-heading"]')).toHaveText(title);
  await expect(page.locator('[data-test="lots-empty"]')).toBeVisible();

  await page.click('[data-test="lot-new"]');
  await fillLot(page, 'Socle', '5', 'Paula Durand');
  await page.click('[data-test="lot-submit-add-another"]');
  await expect(page.locator('[data-test="lot-title"]')).toHaveValue('');
  await fillLot(page, 'Écrans', '8', 'Louis Bernard');
  await page.click('[data-test="lot-submit-add-another"]');
  await expect(page.locator('[data-test="lot-title"]')).toHaveValue('');
  await fillLot(page, 'Recette', '3', 'Louis Bernard');
  await page.click('[data-test="lot-submit"]');

  await expect(page.locator('[data-test="lot-row"]')).toHaveCount(3);
  await expect(page.locator('[data-test="project-total"]')).toContainText('16 j');
});

test('le responsable modifie l\'estimation de sa feuille, et seulement de la sienne', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.click('[data-test="nav-projects"]');
  await page.click('[data-test="filter-mine"]');
  await page.click(`[data-title="${title}"] [data-test="project-link"]`);

  const socle = page.locator('[data-test="lot-row"][data-title="Socle"]');
  await expect(socle).toHaveAttribute('data-mine', 'true');
  await expect(page.locator('[data-test="lot-row"][data-title="Recette"] [data-test="lot-edit"]')).toHaveCount(0);
  await expect(page.locator('[data-test="lot-new"]')).toHaveCount(0);

  await socle.locator('[data-test="lot-edit"]').click();
  await expect(page.locator('[data-test="lot-owner"]')).toHaveCount(0);
  await page.fill('[data-test="lot-estimate"]', '7');
  await page.click('[data-test="lot-submit"]');

  await expect(socle.locator('[data-test="estimate"]')).toHaveText('7 j');
  await expect(page.locator('[data-test="project-total"]')).toContainText('18 j');
});
