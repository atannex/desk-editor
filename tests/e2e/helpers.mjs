import AxeBuilder from '@axe-core/playwright';
import { expect } from '@playwright/test';

export async function login(page) {
    await page.goto('/workbench/login');
    await expect(page).toHaveURL(/\/desk\/articles/);
}

export function trackErrors(page) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (msg) => {
        if (msg.type() === 'error' && !/favicon|ERR_BLOCKED|net::/.test(msg.text())) errors.push(msg.text());
    });
    return errors;
}

/** Fails on serious/critical accessibility violations. */
export async function expectAccessible(page, { exclude = [] } = {}) {
    // Audit the settled page, not mid-animation frames (fade-ins lower contrast temporarily).
    await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running' || a.effect?.getTiming().iterations === Infinity), null, { timeout: 5000 }).catch(() => {});
    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
    for (const selector of exclude) builder = builder.exclude(selector);
    const results = await builder.analyze();
    const serious = results.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
    expect(serious.map((v) => `${v.id}: ${v.help} (${v.nodes.length}) → ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);
}

export async function editorReady(page) {
    await page.waitForFunction(() => window.DeskEditor && document.querySelector('.desk-editor.is-ready'));
    return page.locator('.desk-editor .ProseMirror');
}
