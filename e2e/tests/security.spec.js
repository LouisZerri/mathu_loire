const { test, expect } = require('@playwright/test');
const { resetDatabase } = require('../helpers/db');

test.describe('Sécurité', () => {
    test.beforeAll(() => {
        resetDatabase();
    });

    test('Accès à /admin sans login redirige vers /login', async ({ page }) => {
        await page.goto('/admin/');
        await expect(page).toHaveURL(/\/login/);
    });

    test('Accès à la liste des réservations sans login redirige vers /login', async ({ page }) => {
        await page.goto('/admin/reservations');
        await expect(page).toHaveURL(/\/login/);
    });

    test('Login avec mauvais mot de passe affiche une erreur', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('l.zerri@gmail.com');
        await page.locator('#password').fill('mauvais-mot-de-passe');
        await page.getByRole('button', { name: /Se connecter/i }).click();

        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText(/identifiants invalides|invalid credentials|incorrect/i)).toBeVisible();
    });

    test('Une page publique reste accessible sans login', async ({ page }) => {
        await page.goto('/billetterie/');
        await expect(page.getByRole('heading', { name: 'Billetterie' })).toBeVisible();
    });
});
