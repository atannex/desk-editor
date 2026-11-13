import { NodeSelection, TextSelection } from '@tiptap/pm/state';

/**
 * Inserts a block-level atom and leaves the cursor in the paragraph after it
 * (instead of leaving the new block selected, where the next keystroke or
 * insertion would replace it). Creates that paragraph when none follows.
 */
export function insertBlock(content) {
    return ({ chain }) => chain()
        .insertContent(content)
        .command(({ tr }) => {
            const { selection } = tr;
            if (!(selection instanceof NodeSelection)) return true;

            const after = selection.to;
            const next = tr.doc.resolve(after).nodeAfter;
            if (!next || !next.isTextblock) {
                tr.insert(after, tr.doc.type.schema.nodes.paragraph.create());
            }
            tr.setSelection(TextSelection.create(tr.doc, after + 1));
            return true;
        })
        .run();
}
