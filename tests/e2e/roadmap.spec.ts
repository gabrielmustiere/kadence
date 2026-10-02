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

  const days = [...weekDays(-3), weekDays(-2)[2], ...weekDays(-1)];
  const leafAndFormer = `lot l JOIN project p ON p.id = l.project_id JOIN "user" u ON u.email = 'ancien@example.com'`;
  const interrupted = `p.title = '${project}' AND l.title = 'Interrompue'`;
  sql(`INSERT INTO lot (title, estimate_days, start_date, project_id) SELECT 'Interrompue', 12, '${days[0]}', id FROM project WHERE title = '${project}'`);
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

test('chaque tronçon a son infobulle, une seule à la fois, et le titre donne le récap de la feuille', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await projectRow(page).locator('summary').click();
  const leaf = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Interrompue"]');
  const segments = leaf.locator('[data-test="roadmap-segment-realized"]');
  const shown = page.locator('[role="tooltip"]:visible');
  await expect(segments).toHaveCount(3);

  for (const [index, days] of [[0, '5'], [1, '1'], [2, '5']] as const) {
    await segments.nth(index).hover();
    await expect(shown).toHaveCount(1);
    await expect(shown.locator('[data-test="roadmap-days-entered"]')).toHaveText(days);
    await expect(shown.locator('[data-test="roadmap-team"]')).toHaveText(/Arthur Petit/);
  }

  const first = await segments.nth(0).boundingBox();
  const second = await segments.nth(1).boundingBox();
  const bar = leaf.locator('[data-test="roadmap-bar-realized"]');
  const box = await bar.boundingBox();
  await bar.hover({ position: { x: (first!.x + first!.width + second!.x) / 2 - box!.x, y: box!.height / 2 } });
  await expect(shown).toHaveCount(0);

  await leaf.locator('[data-test="roadmap-leaf-title"]').hover();
  await expect(shown).toHaveCount(1);
  await expect(shown).toHaveAttribute('data-test', 'roadmap-recap');
  await expect(shown.locator('[data-test="roadmap-days-entered"]')).toHaveText('11');
  await expect(shown.locator('[data-test="roadmap-recap-entered"]')).toHaveText('11 j');
});

test('le récap d\'une feuille s\'ouvre au clavier', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await projectRow(page).locator('summary').click();
  const leaf = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Interrompue"]');

  await leaf.locator('[data-test="roadmap-leaf-title"]').focus();
  await expect(leaf.locator('[data-test="roadmap-recap"]')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(leaf.locator('[data-test="roadmap-recap"]')).toBeHidden();
});

test('une infobulle ouverte se ferme avant que Turbo ne mette la page en cache', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await projectRow(page).locator('summary').click();
  await page.mouse.move(0, 0);
  await projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Interrompue"] [data-test="roadmap-leaf-title"]').focus();
  await expect(page.locator('[role="tooltip"]:visible')).toHaveCount(1);

  await page.evaluate(() => document.dispatchEvent(new Event('turbo:before-cache')));
  await expect(page.locator('[role="tooltip"]:visible')).toHaveCount(0);
});

test('la frise se zoome de ×1 à ×8, garde son palier en naviguant et rouvre sur aujourd\'hui', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await projectRow(page).locator('summary').click();
  const level = page.locator('[data-test="roadmap-zoom-level"]');
  const zoomIn = page.locator('[data-test="roadmap-zoom-in"]');
  await expect(level).toHaveText('×1');
  await expect(page.locator('[data-test="roadmap-zoom-out"]')).toBeDisabled();

  for (const expected of ['×2', '×4', '×8']) {
    await zoomIn.click();
    await expect(level).toHaveText(expected);
  }
  await expect(zoomIn).toBeDisabled();
  const oneDay = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Interrompue"] [data-test="roadmap-segment-realized"]').nth(1);
  expect((await oneDay.boundingBox())!.width).toBeGreaterThanOrEqual(20);

  await page.click('[data-test="roadmap-next"]');
  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}$/);
  await expect(level).toHaveText('×8');
  await page.click('[data-test="roadmap-today"]');
  await expect(page).toHaveURL(/\/roadmap$/);
  await expect(level).toHaveText('×8');
  const scroller = await page.locator('[data-test="roadmap"]').boundingBox();
  const today = await page.locator('[data-test="roadmap-today-line"]').first().boundingBox();
  expect(today!.x).toBeGreaterThan(scroller!.x + 320);
  expect(today!.x).toBeLessThan(scroller!.x + scroller!.width);

  await page.click('[data-test="roadmap-zoom-reset"]');
  await expect(level).toHaveText('×1');
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

test('l\'icône d\'un projet ouvre sa fiche sans le déplier, et la fiche ramène à la même fenêtre', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  await page.click('[data-test="roadmap-next"]');
  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}$/);
  const window = page.url();
  const leaf = projectRow(page).locator('[data-test="roadmap-leaf"][data-title="Socle"]');
  await expect(leaf).toBeHidden();

  await projectRow(page).locator('[data-test="roadmap-project-open"]').click();
  await expect(page).toHaveURL(/\/roadmap\/projets\/\d+\?roadmap=\d{4}-W\d{2}$/);
  await expect(page.locator('[data-test="project-page-heading"]')).toHaveText(project);
  await expect(page.locator('[data-test="nav-roadmap"]')).toHaveAttribute('aria-current', 'page');

  await page.click('[data-test="project-page-back"]');
  await expect(page).toHaveURL(window);
  await expect(leaf).toBeHidden();
});

test('la fiche s\'ouvre à ×1 sans toucher au zoom de la roadmap, et détaille chaque tronçon', async ({ page }) => {
  await login(page, 'prod@example.com');
  await page.goto('/roadmap');
  const level = page.locator('[data-test="roadmap-zoom-level"]');
  await page.click('[data-test="roadmap-zoom-in"]');
  await page.click('[data-test="roadmap-zoom-in"]');
  await expect(level).toHaveText('×4');

  await projectRow(page).locator('[data-test="roadmap-project-open"]').click();
  await expect(page.locator('[data-test="project-page-heading"]')).toHaveText(project);
  await expect(level).toHaveText('×1');
  await page.click('[data-test="roadmap-zoom-in"]');
  await expect(level).toHaveText('×2');

  const leaf = page.locator('[data-test="roadmap-leaf"][data-title="Interrompue"]');
  const segments = leaf.locator('[data-test="roadmap-segment-realized"]');
  await expect(segments).toHaveCount(3);
  // Brought into view first: a scroll made by hover() itself would close the tooltip it has just opened.
  await segments.nth(1).evaluate((segment) => segment.scrollIntoView({ block: 'center', inline: 'center' }));
  const shown = page.locator('[role="tooltip"]:visible');
  await segments.nth(1).hover();
  await expect(shown).toHaveCount(1);
  await expect(shown.locator('[data-test="roadmap-days-entered"]')).toHaveText('1');

  const entries = page.locator('[data-test="timeline-entry"][data-leaf="Interrompue"]');
  await expect(entries).toHaveCount(3);
  await expect(entries.locator('[data-test="timeline-entry-days"]')).toHaveText(['5', '1', '5']);
  await expect(entries.first().locator('[data-test="roadmap-team"]')).toHaveText(/Arthur Petit/);

  await page.click('[data-test="project-page-back"]');
  await expect(page).toHaveURL(/\/roadmap\/\d{4}-W\d{2}$/);
  await expect(level).toHaveText('×4');
});
