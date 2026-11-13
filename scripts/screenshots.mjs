// Captures the README screenshots from a running workbench (see README → Development).
// Usage: node scripts/screenshots.mjs [baseUrl]
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const base = process.argv[2] || 'http://127.0.0.1:8765';
const out = new URL('../art/screenshots/', import.meta.url).pathname;
mkdirSync(out, { recursive: true });

const browser = await chromium.launch();

async function session({ theme = 'light', mobile = false, as = null } = {}) {
    const context = await browser.newContext({
        baseURL: base,
        viewport: mobile ? { width: 390, height: 844 } : { width: 1440, height: 900 },
        deviceScaleFactor: mobile ? 2 : 1.5,
        isMobile: mobile,
        hasTouch: mobile,
        colorScheme: theme,
        reducedMotion: 'reduce',
    });
    await context.addInitScript((t) => {
        try { localStorage.setItem('desk-editor-theme', t); } catch {}
    }, theme);
    const page = await context.newPage();
    await page.goto(`/workbench/login${as ? `?as=${as}` : ''}`);
    return page;
}

const desk = async (page) => {
    await page.waitForFunction(() => document.querySelector('.desk-editor.is-ready'));
    await page.evaluate(() => window.DeskEditor.get(document.querySelector('.desk-editor')).ready);
    await page.waitForTimeout(300);
};
const only = (process.argv[3] || '').split(',').filter(Boolean);
const shot = async (page, name, options = {}) => {
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${out}${name}.png`, ...options });
    console.log('✓', name);
};
const run = (page, fn, arg) => page.evaluate(fn, arg);

// 1–2. Editor, light and dark, with rich content.
for (const theme of ['light', 'dark']) {
    const page = await session({ theme });
    await page.goto('/desk/articles/1/edit');
    await desk(page);
    await run(page, () => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        const h2 = d.editor.view.dom.querySelector('h2');
        h2.scrollIntoView({ block: 'start' });
        window.scrollBy(0, -170);
    });
    await shot(page, `editor-${theme}`);
    await page.context().close();
}

// 3. Slash command menu.
{
    const page = await session();
    await page.goto('/desk/articles/2/edit');
    await desk(page);
    await run(page, () => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        const p = d.editor.view.dom.querySelectorAll('p')[0];
        const pos = d.editor.view.posAtDOM(p, p.childNodes.length);
        d.editor.chain().focus().setTextSelection(pos).insertContentAt(pos, { type: 'paragraph' }).setTextSelection(pos + 1).run();
        d.editor.view.dom.querySelectorAll('p')[1].scrollIntoView({ block: 'center' });
        window.scrollBy(0, 160);
        window.scrollBy(0, -120);
    });
    await page.keyboard.type('/');
    await page.waitForSelector('.de-slash');
    await shot(page, 'slash-menu');
    await page.context().close();
}

// 4. Bubble menu on a text selection.
{
    const page = await session();
    await page.goto('/desk/articles/2/edit');
    await desk(page);
    await run(page, () => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        let from = null;
        d.editor.state.doc.descendants((n, p) => { if (from === null && n.isText && n.text.includes('Great writing tools')) from = p; });
        d.editor.view.nodeDOM(from)?.parentElement?.scrollIntoView({ block: 'center' });
        d.editor.chain().focus().setTextSelection({ from, to: from + 19 }).run();
    });
    await page.waitForSelector('.de-bubble');
    await shot(page, 'bubble-menu');
    await page.context().close();
}

// 5. Diagram editor dialog.
{
    const page = await session({ theme: 'dark' });
    await page.goto('/desk/articles/2/edit');
    await desk(page);
    await run(page, () => { DeskEditor.get(document.querySelector('.desk-editor')).dialogs.diagram(); });
    await page.waitForSelector('.de-diagram-preview svg', { timeout: 15000 });
    await shot(page, 'diagram-editor');
    await page.context().close();
}

// 6. Chart editor dialog.
{
    const page = await session();
    await page.goto('/desk/articles/2/edit');
    await desk(page);
    await run(page, () => { DeskEditor.get(document.querySelector('.desk-editor')).dialogs.chart(); });
    await page.waitForSelector('.de-dialog canvas');
    await page.waitForTimeout(800);
    await shot(page, 'chart-editor');
    await page.context().close();
}

// 7. Comments panel + suggestions.
{
    const page = await session();
    await page.goto('/desk/articles/4/edit');
    await desk(page);
    await run(page, async () => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        const e = d.editor;
        let from = null;
        e.state.doc.descendants((n, p) => { if (from === null && n.isText && n.text.startsWith('Great writing tools')) from = p; });
        e.commands.setTextSelection({ from, to: from + 19 });
        d.comments.startThread();
        await new Promise((r) => setTimeout(r, 200));
        d.comments.list.querySelector('textarea').value = 'Can we make this opening punchier?';
        d.comments.list.querySelector('.de-button-primary').click();
        await new Promise((r) => setTimeout(r, 900));
        const view = e.view;
        d.toggleSuggestionMode();
        const text = e.state.doc.textBetween(from, from + 200);
        const at = from + text.indexOf('disappear');
        view.someProp('handleTextInput', (f) => f(view, at, at, 'quietly '));
        const t2 = e.state.doc.textBetween(from, from + 220);
        const del = from + t2.indexOf('while you work');
        e.commands.setTextSelection({ from: del, to: del + 'while you work'.length });
        view.someProp('handleKeyDown', (f) => f(view, new KeyboardEvent('keydown', { key: 'Backspace' })));
        e.commands.setTextSelection(at + 3);
        view.nodeDOM(from)?.parentElement?.scrollIntoView({ block: 'center' });
        window.scrollBy(0, -60);
    });
    await shot(page, 'comments-suggestions');
    await page.context().close();
}

// 8. Image editor.
{
    const page = await session({ theme: 'dark' });
    await page.goto('/desk/articles/3/edit');
    await desk(page);
    const pos = await run(page, async () => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        const cover = document.querySelector('[data-cover-input]')?.value;
        d.editor.chain().focus('start').insertContent({ type: 'image', attrs: { src: cover, alt: 'Cover' } }).run();
        let pos = null;
        d.editor.state.doc.descendants((n, p) => { if (pos === null && n.type.name === 'image') pos = p; });
        return pos;
    });
    await run(page, async (p) => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        d.editor.commands.setNodeSelection(p);
        await new Promise((r) => setTimeout(r, 400));
        [...document.querySelectorAll('.de-bubble button')].find((b) => b.getAttribute('aria-label') === 'Edit image')?.click();
    }, pos);
    await page.waitForSelector('.de-image-editor canvas');
    await page.waitForTimeout(800);
    await shot(page, 'image-editor');
    await page.context().close();
}

// 9. Live collaboration (two writers).
{
    const a = await session();
    const b = await session({ as: 'grace' });
    await a.goto('/desk/articles/5/edit');
    await desk(a);
    await b.goto('/desk/articles/5/edit');
    await desk(b);
    await b.waitForTimeout(1500);
    await b.bringToFront();
    await run(b, () => {
        const d = DeskEditor.get(document.querySelector('.desk-editor'));
        const p = d.editor.view.dom.querySelector('p');
        d.editor.chain().focus().setTextSelection(d.editor.view.posAtDOM(p, 0) + 30).run();
    });
    await b.waitForFunction(() => document.activeElement?.classList.contains('ProseMirror'));
    await b.waitForTimeout(300);
    await b.keyboard.type('(Grace is editing here) ');
    await a.waitForTimeout(1500);
    await run(a, () => { document.querySelector('.desk-editor .ProseMirror h2').scrollIntoView({ block: 'start' }); window.scrollBy(0, -170); });
    await shot(a, 'collaboration');
    await a.context().close();
    await b.context().close();
}

// Seed the media library with a few generated images so it isn't empty.
{
    const page = await session();
    await page.goto('/desk/articles');
    await run(page, async () => {
        const token = document.querySelector('meta[name=csrf-token]').content;
        const palettes = [['#6366f1', '#ec4899'], ['#10b981', '#3b82f6'], ['#f59e0b', '#ef4444'], ['#8b5cf6', '#06b6d4'], ['#0ea5e9', '#a855f7'], ['#f97316', '#e11d48'], ['#14b8a6', '#6366f1'], ['#84cc16', '#10b981']];
        const names = ['team-offsite', 'product-launch', 'dashboard-hero', 'night-city', 'workspace', 'conference-stage', 'mountain-retreat', 'spring-campaign'];
        for (const [i, [a, b]] of palettes.entries()) {
            const c = document.createElement('canvas');
            c.width = 1600;
            c.height = 1000;
            const g = c.getContext('2d');
            const gr = g.createLinearGradient(0, 0, 1600, 1000);
            gr.addColorStop(0, a);
            gr.addColorStop(1, b);
            g.fillStyle = gr;
            g.fillRect(0, 0, 1600, 1000);
            for (let k = 0; k < 7; k++) {
                g.fillStyle = 'rgba(255,255,255,0.14)';
                g.beginPath();
                g.arc((i * 211 + k * 263) % 1600, (i * 97 + k * 181) % 1000, 120 + k * 30, 0, Math.PI * 2);
                g.fill();
            }
            const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.88));
            const body = new FormData();
            body.append('file', new File([blob], `${names[i]}.jpg`, { type: 'image/jpeg' }));
            body.append('kind', 'image');
            await fetch('/desk-editor/upload', { method: 'POST', body, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token } });
        }
    });
    await page.context().close();
}

// 10–14. Admin & public pages.
for (const [name, path, theme, prep] of [
    ['articles-dashboard', '/desk/articles', 'light'],
    ['media-library', '/desk/media', 'dark'],
    ['analytics', '/desk/analytics', 'light'],
    ['blog-home', '/blog', 'dark'],
    ['blog-article', '/blog/introducing-desk-editor', 'light', async (page) => {
        await page.waitForSelector('.de-chart canvas');
        await run(page, () => { document.querySelector('.desk-content .de-columns').scrollIntoView({ block: 'start' }); window.scrollBy(0, -140); });
    }],
]) {
    const page = await session({ theme });
    await page.goto(path);
    if (prep) await prep(page);
    await shot(page, name);
    await page.context().close();
}

// 15. Mobile editor.
{
    const page = await session({ mobile: true });
    await page.goto('/desk/articles/1/edit');
    await desk(page);
    await run(page, () => { document.querySelector('.desk-editor').scrollIntoView({ block: 'start' }); window.scrollBy(0, -60); });
    await shot(page, 'mobile-editor');
    await page.context().close();
}

await browser.close();
