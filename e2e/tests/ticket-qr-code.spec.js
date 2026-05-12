const { test, expect } = require('@playwright/test');
const { resetDatabase } = require('../helpers/db');

test.describe('QR codes sur les billets', () => {
    test.beforeAll(() => {
        resetDatabase();
    });

    test('La page d\'impression affiche un QR code par billet', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('l.zerri@gmail.com');
        await page.locator('#password').fill('password');
        await page.getByRole('button', { name: /se connecter/i }).click();
        await expect(page).toHaveURL(/\/admin\//);

        await page.goto('/admin/reservations/new');
        await page.locator('#admin_reservation_representation').selectOption({ index: 1 });
        await page.locator('#admin_reservation_nbAdults').fill('3');
        await page.locator('#admin_reservation_spectatorLastName').fill('Bernard');
        await page.locator('#admin_reservation_spectatorFirstName').fill('Claire');
        await page.getByRole('button', { name: /Créer la réservation/i }).click();
        await expect(page).toHaveURL(/\/admin\/reservations\/\d+\/edit/);

        const printPagePromise = page.context().waitForEvent('page');
        await page.getByRole('link', { name: 'Imprimer les billets' }).click();
        const printPage = await printPagePromise;
        await printPage.waitForLoadState('domcontentloaded');

        await expect(printPage).toHaveURL(/\/admin\/reservations\/\d+\/print/);

        // 3 adultes → 3 billets → 3 QR codes
        await expect(printPage.locator('.qr-block img')).toHaveCount(3);

        // Le QR est bien un data URI PNG (pas un placeholder cassé)
        const firstQr = await printPage.locator('.qr-block img').first().getAttribute('src');
        expect(firstQr).toMatch(/^data:image\/png;base64,/);
    });

    test('Un HMAC invalide affiche l\'écran "Code invalide"', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('l.zerri@gmail.com');
        await page.locator('#password').fill('password');
        await page.getByRole('button', { name: /se connecter/i }).click();
        await expect(page).toHaveURL(/\/admin\//);

        await page.goto('/admin/scan/1-1/deadbeefdeadbeef');
        await expect(page.getByRole('heading', { name: 'Code invalide' })).toBeVisible();
    });
});
