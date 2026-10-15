import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Tout se passe en 2030, loin des semaines saisies par les autres scénarios. Dans la démo, le manager d'Antoine
// (antoine.leroy@anyti.me) est Olivier (olivier.thomas@anyti.me). Les demandes de 2030 sont purgées en SQL avant et après.
test.describe.configure({ mode: 'serial' });

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  sql(`DELETE FROM time_off_request WHERE start_day BETWEEN '2030-01-01' AND '2030-12-31'`);
  sql(`DELETE FROM time_off_entitlement WHERE year = 2030`);
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
  return page.locator('[data-test="time-off-inbox-item"]').filter({ hasText: 'Antoine Leroy' }).filter({ hasText: '05/03/2030' });
}

test('la prod demande un jour de congé et le voit réservé dans sa semaine', async ({ page }) => {
  await login(page, 'antoine.leroy@anyti.me');
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
  await login(page, 'olivier.thomas@anyti.me');
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
  await login(page, 'thomas.cerqueus@anyti.me');
  await page.goto('/conges/absences/2030-03');

  const antoine = page.locator('[data-test="absences-row"]').filter({ hasText: 'Antoine Leroy' });
  const cell = antoine.locator('[data-test="absences-cell"][data-day="2030-03-05"]');
  await expect(cell).toHaveAttribute('data-state', 'whole');
  await expect(cell).toHaveAttribute('title', 'Congé · CP');

  await login(page, 'antoine.leroy@anyti.me');
  await page.goto('/conges');
  const row = page.locator('[data-test="time-off-request-row"]').filter({ hasText: '05/03/2030' });
  await expect(row.locator('[data-test="time-off-request-status"]')).toHaveText('Validé');
});

test('le manager refuse une demande sans quitter la liste, et un motif vide ne ferme pas la fenêtre', async ({ page }) => {
  await login(page, 'antoine.leroy@anyti.me');
  await page.goto('/conges');
  await page.fill('[data-test="time-off-start"]', '2030-04-09');
  await page.fill('[data-test="time-off-end"]', '2030-04-09');
  await page.locator('[data-test="time-off-end"]').blur();
  await expect(page.locator('[data-test="time-off-preview-days"]')).toHaveText('1 j');
  await page.click('[data-test="time-off-submit"]');
  await expect(page.locator('[data-test="time-off-request-row"]').filter({ hasText: '09/04/2030' })).toBeVisible();

  await login(page, 'olivier.thomas@anyti.me');
  await page.goto('/conges/a-valider');
  const item = page.locator('[data-test="time-off-inbox-item"]').filter({ hasText: 'Antoine Leroy' }).filter({ hasText: '09/04/2030' });
  const count = Number(await page.locator('[data-test="time-off-inbox-count"]').textContent());
  await item.locator('[data-test="time-off-refuse"]').click();
  const dialog = item.locator('dialog');
  await expect(dialog).toBeVisible();

  await dialog.locator('[data-test="time-off-refuse-reason"]').fill('   ');
  await dialog.locator('[data-test="time-off-refuse-confirm"]').click();
  await expect(dialog.locator('[data-test="time-off-refuse-error"]')).toBeVisible();
  await expect(dialog).toBeVisible();

  await dialog.locator('[data-test="time-off-refuse-reason"]').fill('Mise en production');
  await dialog.locator('[data-test="time-off-refuse-confirm"]').click();
  await expect(item).toHaveCount(0);
  await expect(page.locator('[role="alert"]')).toContainText('La demande de Antoine est refusée.');
  await expect(page).toHaveURL(/\/conges\/a-valider$/);
  if (count > 1) {
    await expect(page.locator('[data-test="time-off-inbox-count"]')).toHaveText(String(count - 1));
  } else {
    await expect(page.locator('[data-test="time-off-inbox-count"]')).toHaveCount(0);
  }
});

test('la direction voit le solde suivre les droits saisis, les enregistre sur place et est prévenue avant de les abandonner', async ({ page }) => {
  await login(page, 'thomas.cerqueus@anyti.me');
  await page.goto('/conges/compteurs/rtt/2030');
  const row = page.locator('[data-test="entitlements-row"]').filter({ hasText: 'Antoine Leroy' });

  await row.locator('[data-test="time-off-entitlement"]').fill('12.5');
  await expect(row.locator('[data-test="entitlements-remaining"]')).toHaveText('12,5 j');
  await expect(page.locator('[data-test="entitlements-dirty"]')).toBeVisible();

  page.once('dialog', (dialog) => dialog.dismiss());
  await page.click('[data-test="entitlements-next"]');
  await expect(page).toHaveURL(/\/conges\/compteurs\/rtt\/2030$/);

  await page.click('[data-test="entitlements-submit"]');
  await expect(page.locator('[role="alert"]')).toContainText('Les droits RTT 2030 sont enregistrés.');
  await expect(page.locator('[data-test="entitlements-dirty"]')).toHaveCount(0);
  await page.reload();
  await expect(row.locator('[data-test="time-off-entitlement"]')).toHaveValue('12.5');
});
