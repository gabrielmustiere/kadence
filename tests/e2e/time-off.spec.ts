import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Tout se passe en 2030, loin des semaines saisies par les autres scénarios. Dans la démo, le manager de Paula
// (prod@example.com) est Louis (lead@example.com). Les demandes de 2030 sont purgées en SQL avant et après.
test.describe.configure({ mode: 'serial' });

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  sql(`DELETE FROM time_off_request WHERE start_day BETWEEN '2030-01-01' AND '2030-12-31'`);
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

function inboxItem(page: Page): Locator {
  return page.locator('[data-test="time-off-inbox-item"]').filter({ hasText: 'Paula Durand' }).filter({ hasText: '05/03/2030' });
}

test('la prod demande un jour de congé et le voit réservé dans sa semaine', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.click('[data-test="nav-time-off"]');
  await expect(page).toHaveURL(/\/conges$/);

  await page.fill('[data-test="time-off-start"]', '2030-03-05');
  await page.fill('[data-test="time-off-end"]', '2030-03-05');
  await page.locator('[data-test="time-off-end"]').blur();
  await expect(page.locator('[data-test="time-off-preview-days"]')).toHaveText('1 j');

  await page.click('[data-test="time-off-submit"]');
  await expect(page).toHaveURL(/\/conges$/);
  const row = page.locator('[data-test="time-off-request-row"]').filter({ hasText: '05/03/2030' });
  await expect(row.locator('[data-test="time-off-request-status"]')).toHaveText('En attente');

  await page.goto('/saisie/2030-W10');
  const header = page.locator('[data-test="day-header"][data-day="2030-03-05"]');
  await expect(header).toHaveAttribute('data-time-off', 'whole');
  await expect(header.locator('[data-test="day-time-off"]')).toHaveText('Congé · CP · en attente');
  await expect(page.locator('[data-test="week-total"]')).toHaveText('0 j / 4 j');
});

test('le manager voit la demande avec le solde et la valide', async ({ page }) => {
  await login(page, 'lead@example.com');
  await page.goto('/conges');
  await page.click('[data-test="time-off-tab-inbox"]');
  await expect(page).toHaveURL(/\/conges\/a-valider$/);

  const item = inboxItem(page);
  await expect(item.locator('[data-test="time-off-inbox-balance"]')).toContainText('Solde CP 2029-2030 après la demande');
  await item.locator('[data-test="time-off-approve"]').click();

  await expect(page).toHaveURL(/\/conges\/a-valider$/);
  await expect(inboxItem(page)).toHaveCount(0);
});

test('le congé validé apparaît dans les absences, avec son type pour la direction', async ({ page }) => {
  await login(page, 'admin@example.com');
  await page.goto('/conges/absences/2030-03');

  const paula = page.locator('[data-test="absences-row"]').filter({ hasText: 'Paula Durand' });
  const cell = paula.locator('[data-test="absences-cell"][data-day="2030-03-05"]');
  await expect(cell).toHaveAttribute('data-state', 'whole');
  await expect(cell).toHaveAttribute('title', 'Congé · CP');

  await login(page, 'prod@example.com');
  await page.goto('/conges');
  const row = page.locator('[data-test="time-off-request-row"]').filter({ hasText: '05/03/2030' });
  await expect(row.locator('[data-test="time-off-request-status"]')).toHaveText('Validé');
});
