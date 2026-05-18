import { test, expect } from './fixtures/auth';
test.describe('Gestión de documentos', () => {
    test('muestra listado de documentos tras login', async ({ authenticatedPage: page }) => {
        await page.goto('/documents');
        await expect(page.getByRole('heading', { name: /documentos/i })).toBeVisible();
    });
});
