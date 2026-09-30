import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Le projet est créé en SQL puis planifié par le formulaire ; Paula Durand n'a aucune feuille planifiée dans les
// fixtures, elle peut donc rejoindre l'équipe sans surcharge.
test.describe.configure({ mode: 'serial' });

const project = `Roadmap E2E ${Date.now().toString(36)}`;

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  const lots = `SELECT l.id FROM lot l JOIN project p ON p.id = l.project_id WHERE p.title LIKE 'Roadmap E2E %'`;
  sql(`DELETE FROM lot_member WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'Roadmap E2E %')`);
  sql(`DELETE FROM project WHERE title LIKE 'Roadmap E2E %'`);
}

test.beforeAll(() => {
  purge();
  sql(`INSERT INTO project (title) VALUES ('${project}')`);
  sql(`INSERT INTO lot (title, estimate_days, project_id) SELECT 'Socle', 10, id FROM project WHERE title = '${project}'`);
});
test.afterAll(purge);

function nextMonday(): string {
  const day = new Date();
  day.setDate(day.getDate() + (((8 - day.getDay()) % 7) || 7));

  return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}

async function login(page: Page, email: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

function projectRow(page: Page): Locator {
  return page.locator(`[data-test="roadmap-project"][data-title="${project}"]`);
}

test('un lead planifie une feuille et la voit sur la roadmap', async ({ page }) => {
  await login(page, 'lead@example.com');
  await page.click('[data-test="nav-projects"]');
  await page.click(`[data-title="${project}"] [data-test="project-link"]`);
  await page.click('[data-test="lot-row"][data-title="Socle"] [data-test="lot-edit"]');

  await page.fill('[data-test="lot-start-date"]', nextMonday());
  await expect(page.locator('[data-test="lot-member-row"]')).toHaveCount(0);
  await page.click('[data-test="lot-member-add"]');
  const row = page.locator('[data-test="lot-member-row"]').last();
  await row.locator('[data-test="lot-member-user"]').selectOption({ label: 'Paula Durand' });
  await row.locator('[data-test="lot-member-share"]').selectOption({ label: '50 %' });
  await page.click('[data-test="lot-member-add"]');
  await page.locator('[data-test="lot-member-remove"]').last().click();
  await expect(page.locator('[data-test="lot-member-row"]')).toHaveCount(1);
  await page.click('[data-test="lot-submit"]');
  await expect(page.locator('[data-test="lot-row"][data-title="Socle"]')).toBeVisible();

  await page.click('[data-test="nav-roadmap"]');
  await expect(page).toHaveURL(/\/roadmap$/);
  const leaf = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Socle"]');
  await expect(leaf).toBeHidden();
  await projectRow(page).locator('summary').click();
  await expect(leaf).toBeVisible();
  await expect(leaf.locator('[data-test="roadmap-team"]')).toHaveText('Paula D. 50 %');
  await expect(leaf.locator('[data-test="roadmap-remaining"]')).toHaveText('10 j');
  await expect(leaf.locator('[data-test="roadmap-bar-future"]')).toBeVisible();
  await expect(projectRow(page).locator('summary [data-test="roadmap-bar-span"]')).toBeVisible();
});

test('la frise avance de quatre semaines et revient à aujourd\'hui', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  const period = await page.locator('[data-test="roadmap-period"]').textContent();

  await page.click('[data-test="roadmap-next"]');
  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}$/);
  await expect(page.locator('[data-test="roadmap-period"]')).not.toHaveText(period ?? '');
  await expect(page.locator('[data-test="roadmap-today"]')).not.toHaveAttribute('aria-current', 'page');

  await page.click('[data-test="roadmap-today"]');
  await expect(page).toHaveURL(/\/roadmap$/);
  await expect(page.locator('[data-test="roadmap-period"]')).toHaveText(period ?? '');
  await expect(projectRow(page).locator('[data-test="roadmap-leaf-link"]')).toHaveCount(0);
});

test('un lead ouvre une feuille depuis la roadmap et y revient à la même fenêtre', async ({ page }) => {
  await login(page, 'lead@example.com');
  await page.goto('/roadmap');
  await page.click('[data-test="roadmap-next"]');
  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}$/);
  const window = page.url();

  await projectRow(page).locator('summary').click();
  await projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Socle"] [data-test="roadmap-leaf-link"]').click();
  await expect(page).toHaveURL(/\/lots\/\d+\/modifier\?roadmap=/);
  await page.fill('[data-test="lot-estimate"]', '12');
  await page.click('[data-test="lot-submit"]');

  await expect(page).toHaveURL(window);
  await projectRow(page).locator('summary').click();
  await expect(projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Socle"] [data-test="roadmap-remaining"]')).toHaveText('12 j');
});
