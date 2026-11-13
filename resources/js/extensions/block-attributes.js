import { Extension } from '@tiptap/core';

const MAX_INDENT = 8;

/**
 * Adds `indent` (data-indent) and `dir` (ltr/rtl) attributes to text blocks.
 */
export const BlockAttributes = Extension.create({
    name: 'blockAttributes',

    addOptions() {
        return { types: ['paragraph', 'heading', 'blockquote'] };
    },

    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    indent: {
                        default: 0,
                        parseHTML: (el) => Math.min(MAX_INDENT, parseInt(el.getAttribute('data-indent') || '0', 10) || 0),
                        renderHTML: (attrs) => (attrs.indent ? { 'data-indent': attrs.indent } : {}),
                    },
                    clear: {
                        default: false,
                        parseHTML: (el) => el.getAttribute('data-clear') === 'true',
                        renderHTML: (attrs) => (attrs.clear ? { 'data-clear': 'true' } : {}),
                    },
                    dir: {
                        default: null,
                        parseHTML: (el) => (['ltr', 'rtl'].includes(el.getAttribute('dir')) ? el.getAttribute('dir') : null),
                        renderHTML: (attrs) => (attrs.dir ? { dir: attrs.dir } : {}),
                    },
                },
            },
        ];
    },

    addCommands() {
        const shift = (delta) => ({ state, tr, dispatch }) => {
            let changed = false;
            state.doc.nodesBetween(state.selection.from, state.selection.to, (node, pos) => {
                if (!this.options.types.includes(node.type.name)) return;
                const indent = Math.max(0, Math.min(MAX_INDENT, (node.attrs.indent || 0) + delta));
                if (indent !== node.attrs.indent) {
                    tr.setNodeAttribute(pos, 'indent', indent);
                    changed = true;
                }
                return false;
            });
            if (changed && dispatch) dispatch(tr);

            return changed;
        };

        return {
            indent: () => shift(1),
            outdent: () => shift(-1),
            toggleClear: () => ({ state, tr, dispatch }) => {
                const { $from } = state.selection;
                for (let d = $from.depth; d > 0; d--) {
                    const node = $from.node(d);
                    if (this.options.types.includes(node.type.name)) {
                        if (dispatch) dispatch(tr.setNodeAttribute($from.before(d), 'clear', !node.attrs.clear));
                        return true;
                    }
                }
                return false;
            },
            setTextDirection: (dir) => ({ state, tr, dispatch }) => {
                state.doc.nodesBetween(state.selection.from, state.selection.to, (node, pos) => {
                    if (this.options.types.includes(node.type.name)) tr.setNodeAttribute(pos, 'dir', dir);
                });
                dispatch?.(tr);

                return true;
            },
        };
    },

    addKeyboardShortcuts() {
        const inList = () => this.editor.isActive('listItem') || this.editor.isActive('taskItem');
        const inCode = () => this.editor.isActive('codeBlock') || this.editor.isActive('table');

        return {
            Tab: () => (inList() || inCode() ? false : this.editor.commands.indent()),
            'Shift-Tab': () => (inList() || inCode() ? false : this.editor.commands.outdent()),
        };
    },
});
