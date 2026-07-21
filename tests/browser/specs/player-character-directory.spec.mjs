import { expect, test } from '@playwright/test';
import { gotoWithLocalNetworkRetry } from '../support/navigation.mjs';

const email = process.env.PLAYWRIGHT_PLAYER_EMAIL || 'browser-player@example.test';
const password = process.env.PLAYWRIGHT_PLAYER_PASSWORD || 'BrowserPlayerPassword123!';

const signIn = async (page) => {
    await gotoWithLocalNetworkRetry(page, '/login');
    await page.locator('#login').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('form').getByRole('button').click();
    await expect(page).toHaveURL(/\/account$/);
};

test('player character display mode persists after reload', async ({ page }) => {
    await signIn(page);

    const grouped = page.getByRole('tab', { name: 'По серверам' });
    const all = page.getByRole('tab', { name: 'Все персонажи' });

    await expect(grouped).toHaveAttribute('aria-selected', 'true');
    await all.click();
    await expect(all).toHaveAttribute('aria-selected', 'true');

    await page.reload();
    await expect(all).toHaveAttribute('aria-selected', 'true');
});

test('player shell persists during account navigation and browser history', async ({ page }) => {
    await signIn(page);

    const sidebar = page.locator('[data-account-sidebar]');
    const topbar = page.locator('[data-account-topbar]');
    await sidebar.evaluate((element) => {
        element.dataset.persistenceProbe = 'sidebar-kept';
    });
    await topbar.evaluate((element) => {
        element.dataset.persistenceProbe = 'topbar-kept';
    });

    await page.locator('.account-nav').getByRole('link', { name: 'Игровые аккаунты' }).click();
    await expect(page).toHaveURL(/\/account\/game-accounts$/);
    await expect(sidebar).toHaveAttribute('data-persistence-probe', 'sidebar-kept');
    await expect(topbar).toHaveAttribute('data-persistence-probe', 'topbar-kept');

    await page.getByRole('link', { name: /Подробнее|View details/ }).first().click();
    await expect(page).toHaveURL(/\/account\/game-accounts\/\d+$/);
    await expect(sidebar).toHaveAttribute('data-persistence-probe', 'sidebar-kept');
    await expect(topbar).toHaveAttribute('data-persistence-probe', 'topbar-kept');

    await page.goBack();
    await expect(page).toHaveURL(/\/account\/game-accounts$/);
    await expect(sidebar).toHaveAttribute('data-persistence-probe', 'sidebar-kept');

    await page.goBack();
    await expect(page).toHaveURL(/\/account$/);
    await expect(topbar).toHaveAttribute('data-persistence-probe', 'topbar-kept');
});

test('luxury player theme remains reactive after SPA navigation', async ({ page }) => {
    await signIn(page);

    await expect(page.locator('link[href*="account-themes/luxury/assets/css/app.css"]')).toHaveCount(1);
    await expect(page.locator('.account-hero')).toBeVisible();
    await expect(page.locator('.account-future-balance')).toContainText(/Монеты|Coins/);

    await page.locator('.account-nav').getByRole('link', { name: 'Игровые аккаунты' }).click();
    await expect(page).toHaveURL(/\/account\/game-accounts$/);
    await expect(page.locator('.game-account-card').first()).toBeVisible();

    await page.locator('.account-nav').getByRole('link', { name: 'Обзор' }).click();
    await expect(page).toHaveURL(/\/account$/);

    const allCharacters = page.getByRole('tab', { name: 'Все персонажи' });
    await allCharacters.click();
    await expect(allCharacters).toHaveAttribute('aria-selected', 'true');
});

test('player web inventory is available from the persistent account shell', async ({ page }) => {
    await signIn(page);

    await page.locator('.account-nav').getByRole('link', { name: 'Веб-инвентарь' }).click();

    await expect(page).toHaveURL(/\/account\/web-inventory$/);
    await expect(page.getByRole('heading', { name: 'Веб-инвентарь', exact: true })).toBeVisible();
    await expect(page.getByText('Ваш веб-инвентарь пуст')).toBeVisible();
    await expect(page.locator('[data-account-sidebar]')).toBeVisible();
    await expect(page.locator('[data-account-topbar]')).toBeVisible();
});
