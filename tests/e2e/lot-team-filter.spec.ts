import { test, expect, Page } from '@playwright/test';

// S'appuie sur les tags de la démo : Symfony (Julien, Camille, Chloé, Karim, Lucas), Paie et Back pour Julien et Camille.

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
  await login(page, 'lead@example.com');
  await openLeaf(page, 'Atlas — Plateforme de paie', 'Régularisations');

  const rows = page.locator('[data-test="lot-member-row"] [data-test="lot-member-user"]');
  const thomasRow = rows.first();
  await expect(thomasRow.locator('option:checked')).toContainText('Thomas Girard — Front · React, TypeScript · Paie');
  const everyone = await people(thomasRow).count();

  await page.selectOption('[data-test="lot-tag-filter-competence"]', { label: 'Symfony' });
  await expect(people(thomasRow)).toHaveCount(6);
  await expect(thomasRow.locator('option:checked')).toContainText('Thomas Girard');
  await expect(people(thomasRow).filter({ hasText: 'Emma Rousseau' })).toHaveCount(0);

  await page.selectOption('[data-test="lot-tag-filter-experience"]', { label: 'Paie' });
  await page.selectOption('[data-test="lot-tag-filter-equipe"]', { label: 'Back' });
  await page.click('[data-test="lot-member-add"]');
  const added = rows.last();
  await expect(people(added)).toHaveText([/^Julien Moreau/, /^Camille Roux/]);

  await page.selectOption('[data-test="lot-tag-filter-competence"]', '');
  await page.selectOption('[data-test="lot-tag-filter-experience"]', '');
  await page.selectOption('[data-test="lot-tag-filter-equipe"]', '');
  await expect(people(added)).toHaveCount(everyone);
});

test('revenir sur une feuille filtrée par le bouton retour propose de nouveau tout le monde', async ({ page }) => {
  await login(page, 'lead@example.com');
  await openLeaf(page, 'Atlas — Plateforme de paie', 'Régularisations');
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
