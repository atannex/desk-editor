import { Extension } from '@tiptap/core';
import { Plugin, PluginKey, TextSelection } from '@tiptap/pm/state';
import { Decoration, DecorationSet } from '@tiptap/pm/view';
import { closeHistory } from '@tiptap/pm/history';

export const findReplaceKey = new PluginKey('deskFindReplace');

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function buildPattern({ term, caseSensitive, wholeWord, regex }) {
    if (!term) return null;
    try {
        let source = regex ? term : escapeRegExp(term);
        if (wholeWord) source = `\\b${source}\\b`;
        return new RegExp(source, caseSensitive ? 'gu' : 'giu');
    } catch {
        return null;
    }
}

function findMatches(doc, options) {
    const pattern = buildPattern(options);
    if (!pattern) return [];
    const results = [];

    doc.descendants((node, pos) => {
        if (!node.isTextblock) return;
        // Map every character of the textblock back to a document position.
        let text = '';
        const positions = [];
        node.forEach((child, offset) => {
            if (child.isText) {
                for (let i = 0; i < child.text.length; i++) positions.push(pos + 1 + offset + i);
                text += child.text;
            } else {
                positions.push(null);
                text += '￼';
            }
        });
        pattern.lastIndex = 0;
        let match;
        while ((match = pattern.exec(text)) !== null) {
            if (match[0].length === 0) {
                pattern.lastIndex++;
                continue;
            }
            const from = positions[match.index];
            const to = positions[match.index + match[0].length - 1];
            if (from !== null && to !== null) results.push({ from, to: to + 1 });
            if (results.length > 5000) return false;
        }
        return false;
    });

    return results;
}

export const FindReplace = Extension.create({
    name: 'findReplace',

    addStorage() {
        return { term: '', replace: '', caseSensitive: false, wholeWord: false, regex: false, results: [], index: 0 };
    },

    addCommands() {
        const refresh = (tr, dispatch) => {
            if (dispatch) dispatch(tr.setMeta(findReplaceKey, { refresh: true }));
            return true;
        };

        return {
            setSearch: (options) => ({ tr, dispatch }) => {
                Object.assign(this.storage, options);
                this.storage.index = 0;
                return refresh(tr, dispatch);
            },
            clearSearch: () => ({ tr, dispatch }) => {
                Object.assign(this.storage, { term: '', results: [], index: 0 });
                return refresh(tr, dispatch);
            },
            goToResult: (direction = 1) => ({ tr, dispatch }) => {
                const { results } = this.storage;
                if (!results.length) return false;
                this.storage.index = (this.storage.index + direction + results.length) % results.length;
                const { from, to } = results[this.storage.index];
                if (dispatch) {
                    tr.setSelection(TextSelection.create(tr.doc, from, to)).scrollIntoView();
                    dispatch(tr.setMeta(findReplaceKey, { refresh: true }));
                }
                return true;
            },
            replaceCurrent: () => ({ tr, dispatch }) => {
                const match = this.storage.results[this.storage.index];
                if (!match) return false;
                if (dispatch) {
                    closeHistory(tr); // a replacement is always its own undo step
                    const text = this.storage.replace;
                    if (text) tr.insertText(text, match.from, match.to);
                    else tr.delete(match.from, match.to);
                    dispatch(tr.setMeta(findReplaceKey, { refresh: true }));
                }
                return true;
            },
            replaceAll: () => ({ tr, dispatch }) => {
                const results = [...this.storage.results].reverse();
                if (!results.length) return false;
                if (dispatch) {
                    closeHistory(tr);
                    for (const { from, to } of results) {
                        if (this.storage.replace) tr.insertText(this.storage.replace, from, to);
                        else tr.delete(from, to);
                    }
                    this.storage.index = 0;
                    dispatch(tr.setMeta(findReplaceKey, { refresh: true }));
                }
                return true;
            },
        };
    },

    addProseMirrorPlugins() {
        const storage = this.storage;
        const editor = this.editor;

        const build = (doc) => {
            storage.results = findMatches(doc, storage);
            if (storage.index >= storage.results.length) storage.index = 0;
            const decorations = storage.results.map(({ from, to }, i) =>
                Decoration.inline(from, to, { class: i === storage.index ? 'de-search-match is-current' : 'de-search-match' }),
            );
            queueMicrotask(() => editor.emit('deskSearch', { results: storage.results.length, index: storage.index }));
            return DecorationSet.create(doc, decorations);
        };

        return [
            new Plugin({
                key: findReplaceKey,
                state: {
                    init: () => DecorationSet.empty,
                    apply(tr, old) {
                        if (tr.getMeta(findReplaceKey) || (tr.docChanged && storage.term)) return build(tr.doc);
                        return old.map(tr.mapping, tr.doc);
                    },
                },
                props: {
                    decorations(state) {
                        return this.getState(state);
                    },
                },
            }),
        ];
    },
});
