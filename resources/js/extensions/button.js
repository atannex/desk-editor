import { Node } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

export const BUTTON_VARIANTS = ['primary', 'secondary', 'outline', 'ghost'];

/** Call-to-action button block: <div data-type="button" data-align="center"><a class="de-cta" data-variant="primary">…</a></div> */
export const CtaButton = Node.create({
    name: 'ctaButton',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            href: { default: '#' },
            label: { default: 'Get started' },
            variant: { default: 'primary' },
            align: { default: 'center' },
            newTab: { default: false },
        };
    },

    parseHTML() {
        return [{
            tag: 'div[data-type="button"]',
            getAttrs: (el) => {
                const a = el.querySelector('a');
                if (!a) return false;
                return {
                    href: a.getAttribute('href') || '#',
                    label: a.textContent.trim() || 'Button',
                    variant: BUTTON_VARIANTS.includes(a.getAttribute('data-variant')) ? a.getAttribute('data-variant') : 'primary',
                    align: el.getAttribute('data-align') || 'center',
                    newTab: a.getAttribute('target') === '_blank',
                };
            },
        }];
    },

    renderHTML({ node }) {
        const { href, label, variant, align, newTab } = node.attrs;

        return ['div', { 'data-type': 'button', 'data-align': align, class: 'de-cta-wrap' }, ['a', {
            href,
            class: 'de-cta',
            'data-variant': variant,
            ...(newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {}),
        }, label]];
    },

    addCommands() {
        return {
            setCtaButton: (attrs = {}) => insertBlock({ type: this.name, attrs }),
        };
    },
});
