import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// La feuille est créée et saisie en SQL par l'ancien collaborateur (Arthur Petit), déjà utilisé de la même façon par
// roadmap.spec.ts sur une autre feuille : ses saisies ne gênent pas les autres specs.
test.describe.configure({ mode: 'serial' });

const project = `Fiche personne E2E ${Date.now().toString(36)}`;

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

function purge() {
  const lots = `SELECT l.id FROM lot l JOIN project p ON p.id = l.project_id WHERE p.title LIKE 'Fiche personne E2E %'`;
  sql(`DELETE FROM time_entry WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot_member WHERE lot_id IN (${lots})`);
  sql(`DELETE FROM lot WHERE project_id IN (SELECT id FROM project WHERE title LIKE 'Fiche personne E2E %')`);
  sql(`DELETE FROM project WHERE title LIKE 'Fiche personne E2E %'`);
}

function iso(day: Date): string {
  return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
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

  const days = [...weekDays(-3), ...weekDays(-1)];
  const leafAndFormer = `lot l JOIN project p ON p.id = l.project_id JOIN "user" u ON u.email = 'ancien@example.com'`;
  const leaf = `p.title = '${project}' AND l.title = 'Reprise'`;
  sql(`INSERT INTO lot (title, estimate_days, start_date, project_id) SELECT 'Reprise', 20, '${days[0]}', id FROM project WHERE title = '${project}'`);
  sql(`INSERT INTO lot_member (share, lot_id, user_id) SELECT 100, l.id, u.id FROM ${leafAndFormer} WHERE ${leaf}`);
  sql(`INSERT INTO time_entry (day, quarters, user_id, lot_id) SELECT d.day, 4, u.id, l.id FROM (${days.map((day) => `SELECT '${day}' AS day`).join(' UNION ALL ')}) d, ${leafAndFormer} WHERE ${leaf}`);
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

test('un nom de la timeline d\'une fiche projet ouvre la fiche de la personne, qui détaille ses tronçons', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await page.locator(`[data-test="roadmap-project"][data-title="${project}"] [data-test="roadmap-project-open"]`).click();
  await expect(page.locator('[data-test="project-page-heading"]')).toHaveText(project);

  await page.locator('[data-test="timeline-entry"]').first().locator('[data-test="person-link"]', { hasText: 'Arthur Petit' }).click();
  await expect(page.locator('[data-test="person-heading"]')).toHaveText('Arthur Petit');
  await expect(page.locator('[data-test="person-inactive"]')).toBeVisible();

  const segments = page.locator('[data-test="person-leaf"][data-title="Reprise"] [data-test="roadmap-segment-realized"]');
  await expect(segments).toHaveCount(2);
  // Brought into view first: a scroll made by hover() itself would close the tooltip it has just opened.
  await segments.first().evaluate((segment) => segment.scrollIntoView({ block: 'center', inline: 'center' }));
  const shown = page.locator('[role="tooltip"]:visible');
  await segments.first().hover();
  await expect(shown).toHaveCount(1);
  await expect(shown.locator('[data-test="person-tooltip-days"]')).toHaveText('5');

  const entries = page.locator('[data-test="timeline-entry"][data-leaf="Reprise"]');
  await expect(entries).toHaveCount(2);
  await expect(entries.locator('[data-test="timeline-entry-days"]')).toHaveText(['5', '5']);
});

test('« Ma fiche » ouvre la fiche de la personne connectée depuis le menu du compte', async ({ page }) => {
  await login(page, 'prod@example.com');
  const name = await page.locator('[data-test="user-menu-name"]').textContent();

  await page.click('[data-test="user-menu-toggle"]');
  await page.click('[data-test="nav-person"]');

  await expect(page.locator('[data-test="person-heading"]')).toHaveText(name ?? '');
  await expect(page.locator('[data-test="person-load"]')).toBeVisible();
});
