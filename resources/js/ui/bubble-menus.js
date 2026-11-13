import BubbleMenu from '@tiptap/extension-bubble-menu';
import { NodeSelection } from '@tiptap/pm/state';
import { CellSelection } from '@tiptap/pm/tables';
import { h, iconButton, safeUrl } from './dom.js';
import { icon } from './icons.js';
import { colorPopover, copyToClipboard, imageDialog, linkDialog } from './dialogs.js';
import { Popover, menu } from './popover.js';
import { COLUMN_LAYOUTS, findColumns } from '../extensions/columns.js';
import { suggestionAtCursor } from '../extensions/suggestions.js';

export const CODE_LANGUAGES = [
    ['', 'plainText'], ['bash', 'Bash'], ['c', 'C'], ['cpp', 'C++'], ['csharp', 'C#'], ['css', 'CSS'], ['dart', 'Dart'], ['diff', 'Diff'], ['dockerfile', 'Dockerfile'],
    ['go', 'Go'], ['graphql', 'GraphQL'], ['xml', 'HTML / XML'], ['java', 'Java'], ['javascript', 'JavaScript'], ['json', 'JSON'],
    ['kotlin', 'Kotlin'], ['less', 'Less'], ['lua', 'Lua'], ['makefile', 'Makefile'], ['markdown', 'Markdown'], ['objectivec', 'Objective-C'],
    ['perl', 'Perl'], ['php', 'PHP'], ['python', 'Python'], ['r', 'R'], ['ruby', 'Ruby'], ['rust', 'Rust'], ['scss', 'SCSS'],
    ['shell', 'Shell'], ['sql', 'SQL'], ['swift', 'Swift'], ['typescript', 'TypeScript'], ['yaml', 'YAML'],
];

const isTextSelection = (editor, state) => {
    const { selection } = state;
    if (selection.empty || selection instanceof NodeSelection || selection instanceof CellSelection) return false;
    if (editor.isActive('codeBlock') || editor.isActive('image')) return false;
    if (suggestionAtCursor(state) && selection.empty) return false;
    return state.doc.textBetween(selection.from, selection.to).trim().length > 0;
};

function bar(children) {
    return h('div', { class: 'de-bubble', role: 'toolbar' }, children);
}

function sep() {
    return h('span', { class: 'de-bubble-sep' });
}

/**
 * Builds the BubbleMenu extensions. Each one has its own element and visibility
 * rule so that only one menu shows for any given selection.
 */
export function createBubbleMenus(desk) {
    const t = desk.t;
    const menus = [];
    const refreshers = [];
    const ed = () => desk.editor;
    const run = (fn) => () => fn(ed().chain().focus()).run();

    const markButton = (name, iconName, label, command) => {
        const btn = iconButton(iconName, label, run((c) => c[command]()));
        refreshers.push(() => btn.classList.toggle('is-active', ed().isActive(name)));
        return btn;
    };

    const register = (key, element, shouldShow, options = {}) => {
        menus.push(
            BubbleMenu.extend({ name: `bubbleMenu_${key}` }).configure({
                pluginKey: `deskBubble_${key}`,
                element,
                appendTo: () => desk.layer,
                updateDelay: 120,
                shouldShow: ({ editor, state }) => {
                    if (!editor.isEditable || desk.previewMode) return false;
                    const show = shouldShow(editor, state);
                    if (show) queueMicrotask(() => desk.refreshBubbles());
                    return show;
                },
                options: { strategy: 'fixed', placement: 'top', offset: 8, flip: true, shift: { padding: 8 }, ...options },
            }),
        );
    };

    /* ----------------------------------------------------------- text menu */
    const styleBtn = h('button', {
        type: 'button',
        class: 'de-select de-select-sm',
        onMousedown: (e) => e.preventDefault(),
        onClick: () => {
            const items = [
                { label: t('paragraph'), icon: icon('Pilcrow', 16), run: run((c) => c.setParagraph()) },
                ...[1, 2, 3].map((level) => ({ label: t('heading', { level }), icon: icon(`Heading${level}`, 16), run: run((c) => c.toggleHeading({ level })) })),
                { label: t('blockquote'), icon: icon('Quote', 16), run: run((c) => c.toggleBlockquote()) },
                { label: t('bulletList'), icon: icon('List', 16), run: run((c) => c.toggleBulletList()) },
                { label: t('orderedList'), icon: icon('ListOrdered', 16), run: run((c) => c.toggleOrderedList()) },
                { label: t('taskList'), icon: icon('ListTodo', 16), run: run((c) => c.toggleTaskList()) },
            ];
            const popover = new Popover(desk, { anchor: styleBtn, content: menu(items, (item) => { popover.close(); item.run(); }) });
        },
    }, [h('span', { class: 'de-select-value', text: t('textStyle') }), h('span', { class: 'de-select-caret', html: icon('ChevronDown', 14) })]);
    refreshers.push(() => {
        let label = t('paragraph');
        for (let level = 1; level <= 6; level++) if (ed().isActive('heading', { level })) label = t('heading', { level });
        if (ed().isActive('bulletList')) label = t('bulletList');
        if (ed().isActive('orderedList')) label = t('orderedList');
        if (ed().isActive('taskList')) label = t('taskList');
        if (ed().isActive('blockquote')) label = t('blockquote');
        styleBtn.querySelector('.de-select-value').textContent = label;
    });

    const colorBtn = iconButton('Baseline', t('textColor'), () => colorPopover(desk, colorBtn, { type: 'color' }));
    const highlightBtn = iconButton('Highlighter', t('highlight'), () => colorPopover(desk, highlightBtn, { type: 'highlight' }));
    const linkBtn = iconButton('Link', t('link'), () => linkDialog(desk));
    refreshers.push(() => linkBtn.classList.toggle('is-active', ed().isActive('link')));

    register('text', bar([
        styleBtn,
        sep(),
        markButton('bold', 'Bold', t('bold'), 'toggleBold'),
        markButton('italic', 'Italic', t('italic'), 'toggleItalic'),
        markButton('underline', 'Underline', t('underline'), 'toggleUnderline'),
        markButton('strike', 'Strikethrough', t('strike'), 'toggleStrike'),
        markButton('code', 'Code', t('code'), 'toggleCode'),
        sep(),
        linkBtn,
        colorBtn,
        highlightBtn,
        iconButton('RemoveFormatting', t('clearFormatting'), run((c) => c.unsetAllMarks())),
        ...(desk.config.commentsEnabled ? [sep(), iconButton('MessageSquarePlus', t('addComment'), () => desk.comments?.startThread(), { class: 'de-btn-comment' })] : []),
    ]), (editor, state) => isTextSelection(editor, state));

    /* ---------------------------------------------------- suggestion review */
    const suggestionInfo = h('span', { class: 'de-bubble-label' });
    let currentSuggestion = null;
    refreshers.push(() => {
        currentSuggestion = suggestionAtCursor(ed().state);
        if (currentSuggestion) {
            suggestionInfo.textContent = `${t(currentSuggestion.type === 'insertion' ? 'suggestedInsertion' : 'suggestedDeletion')}${currentSuggestion.author ? ` · ${currentSuggestion.author}` : ''}`;
            suggestionInfo.dataset.kind = currentSuggestion.type;
        }
    });
    register('suggestion', bar([
        suggestionInfo,
        sep(),
        iconButton('Check', t('accept'), () => currentSuggestion && ed().chain().focus().acceptSuggestion(currentSuggestion.id).run(), { class: 'de-btn-accept' }),
        iconButton('X', t('reject'), () => currentSuggestion && ed().chain().focus().rejectSuggestion(currentSuggestion.id).run(), { class: 'de-btn-danger' }),
    ]), (editor, state) => state.selection.empty && Boolean(suggestionAtCursor(state)), { placement: 'bottom' });

    /* -------------------------------------------------------- columns */
    const layoutButtons = Object.keys(COLUMN_LAYOUTS).map((layout) => {
        const btn = h('button', {
            type: 'button',
            class: 'de-btn de-layout-btn',
            title: layout.replace(/-/g, ' : '),
            'aria-label': `${t('layout')} ${layout}`,
            onMousedown: (e) => e.preventDefault(),
            onClick: run((c) => c.setColumnLayout(layout)),
        }, layout.split('-').map((w) => h('span', { style: { flexGrow: w } })));
        refreshers.push(() => btn.classList.toggle('is-active', findColumns(ed().state)?.node.attrs.layout === layout));
        return btn;
    });
    register('columns', bar([
        ...layoutButtons,
        sep(),
        iconButton('Columns2', t('removeColumns'), run((c) => c.unsetColumns()), { class: 'de-btn-danger' }),
    ]), (editor, state) => state.selection.empty && Boolean(findColumns(state)) && !editor.isActive('table') && !editor.isActive('codeBlock') && !suggestionAtCursor(state), { placement: 'top-start' });

    /* ----------------------------------------------------------- link menu */
    const linkHref = h('a', { class: 'de-bubble-link', target: '_blank', rel: 'noopener noreferrer' });
    refreshers.push(() => {
        const href = ed().getAttributes('link').href || '';
        linkHref.textContent = href.replace(/^https?:\/\//, '').slice(0, 42) + (href.length > 50 ? '…' : '');
        linkHref.href = safeUrl(href) || '#';
        linkHref.title = href;
    });
    register('link', bar([
        h('span', { class: 'de-bubble-icon', html: icon('ExternalLink', 15) }),
        linkHref,
        sep(),
        iconButton('Pencil', t('editLink'), () => linkDialog(desk)),
        iconButton('Copy', t('copy'), () => copyToClipboard(desk, ed().getAttributes('link').href || '')),
        iconButton('Unlink', t('removeLink'), run((c) => c.extendMarkRange('link').unsetLink())),
    ]), (editor, state) => state.selection.empty && editor.isActive('link'), { placement: 'bottom' });

    /* ---------------------------------------------------------- image menu */
    const alignButtons = [['left', 'AlignLeft', t('imageAlignLeft')], ['center', 'AlignCenter', t('imageAlignCenter')], ['right', 'AlignRight', t('imageAlignRight')], ['full', 'Columns2', t('imageFull')]]
        .map(([align, iconName, label]) => {
            const btn = iconButton(iconName, label, run((c) => c.updateAttributes('image', { align, width: align === 'full' ? null : ed().getAttributes('image').width })));
            refreshers.push(() => btn.classList.toggle('is-active', ed().getAttributes('image').align === align));
            return btn;
        });
    register('image', bar([
        ...alignButtons,
        sep(),
        h('button', {
            type: 'button',
            class: 'de-btn de-btn-text',
            text: 'Alt',
            title: t('altText'),
            onMousedown: (e) => e.preventDefault(),
            onClick: () => imageDialog(desk, ed().getAttributes('image')),
        }),
        iconButton('Crop', t('editImage'), () => {
            const pos = ed().state.selection.from;
            import('./image-editor.js').then(({ editImage }) => editImage(desk, pos));
        }),
        iconButton('Pencil', t('replaceImage'), () => imageDialog(desk, ed().getAttributes('image'))),
        iconButton('ExternalLink', t('openLink'), () => window.open(ed().getAttributes('image').src, '_blank', 'noopener')),
        sep(),
        iconButton('Trash2', t('deleteBlock'), run((c) => c.deleteSelection()), { class: 'de-btn-danger' }),
    ]), (editor, state) => state.selection instanceof NodeSelection && state.selection.node.type.name === 'image');

    /* ---------------------------------------------------------- embed / attachment menu */
    const editAtom = iconButton('Pencil', t('edit'), () => {
        const { node, from } = ed().state.selection;
        const handlers = { ctaButton: desk.dialogs.button, diagram: desk.dialogs.diagram, chart: desk.dialogs.chart };
        handlers[node?.type.name]?.({ node, pos: from });
    });
    const galleryCols = h('select', { class: 'de-native-select', 'aria-label': t('columnsCount') }, [2, 3, 4, 5].map((n) => h('option', { value: n, text: `${n} ${t('columnsShort')}` })));
    galleryCols.addEventListener('change', () => ed().chain().focus().updateAttributes('gallery', { columns: Number(galleryCols.value) }).run());
    refreshers.push(() => {
        const name = ed().state.selection.node?.type.name;
        editAtom.hidden = !['ctaButton', 'diagram', 'chart'].includes(name);
        galleryCols.hidden = name !== 'gallery';
        if (name === 'gallery') galleryCols.value = String(ed().state.selection.node.attrs.columns);
    });
    register('atom', bar([
        editAtom,
        galleryCols,
        iconButton('Copy', t('duplicate'), () => desk.duplicateSelection()),
        iconButton('Trash2', t('deleteBlock'), run((c) => c.deleteSelection()), { class: 'de-btn-danger' }),
    ]), (editor, state) => state.selection instanceof NodeSelection && ['embed', 'attachment', 'horizontalRule', 'blockMath', 'video', 'audio', 'ctaButton', 'diagram', 'chart', 'gallery', 'bookmark', 'pageBreak'].includes(state.selection.node.type.name));

    /* ---------------------------------------------------------- table menu */
    const tableBtn = (iconName, label, command, cls = '') => iconButton(iconName, label, run((c) => c[command]()), { class: cls });
    const mergeBtn = tableBtn('TableCellsMerge', t('mergeCells'), 'mergeCells');
    const splitBtn = tableBtn('TableCellsSplit', t('splitCell'), 'splitCell');
    refreshers.push(() => {
        mergeBtn.disabled = !ed().can().mergeCells();
        splitBtn.disabled = !ed().can().splitCell();
    });
    const more = iconButton('Ellipsis', t('more'), () => {
        const items = [
            { label: t('addRowBefore'), icon: icon('ArrowUp', 16), run: run((c) => c.addRowBefore()) },
            { label: t('addRowAfter'), icon: icon('ArrowDown', 16), run: run((c) => c.addRowAfter()) },
            { label: t('deleteRow'), icon: icon('Rows3', 16), run: run((c) => c.deleteRow()) },
            '|',
            { label: t('addColumnBefore'), icon: icon('ArrowLeft', 16), run: run((c) => c.addColumnBefore()) },
            { label: t('addColumnAfter'), icon: icon('ArrowRight', 16), run: run((c) => c.addColumnAfter()) },
            { label: t('deleteColumn'), icon: icon('Columns3', 16), run: run((c) => c.deleteColumn()) },
            '|',
            { label: t('toggleHeaderRow'), icon: icon('PanelLeft', 16), run: run((c) => c.toggleHeaderRow()) },
            { label: t('toggleHeaderColumn'), icon: icon('PanelLeft', 16), run: run((c) => c.toggleHeaderColumn()) },
            '|',
            { label: t('deleteTable'), icon: icon('Trash2', 16), run: run((c) => c.deleteTable()) },
        ];
        const popover = new Popover(desk, { anchor: more, content: menu(items, (item) => { popover.close(); item.run(); }) });
    });
    const cellColor = iconButton('PaintBucket', t('cellColor'), () => {
        const swatches = [null, '#f4f4f5', '#fef3c7', '#dcfce7', '#dbeafe', '#ede9fe', '#fce7f3', '#fee2e2', '#e0f2fe', '#27272a'];
        const content = h('div', { class: 'de-color-panel' }, [
            h('div', { class: 'de-swatches' }, swatches.map((color) => h('button', {
                type: 'button',
                class: `de-swatch ${color ? '' : 'de-swatch-none'}`,
                style: { '--swatch': color || 'transparent' },
                title: color || t('noColor'),
                'aria-label': color || t('noColor'),
                onClick: () => {
                    ed().chain().focus().setCellBackground(color).run();
                    popover.close();
                },
            }))),
            h('div', { class: 'de-tabs' }, ['top', 'middle', 'bottom'].map((v) => h('button', { type: 'button', class: 'de-tab', text: t(`valign_${v}`), onClick: () => { ed().chain().focus().setCellVerticalAlign(v).run(); popover.close(); } }))),
        ]);
        const popover = new Popover(desk, { anchor: cellColor, content });
    });
    register('table', bar([
        cellColor,
        sep(),
        tableBtn('ArrowUp', t('addRowBefore'), 'addRowBefore'),
        tableBtn('ArrowDown', t('addRowAfter'), 'addRowAfter'),
        tableBtn('ArrowLeft', t('addColumnBefore'), 'addColumnBefore'),
        tableBtn('ArrowRight', t('addColumnAfter'), 'addColumnAfter'),
        sep(),
        tableBtn('Rows3', t('deleteRow'), 'deleteRow'),
        tableBtn('Columns3', t('deleteColumn'), 'deleteColumn'),
        sep(),
        mergeBtn,
        splitBtn,
        more,
        sep(),
        tableBtn('Trash2', t('deleteTable'), 'deleteTable', 'de-btn-danger'),
    ]), (editor, state) => editor.isActive('table') && (state.selection.empty || state.selection instanceof CellSelection), {
        placement: 'top',
    });

    /* ------------------------------------------------------ code block menu */
    const langSelect = h('select', { class: 'de-native-select', 'aria-label': t('language') });
    for (const [value, label] of CODE_LANGUAGES) langSelect.append(h('option', { value, text: value ? label : t(label) }));
    langSelect.addEventListener('change', () => ed().chain().focus().updateAttributes('codeBlock', { language: langSelect.value || null }).run());
    refreshers.push(() => {
        langSelect.value = ed().getAttributes('codeBlock').language || '';
    });
    register('code', bar([
        langSelect,
        sep(),
        iconButton('Copy', t('copy'), () => {
            const { $from } = ed().state.selection;
            copyToClipboard(desk, $from.parent.textContent);
        }),
    ]), (editor) => editor.isActive('codeBlock'), { placement: 'top-end' });

    desk.refreshBubbles = () => refreshers.forEach((r) => {
        try {
            r();
        } catch {
            // ignore when the menu's node type is absent
        }
    });

    return menus;
}
