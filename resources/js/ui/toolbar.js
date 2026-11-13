import { h, iconButton, shortcut } from './dom.js';
import { icon } from './icons.js';
import { Popover, menu } from './popover.js';
import { CALLOUT_TYPES } from '../extensions/callout.js';
import { colorPopover, embedDialog, mediaDialog, emojiPopover, imageDialog, linkDialog, mathDialog, shortcutsDialog, tablePopover } from './dialogs.js';
import { insertFiles, pickFile } from '../upload.js';
import { specialCharsPopover } from './special-chars.js';

/** Change letter case of the selected text while keeping its formatting. */
export function transformCase(editor, mode) {
    const { state } = editor;
    const { from, to } = state.selection;
    if (from === to) return;
    const tr = state.tr;
    const fullText = state.doc.textBetween(from, to, '\n');
    let index = 0;
    const convert = (text) => {
        let out = text;
        if (mode === 'upper') out = text.toLocaleUpperCase();
        else if (mode === 'lower') out = text.toLocaleLowerCase();
        else if (mode === 'toggle') out = [...text].map((c) => (c === c.toLocaleUpperCase() ? c.toLocaleLowerCase() : c.toLocaleUpperCase())).join('');
        else if (mode === 'title') out = [...text].map((c, i) => { const prev = fullText[index + i - 1]; return !prev || /[\s\-\u2014("\u201c]/.test(prev) ? c.toLocaleUpperCase() : c.toLocaleLowerCase(); }).join('');
        else if (mode === 'sentence') out = [...text].map((c, i) => { const before = fullText.slice(0, index + i).trimEnd(); return !before || /[.!?]$/.test(before) ? c.toLocaleUpperCase() : c.toLocaleLowerCase(); }).join('');
        index += text.length;
        return out;
    };
    const pieces = [];
    state.doc.nodesBetween(from, to, (node, pos) => {
        if (!node.isText) {
            if (node.isBlock && pos >= from && pieces.length) index += 1;
            return;
        }
        const start = Math.max(from, pos);
        const end = Math.min(to, pos + node.nodeSize);
        pieces.push({ start, end, text: node.text.slice(start - pos, end - pos), marks: node.marks });
    });
    const converted = pieces.map((p) => ({ ...p, text: convert(p.text) }));
    converted.reverse().forEach(({ start, end, text, marks }) => tr.replaceWith(start, end, state.schema.text(text, marks)));
    editor.view.dispatch(tr);
    editor.commands.setTextSelection({ from, to });
}

const CALLOUT_ICONS = { info: 'Info', tip: 'Lightbulb', success: 'CircleCheck', warning: 'TriangleAlert', danger: 'OctagonAlert', note: 'StickyNote' };

/**
 * Every toolbar item, keyed by the name used in config('desk-editor.toolbar').
 */
export function createItems(desk) {
    const { editor, t } = desk;
    const chain = () => editor.chain().focus();
    const can = () => editor.can().chain().focus();
    const has = (name) => editor.extensionManager.extensions.some((e) => e.name === name);

    const toggle = (name, iconName, label, keys, command, active = name) => ({
        type: 'button',
        icon: iconName,
        label,
        shortcut: keys,
        requires: active,
        run: () => chain()[command]().run(),
        active: () => editor.isActive(active),
        disabled: () => !can()[command](),
    });

    const blockStyles = () => [
        { key: 'p', label: t('paragraph'), icon: icon('Pilcrow', 16), hint: shortcut('Mod-Alt-0'), active: editor.isActive('paragraph'), run: () => chain().setParagraph().run() },
        ...[1, 2, 3, 4, 5, 6].filter((l) => desk.config.headings.includes(l)).map((level) => ({
            key: `h${level}`,
            label: t('heading', { level }),
            icon: icon(`Heading${level}`, 16),
            hint: level <= 3 ? shortcut(`Mod-Alt-${level}`) : null,
            style: { fontSize: `${[1.45, 1.3, 1.15, 1.05, 0.95, 0.9][level - 1]}em`, fontWeight: 650 },
            active: editor.isActive('heading', { level }),
            run: () => chain().toggleHeading({ level }).run(),
        })),
        '|',
        { key: 'quote', label: t('blockquote'), icon: icon('Quote', 16), active: editor.isActive('blockquote'), run: () => chain().toggleBlockquote().run() },
        { key: 'code', label: t('codeBlock'), icon: icon('SquareCode', 16), active: editor.isActive('codeBlock'), run: () => chain().toggleCodeBlock().run() },
    ];

    const currentBlockLabel = () => {
        for (let level = 1; level <= 6; level++) if (editor.isActive('heading', { level })) return t('heading', { level });
        if (editor.isActive('codeBlock')) return t('codeBlock');
        if (editor.isActive('blockquote')) return t('blockquote');
        return t('paragraph');
    };

    const dropdown = (anchor, items) => {
        anchor.setAttribute('aria-expanded', 'true');
        const popover = new Popover(desk, {
            anchor,
            content: menu(items, (item) => {
                popover.close();
                item.run();
            }),
        });
        popover.el.querySelector('.de-menu-item.is-active, .de-menu-item')?.focus();
    };

    return {
        undo: { type: 'button', icon: 'Undo2', label: t('undo'), shortcut: 'Mod-Z', run: () => chain().undo().run(), disabled: () => !can().undo() },
        redo: { type: 'button', icon: 'Redo2', label: t('redo'), shortcut: 'Mod-Shift-Z', run: () => chain().redo().run(), disabled: () => !can().redo() },

        blockStyle: {
            type: 'select',
            label: t('textStyle'),
            width: '9.5rem',
            value: currentBlockLabel,
            open: (anchor) => dropdown(anchor, blockStyles()),
        },
        fontFamily: {
            type: 'select',
            label: t('fontFamily'),
            width: '8.5rem',
            requires: 'fontFamily',
            value: () => {
                const family = editor.getAttributes('textStyle').fontFamily;
                return desk.config.fonts.find((f) => f.value === family)?.label || (family ? family.split(',')[0].replace(/["']/g, '') : t('defaultFont'));
            },
            open: (anchor) => dropdown(anchor, [
                { label: t('defaultFont'), active: !editor.getAttributes('textStyle').fontFamily, run: () => chain().unsetFontFamily().run() },
                '|',
                ...desk.config.fonts.map((font) => ({
                    label: font.label,
                    style: { fontFamily: font.value },
                    active: editor.isActive('textStyle', { fontFamily: font.value }),
                    run: () => chain().setFontFamily(font.value).run(),
                })),
            ]),
        },
        fontSize: {
            type: 'select',
            label: t('fontSize'),
            width: '5.25rem',
            requires: 'fontSize',
            value: () => editor.getAttributes('textStyle').fontSize?.replace('px', '') || t('defaultSize'),
            open: (anchor) => dropdown(anchor, [
                { label: t('defaultSize'), active: !editor.getAttributes('textStyle').fontSize, run: () => chain().unsetFontSize().run() },
                '|',
                ...desk.config.fontSizes.map((size) => ({
                    label: size.replace('px', ''),
                    active: editor.getAttributes('textStyle').fontSize === size,
                    run: () => chain().setFontSize(size).run(),
                })),
            ]),
        },
        lineHeight: {
            type: 'button',
            icon: 'MoveVertical',
            label: t('lineHeight'),
            caret: true,
            requires: 'lineHeight',
            run: (anchor) => dropdown(anchor, [
                { label: t('defaultSize'), active: !editor.getAttributes('textStyle').lineHeight, run: () => chain().unsetLineHeight().run() },
                '|',
                ...['1', '1.15', '1.5', '1.75', '2', '2.5'].map((value) => ({
                    label: value,
                    active: editor.getAttributes('textStyle').lineHeight === value,
                    run: () => chain().setLineHeight(value).run(),
                })),
            ]),
        },

        bold: toggle('bold', 'Bold', t('bold'), 'Mod-B', 'toggleBold'),
        italic: toggle('italic', 'Italic', t('italic'), 'Mod-I', 'toggleItalic'),
        underline: toggle('underline', 'Underline', t('underline'), 'Mod-U', 'toggleUnderline'),
        strike: toggle('strike', 'Strikethrough', t('strike'), 'Mod-Shift-S', 'toggleStrike'),
        code: toggle('code', 'Code', t('code'), 'Mod-E', 'toggleCode'),
        subscript: toggle('subscript', 'Subscript', t('subscript'), 'Mod-,', 'toggleSubscript'),
        superscript: toggle('superscript', 'Superscript', t('superscript'), 'Mod-.', 'toggleSuperscript'),
        textColor: {
            type: 'button',
            icon: 'Baseline',
            label: t('textColor'),
            caret: true,
            requires: 'color',
            indicator: () => editor.getAttributes('textStyle').color || null,
            run: (anchor) => colorPopover(desk, anchor, { type: 'color' }),
        },
        highlight: {
            type: 'button',
            icon: 'Highlighter',
            label: t('highlight'),
            caret: true,
            requires: 'highlight',
            shortcut: 'Mod-Shift-H',
            indicator: () => editor.getAttributes('highlight').color || (editor.isActive('highlight') ? '#fde047' : null),
            active: () => editor.isActive('highlight'),
            run: (anchor) => colorPopover(desk, anchor, { type: 'highlight' }),
        },
        clearFormatting: {
            type: 'button',
            icon: 'RemoveFormatting',
            label: t('clearFormatting'),
            shortcut: 'Mod-\\',
            run: () => chain().unsetAllMarks().clearNodes().run(),
        },

        align: {
            type: 'button',
            icon: 'AlignLeft',
            label: t('align'),
            caret: true,
            requires: 'textAlign',
            dynamicIcon: () => ({ center: 'AlignCenter', right: 'AlignRight', justify: 'AlignJustify' })[currentAlign(editor)] || 'AlignLeft',
            run: (anchor) => dropdown(anchor, [
                ['left', 'AlignLeft', t('alignLeft'), 'Mod-Shift-L'],
                ['center', 'AlignCenter', t('alignCenter'), 'Mod-Shift-E'],
                ['right', 'AlignRight', t('alignRight'), 'Mod-Shift-R'],
                ['justify', 'AlignJustify', t('alignJustify'), 'Mod-Shift-J'],
            ].map(([value, iconName, label, keys]) => ({
                label,
                icon: icon(iconName, 16),
                hint: shortcut(keys),
                active: currentAlign(editor) === value,
                run: () => chain().setTextAlign(value).run(),
            }))),
        },
        alignLeft: { type: 'button', icon: 'AlignLeft', label: t('alignLeft'), requires: 'textAlign', run: () => chain().setTextAlign('left').run(), active: () => currentAlign(editor) === 'left' },
        alignCenter: { type: 'button', icon: 'AlignCenter', label: t('alignCenter'), requires: 'textAlign', run: () => chain().setTextAlign('center').run(), active: () => editor.isActive({ textAlign: 'center' }) },
        alignRight: { type: 'button', icon: 'AlignRight', label: t('alignRight'), requires: 'textAlign', run: () => chain().setTextAlign('right').run(), active: () => editor.isActive({ textAlign: 'right' }) },
        alignJustify: { type: 'button', icon: 'AlignJustify', label: t('alignJustify'), requires: 'textAlign', run: () => chain().setTextAlign('justify').run(), active: () => editor.isActive({ textAlign: 'justify' }) },
        indent: { type: 'button', icon: 'IndentIncrease', label: t('indent'), shortcut: 'Tab', run: () => (editor.isActive('listItem') ? chain().sinkListItem('listItem').run() : editor.isActive('taskItem') ? chain().sinkListItem('taskItem').run() : chain().indent().run()) },
        outdent: { type: 'button', icon: 'IndentDecrease', label: t('outdent'), shortcut: 'Shift-Tab', run: () => (editor.isActive('listItem') ? chain().liftListItem('listItem').run() : editor.isActive('taskItem') ? chain().liftListItem('taskItem').run() : chain().outdent().run()) },
        textDirection: {
            type: 'button',
            icon: 'PilcrowRight',
            label: t('textDirection'),
            caret: true,
            run: (anchor) => dropdown(anchor, [
                { label: t('ltr'), icon: icon('PilcrowRight', 16), run: () => chain().setTextDirection('ltr').run() },
                { label: t('rtl'), icon: icon('PilcrowLeft', 16), run: () => chain().setTextDirection('rtl').run() },
            ]),
        },

        bulletList: toggle('bulletList', 'List', t('bulletList'), 'Mod-Shift-8', 'toggleBulletList'),
        orderedList: toggle('orderedList', 'ListOrdered', t('orderedList'), 'Mod-Shift-7', 'toggleOrderedList'),
        taskList: toggle('taskList', 'ListTodo', t('taskList'), 'Mod-Shift-9', 'toggleTaskList'),

        link: { type: 'button', icon: 'Link', label: t('link'), shortcut: 'Mod-K', requires: 'link', run: () => linkDialog(desk), active: () => editor.isActive('link') },
        image: { type: 'button', icon: 'Image', label: t('image'), requires: 'image', run: () => imageDialog(desk) },
        attachment: {
            type: 'button',
            icon: 'Paperclip',
            label: t('attachment'),
            requires: 'attachment',
            hidden: () => !desk.config.upload?.url,
            run: async () => {
                const files = await pickFile((desk.config.upload.files || []).join(','));
                if (files.length) insertFiles(desk, files);
            },
        },
        video: { type: 'button', icon: 'Video', label: t('video'), requires: 'video', run: () => mediaDialog(desk, 'video') },
        audio: { type: 'button', icon: 'Music', label: t('audio'), requires: 'audio', run: () => mediaDialog(desk, 'audio') },
        table: { type: 'button', icon: 'Table', label: t('table'), caret: true, requires: 'table', run: (anchor) => tablePopover(desk, anchor), active: () => editor.isActive('table') },
        embed: { type: 'button', icon: 'MonitorPlay', label: t('embed'), requires: 'embed', run: () => embedDialog(desk) },
        blockquote: toggle('blockquote', 'Quote', t('blockquote'), 'Mod-Shift-B', 'toggleBlockquote'),
        codeBlock: toggle('codeBlock', 'SquareCode', t('codeBlock'), 'Mod-Alt-C', 'toggleCodeBlock'),
        callout: {
            type: 'button',
            icon: 'Info',
            label: t('callout'),
            caret: true,
            requires: 'callout',
            active: () => editor.isActive('callout'),
            run: (anchor) => dropdown(anchor, [
                ...CALLOUT_TYPES.map((type) => ({
                    label: t(`callout${type[0].toUpperCase()}${type.slice(1)}`),
                    icon: icon(CALLOUT_ICONS[type], 16),
                    active: editor.isActive('callout', { type }),
                    run: () => chain().toggleCallout(type).run(),
                })),
                ...(editor.isActive('callout') ? ['|', { label: t('remove'), icon: icon('Trash2', 16), run: () => chain().unsetCallout().run() }] : []),
            ]),
        },
        details: {
            type: 'button',
            icon: 'ListCollapse',
            label: t('details'),
            requires: 'details',
            active: () => editor.isActive('details'),
            run: () => (editor.isActive('details') ? chain().unsetDetails().run() : chain().setDetails().run()),
        },
        math: {
            type: 'button',
            icon: 'Sigma',
            label: t('math'),
            requires: 'inlineMath',
            run: () => mathDialog(desk, { latex: editor.state.doc.textBetween(editor.state.selection.from, editor.state.selection.to) }),
        },
        horizontalRule: { type: 'button', icon: 'Minus', label: t('horizontalRule'), requires: 'horizontalRule', run: () => chain().setHorizontalRule().run() },
        emoji: { type: 'button', icon: 'Smile', label: t('emoji'), run: (anchor) => emojiPopover(desk, anchor) },
        insert: {
            type: 'button',
            icon: 'Plus',
            label: t('insert'),
            caret: true,
            run: (anchor) => dropdown(anchor, desk.slashItems().map((item) => ({ ...item, icon: item.icon, label: item.label, run: () => item.run() }))),
        },

        findReplace: { type: 'button', icon: 'Search', label: t('findReplace'), shortcut: 'Mod-F', run: () => desk.toggleFind(), active: () => desk.findOpen },
        source: { type: 'button', icon: 'FileCode', label: t('source'), run: () => desk.toggleSource(), active: () => desk.sourceOpen, keepEnabled: true },
        toc: { type: 'button', icon: 'ListTree', label: t('toc'), requires: 'tableOfContents', run: () => desk.toggleToc(), active: () => desk.tocOpen, keepEnabled: true },
        invisibles: { type: 'button', icon: 'Pilcrow', label: t('invisibles'), requires: 'invisibleCharacters', run: () => editor.commands.toggleInvisibleCharacters(), active: () => editor.storage.invisibleCharacters?.visibility?.() },
        fullscreen: {
            type: 'button',
            icon: 'Maximize2',
            label: t('fullscreen'),
            shortcut: 'Mod-Shift-F',
            keepEnabled: true,
            dynamicIcon: () => (desk.fullscreen ? 'Minimize2' : 'Maximize2'),
            run: () => desk.toggleFullscreen(),
            active: () => desk.fullscreen,
        },
        theme: {
            type: 'button',
            icon: 'Moon',
            label: t('theme'),
            keepEnabled: true,
            dynamicIcon: () => (desk.resolvedTheme === 'dark' ? 'Sun' : 'Moon'),
            run: () => desk.toggleTheme(),
        },
        focusMode: { type: 'button', icon: 'Focus', label: t('focusMode'), keepEnabled: true, run: () => desk.toggleFocusMode(), active: () => desk.focusMode },
        preview: { type: 'button', icon: 'Eye', label: t('preview'), keepEnabled: true, run: () => desk.togglePreview(), active: () => desk.previewMode },
        export: {
            type: 'button',
            icon: 'Download',
            label: t('export'),
            caret: true,
            keepEnabled: true,
            run: (anchor) => dropdown(anchor, [
                { label: t('exportHtml'), icon: icon('FileCode', 16), run: () => desk.export('html') },
                { label: t('exportMarkdown'), icon: icon('FileDown', 16), run: () => desk.export('markdown') },
                { label: t('exportText'), icon: icon('FileText', 16), run: () => desk.export('text') },
                '|',
                { label: t('copyHtml'), icon: icon('Copy', 16), run: () => desk.export('clipboard') },
                { label: t('print'), icon: icon('Printer', 16), hint: shortcut('Mod-P'), run: () => desk.print() },
            ]),
        },
        columns: {
            type: 'button',
            icon: 'Columns3',
            label: t('columns'),
            caret: true,
            requires: 'columns',
            active: () => editor.isActive('columns'),
            run: (anchor) => dropdown(anchor, [
                ...['1-1', '2-1', '1-2', '1-1-1', '1-2-1', '1-1-1-1'].map((layout) => ({
                    label: t(`layout_${layout}`),
                    icon: layoutIcon(layout),
                    run: () => (editor.isActive('columns') ? chain().setColumnLayout(layout).run() : chain().insertColumns(layout).run()),
                })),
                ...(editor.isActive('columns') ? ['|', { label: t('removeColumns'), icon: icon('Trash2', 16), run: () => chain().unsetColumns().run() }] : []),
            ]),
        },
        gallery: { type: 'button', icon: 'Images', label: t('gallery'), requires: 'gallery', run: () => desk.dialogs.gallery() },
        mediaLibrary: { type: 'button', icon: 'FolderOpen', label: t('mediaLibrary'), hidden: () => !desk.config.endpoints?.media, run: () => desk.dialogs.library() },
        footnote: { type: 'button', icon: 'Asterisk', label: t('footnote'), requires: 'footnote', run: () => desk.dialogs.footnote() },
        button: { type: 'button', icon: 'MousePointerClick', label: t('button'), requires: 'ctaButton', run: () => desk.dialogs.button() },
        pageBreak: { type: 'button', icon: 'SeparatorHorizontal', label: t('pageBreak'), shortcut: 'Mod-Shift-Enter', requires: 'pageBreak', run: () => chain().setPageBreak().run() },
        specialChars: { type: 'button', icon: 'Omega', label: t('specialChars'), run: (anchor) => specialCharsPopover(desk, anchor) },
        diagram: { type: 'button', icon: 'Workflow', label: t('diagram'), requires: 'diagram', run: () => desk.dialogs.diagram() },
        chart: { type: 'button', icon: 'ChartColumn', label: t('chart'), requires: 'chart', run: () => desk.dialogs.chart() },
        bookmark: { type: 'button', icon: 'Bookmark', label: t('bookmark'), requires: 'bookmark', run: () => desk.dialogs.bookmark() },
        textCase: {
            type: 'button',
            icon: 'CaseSensitive',
            label: t('textCase'),
            caret: true,
            run: (anchor) => dropdown(anchor, ['upper', 'lower', 'title', 'sentence', 'toggle'].map((mode) => ({
                label: t(`case_${mode}`),
                run: () => transformCase(editor, mode),
            }))),
        },
        clearFloat: {
            type: 'button',
            icon: 'WrapText',
            label: t('clearFloat'),
            requires: 'blockAttributes',
            active: () => Boolean(editor.getAttributes('paragraph').clear || editor.getAttributes('heading').clear),
            run: () => chain().toggleClear().run(),
        },
        comments: {
            type: 'button',
            icon: 'MessagesSquare',
            label: t('comments'),
            keepEnabled: true,
            hidden: () => !desk.comments,
            badge: () => desk.comments?.openCount() || 0,
            active: () => desk.commentsOpen,
            run: () => desk.toggleComments(),
        },
        suggestions: {
            type: 'button',
            icon: 'FilePenLine',
            label: t('suggestMode'),
            caret: true,
            requires: 'suggestions',
            hidden: () => desk.config.suggestions === false,
            active: () => desk.suggesting,
            run: (anchor) => dropdown(anchor, [
                { label: desk.suggesting ? t('stopSuggesting') : t('startSuggesting'), icon: icon('FilePenLine', 16), active: desk.suggesting, run: () => desk.toggleSuggestionMode() },
                '|',
                { label: t('acceptAll'), icon: icon('CircleCheck', 16), run: () => chain().acceptAllSuggestions().run() },
                { label: t('rejectAll'), icon: icon('Ban', 16), run: () => chain().rejectAllSuggestions().run() },
            ]),
        },
        shortcuts: { type: 'button', icon: 'Keyboard', label: t('shortcuts'), shortcut: 'Mod-/', keepEnabled: true, run: () => shortcutsDialog(desk) },
    };

    function layoutIcon(layout) {
        const parts = layout.split('-').map(Number);
        const total = parts.reduce((a, b) => a + b, 0);
        let x = 2;
        const rects = parts.map((p) => {
            const w = (20 * p) / total - 1;
            const r = `<rect x="${x}" y="5" width="${w}" height="14" rx="1.5"/>`;
            x += w + 1;
            return r;
        }).join('');
        return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">${rects}</svg>`;
    }

    function currentAlign(ed) {
        return ['center', 'right', 'justify'].find((value) => ed.isActive({ textAlign: value })) || 'left';
    }
}

/**
 * Renders the toolbar from a list like ['undo', 'redo', '|', 'bold', ...].
 */
export class Toolbar {
    constructor(desk, layout) {
        this.desk = desk;
        this.items = createItems(desk);
        this.controls = [];
        this.el = h('div', { class: 'de-toolbar', role: 'toolbar', 'aria-label': 'Formatting' });

        const available = (name) => {
            const item = this.items[name];
            if (!item) return false;
            if (item.requires && !desk.editor.extensionManager.extensions.some((e) => e.name === item.requires)) return false;
            return true;
        };

        let group = h('div', { class: 'de-toolbar-group' });
        const flush = () => {
            if (group.childElementCount) this.el.append(group);
            group = h('div', { class: 'de-toolbar-group' });
        };

        for (const name of layout) {
            if (name === '|') {
                flush();
                continue;
            }
            if (name === '->') {
                flush();
                this.el.append(h('div', { class: 'de-toolbar-spacer' }));
                continue;
            }
            if (!available(name)) continue;
            const item = this.items[name];
            const control = this.renderItem(name, item);
            group.append(control.el);
            this.controls.push(control);
        }
        flush();

        this.el.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const buttons = [...this.el.querySelectorAll('button:not([disabled])')].filter((b) => b.offsetParent);
            const index = buttons.indexOf(document.activeElement);
            if (index < 0) return;
            event.preventDefault();
            const next = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: buttons.length - 1 }[event.key];
            buttons[(next + buttons.length) % buttons.length].focus();
        });
    }

    renderItem(name, item) {
        if (item.type === 'select') {
            const value = h('span', { class: 'de-select-value' });
            const el = h('button', {
                type: 'button',
                class: 'de-select',
                style: { width: item.width },
                title: item.label,
                'aria-label': item.label,
                'aria-haspopup': 'menu',
                onMousedown: (event) => event.preventDefault(),
                onClick: () => item.open(el),
            }, [value, h('span', { class: 'de-select-caret', html: icon('ChevronDown', 14) })]);

            return {
                el,
                item,
                update: (disabled) => {
                    const current = item.value();
                    value.textContent = current;
                    // Accessible name starts with the visible value (WCAG 2.5.3 label in name).
                    el.setAttribute('aria-label', `${current} — ${item.label}`);
                    el.disabled = disabled;
                },
            };
        }

        const el = iconButton(item.icon, item.label, () => item.run(el), {
            shortcut: shortcut(item.shortcut),
            caret: item.caret,
            class: item.caret ? 'de-btn-caret' : '',
            attrs: { 'data-item': name, ...(item.caret ? { 'aria-haspopup': 'true' } : {}) },
        });
        let lastIcon = item.icon;
        let badge = null;
        if (item.badge) {
            badge = h('span', { class: 'de-btn-badge', hidden: true });
            el.append(badge);
        }
        let bar = null;
        if (item.indicator) {
            bar = h('span', { class: 'de-color-bar' });
            el.append(bar);
        }

        return {
            el,
            item,
            update: (disabled) => {
                if (item.dynamicIcon) {
                    const next = item.dynamicIcon();
                    if (next !== lastIcon) {
                        lastIcon = next;
                        el.querySelector('svg').outerHTML = icon(next);
                    }
                }
                const active = item.active?.() || false;
                el.classList.toggle('is-active', Boolean(active));
                el.setAttribute('aria-pressed', active ? 'true' : 'false');
                el.disabled = item.keepEnabled ? false : disabled || Boolean(item.disabled?.());
                el.hidden = Boolean(item.hidden?.());
                if (bar) bar.style.background = item.indicator() || 'transparent';
                if (badge) {
                    const count = item.badge();
                    badge.hidden = !count;
                    badge.textContent = count > 9 ? '9+' : String(count);
                }
            },
        };
    }

    update() {
        const locked = !this.desk.editor.isEditable || this.desk.sourceOpen || this.desk.previewMode;
        for (const control of this.controls) {
            try {
                control.update(locked);
            } catch {
                // An item whose extension is disabled can throw; ignore it.
            }
        }
    }
}
