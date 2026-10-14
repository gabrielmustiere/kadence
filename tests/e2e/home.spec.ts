import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

// Le serveur tourne à la date réelle et les fixtures tirent au hasard les journées oubliées : la personne de ce
// scénario, copiée de prod@example.com en SQL, n'a saisi aucun temps. Ses jours ouvrés passés sont donc incomplets.
const email = `e2e-home-${Date.now().toString(36)}@example.com`;

function sql(query: string) {
  execFileSync('symfony', ['console', 'dbal:run-sql', query]);
}

test.beforeAll(() => {
  sql(`INSERT INTO "user" (email, password, first_name, last_name, role, active, must_change_password, holiday_calendar)
       SELECT '${email}', password, 'Hugo', 'Accueil', role, 1, 0, holiday_calendar FROM "user" WHERE email = 'prod@example.com'`);
});

test.afterAll(() => {
  sql(`DELETE FROM "user" WHERE email = '${email}'`);
});

test('depuis l\'accueil, une journée incomplète ouvre Ma semaine à la bonne semaine', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="_username"]', email);
  await page.fill('input[name="_password"]', 'password');
  await page.click('button[type="submit"]');
  await expect(page.locator('h1')).toContainText('Ma semaine');

  await page.click('[data-test="nav-home"]');
  await expect(page.locator('h1')).toHaveText('Accueil');
  const day = page.locator('[data-test="home-forgotten-day"]').first();
  const date = await day.getAttribute('data-day');
  await day.locator('a').click();

  await expect(page.locator('h1')).toContainText('Ma semaine');
  await expect(page.locator(`[data-test="day-header"][data-day="${date}"]`)).toHaveAttribute('data-forgotten', 'true');
});
