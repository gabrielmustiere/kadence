import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Filet en cas d'échec avant la suppression : on purge les tags créés ici, et leurs attributions avant eux.
test.afterAll(() => {
  execFileSync('symfony', ['console', 'dbal:run-sql', `DELETE FROM user_tag WHERE tag_id IN (SELECT id FROM tag WHERE label LIKE 'E2E %')`]);
  execFileSync('symfony', ['console', 'dbal:run-sql', `DELETE FROM tag WHERE label LIKE 'E2E %'`]);
});

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

function tagRow(page: Page, label: string) {
  return page.locator(`[data-test="tag-category"][data-category="experience"] [data-test="tag-row"][data-label="${label}"]`);
}

test('la direction ajoute, renomme puis supprime un tag', async ({ page }) => {
  const label = `E2E ${Date.now().toString(36)}`;
  const renamed = `${label} renommé`;

  await login(page, 'admin@example.com');
  await page.click('[data-test="nav-tags"]');
  await page.check('[data-test="tag-category-experience"]');
  await page.fill('[data-test="tag-label"]', label);
  await page.click('[data-test="tag-submit"]');

  await expect(tagRow(page, label)).toBeVisible();
  await expect(tagRow(page, label).locator('[data-test="tag-holders"]')).toHaveText('0 personne');

  await tagRow(page, label).locator('[data-test="tag-rename"]').click();
  await expect(page).toHaveURL(/\/renommer$/);
  await page.fill('[data-test="tag-label"]', renamed);
  await page.click('[data-test="tag-submit"]');
  await expect(tagRow(page, renamed)).toBeVisible();
  await expect(tagRow(page, label)).toHaveCount(0);

  const dialog = tagRow(page, renamed).locator('dialog');
  await tagRow(page, renamed).locator('[data-test="tag-delete"]').click();
  await expect(dialog).toBeVisible();
  await expect(dialog.locator('[data-test="tag-delete-holders"]')).toContainText('Personne ne le porte');
  await dialog.locator('[data-test="tag-delete-confirm"]').click();

  await expect(page.locator('[role="alert"]')).toContainText(`Le tag « ${renamed} » est supprimé.`);
  await expect(tagRow(page, renamed)).toHaveCount(0);
});

test('un lead n\'a pas accès aux tags', async ({ page }) => {
  await login(page, 'lead@example.com');
  await expect(page.locator('[data-test="nav-tags"]')).toHaveCount(0);

  const response = await page.goto('/tags');
  expect(response?.status()).toBe(403);
});
