import { test, expect, Page } from '@playwright/test';

// S'appuie sur les tags de la démo : Symfony (Gabriel, Maxime, Romain, Damien), SCA et Back pour Gabriel, Maxime et Romain.

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

async function openLeaf(page: Page, project: string, leaf: string) {
  await page.goto('/projets');
  await page.locator(`[data-test="project-row"][data-title="${project}"] [data-test="project-link"]`).click();
  await page.locator(`[data-test="lot-row"][data-title="${leaf}"] [data-test="lot-edit"]`).click();
  await expect(page.locator('[data-test="lot-planning"]')).toBeVisible();
}

function people(select: ReturnType<Page['locator']>) {
  return select.locator('option:not([value=""])');
}

test('un lead restreint les personnes proposées pour l\'équipe aux tags choisis', async ({ page }) => {
  await login(page, 'olivier.thomas@anyti.me');
  await openLeaf(page, 'SCA', 'Run et suivi du déploiement');

  const rows = page.locator('[data-test="lot-member-row"] [data-test="lot-member-user"]');
  const hobiRow = rows.first();
  await expect(hobiRow.locator('option:checked')).toContainText('Hobi Lambert — QA · API REST, Playwright · Onboarding, SCA');
  const everyone = await people(hobiRow).count();

  await page.selectOption('[data-test="lot-tag-filter-competence"]', { label: 'Symfony' });
  await expect(people(hobiRow)).toHaveCount(5);
  await expect(hobiRow.locator('option:checked')).toContainText('Hobi Lambert');
  await expect(people(hobiRow).filter({ hasText: 'Antoine Leroy' })).toHaveCount(0);

  await page.selectOption('[data-test="lot-tag-filter-experience"]', { label: 'SCA' });
  await page.selectOption('[data-test="lot-tag-filter-equipe"]', { label: 'Back' });
  await page.click('[data-test="lot-member-add"]');
  const added = rows.last();
  await expect(people(added)).toHaveText([/^Romain Garnier/, /^Gabriel Mustiere/, /^Maxime Roux/]);

  await page.selectOption('[data-test="lot-tag-filter-competence"]', '');
  await page.selectOption('[data-test="lot-tag-filter-experience"]', '');
  await page.selectOption('[data-test="lot-tag-filter-equipe"]', '');
  await expect(people(added)).toHaveCount(everyone);
});

test('revenir sur une feuille filtrée par le bouton retour propose de nouveau tout le monde', async ({ page }) => {
  await login(page, 'olivier.thomas@anyti.me');
  await openLeaf(page, 'SCA', 'Run et suivi du déploiement');
  const firstRow = page.locator('[data-test="lot-member-row"] [data-test="lot-member-user"]').first();
  const everyone = await people(firstRow).count();

  await page.selectOption('[data-test="lot-tag-filter-competence"]', { label: 'Symfony' });
  await expect(people(firstRow)).not.toHaveCount(everyone);
  await page.click('[data-test="nav-roadmap"]');
  await expect(page).toHaveURL(/\/roadmap/);
  await page.goBack();

  await expect(page.locator('[data-test="lot-tag-filter-competence"]')).toHaveValue('');
  await expect(people(firstRow)).toHaveCount(everyone);
});

test('le formulaire d\'une feuille signale aussitôt une personne choisie deux fois, sans rien enregistrer', async ({ page }) => {
  await login(page, 'olivier.thomas@anyti.me');
  await openLeaf(page, 'SCA', 'Run et suivi du déploiement');
  const rows = page.locator('[data-test="lot-member-row"]');
  const count = await rows.count();
  const first = await rows.first().locator('[data-test="lot-member-user"]').inputValue();

  await page.click('[data-test="lot-member-add"]');
  await expect(rows).toHaveCount(count + 1);
  await expect(rows.last().locator('[data-test="lot-member-share"]')).toHaveValue('100');
  await rows.last().locator('[data-test="lot-member-user"]').selectOption(first);

  await expect(page.locator('[data-test="lot-planning"]')).toContainText('Une personne ne figure qu\'une fois dans l\'équipe.');
  await expect(page).toHaveURL(/\/modifier$/);
  await rows.last().locator('[data-test="lot-member-remove"]').click();
  await expect(rows).toHaveCount(count);
  await expect(page.locator('[data-test="lot-planning"]')).not.toContainText('Une personne ne figure qu\'une fois dans l\'équipe.');
});
