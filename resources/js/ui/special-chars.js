import { h } from './dom.js';
import { Popover } from './popover.js';

const SETS = {
    Common: '© ® ™ § ¶ † ‡ • · … ‰ ° ′ ″ № ℃ ℉ µ ¿ ¡ « » ‹ › “ ” ‘ ’ „ – —',
    Currency: '$ € £ ¥ ¢ ₹ ₽ ₩ ₪ ₦ ₱ ₫ ₴ ₸ ₺ ₿ ¤ ƒ ₣ ₡',
    Math: '± × ÷ = ≠ ≈ ≡ < > ≤ ≥ ∞ √ ∛ ∑ ∏ ∫ ∂ ∆ ∇ ∈ ∉ ⊂ ⊃ ∪ ∩ ∧ ∨ ¬ ∀ ∃ ∅ ⊕ ⊗ ∝ ∠ ⊥ ∥ ½ ⅓ ⅔ ¼ ¾ ⅛ ¹ ² ³ ⁴ ⁿ',
    Arrows: '← → ↑ ↓ ↔ ↕ ⇐ ⇒ ⇑ ⇓ ⇔ ↖ ↗ ↘ ↙ ↩ ↪ ⟵ ⟶ ⟷ ➔ ➜ ➤ ⤴ ⤵',
    Greek: 'α β γ δ ε ζ η θ ι κ λ μ ν ξ ο π ρ σ τ υ φ χ ψ ω Α Β Γ Δ Θ Λ Ξ Π Σ Φ Ψ Ω',
    Latin: 'À Á Â Ã Ä Å Æ Ç È É Ê Ë Ì Í Î Ï Ñ Ò Ó Ô Õ Ö Ø Ù Ú Û Ü Ý ß à á â ã ä å æ ç è é ê ë ì í î ï ñ ò ó ô õ ö ø ù ú û ü ý ÿ Œ œ Š š Ž ž',
    Symbols: '✓ ✔ ✗ ✘ ★ ☆ ♥ ♦ ♣ ♠ ☀ ☁ ☂ ☎ ✉ ✎ ✂ ⚑ ⚠ ☑ ☐ ♪ ♫ ⌘ ⌥ ⇧ ⌫ ⏎ ⎋',
};

export function specialCharsPopover(desk, anchor) {
    const { editor, t } = desk;
    const tabs = h('div', { class: 'de-emoji-tabs de-chars-tabs' });
    const grid = h('div', { class: 'de-chars-grid' });
    const preview = h('div', { class: 'de-chars-preview', 'aria-live': 'polite' });
    let current = 'Common';

    const render = () => {
        grid.replaceChildren(...SETS[current].split(' ').map((char) => h('button', {
            type: 'button',
            class: 'de-char',
            text: char,
            title: `U+${char.codePointAt(0).toString(16).toUpperCase().padStart(4, '0')}`,
            onMouseenter: () => (preview.textContent = `${char}  U+${char.codePointAt(0).toString(16).toUpperCase().padStart(4, '0')}`),
            onClick: () => {
                editor.chain().focus().insertContent(char).run();
                popover.close();
            },
        })));
        tabs.querySelectorAll('button').forEach((b) => b.classList.toggle('is-active', b.dataset.set === current));
    };
    for (const name of Object.keys(SETS)) {
        tabs.append(h('button', { type: 'button', class: 'de-tab', dataset: { set: name }, text: t(`chars${name}`), onClick: () => {
            current = name;
            render();
        } }));
    }
    render();
    const popover = new Popover(desk, { anchor, content: h('div', { class: 'de-chars-panel' }, [tabs, grid, preview]) });
}
