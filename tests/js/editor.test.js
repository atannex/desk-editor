import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import { Column, Columns } from '../../resources/js/extensions/columns.js';
import { Deletion, Insertion, Suggestions } from '../../resources/js/extensions/suggestions.js';
import { FindReplace } from '../../resources/js/extensions/find-replace.js';
import { Footnote } from '../../resources/js/extensions/footnote.js';
import { CtaButton } from '../../resources/js/extensions/button.js';
import { PageBreak } from '../../resources/js/extensions/page-break.js';
import { Bookmark } from '../../resources/js/extensions/bookmark.js';
import { Callout } from '../../resources/js/extensions/callout.js';
import { CommentMark } from '../../resources/js/extensions/comments.js';
import { TableCellStyle } from '../../resources/js/extensions/table-cell-style.js';
import { BlockAttributes } from '../../resources/js/extensions/block-attributes.js';
import { transformCase } from '../../resources/js/ui/toolbar.js';

let editor;

const make = (content) => {
    editor = new Editor({
        element: document.createElement('div'),
        extensions: [StarterKit, TableKit, Columns, Column, Suggestions.configure({ author: 'Ada' }), Insertion, Deletion, FindReplace, Footnote, CtaButton, PageBreak, Bookmark, Callout, CommentMark, TableCellStyle, BlockAttributes],
        content,
    });
    return editor;
};

beforeEach(() => {
    document.body.innerHTML = '';
});

afterEach(() => editor?.destroy());

describe('columns', () => {
    it('inserts, re-lays out and removes columns without losing content', () => {
        make('<p></p>');
        editor.commands.insertColumns('1-1');
        editor.commands.insertContent('Left');
        expect(editor.getHTML()).toContain('data-layout="1-1"');
        expect(editor.getHTML().match(/data-type="column"/g)).toHaveLength(2);

        editor.commands.setColumnLayout('1-1-1');
        expect(editor.getHTML().match(/data-type="column"/g)).toHaveLength(3);

        editor.commands.setColumnLayout('2-1');
        expect(editor.getHTML().match(/data-type="column"/g)).toHaveLength(2);
        expect(editor.getHTML()).toContain('Left');

        editor.commands.unsetColumns();
        expect(editor.getHTML()).not.toContain('columns');
        expect(editor.getHTML()).toContain('<p>Left</p>');
    });

    it('parses stored layouts', () => {
        make('<div data-type="columns" data-layout="1-2"><div data-type="column"><p>A</p></div><div data-type="column"><p>B</p></div></div>');
        expect(editor.getJSON().content[0]).toMatchObject({ type: 'columns', attrs: { layout: '1-2' } });
    });
});

describe('suggestions', () => {
    it('records insertions and deletions and resolves them', () => {
        make('<p>The quick fox</p>');
        editor.commands.toggleSuggestionMode();
        const view = editor.view;
        const insert = (pos, text) => view.someProp('handleTextInput', (f) => f(view, pos, pos, text));
        insert(5, 'very ');
        expect(editor.getHTML()).toContain('<ins data-suggestion=');
        expect(editor.getHTML()).toContain('data-author="Ada"');

        // Select "fox" (positions shift by 5 after the insertion) and delete it.
        editor.commands.setTextSelection({ from: 16, to: 19 });
        view.someProp('handleKeyDown', (f) => f(view, new KeyboardEvent('keydown', { key: 'Backspace' })));
        expect(editor.getHTML()).toMatch(/<del data-suggestion="[\w-]+" data-author="Ada"[^>]*>fox<\/del>/);

        const accepted = editor.getHTML();
        editor.commands.acceptAllSuggestions();
        expect(editor.getHTML()).toBe('<p>The very quick </p>');

        editor.commands.setContent(accepted);
        editor.commands.rejectAllSuggestions();
        expect(editor.getHTML()).toBe('<p>The quick fox</p>');
    });

    it('does nothing when the mode is off', () => {
        make('<p>abc</p>');
        const view = editor.view;
        expect(view.someProp('handleTextInput', (f) => f(view, 1, 1, 'x'))).toBeFalsy();
    });
});

describe('find & replace', () => {
    it('finds with options and replaces all', () => {
        make('<p>Cat cat CAT category</p>');
        editor.commands.setSearch({ term: 'cat' });
        expect(editor.storage.findReplace.results).toHaveLength(4);
        editor.commands.setSearch({ term: 'cat', caseSensitive: true });
        expect(editor.storage.findReplace.results).toHaveLength(2);
        editor.commands.setSearch({ term: 'cat', wholeWord: true, caseSensitive: false });
        expect(editor.storage.findReplace.results).toHaveLength(3);
        editor.commands.setSearch({ term: 'cat', wholeWord: true, replace: 'dog' });
        editor.commands.replaceAll();
        expect(editor.getText()).toBe('dog dog dog category');
    });

    it('tolerates invalid regular expressions', () => {
        make('<p>a(b</p>');
        editor.commands.setSearch({ term: '(', regex: true });
        expect(editor.storage.findReplace.results).toHaveLength(0);
    });
});

describe('blocks', () => {
    it('renders footnotes, buttons, page breaks and bookmarks', () => {
        make('<p>Text</p>');
        editor.commands.focus('end');
        editor.commands.setFootnote('A "quoted" source');
        editor.commands.setCtaButton({ label: 'Go', href: 'https://x.test', variant: 'outline', align: 'right', newTab: true });
        editor.commands.setPageBreak();
        editor.commands.setBookmark({ url: 'https://laravel.com', title: 'Laravel', site: 'laravel.com' });
        const html = editor.getHTML();
        expect(html).toContain('data-type="footnote"');
        expect(html).toContain('data-content="A &quot;quoted&quot; source"');
        expect(html).toMatch(/<div[^>]*data-type="button"[^>]*>/);
        expect(html).toContain('data-align="right"');
        expect(html).toMatch(/<a[^>]*data-variant="outline"[^>]*target="_blank"/);
        expect(html).toContain('data-type="page-break"');
        expect(html).toContain('<span class="de-bookmark-title">Laravel</span>');

        // Round trip through HTML keeps every attribute.
        const json = editor.getJSON();
        editor.commands.setContent(html);
        expect(editor.getJSON()).toEqual(json);
    });

    it('stores table cell colours and clear-float paragraphs', () => {
        make('<table><tbody><tr><td><p>a</p></td></tr></tbody></table><p>b</p>');
        editor.commands.setTextSelection(4);
        editor.commands.setCellBackground('#fef3c7');
        expect(editor.getHTML()).toMatch(/<td style="background-color: (#fef3c7|rgb\(254, 243, 199\));?"/);
        editor.commands.setTextSelection(editor.state.doc.content.size - 1);
        editor.commands.toggleClear();
        expect(editor.getHTML()).toContain('<p data-clear="true">b</p>');
    });

    it('anchors comments and removes them by id', () => {
        make('<p>Hello world</p>');
        editor.commands.setTextSelection({ from: 1, to: 6 });
        editor.commands.setComment('c-1');
        expect(editor.getHTML()).toBe('<p><span data-comment="c-1" class="de-comment">Hello</span> world</p>');
        editor.commands.unsetCommentById('c-1');
        expect(editor.getHTML()).toBe('<p>Hello world</p>');
    });
});

describe('text case', () => {
    it.each([
        ['upper', 'HELLO WORLD. THIS IS DESK'],
        ['lower', 'hello world. this is desk'],
        ['title', 'Hello World. This Is Desk'],
        ['sentence', 'Hello world. This is desk'],
    ])('%s', (mode, expected) => {
        make('<p>hello WORLD. this is Desk</p>');
        editor.commands.setTextSelection({ from: 1, to: editor.state.doc.content.size - 1 });
        transformCase(editor, mode);
        expect(editor.getText()).toBe(expected);
    });

    it('keeps formatting', () => {
        make('<p>make <strong>this</strong> loud</p>');
        editor.commands.setTextSelection({ from: 1, to: editor.state.doc.content.size - 1 });
        transformCase(editor, 'upper');
        expect(editor.getHTML()).toBe('<p>MAKE <strong>THIS</strong> LOUD</p>');
    });
});

describe('history', () => {
    it('undoes a replace-all on its own, even right after typing', () => {
        make('<p></p>');
        editor.commands.insertContent('alpha beta alpha');
        editor.commands.setSearch({ term: 'alpha', replace: 'gamma' });
        editor.commands.replaceAll();
        expect(editor.getText()).toBe('gamma beta gamma');
        editor.commands.undo();
        expect(editor.getText()).toBe('alpha beta alpha');
    });
});
