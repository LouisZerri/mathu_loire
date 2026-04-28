const { test, expect } = require('@playwright/test');
const { resetDatabase, findEmailTo } = require('../helpers/db');

test.describe('Parcours public de réservation', () => {
    test.beforeAll(() => {
        resetDatabase();
    });

    test('Un spectateur réserve 2 places adultes et paye via HelloAsso (mock)', async ({ page }) => {
        // 1. Page d'accueil de la billetterie
        await page.goto('/billetterie/');
        await expect(page.getByRole('heading', { name: 'Billetterie' })).toBeVisible();

        // 2. Choisir le premier spectacle disponible
        await page.locator('a[href^="/spectacle/"]').first().click();
        await expect(page).toHaveURL(/\/spectacle\/\d+/);

        // 3. Cliquer sur le premier bouton "Réserver" d'une représentation active
        await page.locator('main a:visible').filter({ hasText: /^Réserver$/i }).first().click();
        await expect(page).toHaveURL(/\/billetterie\/\d+$/);

        // 4. Remplir le formulaire de réservation
        await page.locator('#reservation_nbAdults').fill('2');
        await page.locator('#reservation_spectatorLastName').fill('Dupont');
        await page.locator('#reservation_spectatorFirstName').fill('Jean');
        await page.locator('#reservation_spectatorCity').fill('Saint-Mathurin');
        await page.locator('#reservation_spectatorPhone').fill('0612345678');
        await page.locator('#reservation_spectatorEmail').fill('jean.dupont@example.test');
        await page.locator('#reservation_rgpdConsent').check();

        await page.getByRole('button', { name: 'Confirmer la réservation' }).click();

        // 5. Page récapitulatif
        await expect(page).toHaveURL(/\/billetterie\/recapitulatif\/\d+/);
        await expect(page.getByText('Récapitulatif de votre réservation')).toBeVisible();
        await expect(page.getByText('jean.dupont@example.test')).toBeVisible();

        // 6. Cliquer sur "Payer en ligne" → mock HelloAsso renvoie tout droit sur /retour/{id}
        await page.getByRole('link', { name: /Payer en ligne/ }).click();

        // 7. Page de confirmation
        await expect(page).toHaveURL(/\/billetterie\/confirmation\/\d+\/[a-f0-9]+/);
        await expect(page.getByText(/confirm|merci|réservation/i).first()).toBeVisible();

        // 8. Un email de confirmation a été envoyé au spectateur
        const emails = findEmailTo('jean.dupont@example.test');
        expect(emails.length).toBeGreaterThanOrEqual(1);
        expect(emails[0].subject).toMatch(/confirmation|réservation/i);
    });

    test('Un spectateur réserve avec paiement au guichet', async ({ page }) => {
        await page.goto('/billetterie/');
        await page.locator('a[href^="/spectacle/"]').first().click();

        await page.locator('main a:visible').filter({ hasText: /^Réserver$/i }).first().click();

        await page.locator('#reservation_nbAdults').fill('1');
        await page.locator('#reservation_spectatorLastName').fill('Martin');
        await page.locator('#reservation_spectatorFirstName').fill('Sophie');
        await page.locator('#reservation_spectatorCity').fill('Angers');
        await page.locator('#reservation_spectatorPhone').fill('0698765432');
        await page.locator('#reservation_spectatorEmail').fill('sophie.martin@example.test');
        await page.locator('#reservation_rgpdConsent').check();

        await page.getByRole('button', { name: 'Confirmer la réservation' }).click();
        await expect(page).toHaveURL(/\/billetterie\/recapitulatif\/\d+/);

        await page.getByRole('button', { name: /paiement au guichet/i }).click();

        await expect(page).toHaveURL(/\/billetterie\/confirmation\/\d+\/[a-f0-9]+/);

        const emails = findEmailTo('sophie.martin@example.test');
        expect(emails.length).toBeGreaterThanOrEqual(1);
    });
});
