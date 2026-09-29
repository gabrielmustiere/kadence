import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Le serveur tourne à la date réelle : on ne saisit que le lundi de la semaine en cours, toujours passé ou aujourd'hui.
// Les données de la story sont créées et purgées en SQL (SQLite n'applique pas les clés étrangères : temps avant lots).
test.describe.configure({ mode: 'serial' });

const project = `Saisie E2E ${Date.now().toString(36)}`;
const monday = mondayOf(new Date());
const previousMonday = shiftDays(monday, -7);

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function mondayOf(date: Date): Date {
  const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  day.setDate(day.getDate() - ((day.getDay() + 6) % 7));

  return day;
}

function shiftDays(date: Date, days: number): Date {
  const shifted = new Date(date);
  shifted.setDate(shifted.getDate() + days);

  return shifted;
}

function iso(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function purge() {
  const lots = `SELECT l.id FROM lot l JOIN project p ON p.id = l.project_id WHERE p.title LIKE 'Saisie E2E %'`;
  sql(`DELETE FROM time_entry WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'Saisie E2E %')`);
  sql(`DELETE FROM project WHERE title LIKE 'Saisie E2E %'`);
  sql(`DELETE FROM weekly_max WHERE user_id = (SELECT id FROM "user" WHERE email = 'ancien@example.com')`);
}

test.beforeAll(() => {
  purge();
  sql(`DELETE FROM time_entry WHERE user_id = (SELECT id FROM "user" WHERE email = 'prod@example.com') AND day >= '${iso(monday)}'`);
  sql(`INSERT INTO project (title) VALUES ('${project}')`);
  for (const [title, days] of [['Alpha', 5], ['Bêta', 3], ['Gamma', 2]] as const) {
    sql(`INSERT INTO lot (title, estimate_days, project_id) SELECT '${title}', ${days}, id FROM project WHERE title = '${project}'`);
  }
  for (const title of ['Alpha', 'Bêta']) {
    sql(`INSERT INTO time_entry (day, quarters, user_id, lot_id)
         SELECT '${iso(previousMonday)}', 1, u.id, l.id FROM "user" u, lot l JOIN project p ON p.id = l.project_id
         WHERE u.email = 'prod@example.com' AND p.title = '${project}' AND l.title = '${title}'`);
  }
});

test.afterAll(purge);

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

function row(page: Page, title: string): Locator {
  return page.locator('[data-test="timesheet-row"]', { has: page.locator('[data-test="leaf-title"]', { hasText: new RegExp(`^${title}$`) }) });
}

function mondayBar(page: Page, title: string): Locator {
  return row(page, title).locator(`[data-test="timesheet-cell"][data-day="${iso(monday)}"] [data-test="quarter-bar"]`);
}

async function backgroundColors(bar: Locator): Promise<string[]> {
  return bar.locator('[data-test="quarter"]').evaluateAll((notches) => notches.map((notch) => getComputedStyle(notch).backgroundColor));
}

test('la connexion mène à la semaine, qui propose les lignes saisies la semaine précédente', async ({ page }) => {
  await login(page, 'prod@example.com');

  await expect(page).toHaveURL(/\/saisie$/);
  await expect(page.locator('h1')).toHaveText('Ma semaine');
  await expect(row(page, 'Alpha')).toBeVisible();
  await expect(row(page, 'Bêta')).toBeVisible();
  await expect(row(page, 'Gamma')).toHaveCount(0);
});

test('une journée répartie sur deux lignes proposées se saisit en deux clics et la colonne passe au vert', async ({ page }) => {
  await login(page, 'prod@example.com');
  const start = Date.now();

  await mondayBar(page, 'Alpha').locator('[data-value="2"]').click();
  await expect(mondayBar(page, 'Alpha')).toHaveAttribute('data-quarters', '2');
  await mondayBar(page, 'Bêta').locator('[data-value="2"]').click();

  const header = page.locator(`[data-test="day-header"][data-day="${iso(monday)}"]`);
  await expect(header).toHaveAttribute('data-complete', 'true');
  await expect(header.locator('[data-test="day-total"]')).toContainText('1 j');
  expect(Date.now() - start).toBeLessThan(60_000);

  await page.reload();
  await expect(mondayBar(page, 'Alpha')).toHaveAttribute('data-quarters', '2');
  await expect(mondayBar(page, 'Bêta')).toHaveAttribute('data-quarters', '2');
});

test('le survol remplit les crans jusqu\'au cran pointé, le re-clic sur le cran actif vide la case', async ({ page }) => {
  await login(page, 'prod@example.com');
  await mondayBar(page, 'Bêta').locator('[data-value="2"]').click();
  await expect(mondayBar(page, 'Bêta')).toHaveAttribute('data-quarters', '0');
  const bar = mondayBar(page, 'Alpha');

  await bar.locator('[data-value="3"]').hover();
  await expect.poll(async () => {
    const [first, second, third, fourth] = await backgroundColors(bar);

    return second === first && third === first && fourth !== first;
  }).toBe(true);

  await bar.locator('[data-value="3"]').click();
  await expect(bar).toHaveAttribute('data-quarters', '3');
  await page.reload();
  await expect(mondayBar(page, 'Alpha')).toHaveAttribute('data-quarters', '3');

  await mondayBar(page, 'Alpha').locator('[data-value="3"]').click();
  await expect(mondayBar(page, 'Alpha')).toHaveAttribute('data-quarters', '0');
});

test('les crans qui dépasseraient une journée sont verrouillés', async ({ page }) => {
  await login(page, 'prod@example.com');
  await mondayBar(page, 'Alpha').locator('[data-value="3"]').click();
  await expect(mondayBar(page, 'Alpha')).toHaveAttribute('data-quarters', '3');

  const notches = mondayBar(page, 'Bêta').locator('[data-test="quarter"]');
  await expect(notches.nth(0)).toBeEnabled();
  await expect(notches.nth(1)).toBeDisabled();
  await expect(notches.nth(2)).toBeDisabled();
  await expect(notches.nth(3)).toBeDisabled();
});

test('une ligne ajoutée par la recherche disparaît au rechargement si elle ne reçoit aucun temps', async ({ page }) => {
  await login(page, 'prod@example.com');

  await page.fill('[data-test="add-line-input"]', `${project} gamma`);
  const result = page.locator('[data-test="add-line-result"]', { hasText: 'Gamma' });
  await expect(result).toBeVisible();
  await result.click();
  await expect(row(page, 'Gamma')).toBeVisible();

  await page.reload();
  await expect(row(page, 'Gamma')).toHaveCount(0);
});

test('on navigue de semaine en semaine', async ({ page }) => {
  await login(page, 'prod@example.com');
  const label = page.locator('[data-test="week-label"]');
  const currentWeek = await label.getAttribute('data-week');

  await page.click('[data-test="week-prev"]');
  await expect(label).not.toHaveAttribute('data-week', currentWeek ?? '');
  await expect(page.locator(`[data-test="timesheet-cell"][data-day="${iso(previousMonday)}"]`).first()).toBeVisible();

  await page.click('[data-test="week-next"]');
  await expect(label).toHaveAttribute('data-week', currentWeek ?? '');

  await page.click('[data-test="week-next"]');
  await expect(page.locator('[data-test="quarter"]:not([disabled])')).toHaveCount(0);

  await page.click('[data-test="week-current"]');
  await expect(page).toHaveURL(/\/saisie$/);
});

test('sur téléphone, la grille présente un jour à la fois', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, 'prod@example.com');

  await expect(page.locator('[data-test="day-header"]:visible')).toHaveCount(1);
  await page.locator(`[data-test="day-tab"][data-day="${iso(monday)}"]`).click();
  await expect(page.locator(`[data-test="day-tab"][data-day="${iso(monday)}"]`)).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator(`[data-test="day-header"][data-day="${iso(monday)}"]`)).toBeVisible();
  await expect(page.locator('[data-test="day-header"]:visible')).toHaveCount(1);
  await expect(mondayBar(page, 'Alpha')).toBeVisible();
});

test('un lot qui porte des temps ne peut plus être supprimé', async ({ page }) => {
  await login(page, 'lead@example.com');
  await page.click('[data-test="nav-projects"]');
  await page.click(`[data-title="${project}"] [data-test="project-link"]`);

  const alpha = page.locator('[data-test="lot-row"][data-title="Alpha"]');
  await alpha.locator('[data-test="lot-delete"]').click();
  await expect(alpha.locator('[data-test="lot-delete-blocked"]')).toBeVisible();
  await expect(alpha.locator('[data-test="lot-delete-confirm"]')).toHaveCount(0);
  await expect(alpha.locator('[data-test="initial-estimate"]')).toHaveText('initiale 5 j');
});

test('la direction fixe un maximum hebdomadaire à partir d\'une semaine, puis le supprime', async ({ page }) => {
  await login(page, 'admin@example.com');
  await page.goto('/equipe');
  await page.click('[data-email="ancien@example.com"] [data-test="member-edit"]');

  await page.fill('[data-test="member-weekly-max"]', '4.5');
  await page.fill('[data-test="member-weekly-max-from"]', iso(shiftDays(monday, 9)));
  await page.click('[data-test="team-member-submit"]');
  await page.click('[data-email="ancien@example.com"] [data-test="member-edit"]');

  const entry = page.locator(`[data-test="weekly-max-entry"][data-from="${iso(shiftDays(monday, 7))}"]`);
  await expect(entry).toContainText('4,5 j');
  await entry.locator('[data-test="weekly-max-delete"]').click();
  await expect(page.locator('[data-test="weekly-max-entry"]')).toHaveCount(0);
});
