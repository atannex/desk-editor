import { expect, test } from '@playwright/test';
import { expectAccessible, login, trackErrors } from './helpers.mjs';

test.describe('admin & public site', () => {
    test('article list renders and is accessible', async ({ page }) => {
        await login(page);
        await expect(page.getByRole('heading', { name: 'Articles' })).toBeVisible();
        await expectAccessible(page);
    });

    test('blog home and article render rich content', async ({ page }) => {
        const errors = trackErrors(page);
        await page.goto('/blog');
        await expect(page.locator('h1, h2').first()).toBeVisible();
        await expectAccessible(page);

        await page.goto('/blog/introducing-desk-editor');
        await expect(page.locator('h1')).toHaveText('Introducing Desk Editor');
        await expect(page.locator('.desk-content .de-chart canvas')).toBeVisible();
        await expect(page.locator('.de-footnotes li')).toHaveCount(1);
        await expect(page.locator('.desk-content .de-columns')).toBeVisible();
        await expectAccessible(page, { exclude: ['.de-chart canvas'] });
        expect(errors).toEqual([]);
    });

    test('lightbox opens on content images', async ({ page }) => {
        await login(page);
        await page.goto('/blog/introducing-desk-editor');
        const img = page.locator('.desk-content img').first();
        if (await img.count()) {
            await img.click();
            await expect(page.locator('.de-lightbox')).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(page.locator('.de-lightbox')).toHaveCount(0);
        }
    });

    for (const path of ['/desk/media', '/desk/comments', '/desk/analytics', '/desk/categories', '/desk/tags']) {
        test(`${path} loads without errors and is accessible`, async ({ page }) => {
            const errors = trackErrors(page);
            await login(page);
            await page.goto(path);
            await expect(page.locator('main, [role=main]').first()).toBeVisible();
            await expectAccessible(page);
            expect(errors).toEqual([]);
        });
    }
});
