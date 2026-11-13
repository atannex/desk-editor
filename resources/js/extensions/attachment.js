import { Node } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

export const Attachment = Node.create({
    name: 'attachment',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            href: { default: null, parseHTML: (el) => el.getAttribute('href') },
            name: { default: 'file', parseHTML: (el) => el.getAttribute('data-name') || el.textContent.trim() },
            size: { default: null, parseHTML: (el) => el.getAttribute('data-size') },
            mime: { default: null, parseHTML: (el) => el.getAttribute('data-mime') },
        };
    },

    parseHTML() {
        // Higher priority than the Link mark, which also matches <a href>.
        return [{ tag: 'a[data-attachment]', priority: 100 }];
    },

    renderHTML({ node }) {
        const ext = (node.attrs.name.split('.').pop() || '').slice(0, 5).toUpperCase();

        return [
            'a',
            {
                href: node.attrs.href,
                class: 'de-attachment',
                'data-attachment': ext,
                'data-name': node.attrs.name,
                'data-size': node.attrs.size,
                'data-mime': node.attrs.mime,
                target: '_blank',
                rel: 'noopener noreferrer',
                download: node.attrs.name,
            },
            node.attrs.name,
        ];
    },

    addCommands() {
        return {
            setAttachment: (attrs) => insertBlock({ type: this.name, attrs }),
        };
    },
});
