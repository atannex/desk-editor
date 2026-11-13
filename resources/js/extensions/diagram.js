import { Node } from '@tiptap/core';
import { renderDiagram } from './lazy-render.js';
import { insertBlock } from './insert-block.js';

/** Mermaid diagram block: <div data-type="diagram" data-code="graph TD; A-->B"></div> */
export const Diagram = Node.create({
    name: 'diagram',
    group: 'block',
    atom: true,
    draggable: true,

    addOptions() {
        return { onEdit: null, theme: () => 'light' };
    },

    addAttributes() {
        return {
            code: {
                default: 'graph TD\n  A[Start] --> B{Decision}\n  B -->|Yes| C[Ship it]\n  B -->|No| D[Iterate]',
                parseHTML: (el) => el.getAttribute('data-code') || '',
                renderHTML: (attrs) => ({ 'data-code': attrs.code }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="diagram"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', { ...HTMLAttributes, 'data-type': 'diagram', class: 'de-diagram' }];
    },

    addNodeView() {
        return ({ node, getPos }) => {
            let current = node;
            const dom = document.createElement('div');
            dom.className = 'de-diagram';
            dom.dataset.type = 'diagram';
            const canvas = document.createElement('div');
            canvas.className = 'de-diagram-canvas';
            canvas.textContent = '…';
            dom.append(canvas);
            dom.addEventListener('dblclick', () => this.options.onEdit?.(current, getPos()));
            const draw = () => renderDiagram(current.attrs.code, canvas, this.options.theme());
            draw();

            return {
                dom,
                update(updated) {
                    if (updated.type.name !== 'diagram') return false;
                    const changed = updated.attrs.code !== current.attrs.code;
                    current = updated;
                    if (changed) draw();
                    return true;
                },
                ignoreMutation: () => true,
                selectNode: () => dom.classList.add('ProseMirror-selectednode'),
                deselectNode: () => dom.classList.remove('ProseMirror-selectednode'),
                redraw: draw,
            };
        };
    },

    addCommands() {
        return {
            setDiagram: (code) => insertBlock({ type: this.name, attrs: code ? { code } : {} }),
        };
    },
});
