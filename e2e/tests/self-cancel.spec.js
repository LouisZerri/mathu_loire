const { test, expect } = require('@playwright/test');
const { resetDatabase, findEmailTo } = require('../helpers/db');

test.describe('Annulation par le spectateur via /suivi/{id}/{token}', () => {
    test.beforeAll(() => {
        resetDatabase();
    });

    test('Le spectateur consulte sa réservation puis l\'annule lui-même', async ({ page }) => {
        // 1. Créer une réservation au guichet (sans paiement HelloAsso, plus simple)
        await page.goto('/billetterie/');
        await page.locator('a[href^="/spectacle/"]').first().click();
        await page.locator('main a:visible').filter({ hasText: /^Réserver$/i }).first().click();

        await page.locator('#reservation_nbAdults').fill('1');
        await page.locator('#reservation_spectatorLastName').fill('Bernard');
        await page.locator('#reservation_spectatorFirstName').fill('Claire');
        await page.locator('#reservation_spectatorCity').fill('Angers');
        await page.locator('#reservation_spectatorPhone').fill('0698765432');
        await page.locator('#reservation_spectatorEmail').fill('claire.bernard@example.test');
        await page.locator('#reservation_rgpdConsent').check();
        await page.getByRole('button', { name: 'Confirmer la réservation' }).click();

        await page.getByRole('button', { name: /paiement au guichet/i }).click();

        // 2. Récupérer id + token depuis l'URL de confirmation
        await expect(page).toHaveURL(/\/billetterie\/confirmation\/\d+\/[a-f0-9]+/);
        const match = page.url().match(/\/confirmation\/(\d+)\/([a-f0-9]+)/);
        expect(match).not.toBeNull();
        const [, reservationId, token] = match;

        // 3. Aller sur le lien de suivi
        await page.goto(`/billetterie/suivi/${reservationId}/${token}`);
        await expect(page.getByRole('heading', { name: `Réservation n° ${reservationId}` })).toBeVisible();
        await expect(page.getByText('Confirmée')).toBeVisible();

        // 4. Cliquer "Annuler ma réservation" (gérer le confirm() JS)
        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: /Annuler ma réservation/i }).click();

        // 5. La page doit afficher la résa en statut "Annulée"
        await expect(page).toHaveURL(/\/billetterie\/suivi\/\d+\/[a-f0-9]+/);
        await expect(page.getByText('Annulée')).toBeVisible();

        // 6. Le spectateur a reçu un email de confirmation puis un email d'annulation
        const emails = findEmailTo('claire.bernard@example.test');
        expect(emails.length).toBeGreaterThanOrEqual(2);
        expect(emails.some(e => /annulation|annulé/i.test(e.subject))).toBe(true);
    });

    test('Tenter d\'annuler avec un mauvais token retourne 404', async ({ page }) => {
        const response = await page.goto('/billetterie/suivi/1/mauvais-token');
        expect(response.status()).toBe(404);
    });
});
