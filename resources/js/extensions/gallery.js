import { Node } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

/**
 * Image gallery: <figure data-type="gallery" data-columns="3" class="de-gallery"><figure><img><figcaption></figure>…</figure>
 */
export const Gallery = Node.create({
    name: 'gallery',
    group: 'block',
    atom: true,
    draggable: true,

    addOptions() {
        return { onAddImages: null, onEditCaption: null };
    },

    addAttributes() {
        return {
            images: {
                default: [],
                parseHTML: (el) => [...el.querySelectorAll(':scope > figure, :scope > .de-gallery-item')].map((item) => {
                    const img = item.querySelector('img');
                    return img ? { src: img.getAttribute('src'), alt: img.getAttribute('alt') || '', caption: item.querySelector('figcaption')?.textContent || '' } : null;
                }).filter(Boolean),
                renderHTML: () => ({}),
            },
            columns: {
                default: 3,
                parseHTML: (el) => Math.min(6, Math.max(1, parseInt(el.getAttribute('data-columns') || '3', 10))),
                renderHTML: (attrs) => ({ 'data-columns': attrs.columns }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-type="gallery"]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return ['figure', { ...HTMLAttributes, 'data-type': 'gallery', class: 'de-gallery' }, ...node.attrs.images.map((image) => {
            const item = ['figure', { class: 'de-gallery-item' }, ['img', { src: image.src, alt: image.alt || '', loading: 'lazy' }]];
            if (image.caption) item.push(['figcaption', {}, image.caption]);
            return item;
        })];
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            let current = node;
            const dom = document.createElement('figure');
            dom.className = 'de-gallery';
            dom.dataset.type = 'gallery';

            const update = (attrs) => {
                const pos = getPos();
                if (typeof pos !== 'number') return;
                editor.view.dispatch(editor.state.tr.setNodeMarkup(pos, null, { ...current.attrs, ...attrs }));
            };

            const render = () => {
                dom.dataset.columns = current.attrs.columns;
                dom.replaceChildren();
                current.attrs.images.forEach((image, index) => {
                    const item = document.createElement('figure');
                    item.className = 'de-gallery-item';
                    const img = document.createElement('img');
                    img.src = image.src;
                    img.alt = image.alt || '';
                    item.append(img);
                    if (image.caption) {
                        const cap = document.createElement('figcaption');
                        cap.textContent = image.caption;
                        item.append(cap);
                    }
                    if (editor.isEditable) {
                        const tools = document.createElement('div');
                        tools.className = 'de-gallery-tools';
                        const button = (label, text, fn) => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.title = label;
                            b.setAttribute('aria-label', label);
                            b.textContent = text;
                            b.addEventListener('mousedown', (e) => e.preventDefault());
                            b.addEventListener('click', (e) => {
                                e.stopPropagation();
                                fn();
                            });
                            tools.append(b);
                        };
                        const images = [...current.attrs.images];
                        if (index > 0) button('Move left', '←', () => {
                            [images[index - 1], images[index]] = [images[index], images[index - 1]];
                            update({ images });
                        });
                        if (index < images.length - 1) button('Move right', '→', () => {
                            [images[index + 1], images[index]] = [images[index], images[index + 1]];
                            update({ images });
                        });
                        button('Caption', 'Aa', () => this.options.onEditCaption?.(image, (next) => {
                            const copy = [...current.attrs.images];
                            copy[index] = { ...copy[index], ...next };
                            update({ images: copy });
                        }));
                        button('Remove', '×', () => {
                            images.splice(index, 1);
                            if (images.length) update({ images });
                            else editor.chain().focus().deleteRange({ from: getPos(), to: getPos() + current.nodeSize }).run();
                        });
                        item.append(tools);
                    }
                    dom.append(item);
                });
                if (editor.isEditable) {
                    const add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'de-gallery-add';
                    add.innerHTML = '<span>+</span>';
                    add.title = 'Add images';
                    add.addEventListener('mousedown', (e) => e.preventDefault());
                    add.addEventListener('click', () => this.options.onAddImages?.((images) => update({ images: [...current.attrs.images, ...images] })));
                    dom.append(add);
                }
            };
            render();

            return {
                dom,
                update(updated) {
                    if (updated.type.name !== 'gallery') return false;
                    current = updated;
                    render();
                    return true;
                },
                stopEvent: (event) => Boolean(event.target.closest?.('button')),
                ignoreMutation: () => true,
                selectNode: () => dom.classList.add('ProseMirror-selectednode'),
                deselectNode: () => dom.classList.remove('ProseMirror-selectednode'),
            };
        };
    },

    addCommands() {
        return {
            setGallery: (attrs) => insertBlock({ type: this.name, attrs }),
        };
    },
});
