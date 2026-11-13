import { expect, test } from '@playwright/test';
import { editorReady, expectAccessible, login, trackErrors } from './helpers.mjs';

test.describe('editor', () => {
    test('writes, formats and saves an article', async ({ page, isMobile }) => {
        const errors = trackErrors(page);
        await login(page);
        await page.goto('/desk/articles/create');
        const editor = await editorReady(page);

        await page.locator('textarea[name=title]').fill(`E2E story ${Date.now()}`);
        await editor.click();
        await page.keyboard.type('Hello from Playwright. ');
        await page.keyboard.press('ControlOrMeta+b');
        await page.keyboard.type('Bold words');
        await page.keyboard.press('ControlOrMeta+b');
        await expect(editor.locator('strong')).toHaveText('Bold words');

        // Markdown shortcut + slash command.
        await page.keyboard.press('Enter');
        await page.keyboard.type('## Section heading');
        await page.keyboard.press('Enter');
        await expect(editor.locator('h2')).toHaveText('Section heading');
        await page.keyboard.type('/colu');
        await expect(page.locator('.de-slash')).toBeVisible();
        await page.keyboard.press('Enter');
        await expect(editor.locator('.de-columns')).toBeVisible();
        await page.keyboard.type('Inside a column');

        // Status bar counts words.
        if (!isMobile) await expect(page.locator('.de-statusbar')).toContainText('words');

        await page.locator('button[name=intent][value=save]').click();
        await expect(page).toHaveURL(/\/desk\/articles\/\d+\/edit/);
        await expect(page.locator('.desk-editor .ProseMirror .de-columns')).toContainText('Inside a column');
        expect(errors).toEqual([]);
    });

    test('find & replace, table and undo', async ({ page }) => {
        await login(page);
        await page.goto('/desk/articles/create');
        const editor = await editorReady(page);
        await editor.click();
        await page.keyboard.type('alpha beta alpha');
        await page.keyboard.press('ControlOrMeta+f');
        await page.locator('.de-find input[type=search]').fill('alpha');
        await expect(page.locator('.de-find-count')).toHaveText('1 of 2');
        await page.locator('.de-find input[aria-label=Replace]').fill('gamma');
        await page.getByRole('button', { name: 'Replace all' }).click();
        await expect(editor).toContainText('gamma beta gamma');
        await page.keyboard.press('Escape');
        await editor.click();
        await page.keyboard.press('ControlOrMeta+z');
        await expect(editor).toContainText('alpha beta alpha');
    });

    test('editor page is accessible', async ({ page }) => {
        await login(page);
        await page.goto('/desk/articles/1/edit');
        await editorReady(page);
        // Third-party renderers (charts/diagrams) are excluded from the audit.
        await expectAccessible(page, { exclude: ['.de-chart', '.de-diagram'] });
    });
});
