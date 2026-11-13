import { Node } from '@tiptap/core';

/**
 * Inline footnote: <sup data-type="footnote" data-content="Source…"></sup>.
 * Numbered automatically (CSS counters while editing, a generated list for readers).
 */
export const Footnote = Node.create({
    name: 'footnote',
    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addOptions() {
        return { onEdit: null };
    },

    addAttributes() {
        return {
            content: {
                default: '',
                parseHTML: (el) => el.getAttribute('data-content') || el.textContent || '',
                renderHTML: (attrs) => ({ 'data-content': attrs.content }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'sup[data-type="footnote"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['sup', { ...HTMLAttributes, 'data-type': 'footnote', class: 'de-footnote' }];
    },

    addNodeView() {
        return ({ node, getPos }) => {
            const dom = document.createElement('sup');
            dom.className = 'de-footnote';
            dom.dataset.type = 'footnote';
            dom.contentEditable = 'false';
            dom.title = node.attrs.content;
            dom.addEventListener('click', () => this.options.onEdit?.(node, getPos(), dom));

            return {
                dom,
                update: (updated) => {
                    if (updated.type.name !== 'footnote') return false;
                    dom.title = updated.attrs.content;
                    return true;
                },
            };
        };
    },

    addCommands() {
        return {
            setFootnote: (content) => ({ commands }) => commands.insertContent({ type: this.name, attrs: { content } }),
        };
    },
});
