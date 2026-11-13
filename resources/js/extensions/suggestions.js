import { Extension, Mark } from '@tiptap/core';
import { Plugin, PluginKey, TextSelection } from '@tiptap/pm/state';

export const suggestionKey = new PluginKey('deskSuggestions');

const uid = () => `s-${Math.random().toString(36).slice(2, 10)}`;

function suggestionMark(name, tag) {
    return Mark.create({
        name,
        inclusive: name === 'insertion',
        excludes: name === 'insertion' ? 'deletion' : 'insertion',

        addAttributes() {
            return {
                id: { default: null, parseHTML: (el) => el.getAttribute('data-suggestion'), renderHTML: (a) => ({ 'data-suggestion': a.id }) },
                author: { default: null, parseHTML: (el) => el.getAttribute('data-author'), renderHTML: (a) => (a.author ? { 'data-author': a.author } : {}) },
            };
        },

        parseHTML() {
            // Higher priority than Strike/Underline, which also parse <del>/<ins>.
            return [{ tag: `${tag}[data-suggestion]`, priority: 100 }];
        },

        renderHTML({ HTMLAttributes }) {
            return [tag, { ...HTMLAttributes, class: `de-suggestion de-suggestion-${name}` }, 0];
        },
    });
}

export const Insertion = suggestionMark('insertion', 'ins');
export const Deletion = suggestionMark('deletion', 'del');

/**
 * Suggestion (track changes) mode. When active, typing is recorded as an
 * insertion and deleting marks text as a deletion instead of removing it.
 * Each change can be accepted or rejected individually or all at once.
 */
export const Suggestions = Extension.create({
    name: 'suggestions',

    addOptions() {
        return { author: null };
    },

    addStorage() {
        return { enabled: false };
    },

    addCommands() {
        const resolve = (accept, id = null) => ({ state, tr, dispatch }) => {
            const { insertion, deletion } = state.schema.marks;
            const ranges = [];
            state.doc.descendants((node, pos) => {
                if (!node.isText) return;
                node.marks.forEach((mark) => {
                    if ((mark.type === insertion || mark.type === deletion) && (!id || mark.attrs.id === id)) {
                        ranges.push({ from: pos, to: pos + node.nodeSize, mark });
                    }
                });
            });
            if (!ranges.length) return false;
            if (!dispatch) return true;
            // Process from the end so earlier positions stay valid.
            ranges.sort((a, b) => b.from - a.from).forEach(({ from, to, mark }) => {
                const keepText = (mark.type === insertion) === accept;
                if (keepText) tr.removeMark(from, to, mark.type);
                else tr.delete(from, to);
            });
            dispatch(tr.setMeta(suggestionKey, { internal: true }));
            return true;
        };

        return {
            toggleSuggestionMode: () => ({ tr, dispatch }) => {
                this.storage.enabled = !this.storage.enabled;
                if (dispatch) dispatch(tr.setMeta(suggestionKey, { toggled: true }));
                this.editor.emit('deskSuggestionMode', this.storage.enabled);
                return true;
            },
            acceptSuggestion: (id) => resolve(true, id),
            rejectSuggestion: (id) => resolve(false, id),
            acceptAllSuggestions: () => resolve(true),
            rejectAllSuggestions: () => resolve(false),
        };
    },

    addProseMirrorPlugins() {
        const extension = this;
        const author = () => (typeof this.options.author === 'function' ? this.options.author() : this.options.author) || null;

        const insertionMarkAt = (state, pos) => {
            const { insertion } = state.schema.marks;
            const $pos = state.doc.resolve(pos);
            const mark = $pos.nodeBefore?.marks.find((m) => m.type === insertion);
            return mark && mark.attrs.author === author() ? mark : null;
        };

        const markDeleted = (state, from, to) => {
            const { insertion, deletion } = state.schema.marks;
            const tr = state.tr;
            // Continue an adjacent deletion by the same author so a run of backspaces is one suggestion.
            const neighbour = [state.doc.resolve(from).nodeBefore, state.doc.resolve(to).nodeAfter]
                .flatMap((node) => node?.marks || [])
                .find((m) => m.type === deletion && m.attrs.author === author());
            const id = neighbour?.attrs.id || uid();
            // Own pending insertions are simply removed; everything else gets a deletion mark.
            const pieces = [];
            state.doc.nodesBetween(from, to, (node, pos) => {
                if (!node.isText) return;
                const start = Math.max(from, pos);
                const end = Math.min(to, pos + node.nodeSize);
                pieces.push({ start, end, inserted: node.marks.some((m) => m.type === insertion), deleted: node.marks.some((m) => m.type === deletion) });
            });
            pieces.sort((a, b) => b.start - a.start).forEach(({ start, end, inserted, deleted }) => {
                if (inserted) tr.delete(start, end);
                else if (!deleted) tr.addMark(start, end, deletion.create({ id, author: author() }));
            });
            return tr;
        };

        return [
            new Plugin({
                key: suggestionKey,
                props: {
                    handleTextInput(view, from, to, text) {
                        if (!extension.storage.enabled) return false;
                        const { state } = view;
                        let tr = from !== to ? markDeleted(state, from, to) : state.tr;
                        const insertAt = tr.mapping.map(to);
                        const existing = insertionMarkAt(state, from);
                        const mark = state.schema.marks.insertion.create({ id: existing?.attrs.id || uid(), author: author() });
                        const marks = [...(state.storedMarks || state.doc.resolve(from).marks()).filter((m) => m.type.name !== 'deletion' && m.type.name !== 'insertion'), mark];
                        tr = tr.insert(insertAt, state.schema.text(text, marks));
                        tr.setSelection(TextSelection.create(tr.doc, insertAt + text.length));
                        view.dispatch(tr.setMeta(suggestionKey, { internal: true }));
                        return true;
                    },
                    handleKeyDown(view, event) {
                        if (!extension.storage.enabled || !['Backspace', 'Delete'].includes(event.key)) return false;
                        const { state } = view;
                        const { from, to, empty } = state.selection;
                        let start = from;
                        let end = to;
                        if (empty) {
                            if (event.key === 'Backspace') start = from - 1;
                            else end = to + 1;
                            const $s = state.doc.resolve(Math.max(0, start));
                            const $e = state.doc.resolve(Math.min(state.doc.content.size, end));
                            if (!$s.sameParent($e)) return false; // joining blocks is a structural change: let it through
                        }
                        if (start < 0 || end > state.doc.content.size) return false;
                        const tr = markDeleted(state, start, end);
                        const cursor = event.key === 'Backspace' ? tr.mapping.map(start, -1) : tr.mapping.map(end);
                        tr.setSelection(TextSelection.create(tr.doc, cursor));
                        view.dispatch(tr.setMeta(suggestionKey, { internal: true }));
                        return true;
                    },
                    handlePaste(view, event) {
                        if (!extension.storage.enabled) return false;
                        const text = event.clipboardData?.getData('text/plain');
                        if (!text) return false;
                        const { from, to } = view.state.selection;
                        return this.props.handleTextInput.call(this, view, from, to, text);
                    },
                    handleDOMEvents: {
                        cut(view, event) {
                            if (!extension.storage.enabled) return false;
                            const { from, to, empty } = view.state.selection;
                            if (empty) return false;
                            event.clipboardData?.setData('text/plain', view.state.doc.textBetween(from, to, '\n'));
                            event.preventDefault();
                            const tr = markDeleted(view.state, from, to);
                            view.dispatch(tr.setMeta(suggestionKey, { internal: true }));
                            return true;
                        },
                    },
                },
            }),
        ];
    },
});

/** The suggestion under the cursor, if any: { id, type, author }. */
export function suggestionAtCursor(state) {
    const { $from } = state.selection;
    const marks = [...($from.nodeBefore?.marks || []), ...($from.nodeAfter?.marks || [])];
    const mark = marks.find((m) => m.type.name === 'insertion' || m.type.name === 'deletion');
    return mark ? { id: mark.attrs.id, type: mark.type.name, author: mark.attrs.author } : null;
}
