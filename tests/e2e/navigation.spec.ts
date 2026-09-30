import { test, expect, Page } from '@playwright/test';

async function login(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);
}

async function adminSectionGap(page: Page): Promise<number> {
  const containerBottom = await page.locator('#app-sidebar > div').evaluate(
    (container) => container.getBoundingClientRect().bottom - parseFloat(getComputedStyle(container).paddingBottom),
  );
  const admin = await page.locator('[data-test="nav-admin"]').boundingBox();
  expect(admin).not.toBeNull();

  return containerBottom - (admin!.y + admin!.height);
}

test('sur un écran haut, la section Administration est collée en bas du menu', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await login(page, 'admin@example.com');

  expect(Math.abs(await adminSectionGap(page))).toBeLessThanOrEqual(1);
});

test('sur un écran bas, la section Administration suit les entrées du haut sans les recouvrir', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 360 });
  await login(page, 'admin@example.com');

  const timesheet = await page.locator('[data-test="nav-timesheet"]').boundingBox();
  const admin = await page.locator('[data-test="nav-admin"]').boundingBox();
  expect(timesheet).not.toBeNull();
  expect(admin).not.toBeNull();
  expect(admin!.y).toBeGreaterThanOrEqual(timesheet!.y + timesheet!.height);

  await page.locator('[data-test="nav-holidays"]').scrollIntoViewIfNeeded();
  await expect(page.locator('[data-test="nav-holidays"]')).toBeInViewport();
});

test('sur téléphone, le menu déroulant présente le même découpage', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await login(page, 'prod@example.com');

  await expect(page.locator('[data-test="nav-timesheet"]')).not.toBeInViewport();
  await page.click('[data-test="sidebar-toggle"]');

  await expect(page.locator('[data-test="nav-timesheet"]')).toBeInViewport();
  await expect(page.locator('[data-test="nav-timesheet"]')).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('[data-test="nav-admin"] a')).toHaveCount(1);
  await expect(page.locator('[data-test="nav-admin"] [data-test="nav-projects"]')).toBeInViewport();
});

test('l\'entrée de la page affichée est mise en évidence au fil de la navigation', async ({ page }) => {
  await login(page, 'admin@example.com');

  await page.click('[data-test="nav-projects"]');
  await expect(page.locator('[data-test="nav-projects"]')).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('#app-sidebar [aria-current="page"]')).toHaveCount(1);

  await page.click('[data-test="nav-holidays"]');
  await expect(page.locator('[data-test="nav-holidays"]')).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('[data-test="nav-projects"]')).not.toHaveAttribute('aria-current', 'page');
});
