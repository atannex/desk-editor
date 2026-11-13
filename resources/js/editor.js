import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { CharacterCount, Focus, Placeholder, Selection } from '@tiptap/extensions';
import { TextStyleKit } from '@tiptap/extension-text-style';
import { TaskItem, TaskList } from '@tiptap/extension-list';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import Typography from '@tiptap/extension-typography';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { Details, DetailsContent, DetailsSummary } from '@tiptap/extension-details';
import { Mathematics } from '@tiptap/extension-mathematics';
import Mention from '@tiptap/extension-mention';
import DragHandle from '@tiptap/extension-drag-handle';
import TableOfContents from '@tiptap/extension-table-of-contents';
import FileHandler from '@tiptap/extension-file-handler';
import InvisibleCharacters from '@tiptap/extension-invisible-characters';
import { Markdown } from '@tiptap/markdown';
import { NodeSelection, TextSelection } from '@tiptap/pm/state';

import { lowlight } from './lowlight.js';
import { translator } from './i18n.js';
import { DeskImage } from './extensions/image.js';
import { Callout } from './extensions/callout.js';
import { Embed } from './extensions/embed.js';
import { Attachment } from './extensions/attachment.js';
import { Audio, Video } from './extensions/media.js';
import { Column, Columns } from './extensions/columns.js';
import { Gallery } from './extensions/gallery.js';
import { Footnote } from './extensions/footnote.js';
import { CtaButton } from './extensions/button.js';
import { PageBreak } from './extensions/page-break.js';
import { Bookmark } from './extensions/bookmark.js';
import { Diagram } from './extensions/diagram.js';
import { ChartBlock } from './extensions/chart.js';
import { TableCellStyle } from './extensions/table-cell-style.js';
import { CommentMark } from './extensions/comments.js';
import { Deletion, Insertion, Suggestions } from './extensions/suggestions.js';
import { cleanPastedHtml } from './paste.js';
import { CommentsPanel } from './ui/comments-panel.js';
import { blockDialogs } from './ui/block-dialogs.js';
import { BlockAttributes } from './extensions/block-attributes.js';
import { FindReplace } from './extensions/find-replace.js';
import { triggerMenu } from './extensions/trigger-menu.js';
import { Toolbar } from './ui/toolbar.js';
import { createBubbleMenus } from './ui/bubble-menus.js';
import { suggestionRenderer } from './ui/suggestion-list.js';
import { Popover, menu } from './ui/popover.js';
import { openDialog, toast } from './ui/dialog.js';
import { debounce, download, h, iconButton, shortcut } from './ui/dom.js';
import { icon } from './ui/icons.js';
import { embedDialog, imageDialog, linkDialog, mathDialog, mediaDialog, shortcutsDialog, tablePopover } from './ui/dialogs.js';
import { insertFiles, pickFile } from './upload.js';

export const DEFAULTS = {
    format: 'html',
    content: null,
    placeholder: null,
    toolbar: [
        'undo', 'redo', '|',
        'blockStyle', 'fontFamily', 'fontSize', '|',
        'bold', 'italic', 'underline', 'strike', 'textColor', 'highlight', '|',
        'align', 'bulletList', 'orderedList', 'taskList', 'outdent', 'indent', '|',
        'link', 'image', 'video', 'gallery', 'table', 'columns', 'embed', 'callout', 'insert', '->',
        'comments', 'suggestions', 'findReplace', 'source', 'toc', 'focusMode', 'fullscreen', 'theme', 'export', 'shortcuts',
    ],
    features: {
        bubbleMenu: true,
        slashCommands: true,
        dragHandle: true,
        typography: true,
        markdownPaste: true,
        statusbar: true,
        emojiSuggestions: true,
        stickyToolbar: true,
    },
    headings: [1, 2, 3, 4, 5, 6],
    fonts: [
        { label: 'Sans Serif', value: 'ui-sans-serif, system-ui, sans-serif' },
        { label: 'Serif', value: 'ui-serif, Georgia, serif' },
        { label: 'Monospace', value: 'ui-monospace, SFMono-Regular, monospace' },
        { label: 'Inter', value: 'Inter, sans-serif' },
        { label: 'Georgia', value: 'Georgia, serif' },
        { label: 'Arial', value: 'Arial, sans-serif' },
        { label: 'Times New Roman', value: '"Times New Roman", serif' },
        { label: 'Courier New', value: '"Courier New", monospace' },
    ],
    fontSizes: ['12px', '14px', '16px', '18px', '20px', '24px', '30px', '36px', '48px', '60px'],
    colors: ['#0f172a', '#475569', '#94a3b8', '#dc2626', '#ea580c', '#d97706', '#16a34a', '#0d9488', '#0284c7', '#4f46e5', '#7c3aed', '#c026d3', '#db2777', '#ffffff'],
    highlights: ['#fef08a', '#fed7aa', '#fecaca', '#fbcfe8', '#e9d5ff', '#c7d2fe', '#bae6fd', '#a7f3d0', '#d9f99d', '#e2e8f0'],
    theme: 'auto',
    accent: null,
    minHeight: '18rem',
    maxHeight: null,
    readonly: false,
    autofocus: false,
    spellcheck: true,
    limit: { characters: null, words: null },
    autosave: { enabled: false, key: null, debounce: 800 },
    upload: { url: null, maxSize: 10240, images: ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], files: [] },
    mentions: { url: null, trigger: '@' },
    link: { newTab: true, autolink: true },
    csrf: null,
    strings: {},
};

const instances = new WeakMap();

export class DeskEditor {
    static get(el) {
        return instances.get(el.closest?.('.desk-editor') || el) || null;
    }

    constructor(root, config = {}) {
        if (instances.has(root)) return instances.get(root);
        instances.set(root, this);

        this.root = root;
        this.config = mergeConfig(DEFAULTS, config);
        this.t = translator(this.config.strings);
        this.input = this.config.input ? document.getElementById(this.config.input) : root.querySelector('[data-desk-input]');
        this.listeners = {};
        this.findOpen = false;
        this.sourceOpen = false;
        this.tocOpen = false;
        this.previewMode = false;
        this.fullscreen = false;
        this.activePopover = null;

        this.commentsOpen = false;
        this.focusMode = false;

        this.buildShell();
        this.applyTheme(this.config.theme === 'auto' ? safeStorage('get', 'desk-editor-theme') || 'auto' : this.config.theme);
        this.dialogs = blockDialogs(this);
        this.ready = this.boot();
    }

    async boot() {
        if (this.config.collaborationEnabled && this.config.collaboration && this.config.documentKey) {
            try {
                const { setupCollaboration } = await import('./collab/collab.js');
                this.collab = await setupCollaboration(this);
            } catch (error) {
                console.error('[desk-editor] collaboration unavailable', error);
                this.collab = null;
            }
        }

        this.createEditor();
        this.toolbar = new Toolbar(this, this.config.toolbar);
        this.toolbarSlot.replaceWith(this.toolbar.el);

        if (this.config.commentsEnabled && this.config.documentKey && this.config.endpoints?.threads) {
            this.comments = new CommentsPanel(this);
            this.commentsSlot.replaceWith(this.comments.el);
            this.comments.load();
        }

        this.updateUi();
        this.bindForm();
        this.root.classList.add('is-ready');

        if (this.collab) {
            this.collab.provider.onPeers((peers) => this.renderPeers(peers));
            const { seed } = await this.collab.ready;
            if (seed && this.initialContent) {
                this.editor.commands.setContent(this.parseInitial(this.initialContent), { emitUpdate: false, contentType: this.config.format === 'markdown' ? 'markdown' : undefined });
            }
            this.lastValue = this.serialize();
            this.root.classList.add('is-collaborative');
        } else {
            this.restoreDraft();
        }

        this.emit('ready', { editor: this });
        return this;
    }

    /* ------------------------------------------------------------- structure */

    buildShell() {
        const { config } = this;
        this.root.classList.add('desk-editor');
        this.root.classList.toggle('de-sticky', config.features.stickyToolbar);
        this.root.setAttribute('data-desk-ready', '');
        if (config.accent) this.root.style.setProperty('--de-accent', config.accent);

        this.toolbarSlot = h('div');
        this.findPanel = h('div', { class: 'de-find', hidden: true });
        this.banner = h('div', { class: 'de-banner', hidden: true });
        this.editorEl = h('div', { class: 'de-editor-mount' });
        this.canvas = h('div', { class: 'de-canvas', style: { minHeight: config.minHeight, maxHeight: config.maxHeight } }, [this.editorEl]);
        this.source = h('textarea', { class: 'de-source', spellcheck: 'false', hidden: true, 'aria-label': this.t('source') });
        this.sourceBar = h('div', { class: 'de-source-bar', hidden: true });
        this.tocPanel = h('aside', { class: 'de-toc', hidden: true, 'aria-label': this.t('toc') });
        this.commentsSlot = h('div', { hidden: true });
        this.presence = h('div', { class: 'de-presence', hidden: true, 'aria-label': this.t('collaborators') });
        this.statusbar = h('div', { class: 'de-statusbar', hidden: !config.features.statusbar });
        this.layer = h('div', { class: 'desk-editor-layer' });
        if (config.accent) this.layer.style.setProperty('--de-accent', config.accent);

        this.shell = h('div', { class: 'de-shell' }, [
            this.toolbarSlot,
            this.findPanel,
            this.banner,
            h('div', { class: 'de-body' }, [h('div', { class: 'de-main' }, [this.canvas, this.sourceBar, this.source]), this.tocPanel, this.commentsSlot]),
            this.statusbar,
        ]);

        [...this.root.children].forEach((child) => {
            if (child !== this.input) child.remove();
        });
        this.shell.append(this.presence);
        this.root.append(this.shell);
        if (this.config.dir) {
            this.root.setAttribute('dir', this.config.dir);
            this.layer.setAttribute('dir', this.config.dir);
        }
        document.body.append(this.layer);
    }

    extensions() {
        const { config, t } = this;
        const f = config.features;
        const exts = [
            StarterKit.configure({
                heading: { levels: config.headings },
                codeBlock: false,
                undoRedo: this.collab ? false : undefined,
                link: {
                    openOnClick: false,
                    autolink: config.link.autolink !== false,
                    defaultProtocol: 'https',
                    HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: null },
                    isAllowedUri: (url, ctx) => ctx.defaultValidate(url) && !/^(javascript|vbscript|data):/i.test(url),
                },
                dropcursor: { color: 'var(--de-accent)', width: 2 },
            }),
            TextStyleKit,
            Highlight.configure({ multicolor: true }),
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Subscript,
            Superscript,
            TaskList,
            TaskItem.configure({ nested: true }),
            TableKit.configure({ table: { resizable: true, HTMLAttributes: { class: 'de-table' } } }),
            CodeBlockLowlight.configure({ lowlight, defaultLanguage: null, HTMLAttributes: { class: 'de-code' } }),
            DeskImage,
            Callout,
            Embed,
            Attachment,
            Video,
            Audio,
            Columns,
            Column,
            Gallery.configure({
                onAddImages: (done) => this.dialogs.addGalleryImages(done),
                onEditCaption: (image, done) => this.dialogs.galleryCaption(image, done),
            }),
            Footnote.configure({ onEdit: (node, pos, dom) => this.dialogs.footnote({ node, pos, anchor: dom }) }),
            CtaButton,
            PageBreak,
            Bookmark,
            Diagram.configure({ onEdit: (node, pos) => this.dialogs.diagram({ node, pos }), theme: () => this.resolvedTheme }),
            ChartBlock.configure({ onEdit: (node, pos) => this.dialogs.chart({ node, pos }), theme: () => this.resolvedTheme }),
            TableCellStyle,
            CommentMark,
            Insertion,
            Deletion,
            Suggestions.configure({ author: () => config.collaboration?.user?.name || config.currentUser?.name || null }),
            Focus.configure({ className: 'has-focus', mode: 'shallowest' }),
            BlockAttributes,
            FindReplace,
            Details.configure({ persist: true, HTMLAttributes: { class: 'de-details' } }),
            DetailsSummary,
            DetailsContent,
            Mathematics.configure({
                katexOptions: { throwOnError: false },
                inlineOptions: { onClick: (node, pos) => mathDialog(this, { latex: node.attrs.latex, pos }) },
                blockOptions: { onClick: (node, pos) => mathDialog(this, { latex: node.attrs.latex, pos, block: true }) },
            }),
            TableOfContents.configure({
                scrollParent: () => (this.config.maxHeight || this.fullscreen ? this.canvas : window),
                onUpdate: (anchors) => this.renderToc(anchors),
            }),
            InvisibleCharacters.configure({ visible: false }),
            Markdown,
            Selection,
            Placeholder.configure({
                includeChildren: false,
                placeholder: ({ node }) => {
                    if (node.type.name === 'heading') return t('heading', { level: node.attrs.level });
                    if (node.type.name === 'detailsSummary') return t('detailsSummary');
                    return config.placeholder || (f.slashCommands ? t('slashPlaceholder') : t('placeholder'));
                },
            }),
            CharacterCount.configure({ limit: config.limit.characters || null }),
            this.shortcutsExtension(),
        ];

        if (this.collab) exts.push(...this.collab.extensions);
        if (f.typography) exts.push(Typography);
        if (f.bubbleMenu) exts.push(...createBubbleMenus(this));
        if (f.slashCommands) {
            exts.push(triggerMenu('slashCommands', {
                char: '/',
                items: (query) => filterItems(this.slashItems(), query),
                render: suggestionRenderer(this, { emptyText: t('noCommands'), className: 'de-slash' }),
            }));
        }
        if (f.emojiSuggestions) {
            exts.push(triggerMenu('emojiSuggestions', {
                char: ':',
                items: async (query) => (query.length < 2 ? [] : (await loadEmojis()).filter((e) => e.shortcodes.some((s) => s.startsWith(query.toLowerCase()))).slice(0, 8).map((e) => ({
                    label: `:${e.shortcodes[0]}:`,
                    emoji: e.emoji,
                    run: (editor) => editor.chain().focus().insertContent(`${e.emoji}`).run(),
                }))),
                render: suggestionRenderer(this, {
                    emptyText: t('noResults'),
                    className: 'de-emoji-suggest',
                    renderItem: (button, item) => button.append(h('span', { class: 'de-suggest-emoji', text: item.emoji }), h('span', { class: 'de-suggest-label', text: item.label })),
                }),
            }));
        }
        if (config.mentions?.url) exts.push(this.mentionExtension());
        if (f.dragHandle && !config.readonly) exts.push(this.dragHandleExtension());
        if (config.upload?.url) {
            exts.push(FileHandler.configure({
                allowedMimeTypes: [...(config.upload.images || []), ...(config.upload.videos || []), ...(config.upload.audio || []), ...(config.upload.files || [])].filter((m) => m.includes('/')),
                onDrop: (editor, files, pos) => insertFiles(this, files, pos),
                onPaste: (editor, files, html) => {
                    if (html && files.every((file) => file.type.startsWith('image/')) && /<img[^>]+src="https?:/i.test(html)) return;
                    insertFiles(this, files);
                },
            }));
        }

        return exts;
    }

    createEditor() {
        const { config } = this;
        const initial = config.content ?? this.input?.value ?? '';
        this.initialContent = initial;

        this.editor = new Editor({
            element: this.editorEl,
            extensions: this.extensions(),
            // With collaboration the shared document is seeded after syncing with peers.
            content: this.collab || initial === '' ? '' : this.parseInitial(initial),
            contentType: config.format === 'markdown' ? 'markdown' : undefined,
            editable: !config.readonly,
            autofocus: config.autofocus,
            injectCSS: false,
            editorProps: {
                attributes: {
                    class: 'desk-content de-prose',
                    spellcheck: config.spellcheck ? 'true' : 'false',
                    role: 'textbox',
                    'aria-multiline': 'true',
                    'aria-label': config.label || this.t('placeholder'),
                },
                handlePaste: (view, event) => this.handleMarkdownPaste(event),
                transformPastedHTML: (html) => cleanPastedHtml(html),
            },
            onUpdate: () => this.onChange(),
            onSelectionUpdate: () => this.scheduleUi(),
            onTransaction: () => this.scheduleUi(),
            onFocus: () => this.root.classList.add('is-focused'),
            onBlur: () => this.root.classList.remove('is-focused'),
        });

        this.editor.on('deskSearch', (data) => this.updateFindCount(data));
        this.editor.on('deskSuggestionMode', (on) => {
            this.root.classList.toggle('is-suggesting', on);
            this.scheduleUi();
        });
        this.lastValue = this.serialize();

        // Extensions (heading ids, normalisation) may adjust the document right after
        // load; treat the settled document as the pristine value.
        this.settled = false;
        setTimeout(() => {
            this.lastValue = this.serialize();
            this.settled = true;
        }, 300);
    }

    parseInitial(value) {
        if (this.config.format === 'json' && typeof value === 'string') {
            try {
                return JSON.parse(value);
            } catch {
                return value;
            }
        }

        return value;
    }

    /* ---------------------------------------------------------- extensions */

    shortcutsExtension() {
        const desk = this;

        return Extension.create({
            name: 'deskShortcuts',
            addKeyboardShortcuts() {
                return {
                    'Mod-k': () => {
                        linkDialog(desk);
                        return true;
                    },
                    'Mod-f': () => {
                        desk.toggleFind(true);
                        return true;
                    },
                    'Mod-Shift-f': () => {
                        desk.toggleFullscreen();
                        return true;
                    },
                    'Mod-/': () => {
                        shortcutsDialog(desk);
                        return true;
                    },
                    'Mod-\\': () => this.editor.chain().focus().unsetAllMarks().clearNodes().run(),
                    'Mod-p': () => {
                        desk.print();
                        return true;
                    },
                    Escape: () => {
                        if (desk.fullscreen) {
                            desk.toggleFullscreen(false);
                            return true;
                        }
                        return false;
                    },
                };
            },
        });
    }

    mentionExtension() {
        const { config, t } = this;
        let controller;

        return Mention.configure({
            HTMLAttributes: { class: 'de-mention' },
            renderText: ({ node }) => `${config.mentions.trigger}${node.attrs.label ?? node.attrs.id}`,
            renderHTML: ({ options, node }) => ['span', { ...options.HTMLAttributes, 'data-type': 'mention', 'data-id': node.attrs.id, 'data-label': node.attrs.label }, `${config.mentions.trigger}${node.attrs.label ?? node.attrs.id}`],
            suggestion: {
                char: config.mentions.trigger || '@',
                items: async ({ query }) => {
                    controller?.abort();
                    controller = new AbortController();
                    try {
                        const url = new URL(config.mentions.url, window.location.href);
                        url.searchParams.set('q', query);
                        const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' });
                        const data = await response.json();
                        return (data.data || data).slice(0, 8).map((item) => ({
                            id: item.id,
                            label: item.label ?? item.name,
                            description: item.description ?? item.email ?? null,
                            avatar: item.avatar ?? null,
                        }));
                    } catch {
                        return [];
                    }
                },
                render: suggestionRenderer(this, {
                    emptyText: t('noMentions'),
                    className: 'de-mentions',
                    renderItem: (button, item) => {
                        button.append(
                            item.avatar
                                ? h('img', { class: 'de-avatar', src: item.avatar, alt: '' })
                                : h('span', { class: 'de-avatar', text: String(item.label || '?').slice(0, 1).toUpperCase() }),
                            h('span', { class: 'de-suggest-text' }, [
                                h('span', { class: 'de-suggest-label', text: item.label }),
                                item.description ? h('span', { class: 'de-suggest-desc', text: item.description }) : null,
                            ]),
                        );
                    },
                }),
            },
        });
    }

    dragHandleExtension() {
        const handle = h('div', { class: 'de-drag-handle' }, [
            h('button', {
                type: 'button',
                class: 'de-drag-add',
                title: this.t('insert'),
                html: icon('Plus', 16),
                onMousedown: (e) => e.preventDefault(),
                onClick: () => this.insertBelowHovered(),
            }),
            h('button', {
                type: 'button',
                class: 'de-drag-grip',
                title: this.t('more'),
                html: icon('GripVertical', 16),
                onClick: (event) => this.openBlockMenu(event.currentTarget),
            }),
        ]);
        this.dragHandle = handle;

        return DragHandle.configure({
            render: () => handle,
            onNodeChange: ({ node }) => {
                this.hoveredNode = node;
            },
        });
    }

    hoveredPos() {
        const rect = this.dragHandle.getBoundingClientRect();
        const dom = this.editor.view.dom.getBoundingClientRect();
        const hit = this.editor.view.posAtCoords({ left: Math.max(rect.right + 24, dom.left + 8), top: rect.top + rect.height / 2 });
        if (!hit) return null;
        const $pos = this.editor.state.doc.resolve(hit.inside >= 0 ? hit.inside : hit.pos);

        return $pos.depth === 0 ? hit.inside : $pos.before(1);
    }

    insertBelowHovered() {
        const pos = this.hoveredPos();
        if (pos === null || pos < 0) return;
        const node = this.editor.state.doc.nodeAt(pos);
        if (!node) return;
        const end = pos + node.nodeSize;
        const empty = node.type.name === 'paragraph' && node.content.size === 0;
        const chain = this.editor.chain();
        if (empty) chain.setTextSelection(pos + 1);
        else chain.insertContentAt(end, { type: 'paragraph' }).setTextSelection(end + 1);
        chain.insertContent('/').focus().run();
    }

    openBlockMenu(anchor) {
        const pos = this.hoveredPos();
        if (pos === null || pos < 0) return;
        const { editor, t } = this;
        const node = editor.state.doc.nodeAt(pos);
        if (!node) return;
        const select = () => {
            if (node.isTextblock) editor.chain().focus().setTextSelection({ from: pos + 1, to: pos + node.nodeSize - 1 });
            else editor.chain().focus().setNodeSelection(pos);
        };
        const turn = (fn) => () => {
            editor.chain().focus().setTextSelection(pos + 1).run();
            fn(editor.chain().focus()).run();
        };
        const items = [
            { heading: t('textStyle') },
            { label: t('paragraph'), icon: icon('Pilcrow', 16), run: turn((c) => c.setParagraph()) },
            { label: t('heading', { level: 1 }), icon: icon('Heading1', 16), run: turn((c) => c.setHeading({ level: 1 })) },
            { label: t('heading', { level: 2 }), icon: icon('Heading2', 16), run: turn((c) => c.setHeading({ level: 2 })) },
            { label: t('heading', { level: 3 }), icon: icon('Heading3', 16), run: turn((c) => c.setHeading({ level: 3 })) },
            { label: t('bulletList'), icon: icon('List', 16), run: turn((c) => c.toggleBulletList()) },
            { label: t('orderedList'), icon: icon('ListOrdered', 16), run: turn((c) => c.toggleOrderedList()) },
            { label: t('taskList'), icon: icon('ListTodo', 16), run: turn((c) => c.toggleTaskList()) },
            { label: t('blockquote'), icon: icon('Quote', 16), run: turn((c) => c.toggleBlockquote()) },
            { label: t('codeBlock'), icon: icon('SquareCode', 16), run: turn((c) => c.toggleCodeBlock()) },
            { label: t('clearFloat'), icon: icon('WrapText', 16), run: turn((c) => c.toggleClear()) },
            '|',
            { label: t('duplicate'), icon: icon('Copy', 16), run: () => editor.chain().focus().insertContentAt(pos + node.nodeSize, node.toJSON()).run() },
            { label: t('copy'), icon: icon('FileText', 16), run: () => navigator.clipboard?.writeText(node.textContent).then(() => toast(this, t('copied'), 'success')) },
            { label: t('deleteBlock'), icon: icon('Trash2', 16), run: () => editor.chain().focus().deleteRange({ from: pos, to: pos + node.nodeSize }).run() },
        ].filter((item) => item === '|' || item.heading || node.isTextblock || ['duplicate', 'copy', 'deleteBlock'].some((k) => item.label === t(k)));
        if (node.isTextblock && !node.attrs.clear) items.find((i) => i.label === t('clearFloat')).active = false;
        if (!node.isTextblock) items.splice(0, items.findIndex((i) => i === '|') + 1);
        select();
        const popover = new Popover(this, { anchor, placement: 'left-start', content: menu(items, (item) => { popover.close(); item.run(); }) });
    }

    slashItems() {
        const { t } = this;
        const has = (name) => this.editor?.extensionManager.extensions.some((e) => e.name === name) ?? true;
        const c = (editor) => editor.chain().focus();
        const items = [
            { group: t('basicBlocks'), label: t('paragraph'), description: 'Plain text', icon: icon('Pilcrow'), keywords: 'text p', run: (e) => c(e).setParagraph().run() },
            { group: t('basicBlocks'), label: t('heading', { level: 1 }), description: 'Large section heading', icon: icon('Heading1'), keywords: 'h1 title', hint: '#', run: (e) => c(e).setHeading({ level: 1 }).run() },
            { group: t('basicBlocks'), label: t('heading', { level: 2 }), description: 'Medium section heading', icon: icon('Heading2'), keywords: 'h2 subtitle', hint: '##', run: (e) => c(e).setHeading({ level: 2 }).run() },
            { group: t('basicBlocks'), label: t('heading', { level: 3 }), description: 'Small section heading', icon: icon('Heading3'), keywords: 'h3', hint: '###', run: (e) => c(e).setHeading({ level: 3 }).run() },
            { group: t('basicBlocks'), label: t('bulletList'), description: 'Simple bulleted list', icon: icon('List'), keywords: 'ul unordered', hint: '-', run: (e) => c(e).toggleBulletList().run() },
            { group: t('basicBlocks'), label: t('orderedList'), description: 'List with numbering', icon: icon('ListOrdered'), keywords: 'ol numbered', hint: '1.', run: (e) => c(e).toggleOrderedList().run() },
            { group: t('basicBlocks'), label: t('taskList'), description: 'Track tasks with checkboxes', icon: icon('ListTodo'), keywords: 'todo checkbox', hint: '[ ]', run: (e) => c(e).toggleTaskList().run() },
            { group: t('basicBlocks'), label: t('blockquote'), description: 'Capture a quotation', icon: icon('Quote'), keywords: 'citation', hint: '>', run: (e) => c(e).toggleBlockquote().run() },
            { group: t('basicBlocks'), label: t('codeBlock'), description: 'Syntax highlighted code', icon: icon('SquareCode'), keywords: 'pre snippet', hint: '```', run: (e) => c(e).toggleCodeBlock().run() },
            { group: t('basicBlocks'), label: t('horizontalRule'), description: 'Visually divide sections', icon: icon('Minus'), keywords: 'hr line separator', hint: '---', run: (e) => c(e).setHorizontalRule().run() },
            { group: t('media'), label: t('image'), description: 'Upload or embed an image', icon: icon('Image'), keywords: 'picture photo', requires: 'image', run: () => imageDialog(this) },
            { group: t('media'), label: t('video'), description: 'Upload or link a video file', icon: icon('Video'), keywords: 'movie mp4 clip film', requires: 'video', run: () => mediaDialog(this, 'video') },
            { group: t('media'), label: t('audio'), description: 'Upload a podcast or sound clip', icon: icon('Music'), keywords: 'sound podcast mp3 music', requires: 'audio', run: () => mediaDialog(this, 'audio') },
            { group: t('media'), label: t('embed'), description: 'YouTube, Vimeo, Loom…', icon: icon('MonitorPlay'), keywords: 'video youtube vimeo', requires: 'embed', run: () => embedDialog(this) },
            ...(this.config.upload?.url ? [{ group: t('media'), label: t('attachment'), description: 'Upload a downloadable file', icon: icon('Paperclip'), keywords: 'file upload pdf', run: async () => {
                const files = await pickFile((this.config.upload.files || []).join(','));
                if (files.length) insertFiles(this, files);
            } }] : []),
            { group: t('media'), label: t('table'), description: 'Insert a 3 × 3 table', icon: icon('Table'), keywords: 'grid', requires: 'table', run: (e) => c(e).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
            ...['info', 'tip', 'success', 'warning', 'danger', 'note'].map((type) => ({
                group: t('advanced'),
                label: `${t('callout')} · ${t(`callout${type[0].toUpperCase()}${type.slice(1)}`)}`,
                description: `Highlighted ${type} box`,
                icon: icon({ info: 'Info', tip: 'Lightbulb', success: 'CircleCheck', warning: 'TriangleAlert', danger: 'OctagonAlert', note: 'StickyNote' }[type]),
                keywords: `callout alert admonition ${type}`,
                requires: 'callout',
                run: (e) => c(e).setCallout(type).run(),
            })),
            { group: t('layout'), label: t('columns'), description: t('columnsDescription'), icon: icon('Columns3'), keywords: 'columns layout side by side grid two three', requires: 'columns', run: (e) => c(e).insertColumns('1-1').run() },
            { group: t('layout'), label: `${t('columns')} · 3`, description: t('threeColumnsDescription'), icon: icon('Columns3'), keywords: 'columns three layout', requires: 'columns', run: (e) => c(e).insertColumns('1-1-1').run() },
            { group: t('layout'), label: t('button'), description: t('buttonDescription'), icon: icon('MousePointerClick'), keywords: 'button cta call to action link', requires: 'ctaButton', run: () => this.dialogs.button() },
            { group: t('layout'), label: t('pageBreak'), description: t('pageBreakDescription'), icon: icon('SeparatorHorizontal'), keywords: 'page break print', requires: 'pageBreak', run: (e) => c(e).setPageBreak().run() },
            { group: t('media'), label: t('gallery'), description: t('galleryDescription'), icon: icon('Images'), keywords: 'gallery grid photos images', requires: 'gallery', run: () => this.dialogs.gallery() },
            { group: t('media'), label: t('bookmark'), description: t('bookmarkDescription'), icon: icon('Bookmark'), keywords: 'link preview card bookmark unfurl', requires: 'bookmark', run: () => this.dialogs.bookmark() },
            ...(this.config.endpoints?.media ? [{ group: t('media'), label: t('mediaLibrary'), description: t('mediaLibraryDescription'), icon: icon('FolderOpen'), keywords: 'library media files uploads', run: () => this.dialogs.library() }] : []),
            { group: t('advanced'), label: t('diagram'), description: t('diagramDescription'), icon: icon('Workflow'), keywords: 'mermaid diagram flowchart sequence gantt mindmap', requires: 'diagram', run: () => this.dialogs.diagram() },
            { group: t('advanced'), label: t('chart'), description: t('chartDescription'), icon: icon('ChartColumn'), keywords: 'chart graph bar line pie data', requires: 'chart', run: () => this.dialogs.chart() },
            { group: t('advanced'), label: t('footnote'), description: t('footnoteDescription'), icon: icon('Asterisk'), keywords: 'footnote reference citation note', requires: 'footnote', run: () => this.dialogs.footnote() },
            { group: t('advanced'), label: t('specialChars'), description: t('specialCharsDescription'), icon: icon('Omega'), keywords: 'symbol character special copyright currency', run: () => import('./ui/special-chars.js').then(({ specialCharsPopover }) => specialCharsPopover(this, this.dialogs.caretAnchor())) },
            { group: t('advanced'), label: t('details'), description: 'Collapsible toggle section', icon: icon('ListCollapse'), keywords: 'toggle accordion collapse', requires: 'details', run: (e) => c(e).setDetails().run() },
            { group: t('advanced'), label: t('blockMath'), description: 'LaTeX equation on its own line', icon: icon('Sigma'), keywords: 'math latex formula katex', requires: 'blockMath', run: () => mathDialog(this, { block: true }) },
            { group: t('advanced'), label: t('inlineMath'), description: 'LaTeX inside the text', icon: icon('Pi'), keywords: 'math latex formula', requires: 'inlineMath', run: () => mathDialog(this) },
            { group: t('advanced'), label: t('emoji'), description: 'Pick an emoji', icon: icon('Smile'), keywords: 'emoticon smiley', run: () => {
                const { from } = this.editor.state.selection;
                const coords = this.editor.view.coordsAtPos(from);
                const anchor = { getBoundingClientRect: () => new DOMRect(coords.left, coords.top, 1, coords.bottom - coords.top) };
                import('./ui/dialogs.js').then(({ emojiPopover }) => emojiPopover(this, anchor));
            } },
        ];

        return items.filter((item) => !item.requires || has(item.requires));
    }

    /* ---------------------------------------------------------- data flow */

    serialize() {
        if (this.config.format === 'json') return JSON.stringify(this.editor.getJSON());
        if (this.config.format === 'markdown') return this.editor.getMarkdown();

        return this.isEmpty() ? '' : this.editor.getHTML();
    }

    isEmpty() {
        return this.editor.isEmpty;
    }

    onChange() {
        if (!this.settled) return;
        this.syncSoon();
        this.scheduleUi();
        this.saveDraft?.();
        this.saveServerDraft?.();
    }

    syncSoon = debounce(() => this.sync(), 150);

    sync() {
        const value = this.serialize();
        if (value === this.lastValue) return;
        this.lastValue = value;
        if (this.input) {
            this.input.value = value;
            this.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        this.emit('change', { value, html: this.editor.getHTML(), json: this.editor.getJSON(), text: this.editor.getText(), ...this.stats() });
    }

    handleMarkdownPaste(event) {
        if (!this.config.features.markdownPaste) return false;
        const data = event.clipboardData;
        if (!data || data.files?.length) return false;
        const text = data.getData('text/plain');
        const html = data.getData('text/html');
        if (!text || (html && !/<meta[^>]+vscode|data-pm-slice/i.test(html) && html.length > text.length * 0.5 && !/^\s*<pre/i.test(html))) {
            if (html) return false;
        }
        if (this.editor.isActive('codeBlock') || !looksLikeMarkdown(text)) return false;
        try {
            const json = this.editor.storage.markdown.manager.parse(text);
            this.editor.chain().focus().insertContent(json.content || json).run();
            return true;
        } catch {
            return false;
        }
    }

    /* ---------------------------------------------------------- ui updates */

    scheduleUi() {
        if (this.uiFrame) return;
        this.uiFrame = requestAnimationFrame(() => {
            this.uiFrame = null;
            this.updateUi();
        });
    }

    updateUi() {
        if (!this.editor) return; // theme/UI refreshes can fire before an async (collaborative) boot finishes
        this.toolbar?.update();
        this.updateStatusbar();
        if (this.sourceOpen) return;
    }

    stats() {
        if (!this.editor) return { words: 0, characters: 0, readingTime: 0, empty: true };
        const text = this.editor.getText({ blockSeparator: ' ' });
        const words = this.editor.storage.characterCount.words();
        const characters = this.editor.storage.characterCount.characters();

        return { words, characters, readingTime: Math.max(1, Math.ceil(words / 230)), empty: !text.trim() };
    }

    updateStatusbar() {
        if (!this.config.features.statusbar) return;
        const { words, characters, readingTime } = this.stats();
        const { t } = this;
        const limit = this.config.limit.characters;
        const wordLimit = this.config.limit.words;

        if (!this.statusParts) {
            this.statusParts = {
                path: h('span', { class: 'de-status-path' }),
                message: h('span', { class: 'de-status-message', 'aria-live': 'polite' }),
                words: h('span', { class: 'de-status-item' }),
                characters: h('span', { class: 'de-status-item' }),
                reading: h('span', { class: 'de-status-item' }),
                ring: h('span', { class: 'de-status-ring', hidden: !limit }),
            };
            const p = this.statusParts;
            this.statusbar.append(p.path, p.message, h('span', { class: 'de-status-spacer' }), p.words, p.characters, p.reading, p.ring);
        }
        const p = this.statusParts;
        p.words.textContent = t(words === 1 ? 'word' : 'words', { count: words.toLocaleString() }) + (wordLimit ? ` / ${wordLimit.toLocaleString()}` : '');
        p.words.classList.toggle('is-over', Boolean(wordLimit && words > wordLimit));
        p.characters.textContent = t('characters', { count: characters.toLocaleString() }) + (limit ? ` / ${limit.toLocaleString()}` : '');
        p.reading.textContent = t('readingTime', { count: readingTime });
        if (limit) {
            const pct = Math.min(100, Math.round((characters / limit) * 100));
            p.ring.style.setProperty('--pct', pct);
            p.ring.classList.toggle('is-warn', pct >= 90);
            p.ring.title = pct >= 100 ? t('limitReached') : `${pct}%`;
        }
        p.path.textContent = this.blockPath();
    }

    blockPath() {
        const { $from } = this.editor.state.selection;
        const names = [];
        for (let d = 1; d <= $from.depth; d++) {
            const node = $from.node(d);
            let name = node.type.name;
            if (name === 'heading') name = `h${node.attrs.level}`;
            if (['listItem', 'taskItem', 'tableRow', 'detailsContent'].includes(name)) continue;
            names.push(name.replace(/([A-Z])/g, ' $1').toLowerCase());
        }
        if (this.editor.state.selection instanceof NodeSelection) names.push(this.editor.state.selection.node.type.name);

        return names.join(' › ');
    }

    setStatus(message) {
        if (this.statusParts) this.statusParts.message.textContent = message;
    }

    setBusy(busy) {
        this.root.classList.toggle('is-busy', busy);
        this.root.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    /* ---------------------------------------------------------- panels */

    toggleFind(force) {
        const open = force ?? !this.findOpen;
        if (open && !this.findInputs) this.buildFind();
        this.findOpen = open;
        this.findPanel.hidden = !open;
        if (open) {
            const { from, to } = this.editor.state.selection;
            const selected = this.editor.state.doc.textBetween(from, to, ' ');
            if (selected && selected.length < 100) this.findInputs.term.value = selected;
            this.findInputs.term.focus();
            this.findInputs.term.select();
            this.runSearch();
        } else {
            this.editor.commands.clearSearch();
            this.editor.commands.focus();
        }
        this.scheduleUi();
    }

    buildFind() {
        const { t } = this;
        const term = h('input', { type: 'search', class: 'de-input de-input-sm', placeholder: t('find'), 'aria-label': t('find') });
        const replace = h('input', { type: 'text', class: 'de-input de-input-sm', placeholder: t('replace'), 'aria-label': t('replace') });
        const count = h('span', { class: 'de-find-count' });
        const optionBtn = (key, iconName, label) => {
            const btn = iconButton(iconName, label, () => {
                btn.classList.toggle('is-active');
                this.findOptions[key] = btn.classList.contains('is-active');
                this.runSearch();
            });
            return btn;
        };
        this.findOptions = { caseSensitive: false, wholeWord: false, regex: false };
        this.findInputs = { term, replace, count };
        const go = (dir) => this.editor.commands.goToResult(dir);

        term.addEventListener('input', () => this.runSearch());
        replace.addEventListener('input', () => this.editor.storage.findReplace && (this.editor.storage.findReplace.replace = replace.value));
        const keys = (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (event.target === replace) this.editor.commands.replaceCurrent();
                else go(event.shiftKey ? -1 : 1);
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                this.toggleFind(false);
            }
        };
        term.addEventListener('keydown', keys);
        replace.addEventListener('keydown', keys);

        this.findPanel.append(
            h('div', { class: 'de-find-row' }, [
                h('span', { class: 'de-find-icon', html: icon('Search', 16) }),
                term,
                count,
                optionBtn('caseSensitive', 'CaseSensitive', t('matchCase')),
                optionBtn('wholeWord', 'WholeWord', t('wholeWord')),
                optionBtn('regex', 'Regex', t('useRegex')),
                h('span', { class: 'de-bubble-sep' }),
                iconButton('ArrowUp', 'Previous', () => go(-1)),
                iconButton('ArrowDown', 'Next', () => go(1)),
            ]),
            h('div', { class: 'de-find-row' }, [
                h('span', { class: 'de-find-icon', html: icon('Replace', 16) }),
                replace,
                h('button', { type: 'button', class: 'de-button de-button-sm', text: t('replace'), onClick: () => this.editor.commands.replaceCurrent() }),
                h('button', { type: 'button', class: 'de-button de-button-sm', text: t('replaceAll'), onClick: () => this.editor.commands.replaceAll() }),
                h('span', { class: 'de-status-spacer' }),
                iconButton('X', t('close'), () => this.toggleFind(false)),
            ]),
        );
    }

    runSearch() {
        this.editor.commands.setSearch({ term: this.findInputs.term.value, replace: this.findInputs.replace.value, ...this.findOptions });
    }

    updateFindCount({ results, index }) {
        if (!this.findInputs) return;
        const { count, term } = this.findInputs;
        count.textContent = !term.value ? '' : results ? this.t('resultCount', { current: index + 1, total: results }) : this.t('noResults');
        count.classList.toggle('is-empty', Boolean(term.value) && !results);
    }

    toggleSource(force) {
        const open = force ?? !this.sourceOpen;
        if (open === this.sourceOpen) return;
        const { t } = this;

        if (open) {
            this.sourceMode = this.sourceMode || 'html';
            if (!this.sourceBar.childElementCount) {
                const tab = (mode, label) => h('button', {
                    type: 'button',
                    class: 'de-tab',
                    dataset: { mode },
                    text: label,
                    onClick: () => {
                        this.applySource();
                        this.sourceMode = mode;
                        this.fillSource();
                    },
                });
                this.sourceBar.append(h('div', { class: 'de-tabs' }, [tab('html', t('sourceHtml')), tab('markdown', t('sourceMarkdown'))]));
            }
            this.fillSource();
        } else {
            this.applySource();
        }

        this.sourceOpen = open;
        this.root.classList.toggle('is-source', open);
        this.canvas.hidden = open;
        this.source.hidden = !open;
        this.sourceBar.hidden = !open;
        if (open) this.source.focus();
        else this.editor.commands.focus();
        this.scheduleUi();
    }

    fillSource() {
        this.source.value = this.sourceMode === 'markdown' ? this.editor.getMarkdown() : formatHtml(this.editor.getHTML());
        this.sourceSnapshot = this.source.value;
        this.sourceBar.querySelectorAll('.de-tab').forEach((b) => b.classList.toggle('is-active', b.dataset.mode === this.sourceMode));
    }

    applySource() {
        if (this.source.value === this.sourceSnapshot) return;
        this.editor.commands.setContent(this.source.value, { emitUpdate: true, contentType: this.sourceMode === 'markdown' ? 'markdown' : 'html' });
    }

    renderToc(anchors) {
        this.tocAnchors = anchors;
        if (!this.tocOpen) return;
        const { t } = this;
        this.tocPanel.replaceChildren(h('div', { class: 'de-toc-title', text: t('toc') }));
        if (!anchors.length) {
            this.tocPanel.append(h('p', { class: 'de-toc-empty', text: t('tocEmpty') }));
            return;
        }
        const list = h('nav', { class: 'de-toc-list' });
        for (const anchor of anchors) {
            list.append(h('a', {
                href: `#${anchor.id}`,
                class: `de-toc-item ${anchor.isActive ? 'is-active' : ''} ${anchor.isScrolledOver ? 'is-read' : ''}`,
                style: { '--level': anchor.level - 1 },
                text: anchor.textContent || '—',
                onClick: (event) => {
                    event.preventDefault();
                    const pos = anchor.pos;
                    this.editor.chain().focus().setTextSelection(pos + 1).scrollIntoView().run();
                    anchor.dom?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },
            }));
        }
        this.tocPanel.append(list);
    }

    toggleToc(force) {
        this.tocOpen = force ?? !this.tocOpen;
        this.tocPanel.hidden = !this.tocOpen;
        this.root.classList.toggle('has-toc', this.tocOpen);
        if (this.tocOpen) this.renderToc(this.tocAnchors || this.editor.storage.tableOfContents?.content || []);
        this.scheduleUi();
    }

    toggleFocusMode(force) {
        this.focusMode = force ?? !this.focusMode;
        this.root.classList.toggle('is-focus-mode', this.focusMode);
        this.editor.commands.focus();
        this.scheduleUi();
    }

    toggleComments(force) {
        if (!this.comments) return;
        this.commentsOpen = force ?? !this.commentsOpen;
        this.comments.el.hidden = !this.commentsOpen;
        this.root.classList.toggle('has-comments', this.commentsOpen);
        if (this.commentsOpen && this.tocOpen) this.toggleToc(false);
        this.scheduleUi();
    }

    get suggesting() {
        return Boolean(this.editor?.storage.suggestions?.enabled);
    }

    toggleSuggestionMode() {
        this.editor.commands.toggleSuggestionMode();
    }

    renderPeers(peers) {
        const unique = [...new Map(peers.map((p) => [p.id || p.name, p])).values()];
        this.presence.hidden = !unique.length;
        this.presence.replaceChildren(
            ...unique.slice(0, 5).map((peer) => h('span', {
                class: 'de-peer',
                style: { '--peer': peer.color || '#6366f1' },
                title: peer.name,
                text: String(peer.name || '?').split(/\s+/).map((w) => w[0]).join('').slice(0, 2).toUpperCase(),
            })),
            unique.length > 5 ? h('span', { class: 'de-peer de-peer-more', text: `+${unique.length - 5}` }) : null,
        );
        this.emit('peers', { peers: unique });
    }

    togglePreview(force) {
        this.previewMode = force ?? !this.previewMode;
        this.editor.setEditable(!this.previewMode && !this.config.readonly);
        this.root.classList.toggle('is-preview', this.previewMode);
        this.scheduleUi();
    }

    toggleFullscreen(force) {
        this.fullscreen = force ?? !this.fullscreen;
        this.root.classList.toggle('is-fullscreen', this.fullscreen);
        document.documentElement.classList.toggle('desk-editor-fullscreen-lock', this.fullscreen);
        this.editor.commands.focus();
        this.scheduleUi();
        this.emit('fullscreen', { active: this.fullscreen });
    }

    /* ---------------------------------------------------------- theme */

    applyTheme(theme) {
        this.theme = theme;
        const media = window.matchMedia?.('(prefers-color-scheme: dark)');
        const resolve = () => {
            const html = document.documentElement;
            const pageDark = html.classList.contains('dark') || html.dataset.theme === 'dark' || html.dataset.bsTheme === 'dark';
            const pageLight = html.classList.contains('light') || html.dataset.theme === 'light' || html.dataset.bsTheme === 'light';
            const resolved = this.theme === 'auto' ? (pageDark ? 'dark' : pageLight ? 'light' : media?.matches ? 'dark' : 'light') : this.theme;
            this.resolvedTheme = resolved;
            this.root.dataset.theme = resolved;
            this.layer.dataset.theme = resolved;
            this.scheduleUi?.();
        };
        if (!this.themeWatch) {
            this.themeWatch = new MutationObserver(resolve);
            this.themeWatch.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme', 'data-bs-theme'] });
            media?.addEventListener?.('change', resolve);
        }
        resolve();
    }

    toggleTheme() {
        const next = this.resolvedTheme === 'dark' ? 'light' : 'dark';
        safeStorage('set', 'desk-editor-theme', next);
        this.applyTheme(next);
        this.emit('theme', { theme: next });
    }

    /* ---------------------------------------------------------- export */

    export(type) {
        const name = (this.config.exportName || document.title || 'document').replace(/[^\w-]+/g, '-').toLowerCase().replace(/^-+|-+$/g, '') || 'document';
        if (type === 'html') download(`${name}.html`, this.standaloneHtml(), 'text/html');
        if (type === 'markdown') download(`${name}.md`, this.editor.getMarkdown(), 'text/markdown');
        if (type === 'text') download(`${name}.txt`, this.editor.getText({ blockSeparator: '\n\n' }), 'text/plain');
        if (type === 'clipboard') {
            import('./ui/dialogs.js').then(({ copyToClipboard }) => copyToClipboard(this, this.editor.getHTML()));
        }
    }

    standaloneHtml() {
        const css = [...document.styleSheets].map((sheet) => {
            try {
                return [...sheet.cssRules].map((r) => r.cssText).filter((r) => /desk-content|de-|katex|hljs|:root/.test(r)).join('\n');
            } catch {
                return '';
            }
        }).join('\n');

        return `<!doctype html>\n<html lang="en" data-theme="${this.resolvedTheme}">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>${escapeHtml(document.title || 'Document')}</title>\n<style>${css}\nbody{margin:0;} .desk-content{max-width:760px;margin:3rem auto;padding:0 1.5rem;}</style>\n</head>\n<body class="de-export" data-theme="${this.resolvedTheme}">\n<article class="desk-content de-prose">\n${this.editor.view.dom.innerHTML.replace(/ contenteditable="(true|false)"/g, '')}\n</article>\n</body>\n</html>\n`;
    }

    print() {
        const frame = h('iframe', { style: { position: 'fixed', right: 0, bottom: 0, width: 0, height: 0, border: 0 }, 'aria-hidden': 'true' });
        document.body.append(frame);
        const doc = frame.contentDocument;
        doc.open();
        doc.write(this.standaloneHtml().replace(/data-theme="dark"/g, 'data-theme="light"'));
        doc.close();
        frame.onload = () => {
            frame.contentWindow.focus();
            frame.contentWindow.print();
            setTimeout(() => frame.remove(), 1000);
        };
    }

    duplicateSelection() {
        const { selection } = this.editor.state;
        if (!(selection instanceof NodeSelection)) return;
        this.editor.chain().focus().insertContentAt(selection.to, selection.node.toJSON()).run();
    }

    /* ---------------------------------------------------------- autosave */

    draftKey() {
        const { autosave } = this.config;
        if (!autosave?.enabled) return null;

        return `desk-editor:draft:${autosave.key || `${location.pathname}:${this.input?.name || 'content'}`}`;
    }

    serverDraftUrl() {
        const { autosave, endpoints } = this.config;
        if (!autosave?.server || !autosave.key || !endpoints?.drafts) return null;
        return `${endpoints.drafts}/${encodeURIComponent(autosave.key)}`;
    }

    async draftRequest(method, body) {
        const url = this.serverDraftUrl();
        if (!url) return null;
        const token = this.config.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(url, {
            method,
            keepalive: method === 'DELETE',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.status === 204 ? null : response.json();
    }

    async restoreServerDraft() {
        const { t } = this;
        let data = null;
        try {
            data = await this.draftRequest('GET');
        } catch {
            return false;
        }
        const draft = data?.draft;
        this.saveServerDraft = debounce(async () => {
            this.setStatus(t('saving'));
            try {
                await this.draftRequest('PUT', { content: this.editor.getHTML() });
                this.setStatus(t('savedToCloud'));
                this.root.classList.remove('is-offline');
            } catch {
                this.setStatus(t('offlineDraft'));
                this.root.classList.add('is-offline');
            }
            setTimeout(() => this.setStatus(''), 1800);
        }, this.config.drafts?.interval || 4000);

        if (!draft?.content || draft.content === this.editor.getHTML()) return true;
        this.showDraftBanner(draft.content, draft.updated_at, t('restoreServerDraft', { time: new Date(draft.updated_at).toLocaleString() }), () => this.draftRequest('DELETE').catch(() => {}));
        return true;
    }

    showDraftBanner(html, at, message, onDiscard) {
        const { t } = this;
        this.banner.replaceChildren(
            h('span', { class: 'de-banner-icon', html: icon('Clock', 16) }),
            h('span', { class: 'de-banner-text', text: message }),
            h('button', { type: 'button', class: 'de-button de-button-sm de-button-primary', text: t('restore'), onClick: () => {
                this.editor.commands.setContent(html, { emitUpdate: true });
                this.banner.hidden = true;
            } }),
            h('button', { type: 'button', class: 'de-button de-button-sm', text: t('discard'), onClick: () => {
                onDiscard();
                this.banner.hidden = true;
            } }),
        );
        this.banner.hidden = false;
    }

    async restoreDraft() {
        if (this.serverDraftUrl() && (await this.restoreServerDraft())) return;
        const key = this.draftKey();
        if (!key) return;
        this.saveDraft = debounce(() => {
            safeStorage('set', key, JSON.stringify({ html: this.editor.getHTML(), at: Date.now() }));
            this.setStatus(this.t('saved'));
            setTimeout(() => this.setStatus(''), 1500);
        }, this.config.autosave.debounce || 800);

        let draft = null;
        try {
            draft = JSON.parse(safeStorage('get', key) || 'null');
        } catch {
            draft = null;
        }
        if (!draft?.html || draft.html === this.editor.getHTML()) return;

        const { t } = this;
        this.banner.replaceChildren(
            h('span', { class: 'de-banner-icon', html: icon('Clock', 16) }),
            h('span', { class: 'de-banner-text', text: t('restoreDraft', { time: new Date(draft.at).toLocaleString() }) }),
            h('button', { type: 'button', class: 'de-button de-button-sm de-button-primary', text: t('restore'), onClick: () => {
                this.editor.commands.setContent(draft.html, { emitUpdate: true });
                this.banner.hidden = true;
            } }),
            h('button', { type: 'button', class: 'de-button de-button-sm', text: t('discard'), onClick: () => {
                safeStorage('remove', key);
                this.banner.hidden = true;
            } }),
        );
        this.banner.hidden = false;
    }

    clearDraft() {
        const key = this.draftKey();
        if (key) safeStorage('remove', key);
        this.saveServerDraft?.cancel?.();
        if (this.serverDraftUrl()) this.draftRequest('DELETE').catch(() => {});
    }

    bindForm() {
        const form = this.root.closest('form');
        if (!form) return;
        this.onSubmit = () => {
            if (this.sourceOpen) this.applySource();
            this.syncSoon.cancel();
            this.sync();
            this.clearDraft();
        };
        form.addEventListener('submit', this.onSubmit, true);
        form.addEventListener('formdata', this.onSubmit);
    }

    /* ---------------------------------------------------------- public API */

    getHTML() {
        if (!this.editor) return '';
        return this.editor.getHTML();
    }

    getJSON() {
        if (!this.editor) return null;
        return this.editor.getJSON();
    }

    getText() {
        if (!this.editor) return '';
        return this.editor.getText();
    }

    getMarkdown() {
        if (!this.editor) return '';
        return this.editor.getMarkdown();
    }

    getValue() {
        if (!this.editor) return this.input?.value ?? '';
        return this.serialize();
    }

    setContent(content, { format = null, emit = true } = {}) {
        const type = format || this.config.format;
        this.editor.commands.setContent(content, { emitUpdate: emit, contentType: type === 'markdown' ? 'markdown' : type === 'json' ? 'json' : 'html' });
        this.sync();
    }

    focus(position = 'end') {
        this.editor.commands.focus(position);
    }

    setReadonly(readonly) {
        this.config.readonly = readonly;
        this.editor.setEditable(!readonly);
        this.root.classList.toggle('is-readonly', readonly);
        this.scheduleUi();
    }

    on(event, callback) {
        (this.listeners[event] ||= []).push(callback);
        return () => (this.listeners[event] = this.listeners[event].filter((cb) => cb !== callback));
    }

    emit(event, detail) {
        (this.listeners[event] || []).forEach((cb) => cb(detail));
        this.root.dispatchEvent(new CustomEvent(`desk-editor:${event}`, { bubbles: true, detail }));
    }

    closePopover() {
        this.activePopover?.close();
    }

    destroy() {
        this.closePopover();
        this.themeWatch?.disconnect();
        const form = this.root.closest('form');
        form?.removeEventListener('submit', this.onSubmit, true);
        form?.removeEventListener('formdata', this.onSubmit);
        this.editor.destroy();
        this.layer.remove();
        this.shell.remove();
        document.documentElement.classList.remove('desk-editor-fullscreen-lock');
        instances.delete(this.root);
    }
}

/* ------------------------------------------------------------------ helpers */

function mergeConfig(base, extra) {
    const out = { ...base };
    for (const [key, value] of Object.entries(extra || {})) {
        if (value === null || value === undefined) continue;
        out[key] = value && typeof value === 'object' && !Array.isArray(value) && base[key] && typeof base[key] === 'object' && !Array.isArray(base[key])
            ? { ...base[key], ...value }
            : value;
    }

    return out;
}

function filterItems(items, query) {
    const q = query.toLowerCase().trim();
    if (!q) return items;

    return items.filter((item) => `${item.label} ${item.keywords || ''} ${item.group || ''}`.toLowerCase().includes(q));
}

function looksLikeMarkdown(text) {
    if (text.length < 3) return false;
    const signals = [
        /^#{1,6}\s+\S/m,
        /^\s*[-*+]\s+\S/m,
        /^\s*\d+\.\s+\S/m,
        /^\s*>\s+\S/m,
        /^```/m,
        /\*\*[^*\n]+\*\*/,
        /\[[^\]\n]+\]\([^)\s]+\)/,
        /^\s*\|.+\|\s*$/m,
        /^\s*- \[[ xX]\]\s/m,
        /^---\s*$/m,
    ];

    return signals.filter((re) => re.test(text)).length >= (text.includes('\n') ? 1 : 2);
}

function formatHtml(html) {
    const blocks = 'p|h[1-6]|ul|ol|li|blockquote|pre|table|thead|tbody|tr|figure|div|hr|details|summary';
    return html
        .replace(new RegExp(`(<(?:${blocks})[\\s>])`, 'g'), '\n$1')
        .replace(new RegExp(`(</(?:${blocks})>)`, 'g'), '$1\n')
        .replace(/\n{2,}/g, '\n')
        .trim();
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

let emojiPromise = null;
export function loadEmojis() {
    emojiPromise ||= import('./data/emojis.js').then((m) => m.emojis);
    return emojiPromise;
}

function safeStorage(action, key, value) {
    try {
        if (action === 'get') return window.localStorage.getItem(key);
        if (action === 'set') return window.localStorage.setItem(key, value);
        if (action === 'remove') return window.localStorage.removeItem(key);
    } catch {
        return null;
    }
    return null;
}

export { TextSelection, shortcut, openDialog, tablePopover };
