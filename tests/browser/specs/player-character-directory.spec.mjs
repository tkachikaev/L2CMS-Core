import { expect, test } from '@playwright/test';

const email = process.env.PLAYWRIGHT_PLAYER_EMAIL || 'browser-player@example.test';
const password = process.env.PLAYWRIGHT_PLAYER_PASSWORD || 'BrowserPlayerPassword123!';

const signIn = async (page) => {
    await page.goto('/login');
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
