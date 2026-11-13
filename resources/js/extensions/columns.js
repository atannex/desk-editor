import { Node, mergeAttributes } from '@tiptap/core';
import { TextSelection } from '@tiptap/pm/state';

export const COLUMN_LAYOUTS = {
    '1-1': 2,
    '2-1': 2,
    '1-2': 2,
    '1-1-1': 3,
    '1-2-1': 3,
    '1-1-1-1': 4,
};

/**
 * Side-by-side layout: <div data-type="columns" data-layout="1-1"><div data-type="column">…</div>…</div>
 * Columns stack on small screens.
 */
export const Column = Node.create({
    name: 'column',
    content: 'block+',
    isolating: true,
    defining: true,

    parseHTML() {
        return [{ tag: 'div[data-type="column"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'column', class: 'de-column' }), 0];
    },
});

export const Columns = Node.create({
    name: 'columns',
    group: 'block',
    content: 'column{2,4}',
    defining: true,
    isolating: true,

    addAttributes() {
        return {
            layout: {
                default: '1-1',
                parseHTML: (el) => (COLUMN_LAYOUTS[el.getAttribute('data-layout')] ? el.getAttribute('data-layout') : '1-1'),
                renderHTML: (attrs) => ({ 'data-layout': attrs.layout }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-type="columns"]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'columns', class: 'de-columns' }), 0];
    },

    addCommands() {
        const build = (schema, count, contents = []) => {
            const columns = [];
            for (let i = 0; i < count; i++) {
                columns.push(schema.nodes.column.create(null, contents[i] || schema.nodes.paragraph.create()));
            }
            return columns;
        };

        return {
            insertColumns: (layout = '1-1') => ({ tr, state, dispatch }) => {
                const count = COLUMN_LAYOUTS[layout] || 2;
                const { schema, selection } = state;
                // Move the current block's content into the first column when it has text.
                const $from = selection.$from;
                const block = $from.depth >= 1 ? $from.node(1) : null;
                const node = schema.nodes.columns.create({ layout }, build(schema, count));
                if (!dispatch) return true;
                if (block && block.type.name === 'paragraph' && block.content.size === 0) {
                    const pos = $from.before(1);
                    tr.replaceWith(pos, pos + block.nodeSize, node);
                    tr.setSelection(TextSelection.near(tr.doc.resolve(pos + 3)));
                } else {
                    const pos = block ? $from.after(1) : selection.to;
                    tr.insert(pos, node);
                    tr.setSelection(TextSelection.near(tr.doc.resolve(pos + 3)));
                }
                dispatch(tr.scrollIntoView());
                return true;
            },

            setColumnLayout: (layout) => ({ tr, state, dispatch }) => {
                const count = COLUMN_LAYOUTS[layout];
                if (!count) return false;
                const found = findColumns(state);
                if (!found) return false;
                if (!dispatch) return true;
                const { node, pos } = found;
                const columns = [];
                node.forEach((col) => columns.push(col));
                const { schema } = state;
                const next = [];
                for (let i = 0; i < count; i++) {
                    next.push(columns[i] || schema.nodes.column.create(null, schema.nodes.paragraph.create()));
                }
                // Merge content from removed columns into the last kept column.
                if (columns.length > count) {
                    const extra = columns.slice(count).flatMap((c) => c.content.content);
                    const last = next[count - 1];
                    next[count - 1] = last.copy(last.content.append(schema.nodes.column.create(null, extra).content));
                }
                tr.replaceWith(pos, pos + node.nodeSize, schema.nodes.columns.create({ layout }, next));
                // Keep the cursor inside the layout so it can be changed again.
                tr.setSelection(TextSelection.near(tr.doc.resolve(Math.min(pos + 3, tr.doc.content.size))));
                dispatch(tr);
                return true;
            },

            unsetColumns: () => ({ tr, state, dispatch }) => {
                const found = findColumns(state);
                if (!found) return false;
                if (!dispatch) return true;
                const blocks = [];
                found.node.forEach((col) => col.forEach((child) => blocks.push(child)));
                tr.replaceWith(found.pos, found.pos + found.node.nodeSize, blocks);
                dispatch(tr);
                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        // Mod-Enter leaves the column layout and continues below it.
        return {
            'Mod-Enter': ({ editor }) => {
                const found = findColumns(editor.state);
                if (!found) return false;
                const end = found.pos + found.node.nodeSize;
                return editor.chain().insertContentAt(end, { type: 'paragraph' }).setTextSelection(end + 1).run();
            },
        };
    },
});

export function findColumns(state) {
    const { $from } = state.selection;
    for (let d = $from.depth; d > 0; d--) {
        if ($from.node(d).type.name === 'columns') return { node: $from.node(d), pos: $from.before(d) };
    }
    return null;
}
