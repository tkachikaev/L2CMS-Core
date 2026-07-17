import { expect, test } from '@playwright/test';

const email = process.env.PLAYWRIGHT_ADMIN_EMAIL || 'browser-admin@example.test';
const password = process.env.PLAYWRIGHT_ADMIN_PASSWORD || 'BrowserPassword123!';

const openMenuGroup = async (page, group) => {
    const details = page.locator(`[data-admin-menu-group="${group}"]`);
    if (!(await details.evaluate((element) => element.open))) {
        await details.locator('summary').click();
    }
};

const signIn = async (page) => {
    await page.goto('/admin/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Войти в панель' }).click();
    await expect(page).toHaveURL(/\/admin$/);
};

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('news editor initializes again after SPA navigation', async ({ page }) => {
    await page.goto('/admin/news/create');

    const canvas = page.locator('#body-editor-ru');
    const source = page.locator('#body_ru');
    await expect(canvas).toBeVisible();

    await canvas.evaluate((element) => {
        element.innerHTML = '<p>Первый текст</p>';
        element.dispatchEvent(new InputEvent('input', { bubbles: true }));
    });
    await expect(source).toHaveValue('<p>Первый текст</p>');

    await openMenuGroup(page, 'system');
    await page.getByRole('link', { name: 'Системная информация' }).click();
    await expect(page).toHaveURL(/\/admin\/settings\/system$/);
    await openMenuGroup(page, 'content');
    await page.getByRole('link', { name: 'Новости' }).click();
    await page.getByRole('link', { name: /Создать/ }).first().click();

    await expect(canvas).toBeVisible();
    await canvas.evaluate((element) => {
        element.innerHTML = '<p>Повторная инициализация</p>';
        element.dispatchEvent(new InputEvent('input', { bubbles: true }));
    });
    await expect(source).toHaveValue('<p>Повторная инициализация</p>');
});

test('two-factor QR is rendered after leaving and returning', async ({ page }) => {
    await page.goto('/admin/account/security');
    await page.locator('#current_password').fill(password);
    await page.getByRole('button', { name: 'Включить 2FA' }).click();

    const qr = page.locator('[data-two-factor-qr] svg');
    await expect(qr).toHaveCount(1);

    await openMenuGroup(page, 'content');
    await page.getByRole('link', { name: 'Новости' }).click();
    await expect(page).toHaveURL(/\/admin\/news$/);
    await page.goBack();

    await expect(page).toHaveURL(/\/admin\/account\/security$/);
    await expect(page.locator('[data-two-factor-qr] svg')).toHaveCount(1);
});

test('persisted sidebar keeps group state during navigation and history changes', async ({ page }) => {
    const siteGroup = page.locator('[data-admin-menu-group="site"]');
    await siteGroup.locator('summary').click();
    await expect(siteGroup).toHaveAttribute('open', '');

    await openMenuGroup(page, 'servers');
    await page.getByRole('link', { name: 'Игровые серверы' }).click();
    await expect(page).toHaveURL(/\/admin\/settings\/game-server$/);
    await expect(siteGroup).toHaveAttribute('open', '');

    await page.goBack();
    await expect(page).toHaveURL(/\/admin$/);
    await expect(siteGroup).toHaveAttribute('open', '');

    await page.goForward();
    await expect(page).toHaveURL(/\/admin\/settings\/game-server$/);
    await expect(siteGroup).toHaveAttribute('open', '');
});

test('game server settings keep fields separated by tabs', async ({ page }) => {
    await page.goto('/admin/settings/game-server');
    await page.getByRole('button', { name: 'Настроить' }).first().click();

    const dialog = page.getByRole('dialog', { name: /L2Server|Игровой сервер|Настройки игрового сервера/ });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('tab', { name: 'Основное' })).toHaveAttribute('aria-selected', 'true');

    await dialog.getByRole('tab', { name: 'Статистика' }).click();
    await expect(dialog.getByRole('tab', { name: 'Статистика' })).toHaveAttribute('aria-selected', 'true');
    await expect(dialog.getByText('Публичная статистика').first()).toBeVisible();

    await dialog.getByRole('tab', { name: 'Разное' }).click();
    await expect(dialog.getByRole('tab', { name: 'Разное' })).toHaveAttribute('aria-selected', 'true');
    await expect(dialog.getByText('Режим обслуживания')).toBeVisible();
    await expect(dialog.getByText('Дополнительные сетевые настройки')).toBeVisible();
});
