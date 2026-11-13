import Image from '@tiptap/extension-image';
import { mergeAttributes } from '@tiptap/core';

/**
 * Block image rendered as <figure> with optional caption, alignment and a
 * persisted width. Plain <img> tags (pasted content) are parsed too.
 */
export const DeskImage = Image.extend({
    addOptions() {
        return { ...this.parent?.(), inline: false, allowBase64: false, resize: false };
    },

    addAttributes() {
        return {
            ...this.parent?.(),
            align: {
                default: 'center',
                parseHTML: (el) => el.closest('figure')?.getAttribute('data-align') || el.getAttribute('data-align') || 'center',
                renderHTML: () => ({}),
            },
            caption: {
                default: null,
                parseHTML: (el) => el.closest('figure')?.querySelector('figcaption')?.textContent || null,
                renderHTML: () => ({}),
            },
            width: {
                default: null,
                parseHTML: (el) => {
                    const value = parseInt(el.getAttribute('width') || el.style.width, 10);
                    return Number.isFinite(value) ? value : null;
                },
            },
            height: { default: null, renderHTML: () => ({}) },
        };
    },

    parseHTML() {
        return [
            { tag: 'figure[data-type="image"] img[src]' },
            { tag: 'img[src]:not([src^="data:"])' },
        ];
    },

    renderHTML({ node, HTMLAttributes }) {
        const img = ['img', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, { loading: 'lazy' })];
        const figure = ['figure', { 'data-type': 'image', 'data-align': node.attrs.align || 'center', class: 'de-figure' }, img];
        if (node.attrs.caption) figure.push(['figcaption', {}, node.attrs.caption]);

        return figure;
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            let current = node;
            const dom = document.createElement('figure');
            dom.className = 'de-figure';
            dom.dataset.type = 'image';
            const frame = document.createElement('div');
            frame.className = 'de-figure-frame';
            const img = document.createElement('img');
            img.draggable = false;
            const caption = document.createElement('figcaption');
            caption.contentEditable = 'false';
            frame.append(img);

            const handles = ['w', 'e'].map((side) => {
                const handle = document.createElement('span');
                handle.className = `de-resize-handle de-resize-${side}`;
                handle.contentEditable = 'false';
                handle.addEventListener('pointerdown', (event) => startResize(event, side));
                frame.append(handle);
                return handle;
            });
            dom.append(frame, caption);

            const apply = (n) => {
                if (img.getAttribute('src') !== n.attrs.src) img.src = n.attrs.src;
                img.alt = n.attrs.alt || '';
                if (n.attrs.title) img.title = n.attrs.title;
                else img.removeAttribute('title');
                frame.style.width = n.attrs.width ? `${n.attrs.width}px` : '';
                dom.dataset.align = n.attrs.align || 'center';
                caption.textContent = n.attrs.caption || '';
                caption.hidden = !n.attrs.caption;
            };

            const startResize = (event, side) => {
                if (!editor.isEditable) return;
                event.preventDefault();
                event.stopPropagation();
                const startX = event.clientX;
                const startWidth = frame.getBoundingClientRect().width;
                const maxWidth = editor.view.dom.clientWidth;
                const direction = side === 'e' ? 1 : -1;
                const multiplier = dom.dataset.align === 'center' ? 2 : 1;
                frame.classList.add('is-resizing');

                const move = (e) => {
                    const width = Math.round(Math.min(maxWidth, Math.max(80, startWidth + (e.clientX - startX) * direction * multiplier)));
                    frame.style.width = `${width}px`;
                    frame.dataset.size = `${width}px`;
                };
                const up = () => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                    frame.classList.remove('is-resizing');
                    const pos = getPos();
                    if (typeof pos !== 'number') return;
                    const width = Math.round(frame.getBoundingClientRect().width);
                    editor.chain().setNodeSelection(pos).updateAttributes('image', { width }).run();
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            };

            // Re-measure floating menus once the real image dimensions are known.
            img.addEventListener('load', () => {
                if (!editor.isDestroyed) editor.view.dispatch(editor.state.tr.setMeta('deskImageLoaded', true));
            });

            apply(node);
            handles.forEach((handle) => (handle.hidden = !editor.isEditable));

            return {
                dom,
                update(updated) {
                    if (updated.type !== current.type) return false;
                    current = updated;
                    apply(updated);
                    return true;
                },
                selectNode: () => dom.classList.add('is-selected'),
                deselectNode: () => dom.classList.remove('is-selected'),
                stopEvent: (event) => event.target?.classList?.contains('de-resize-handle'),
                ignoreMutation: () => true,
            };
        };
    },
});
