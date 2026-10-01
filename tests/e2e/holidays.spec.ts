import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Tout se passe en 2030, loin des semaines saisies par les autres scénarios : la semaine 2030-W24 compte le lundi de
// Pentecôte (10/06) et le jour ajouté ici (12/06). Les ajustements de 2030 sont purgés en SQL avant et après.
test.describe.configure({ mode: 'serial' });

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  sql(`DELETE FROM holiday_adjustment WHERE day BETWEEN '2030-01-01' AND '2030-12-31'`);
}

test.beforeAll(purge);
test.afterAll(purge);

async function login(page: Page, email: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

function line(page: Page, calendar: string, day: string): Locator {
  return page.locator(`[data-test="holiday-calendar"][data-calendar="${calendar}"] [data-test="holiday-line"][data-day="${day}"]`);
}

function header(page: Page, day: string): Locator {
  return page.locator(`[data-test="day-header"][data-day="${day}"]`);
}

test('la direction ajoute un jour férié à un seul calendrier', async ({ page }) => {
  await login(page, 'admin@example.com');
  await page.click('[data-test="nav-holidays"]');
  await expect(page).toHaveURL(/\/jours-feries$/);
  await page.goto('/jours-feries/2030');

  await page.locator('[data-test="holiday-calendar-fr"]').check();
  await page.fill('[data-test="holiday-day"]', '2030-06-12');
  await page.fill('[data-test="holiday-label"]', 'Remplacement E2E');
  await page.click('[data-test="holiday-submit"]');

  await expect(line(page, 'fr', '2030-06-12')).toHaveAttribute('data-status', 'added');
  await expect(line(page, 'fr', '2030-06-12').locator('[data-test="holiday-label"]')).toHaveText('Remplacement E2E');
  await expect(line(page, 'be', '2030-06-12')).toHaveCount(0);
});

test('le jour ajouté est verrouillé et nommé dans la grille, sans barre de saisie', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/saisie/2030-W24');

  await expect(header(page, '2030-06-12').locator('[data-test="day-holiday"]')).toHaveText('Férié · Remplacement E2E');
  await expect(header(page, '2030-06-10').locator('[data-test="day-holiday"]')).toHaveText('Férié · Lundi de Pentecôte');
  await expect(page.locator('[data-test="week-total"]')).toHaveText('0 j / 3 j');

  await page.click('[data-test="add-line-open"]');
  await page.fill('[data-test="add-line-input"]', 'roadmap');
  await page.locator('[data-test="add-line-result"]').first().click();
  await expect(page.locator('[data-test="timesheet-row"]')).toHaveCount(1);
  await expect(page.locator('[data-test="timesheet-cell"][data-day="2030-06-12"]')).toHaveAttribute('data-holiday', 'true');
  await expect(page.locator('[data-test="timesheet-cell"][data-day="2030-06-12"] [data-test="quarter"]')).toHaveCount(0);
  await expect(page.locator('[data-test="timesheet-cell"][data-day="2030-06-11"] [data-test="quarter"]')).toHaveCount(4);
});

test('sur téléphone, un jour férié est signalé à la place de la saisie', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, 'prod@example.com');
  await page.goto('/saisie/2030-W24');

  await expect(page.locator('[data-test="holiday-notice"]')).toContainText('Jour férié · Lundi de Pentecôte');
  const tab = page.locator('[data-test="day-tab"][data-day="2030-06-12"]');
  await expect(tab).toHaveAttribute('data-holiday', 'true');
  await expect(tab).toContainText('Férié');
  await tab.click();
  await expect(tab).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator('[data-test="holiday-notice"]')).toContainText('Jour férié · Remplacement E2E');
});

test('la direction annule l\'ajout, et la colonne redevient ordinaire', async ({ page }) => {
  await login(page, 'admin@example.com');
  await page.goto('/jours-feries/2030');
  await line(page, 'fr', '2030-06-12').locator('[data-test="holiday-cancel"]').click();
  await expect(line(page, 'fr', '2030-06-12')).toHaveCount(0);

  await login(page, 'prod@example.com');
  await page.goto('/saisie/2030-W24');
  await expect(header(page, '2030-06-12')).toHaveAttribute('data-holiday', 'false');
  await expect(page.locator('[data-test="week-total"]')).toHaveText('0 j / 4 j');
});

test('la direction retire un jour férié légal puis annule le retrait', async ({ page }) => {
  await login(page, 'admin@example.com');
  await page.goto('/jours-feries/2030');

  await line(page, 'fr', '2030-11-11').locator('[data-test="holiday-remove"]').click();
  await expect(line(page, 'fr', '2030-11-11')).toHaveAttribute('data-status', 'removed');
  await expect(line(page, 'be', '2030-11-11')).toHaveAttribute('data-status', 'legal');

  await line(page, 'fr', '2030-11-11').locator('[data-test="holiday-cancel"]').click();
  await expect(line(page, 'fr', '2030-11-11')).toHaveAttribute('data-status', 'legal');
});

test('un lead n\'accède pas aux jours fériés', async ({ page }) => {
  await login(page, 'lead@example.com');
  await expect(page.locator('[data-test="nav-holidays"]')).toHaveCount(0);

  const response = await page.goto('/jours-feries');
  expect(response?.status()).toBe(403);
});
