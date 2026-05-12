const { test, expect } = require('@playwright/test');
const { resetDatabase } = require('../helpers/db');

test.describe('Parcours admin guichet', () => {
    test.beforeAll(() => {
        resetDatabase();
    });

    test('Login admin → créer une réservation au guichet → imprimer les billets', async ({ page }) => {
        // 1. Login
        await page.goto('/login');
        await page.locator('#email').fill('l.zerri@gmail.com');
        await page.locator('#password').fill('password');
        await page.getByRole('button', { name: /se connecter|connexion|login/i }).click();

        await expect(page).toHaveURL(/\/admin\//);

        // 2. Aller sur la liste des réservations
        await page.goto('/admin/reservations');
        await expect(page.getByRole('heading', { name: 'Réservations', exact: true })).toBeVisible();

        // 3. Créer une nouvelle réservation
        await page.getByRole('link', { name: 'Nouvelle réservation' }).click();
        await expect(page).toHaveURL(/\/admin\/reservations\/new/);

        // 4. Choisir une représentation, remplir le formulaire
        await page.locator('#admin_reservation_representation').selectOption({ index: 1 });
        await page.locator('#admin_reservation_nbAdults').fill('2');
        await page.locator('#admin_reservation_spectatorLastName').fill('Lefevre');
        await page.locator('#admin_reservation_spectatorFirstName').fill('Pierre');
        await page.locator('#admin_reservation_spectatorCity').fill('Saumur');
        await page.locator('#admin_reservation_spectatorPhone').fill('0612345678');
        await page.locator('#admin_reservation_spectatorEmail').fill('pierre.lefevre@example.test');

        await page.getByRole('button', { name: /Créer la réservation/i }).click();

        // 5. La création redirige sur la page d'édition de la réservation
        await expect(page).toHaveURL(/\/admin\/reservations\/\d+\/edit/);
        await expect(page.locator('input[value="Lefevre"]')).toBeVisible();

        // 6. Le bouton "Imprimer les billets" ouvre la page d'impression dans un nouvel onglet
        const printPagePromise = page.context().waitForEvent('page');
        await page.getByRole('link', { name: 'Imprimer les billets' }).click();
        const printPage = await printPagePromise;
        await printPage.waitForLoadState('domcontentloaded');

        await expect(printPage).toHaveURL(/\/admin\/reservations\/\d+\/print/);
        await expect(printPage.getByText("Les Mathu'Loire").first()).toBeVisible();
        await expect(printPage.getByText('LEFEVRE Pierre').first()).toBeVisible();
    });

    test('Réservation téléphone : email, téléphone et ville peuvent être vides en saisie admin', async ({ page }) => {
        await page.goto('/login');
        await page.locator('#email').fill('l.zerri@gmail.com');
        await page.locator('#password').fill('password');
        await page.getByRole('button', { name: /se connecter/i }).click();
        await expect(page).toHaveURL(/\/admin\//);

        await page.goto('/admin/reservations/new');
        await page.locator('#admin_reservation_representation').selectOption({ index: 1 });
        await page.locator('#admin_reservation_nbAdults').fill('1');
        await page.locator('#admin_reservation_spectatorLastName').fill('Renard');
        await page.locator('#admin_reservation_spectatorFirstName').fill('Marie');
        // city / phone / email volontairement laissés vides

        await page.getByRole('button', { name: /Créer la réservation/i }).click();

        await expect(page).toHaveURL(/\/admin\/reservations\/\d+\/edit/);
        await expect(page.locator('input[value="Renard"]')).toBeVisible();
        await expect(page.locator('#admin_reservation_spectatorEmail')).toHaveValue('');
        await expect(page.locator('#admin_reservation_spectatorPhone')).toHaveValue('');
        await expect(page.locator('#admin_reservation_spectatorCity')).toHaveValue('');

        // Le bouton "Renvoyer l'email" doit être absent puisqu'il n'y a pas d'email
        await expect(page.getByRole('button', { name: /Renvoyer l'email/i })).toHaveCount(0);
    });
});
