import { test as base, expect, type Page } from '@playwright/test';

const KEYCLOAK_USER = process.env.E2E_USER ?? 'maya-admin';
const KEYCLOAK_PASSWORD = process.env.E2E_PASSWORD ?? 'maya-admin';

export async function loginViaKeycloak(page: Page): Promise<void> {
  await page.goto('/');

  await page.waitForLoadState('networkidle');

  const onKeycloak = /\/realms\//.test(page.url());
  if (onKeycloak) {
    await page.getByLabel(/username|usuario/i).fill(KEYCLOAK_USER);
    await page.getByLabel(/password|contraseña/i).fill(KEYCLOAK_PASSWORD);
    await page.getByRole('button', { name: /sign in|entrar|iniciar/i }).click();
  }

  await expect(page).toHaveURL(/\/(documents|dashboard)/, { timeout: 15_000 });
}

export const test = base.extend<{ authenticatedPage: Page }>({
  authenticatedPage: async ({ page }, use) => {
    await loginViaKeycloak(page);
    await use(page);
  },
});

export { expect };
