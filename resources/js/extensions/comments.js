import { Mark, mergeAttributes } from '@tiptap/core';

/** Anchor for an editorial comment thread: <span data-comment="anchor-id">…</span> */
export const CommentMark = Mark.create({
    name: 'comment',
    inclusive: false,
    excludes: '',

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: (el) => el.getAttribute('data-comment'),
                renderHTML: (attrs) => ({ 'data-comment': attrs.id }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-comment]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['span', mergeAttributes(HTMLAttributes, { class: 'de-comment' }), 0];
    },

    addCommands() {
        return {
            setComment: (id) => ({ commands }) => commands.setMark(this.name, { id }),
            unsetCommentById: (id) => ({ tr, state, dispatch }) => {
                const type = state.schema.marks.comment;
                state.doc.descendants((node, pos) => {
                    node.marks.forEach((mark) => {
                        if (mark.type === type && mark.attrs.id === id) tr.removeMark(pos, pos + node.nodeSize, mark);
                    });
                });
                if (dispatch) dispatch(tr);
                return true;
            },
        };
    },
});

/** Every comment anchor id with its first position in the document. */
export function commentAnchors(doc) {
    const anchors = new Map();
    doc.descendants((node, pos) => {
        node.marks.forEach((mark) => {
            if (mark.type.name === 'comment' && mark.attrs.id && !anchors.has(mark.attrs.id)) anchors.set(mark.attrs.id, pos);
        });
    });
    return anchors;
}
