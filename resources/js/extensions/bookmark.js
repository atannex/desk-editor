import { Node } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

/** Rich link preview card built from a page's Open Graph data. */
export const Bookmark = Node.create({
    name: 'bookmark',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            url: { default: null },
            title: { default: null },
            description: { default: null },
            image: { default: null },
            site: { default: null },
            favicon: { default: null },
        };
    },

    parseHTML() {
        return [{
            tag: 'a[data-type="bookmark"]',
            priority: 100, // before the Link mark's a[href] rule
            getAttrs: (el) => ({
                url: el.getAttribute('href'),
                title: el.querySelector('.de-bookmark-title')?.textContent || el.getAttribute('href'),
                description: el.querySelector('.de-bookmark-desc')?.textContent || null,
                image: el.querySelector('.de-bookmark-image')?.getAttribute('src') || null,
                site: el.querySelector('.de-bookmark-site')?.textContent || null,
                favicon: el.querySelector('.de-bookmark-favicon')?.getAttribute('src') || null,
            }),
        }];
    },

    renderHTML({ node }) {
        const { url, title, description, image, site, favicon } = node.attrs;
        const body = ['span', { class: 'de-bookmark-body' },
            ['span', { class: 'de-bookmark-title' }, title || url],
            ...(description ? [['span', { class: 'de-bookmark-desc' }, description]] : []),
            ['span', { class: 'de-bookmark-meta' },
                ...(favicon ? [['img', { class: 'de-bookmark-favicon', src: favicon, alt: '', loading: 'lazy' }]] : []),
                ['span', { class: 'de-bookmark-site' }, site || ''],
            ],
        ];

        return ['a', { 'data-type': 'bookmark', class: 'de-bookmark', href: url, target: '_blank', rel: 'noopener noreferrer' },
            body,
            ...(image ? [['img', { class: 'de-bookmark-image', src: image, alt: '', loading: 'lazy' }]] : []),
        ];
    },

    addCommands() {
        return {
            setBookmark: (attrs) => insertBlock({ type: this.name, attrs }),
        };
    },
});
