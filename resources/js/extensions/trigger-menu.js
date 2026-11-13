import { Extension } from '@tiptap/core';
import { PluginKey } from '@tiptap/pm/state';
import Suggestion from '@tiptap/suggestion';

/**
 * Generic trigger-character menu (used for "/" commands and ":" emoji).
 */
export function triggerMenu(name, { char, items, render, allowSpaces = false, startOfLine = false }) {
    return Extension.create({
        name,

        addProseMirrorPlugins() {
            return [
                Suggestion({
                    editor: this.editor,
                    pluginKey: new PluginKey(name),
                    char,
                    allowSpaces,
                    startOfLine,
                    items: ({ query, editor }) => items(query, editor),
                    command: ({ editor, range, props }) => {
                        editor.chain().focus().deleteRange(range).run();
                        props.run(editor);
                    },
                    allow: ({ editor }) => !editor.isActive('codeBlock') && !editor.isActive('code'),
                    render,
                }),
            ];
        },
    });
}
