import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Le projet est créé en SQL puis planifié par le formulaire ; Paula Durand n'a aucune feuille planifiée dans les
// fixtures, elle peut donc rejoindre l'équipe sans surcharge. La feuille interrompue est saisie en SQL par l'ancien
// collaborateur, que les autres specs n'utilisent pas.
test.describe.configure({ mode: 'serial' });

const project = `Roadmap E2E ${Date.now().toString(36)}`;

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  const lots = `SELECT l.id FROM lot l JOIN project p ON p.id = l.project_id WHERE p.title LIKE 'Roadmap E2E %'`;
  sql(`DELETE FROM time_entry WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot_member WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'Roadmap E2E %')`);
  sql(`DELETE FROM project WHERE title LIKE 'Roadmap E2E %'`);
}

function iso(day: Date): string {
  return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}

function nextMonday(): string {
  const day = new Date();
  day.setDate(day.getDate() + (((8 - day.getDay()) % 7) || 7));

  return iso(day);
}

function weekDays(weeksFromNow: number): string[] {
  const monday = new Date();
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7) + 7 * weeksFromNow);

  return [0, 1, 2, 3, 4].map((offset) => {
    const day = new Date(monday);
    day.setDate(monday.getDate() + offset);

    return iso(day);
  });
}

test.beforeAll(() => {
  purge();
  sql(`INSERT INTO project (title) VALUES ('${project}')`);
  sql(`INSERT INTO lot (title, estimate_days, project_id) SELECT 'Socle', 10, id FROM project WHERE title = '${project}'`);

  const days = [...weekDays(-3), ...weekDays(-1)];
  const leafAndFormer = `lot l JOIN project p ON p.id = l.project_id JOIN "user" u ON u.email = 'ancien@example.com'`;
  const interrupted = `p.title = '${project}' AND l.title = 'Interrompue'`;
  sql(`INSERT INTO lot (title, estimate_days, start_date, project_id) SELECT 'Interrompue', 10, '${days[0]}', id FROM project WHERE title = '${project}'`);
  sql(`INSERT INTO lot_member (share, lot_id, user_id) SELECT 100, l.id, u.id FROM ${leafAndFormer} WHERE ${interrupted}`);
  sql(`INSERT INTO time_entry (day, quarters, user_id, lot_id) SELECT d.day, 4, u.id, l.id FROM (${days.map((day) => `SELECT '${day}' AS day`).join(' UNION ALL ')}) d, ${leafAndFormer} WHERE ${interrupted}`);
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
  const tooltip = leaf.locator('[data-test="roadmap-tooltip-future"]');
  await expect(tooltip).toBeHidden();
  await leaf.locator('[data-test="roadmap-bar-future"]').hover();
  await expect(tooltip).toBeVisible();
  await expect(tooltip.locator('[data-test="roadmap-team"]')).toHaveText('Paula Durand 50 %');
  await expect(tooltip.locator('[data-test="roadmap-remaining"]')).toHaveText('10 j');
  await expect(projectRow(page).locator('summary [data-test="roadmap-bar-span"]')).toBeVisible();
});

test('une feuille interrompue montre ses tronçons et la même infobulle depuis chacun', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await projectRow(page).locator('summary').click();
  const leaf = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Interrompue"]');
  const segments = leaf.locator('[data-test="roadmap-segment-realized"]');
  const tooltip = leaf.locator('[data-test="roadmap-tooltip-realized"]');
  await expect(segments).toHaveCount(2);
  await expect(page.locator('[data-test="roadmap-legend-gap"]')).toBeVisible();

  await segments.first().hover();
  await expect(tooltip).toBeVisible();
  await expect(tooltip.locator('[data-test="roadmap-days-entered"]')).toHaveText('10');
  await page.mouse.move(0, 0);
  await expect(tooltip).toBeHidden();

  await segments.last().hover();
  await expect(tooltip).toBeVisible();
  await expect(tooltip.locator('[data-test="roadmap-days-entered"]')).toHaveText('10');
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

test('un projet déplié le reste quand on navigue dans la frise', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  const leaf = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Socle"]');
  await projectRow(page).locator('summary').click();
  await expect(leaf).toBeVisible();

  await page.click('[data-test="roadmap-next"]');
  await expect(page.locator('[data-test="roadmap-today"]')).not.toHaveAttribute('aria-current', 'page');
  await expect(leaf).toBeVisible();

  await projectRow(page).locator('summary').click();
  await page.click('[data-test="roadmap-today"]');
  await expect(page.locator('[data-test="roadmap-today"]')).toHaveAttribute('aria-current', 'page');
  await expect(leaf).toBeHidden();
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
  await expect(projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Socle"] [data-test="roadmap-remaining"]')).toHaveText('12 j');
});
