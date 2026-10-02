import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Le projet est créé en SQL : une feuille de 20 j sans temps saisi, planifiée à partir de lundi prochain avec la
// direction, qui n'a aucune feuille planifiée dans les fixtures.
test.describe.configure({ mode: 'serial' });

const project = `Avancement E2E ${Date.now().toString(36)}`;

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  const lots = `SELECT l.id FROM lot l JOIN project p ON p.id = l.project_id WHERE p.title LIKE 'Avancement E2E %'`;
  sql(`DELETE FROM lot_progress WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot_member WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'Avancement E2E %')`);
  sql(`DELETE FROM project WHERE title LIKE 'Avancement E2E %'`);
}

function nextMonday(): string {
  const day = new Date();
  day.setDate(day.getDate() + (((8 - day.getDay()) % 7) || 7));

  return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}

function today(): string {
  const day = new Date();

  return `${String(day.getDate()).padStart(2, '0')}/${String(day.getMonth() + 1).padStart(2, '0')}`;
}

test.beforeAll(() => {
  purge();
  sql(`INSERT INTO project (title) VALUES ('${project}')`);
  sql(`INSERT INTO lot (title, estimate_days, start_date, project_id) SELECT 'Synchronisation', 20, '${nextMonday()}', id FROM project WHERE title = '${project}'`);
  sql(`INSERT INTO lot_member (share, lot_id, user_id) SELECT 100, l.id, u.id FROM lot l JOIN project p ON p.id = l.project_id JOIN "user" u ON u.email = 'admin@example.com' WHERE p.title = '${project}'`);
});
test.afterAll(purge);

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

test('un lead déclare l\'avancement d\'une feuille et le retrouve sur la fiche du projet', async ({ page }) => {
  await login(page, 'lead@example.com');
  await page.click('[data-test="nav-projects"]');
  await page.click(`[data-title="${project}"] [data-test="project-link"]`);

  const row = page.locator('[data-test="lot-row"][data-title="Synchronisation"]');
  await row.locator('[data-test="progress-select"]').selectOption('30');
  await row.locator('[data-test="progress-submit"]').click();

  await expect(page.locator('[role="alert"]')).toContainText('Avancement de « Synchronisation » : 30 %.');
  await expect(row.locator('[data-test="progress-select"]')).toHaveValue('30');
  await expect(row.locator('[data-test="progress-declared-on"]')).toHaveText(`déclaré le ${today()}`);

  const projectId = new URL(page.url()).pathname.split('/').pop();
  await page.goto(`/roadmap/projets/${projectId}`);

  const line = page.locator('[data-test="consumption-row"][data-title="Synchronisation"]');
  await expect(line.locator('[data-test="consumption-remaining"]')).toHaveText('14 j');
  await expect(line.locator('[data-test="consumption-progress"]')).toContainText(`30 % au ${today()}`);
  await expect(line.locator('[data-test="consumption-projected"]')).toContainText('14 j (-6 j)');
  await expect(page.locator('[data-test="progress-history-leaf"][data-title="Synchronisation"] [data-test="progress-history-entry"]')).toHaveCount(1);

  const leaf = page.locator('[data-test="roadmap-leaf"][data-title="Synchronisation"]');
  const tooltip = leaf.locator('[data-test="roadmap-tooltip-future"]');
  const future = leaf.locator('[data-test="roadmap-bar-future"]');
  // Brought into view first: a scroll made by hover() itself would close the tooltip it has just opened.
  await future.evaluate((bar) => bar.scrollIntoView({ block: 'center', inline: 'center' }));
  await future.hover();
  await expect(tooltip).toBeVisible();
  await expect(tooltip.locator('[data-test="roadmap-remaining"]')).toHaveText('14 j');
  await expect(tooltip.locator('[data-test="roadmap-progress"]')).toHaveText(`30 % au ${today()}`);
  await expect(tooltip.locator('[data-test="roadmap-projected"]')).toHaveText('14 j pour 20 j estimés (-6 j)');
});
