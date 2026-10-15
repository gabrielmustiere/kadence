import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Les projets sont créés en SQL : une feuille sans estimation tombe sous « à estimer », un projet sans lot n'a rien à
// traiter. Les compteurs portent sur tout le portefeuille, fixtures comprises : on vérifie la frise filtrée, pas un total.
test.describe.configure({ mode: 'serial' });

const suffix = Date.now().toString(36);
const awaiting = `Bandeau E2E ${suffix}`;
const calm = `Bandeau E2E calme ${suffix}`;
const leaf = 'Sans estimation';

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  sql(`DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'Bandeau E2E %')`);
  sql(`DELETE FROM project WHERE title LIKE 'Bandeau E2E %'`);
}

test.beforeAll(() => {
  purge();
  sql(`INSERT INTO project (title) VALUES ('${awaiting}'), ('${calm}')`);
  sql(`INSERT INTO lot (title, project_id) SELECT '${leaf}', id FROM project WHERE title = '${awaiting}'`);
});
test.afterAll(purge);

async function login(page: Page, email: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

// An action fired while the live component still renders the previous one is rendered over by it.
async function rendered(page: Page, act: () => Promise<unknown>) {
  await Promise.all([page.waitForResponse((response) => response.url().includes('/_components/')), act()]);
  await expect(page.locator('[data-live-name-value][aria-busy="true"]')).toHaveCount(0);
}

const project = (page: Page, title: string) => page.locator(`[data-test="roadmap-project"][data-title="${title}"]`);

test('un lead ne garde sur la frise que les lots d\'un compteur, et les retrouve en naviguant', async ({ page }) => {
  await login(page, 'olivier.thomas@anyti.me');
  await page.goto('/roadmap');
  await expect(project(page, calm)).toBeVisible();

  await page.click('[data-test="roadmap-board-counter"][data-signal="to_estimate"]');

  await expect(page).toHaveURL(/\?signal=to_estimate$/);
  await expect(page.locator('[data-test="roadmap-board-counter"][data-signal="to_estimate"]')).toHaveAttribute('aria-current', 'true');
  await expect(project(page, calm)).toHaveCount(0);
  await expect(project(page, awaiting).locator('details')).toHaveAttribute('open', '');
  await expect(project(page, awaiting).locator(`[data-test="roadmap-leaf"][data-title="${leaf}"]`)).toBeVisible();

  await page.click('[data-test="roadmap-next"]');
  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}\?signal=to_estimate$/);
  await expect(project(page, awaiting).locator(`[data-test="roadmap-leaf"][data-title="${leaf}"]`)).toBeVisible();

  await page.click('[data-test="roadmap-board-reset"]');
  await expect(page).not.toHaveURL(/signal=/);
  await expect(project(page, calm)).toBeVisible();
});

test('estimer une feuille depuis la frise filtrée y ramène, sans la feuille', async ({ page }) => {
  await login(page, 'olivier.thomas@anyti.me');
  await page.goto('/roadmap?signal=to_estimate');

  await project(page, awaiting).locator(`[data-test="roadmap-leaf"][data-title="${leaf}"] [data-test="roadmap-leaf-link"]`).click();
  await rendered(page, async () => {
    await page.fill('[name="lot[estimateDays]"]', '5');
    await page.press('[name="lot[estimateDays]"]', 'Tab');
  });
  await page.click('[data-test="lot-submit"]');

  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}\?signal=to_estimate$/);
  await expect(project(page, awaiting)).toHaveCount(0);
});

test('sur téléphone, le bandeau passe à la ligne sans faire défiler la page', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await login(page, 'olivier.thomas@anyti.me');
  await page.goto('/roadmap');

  await expect(page.locator('[data-test="roadmap-board"]')).toBeVisible();
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(0);
});
