import { Node } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

/** Forces a new page when printing; shown as a labelled divider while editing. */
export const PageBreak = Node.create({
    name: 'pageBreak',
    group: 'block',
    atom: true,
    selectable: true,

    parseHTML() {
        return [{ tag: 'div[data-type="page-break"]' }];
    },

    renderHTML() {
        return ['div', { 'data-type': 'page-break', class: 'de-page-break' }];
    },

    addCommands() {
        return {
            setPageBreak: () => insertBlock({ type: this.name }),
        };
    },

    addKeyboardShortcuts() {
        return { 'Mod-Shift-Enter': () => this.editor.commands.setPageBreak() };
    },
});
