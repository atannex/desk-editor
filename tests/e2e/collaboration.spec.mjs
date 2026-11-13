import { expect, test } from '@playwright/test';

// Requires a running Reverb server and DESK_EDITOR_COLLABORATION=true (see workbench/collab.env.sh).
test.skip(!process.env.DESK_E2E_COLLAB, 'collaboration e2e needs Reverb (set DESK_E2E_COLLAB=1)');

test('two writers co-edit the same article in real time', async ({ browser }) => {
    const ada = await browser.newContext();
    const grace = await browser.newContext();
    const a = await ada.newPage();
    const b = await grace.newPage();
    const errors = [];
    for (const page of [a, b]) page.on('pageerror', (e) => errors.push(e.message));

    await a.goto('/workbench/login');
    await b.goto('/workbench/login?as=grace');

    await a.goto('/desk/articles/3/edit');
    await a.waitForFunction(() => document.querySelector('.desk-editor.is-collaborative'));
    await expect(a.locator('.desk-editor .ProseMirror h2').first()).toBeVisible();
    const original = await a.locator('.desk-editor .ProseMirror').textContent();

    await b.goto('/desk/articles/3/edit');
    await b.waitForFunction(() => document.querySelector('.desk-editor.is-collaborative'));

    // The second writer syncs from the first: no duplicated content.
    await expect.poll(() => b.locator('.desk-editor .ProseMirror').textContent(), { timeout: 10_000 }).toBe(original);

    // Presence: each sees the other.
    await expect(a.locator('.de-peer[title="Grace Hopper"]')).toBeVisible({ timeout: 10_000 });
    await expect(b.locator('.de-peer[title="Demo Writer"]')).toBeVisible({ timeout: 10_000 });

    // Edits flow both ways.
    // Place the caret precisely (the first heading sits under the sticky toolbar), then type for real.
    const caretAt = (page, selector, edge) => page.evaluate(([sel, end]) => {
        const desk = window.DeskEditor.get(document.querySelector('.desk-editor'));
        const dom = desk.editor.view.dom.querySelector(sel);
        const pos = desk.editor.view.posAtDOM(dom, end ? dom.childNodes.length : 0);
        desk.editor.chain().focus().setTextSelection(pos).run();
    }, [selector, edge === 'end']).then(() => page.waitForFunction(() => document.activeElement?.classList.contains('ProseMirror')));
    await a.bringToFront();
    await caretAt(a, 'h2', 'end');
    await a.keyboard.type(' — edited by Ada');
    await expect(b.locator('.desk-editor .ProseMirror h2').first()).toContainText('edited by Ada', { timeout: 10_000 });

    await b.bringToFront();
    await caretAt(b, 'p', 'start');
    await b.keyboard.type('Grace was here. ');
    await expect(b.locator('.desk-editor .ProseMirror p').first()).toContainText('Grace was here.');
    await expect(a.locator('.desk-editor .ProseMirror p').first()).toContainText('Grace was here.', { timeout: 10_000 });

    // Remote caret is rendered with the collaborator's name.
    await expect(a.locator('.collaboration-carets__label', { hasText: 'Grace Hopper' })).toBeVisible();

    // Both documents converge.
    const html = (page) => page.evaluate(() => window.DeskEditor.get(document.querySelector('.desk-editor')).getHTML());
    await expect.poll(async () => (await html(a)) === (await html(b)), { timeout: 10_000 }).toBeTruthy();
    expect(errors).toEqual([]);

    await ada.close();
    await grace.close();
});
