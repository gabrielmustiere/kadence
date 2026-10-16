import { test, expect, Page } from '@playwright/test';

test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

async function expectFitsTheScreen(page: Page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, `${page.url()} déborde de ${overflow}px`).toBeLessThanOrEqual(0);
}

async function fieldsUnder16px(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    [...document.querySelectorAll<HTMLElement>('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea')]
      .filter((field) => field.offsetParent !== null && parseFloat(getComputedStyle(field).fontSize) < 16)
      .map((field) => field.getAttribute('name') ?? field.id),
  );
}

test('sur mobile, aucune page ne déborde et aucun champ ne fait zoomer iOS', async ({ page }) => {
  await login(page, 'thomas.cerqueus@anyti.me');

  for (const url of ['/', '/saisie', '/projets', '/projets/nouveau', '/roadmap', '/personnes', '/personnes/nouveau', '/conges', '/conges/a-valider', '/conges/absences', '/conges/compteurs', '/jours-feries', '/tags', '/mon-compte/mot-de-passe']) {
    await page.goto(url);
    await expectFitsTheScreen(page);
    expect(await fieldsUnder16px(page), url).toEqual([]);
  }

  await page.goto('/projets');
  await page.locator('[data-test="project-link"]').first().click();
  await expectFitsTheScreen(page);
  expect(await fieldsUnder16px(page)).toEqual([]);
});

test('sur mobile, les crans de la saisie se touchent du doigt', async ({ page }) => {
  await login(page, 'antoine.leroy@anyti.me');
  await page.goto('/saisie');

  const quarter = await page.locator('[data-test="quarter"]:visible').first().boundingBox();
  expect(quarter).not.toBeNull();
  expect(quarter!.height).toBeGreaterThanOrEqual(44);
  expect(quarter!.width).toBeGreaterThanOrEqual(30);
  const star = await page.locator('[data-test="favorite-toggle"]:visible').first().boundingBox();
  expect(star!.height).toBeGreaterThanOrEqual(36);
});

test('sur mobile, la roadmap liste les projets avec leur fin plutôt que la frise', async ({ page }) => {
  await login(page, 'thomas.cerqueus@anyti.me');
  await page.goto('/roadmap');

  await expect(page.locator('[data-test="roadmap"]')).toBeHidden();
  const project = page.locator('[data-test="roadmap-list-project"]').first();
  await expect(project.locator('[data-test="roadmap-end"]')).toBeVisible();

  const title = await project.getAttribute('data-title');
  await project.locator('a').click();
  await expect(page.locator('h1')).toHaveText(title!);
});

test('sur mobile, un lot se lit en carte, chiffres compris, et l\'équipe du journal se déplie', async ({ page }) => {
  await login(page, 'thomas.cerqueus@anyti.me');
  await page.goto('/projets');
  await page.locator('[data-test="project-link"]').first().click();

  const estimate = page.locator('[data-test="lot-row"]').first().locator('[data-test="consumption-estimate"]');
  await estimate.scrollIntoViewIfNeeded();
  await expect(estimate).toBeVisible();
  const box = await estimate.boundingBox();
  expect(box!.x + box!.width).toBeLessThanOrEqual(390);

  const team = page.locator('[data-test="timeline-entry-team"]').first();
  await expect(team).not.toHaveAttribute('open');
  await team.locator('summary').click();
  await expect(team.locator('[data-test="person-link"]').first()).toBeVisible();
});
