import katex from 'katex';
import { checkbox, field, openDialog, toast } from './dialog.js';
import { h, safeUrl, shortcut } from './dom.js';
import { icon } from './icons.js';
import { Popover } from './popover.js';
import { resolveEmbed } from '../extensions/embed.js';
import { insertFiles, pickFile } from '../upload.js';

export async function linkDialog(desk) {
    const { editor, t } = desk;
    editor.chain().focus().extendMarkRange('link').run();
    const attrs = editor.getAttributes('link');
    const { from, to, empty } = editor.state.selection;
    const selectedText = editor.state.doc.textBetween(from, to, ' ');

    const url = h('input', { type: 'text', class: 'de-input', value: attrs.href || '', placeholder: 'https://', autofocus: true, inputmode: 'url' });
    const text = h('input', { type: 'text', class: 'de-input', value: selectedText });
    const newTab = checkbox(t('openInNewTab'), attrs.target ? attrs.target === '_blank' : desk.config.link?.newTab !== false);

    const actions = [
        { label: t('cancel'), onClick: (close) => close(null) },
        {
            label: t('apply'),
            primary: true,
            submit: true,
            onClick: (close) => close({ href: safeUrl(url.value), text: text.value, target: newTab.input.checked }),
        },
    ];
    if (attrs.href) actions.unshift({ label: t('removeLink'), danger: true, onClick: (close) => close({ remove: true }) });

    const result = await openDialog(desk, {
        title: attrs.href ? t('editLink') : t('link'),
        body: h('div', { class: 'de-stack' }, [field(t('linkUrl'), url), field(t('linkText'), text), newTab]),
        actions,
    });

    if (!result) return editor.commands.focus();
    if (result.remove || !result.href) return editor.chain().focus().extendMarkRange('link').unsetLink().run();

    const linkAttrs = { href: result.href, target: result.target ? '_blank' : null, rel: result.target ? 'noopener noreferrer nofollow' : null };
    const label = result.text || result.href;

    if (empty && !attrs.href) {
        editor.chain().focus().insertContent({ type: 'text', text: label, marks: [{ type: 'link', attrs: linkAttrs }] }).insertContent(' ').run();
    } else if (result.text && result.text !== selectedText) {
        editor.chain().focus().extendMarkRange('link').insertContent({ type: 'text', text: label, marks: [{ type: 'link', attrs: linkAttrs }] }).run();
    } else {
        editor.chain().focus().extendMarkRange('link').setLink(linkAttrs).run();
    }
}

export async function imageDialog(desk, existing = null) {
    const { editor, t, config } = desk;
    const canUpload = Boolean(config.upload?.url);
    const url = h('input', { type: 'text', class: 'de-input', placeholder: 'https://…', value: existing?.src || '' });
    const alt = h('input', { type: 'text', class: 'de-input', value: existing?.alt || '' });
    const caption = h('input', { type: 'text', class: 'de-input', value: existing?.caption || '' });
    let mode = canUpload && !existing ? 'upload' : 'url';
    let chosen = null;

    const drop = h('button', { type: 'button', class: 'de-dropzone', html: `${icon('Upload', 28)}<span>${t('uploadOrDrop')}</span>` });
    const setFile = (file) => {
        chosen = file;
        drop.querySelector('span').textContent = file.name;
        drop.classList.add('has-file');
    };
    drop.addEventListener('click', async () => {
        const [file] = await pickFile((config.upload?.images || ['image/*']).join(','), false);
        if (file) setFile(file);
    });
    drop.addEventListener('dragover', (e) => {
        e.preventDefault();
        drop.classList.add('is-over');
    });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        drop.classList.remove('is-over');
        const file = [...e.dataTransfer.files].find((f) => f.type.startsWith('image/'));
        if (file) setFile(file);
    });

    const uploadPane = h('div', { class: 'de-pane' }, [drop]);
    const urlPane = h('div', { class: 'de-pane' }, [field(t('imageUrl'), url)]);
    const tabs = h('div', { class: 'de-tabs', role: 'tablist' });
    const setMode = (next) => {
        mode = next;
        uploadPane.hidden = next !== 'upload';
        urlPane.hidden = next !== 'url';
        tabs.querySelectorAll('button').forEach((b) => b.classList.toggle('is-active', b.dataset.mode === next));
        if (next === 'url') url.focus();
    };
    if (canUpload) {
        tabs.append(
            h('button', { type: 'button', class: 'de-tab', dataset: { mode: 'upload' }, text: t('upload'), onClick: () => setMode('upload') }),
            h('button', { type: 'button', class: 'de-tab', dataset: { mode: 'url' }, text: t('fromUrl'), onClick: () => setMode('url') }),
        );
    }

    const body = h('div', { class: 'de-stack' }, [
        canUpload ? tabs : null,
        uploadPane,
        urlPane,
        field(t('altText'), alt, t('altTextHint')),
        field(t('caption'), caption),
    ]);
    requestAnimationFrame(() => setMode(mode));

    const result = await openDialog(desk, {
        title: existing ? t('replaceImage') : t('image'),
        body,
        actions: [
            { label: t('cancel'), onClick: (close) => close(null) },
            { label: existing ? t('save') : t('insertButton'), primary: true, submit: true, onClick: (close) => close(true) },
        ],
    });
    if (!result) return editor.commands.focus();

    const meta = { alt: alt.value || null, caption: caption.value || null };
    if (mode === 'upload' && chosen) {
        if (existing) editor.chain().focus().deleteSelection().run();
        return insertFiles(desk, [chosen], null, meta);
    }
    const src = safeUrl(url.value);
    if (!src) return;
    if (existing) editor.chain().focus().updateAttributes('image', { src, ...meta }).run();
    else editor.chain().focus().setImage({ src, ...meta }).run();
}

export async function mediaDialog(desk, kind = 'video') {
    const { editor, t, config } = desk;
    const canUpload = Boolean(config.upload?.url);
    const accept = (kind === 'video' ? config.upload?.videos : config.upload?.audio) || [`${kind}/*`];
    const url = h('input', { type: 'text', class: 'de-input', placeholder: kind === 'video' ? 'https://… (.mp4, YouTube, Vimeo, Loom)' : 'https://… (.mp3, .m4a, .ogg)' });
    const caption = h('input', { type: 'text', class: 'de-input' });
    const error = h('p', { class: 'de-field-error', hidden: true });
    let mode = canUpload ? 'upload' : 'url';
    let chosen = null;

    const drop = h('button', { type: 'button', class: 'de-dropzone', html: `${icon(kind === 'video' ? 'Video' : 'Music', 28)}<span>${t('uploadOrDrop')}</span><small>${accept.map((m) => m.split('/')[1]).join(', ').toUpperCase()}</small>` });
    const setFile = (file) => {
        chosen = file;
        drop.querySelector('span').textContent = `${file.name} · ${(file.size / 1048576).toFixed(1)} MB`;
        drop.classList.add('has-file');
    };
    drop.addEventListener('click', async () => {
        const [file] = await pickFile(accept.join(','), false);
        if (file) setFile(file);
    });
    drop.addEventListener('dragover', (e) => {
        e.preventDefault();
        drop.classList.add('is-over');
    });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', (e) => {
        e.preventDefault();
        drop.classList.remove('is-over');
        const file = [...e.dataTransfer.files].find((f) => f.type.startsWith(`${kind}/`));
        if (file) setFile(file);
    });

    const uploadPane = h('div', { class: 'de-pane' }, [drop]);
    const urlPane = h('div', { class: 'de-pane' }, [field(t(kind === 'video' ? 'videoUrl' : 'audioUrl'), url), error]);
    const tabs = h('div', { class: 'de-tabs', role: 'tablist' });
    const setMode = (next) => {
        mode = next;
        uploadPane.hidden = next !== 'upload';
        urlPane.hidden = next !== 'url';
        tabs.querySelectorAll('button').forEach((b) => b.classList.toggle('is-active', b.dataset.mode === next));
    };
    if (canUpload) {
        tabs.append(
            h('button', { type: 'button', class: 'de-tab', dataset: { mode: 'upload' }, text: t('upload'), onClick: () => setMode('upload') }),
            h('button', { type: 'button', class: 'de-tab', dataset: { mode: 'url' }, text: t('fromUrl'), onClick: () => setMode('url') }),
        );
    }
    requestAnimationFrame(() => setMode(mode));

    const result = await openDialog(desk, {
        title: t(kind),
        body: h('div', { class: 'de-stack' }, [canUpload ? tabs : null, uploadPane, urlPane, field(t('caption'), caption)]),
        actions: [
            { label: t('cancel'), onClick: (close) => close(null) },
            {
                label: t('insertButton'),
                primary: true,
                submit: true,
                onClick: (close) => {
                    if (mode === 'upload') return chosen ? close('upload') : null;
                    const value = safeUrl(url.value);
                    if (!value) return;
                    if (kind === 'video' && !resolveEmbed(value) && !/\.(mp4|webm|ogv|ogg|mov|m4v)(\?|#|$)/i.test(value)) {
                        error.textContent = t('embedInvalid');
                        error.hidden = false;
                        return;
                    }
                    close('url');
                },
            },
        ],
    });
    if (!result) return editor.commands.focus();

    const extra = caption.value ? { caption: caption.value } : {};
    if (result === 'upload') return insertFiles(desk, [chosen], null, extra);

    const value = safeUrl(url.value);
    if (kind === 'video' && resolveEmbed(value)) return editor.chain().focus().setEmbed(value).run();
    editor.chain().focus().insertContent({ type: kind, attrs: { src: value, ...extra } }).run();
}

export async function embedDialog(desk) {
    const { editor, t } = desk;
    const url = h('input', { type: 'text', class: 'de-input', placeholder: 'https://www.youtube.com/watch?v=…', autofocus: true });
    const error = h('p', { class: 'de-field-error', hidden: true, text: t('embedInvalid') });
    const preview = h('div', { class: 'de-embed-preview' });
    url.addEventListener('input', () => {
        const resolved = resolveEmbed(url.value);
        error.hidden = !url.value || Boolean(resolved);
        preview.replaceChildren(resolved ? h('iframe', { src: resolved.src, loading: 'lazy', allowfullscreen: true }) : '');
    });

    const result = await openDialog(desk, {
        title: t('embed'),
        body: h('div', { class: 'de-stack' }, [field(t('embedUrl'), url), error, preview]),
        actions: [
            { label: t('cancel'), onClick: (close) => close(null) },
            {
                label: t('insertButton'),
                primary: true,
                submit: true,
                onClick: (close) => {
                    if (!resolveEmbed(url.value)) {
                        error.hidden = false;
                        return;
                    }
                    close(url.value);
                },
            },
        ],
    });
    if (result) editor.chain().focus().setEmbed(result).run();
    else editor.commands.focus();
}

export async function mathDialog(desk, { block = false, latex = '', pos = null } = {}) {
    const { editor, t } = desk;
    const input = h('textarea', { class: 'de-input de-mono', rows: 4, spellcheck: 'false', autofocus: true, placeholder: 'e = mc^2' });
    input.value = latex;
    const preview = h('div', { class: 'de-math-preview' });
    const blockToggle = checkbox(t('blockMath'), block);
    if (pos !== null) blockToggle.hidden = true;
    const render = () => {
        try {
            katex.render(input.value || '\\square', preview, { displayMode: blockToggle.input.checked, throwOnError: true });
            preview.classList.remove('has-error');
        } catch (error) {
            preview.textContent = error.message.replace(/^KaTeX parse error: /, '');
            preview.classList.add('has-error');
        }
    };
    input.addEventListener('input', render);
    blockToggle.input.addEventListener('change', render);
    render();

    const symbols = ['\\frac{a}{b}', '\\sqrt{x}', 'x^{2}', 'x_{i}', '\\sum_{i=1}^{n}', '\\int_{a}^{b}', '\\lim_{x \\to \\infty}', '\\alpha', '\\beta', '\\pi', '\\theta', '\\infty', '\\pm', '\\neq', '\\leq', '\\geq', '\\cdot', '\\times', '\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}'];
    const palette = h('div', { class: 'de-math-palette' });
    for (const symbol of symbols) {
        const button = h('button', { type: 'button', class: 'de-math-symbol', title: symbol });
        try {
            katex.render(symbol, button, { throwOnError: false });
        } catch {
            button.textContent = symbol;
        }
        button.addEventListener('click', () => {
            const { selectionStart: s, selectionEnd: e } = input;
            input.setRangeText(symbol, s, e, 'end');
            input.focus();
            render();
        });
        palette.append(button);
    }

    const result = await openDialog(desk, {
        title: t('math'),
        wide: true,
        body: h('div', { class: 'de-stack' }, [palette, field(t('latex'), input), h('div', { class: 'de-field-label', text: t('latexPreview') }), preview, blockToggle]),
        actions: [
            { label: t('cancel'), onClick: (close) => close(null) },
            { label: pos !== null ? t('save') : t('insertButton'), primary: true, submit: true, onClick: (close) => close(input.value.trim()) },
        ],
    });
    if (result === null) return editor.commands.focus();

    const isBlock = blockToggle.input.checked;
    if (pos !== null) {
        if (!result) {
            const node = editor.state.doc.nodeAt(pos);
            if (node) editor.chain().focus().deleteRange({ from: pos, to: pos + node.nodeSize }).run();
            return;
        }
        if (block) editor.chain().focus().updateBlockMath({ latex: result, pos }).run();
        else editor.chain().focus().updateInlineMath({ latex: result, pos }).run();
        return;
    }
    if (!result) return;
    if (isBlock) editor.chain().focus().insertBlockMath({ latex: result }).run();
    else editor.chain().focus().insertInlineMath({ latex: result }).run();
}

export function shortcutsDialog(desk) {
    const { t } = desk;
    const rows = [
        [t('bold'), 'Mod-B'], [t('italic'), 'Mod-I'], [t('underline'), 'Mod-U'], [t('strike'), 'Mod-Shift-S'],
        [t('code'), 'Mod-E'], [t('link'), 'Mod-K'], [t('highlight'), 'Mod-Shift-H'], [t('clearFormatting'), 'Mod-\\'],
        [t('heading', { level: 1 }), 'Mod-Alt-1'], [t('heading', { level: 2 }), 'Mod-Alt-2'], [t('heading', { level: 3 }), 'Mod-Alt-3'], [t('paragraph'), 'Mod-Alt-0'],
        [t('bulletList'), 'Mod-Shift-8'], [t('orderedList'), 'Mod-Shift-7'], [t('taskList'), 'Mod-Shift-9'], [t('blockquote'), 'Mod-Shift-B'],
        [t('codeBlock'), 'Mod-Alt-C'], [t('alignLeft'), 'Mod-Shift-L'], [t('alignCenter'), 'Mod-Shift-E'], [t('alignRight'), 'Mod-Shift-R'],
        [t('alignJustify'), 'Mod-Shift-J'], [t('indent'), 'Tab'], [t('outdent'), 'Shift-Tab'], [t('findReplace'), 'Mod-F'],
        [t('fullscreen'), 'Mod-Shift-F'], [t('undo'), 'Mod-Z'], [t('redo'), 'Mod-Shift-Z'], [t('shortcuts'), 'Mod-/'],
    ];
    const markdown = [['# ', t('heading', { level: 1 })], ['## ', t('heading', { level: 2 })], ['- ', t('bulletList')], ['1. ', t('orderedList')], ['[ ] ', t('taskList')], ['> ', t('blockquote')], ['```', t('codeBlock')], ['---', t('horizontalRule')], ['**bold**', t('bold')], ['*italic*', t('italic')], ['`code`', t('code')], ['$x^2$', t('inlineMath')], [':::info', t('callout')], ['/', t('insert')]];

    const grid = h('div', { class: 'de-shortcuts' });
    for (const [label, keys] of rows) {
        grid.append(h('div', { class: 'de-shortcut' }, [h('span', { text: label }), h('kbd', { text: shortcut(keys) })]));
    }
    const md = h('div', { class: 'de-shortcuts' });
    for (const [syntax, label] of markdown) {
        md.append(h('div', { class: 'de-shortcut' }, [h('span', { text: label }), h('code', { text: syntax })]));
    }

    openDialog(desk, {
        title: t('shortcuts'),
        wide: true,
        body: h('div', { class: 'de-stack' }, [grid, h('h3', { class: 'de-subtitle', text: 'Markdown' }), md]),
    });
}

/* ------------------------------------------------------------------ popovers */

export function colorPopover(desk, anchor, { type }) {
    const { editor, t, config } = desk;
    const current = type === 'color' ? editor.getAttributes('textStyle').color : editor.getAttributes('highlight').color;
    const palette = type === 'color' ? config.colors : config.highlights;

    const apply = (value) => {
        const chain = editor.chain().focus();
        if (type === 'color') value ? chain.setColor(value) : chain.unsetColor();
        else value ? chain.setHighlight({ color: value }) : chain.unsetHighlight();
        chain.run();
        popover.close();
    };

    const swatches = h('div', { class: 'de-swatches' });
    for (const value of palette) {
        swatches.append(h('button', {
            type: 'button',
            class: `de-swatch ${current?.toLowerCase() === value.toLowerCase() ? 'is-active' : ''}`,
            style: { '--swatch': value },
            title: value,
            'aria-label': value,
            onClick: () => apply(value),
        }));
    }
    const custom = h('input', { type: 'color', class: 'de-color-input', value: /^#[0-9a-f]{6}$/i.test(current || '') ? current : '#4f46e5' });
    custom.addEventListener('change', () => apply(custom.value));

    const content = h('div', { class: 'de-color-panel' }, [
        h('button', { type: 'button', class: 'de-menu-item', html: `${icon('Ban', 16)}<span>${t('noColor')}</span>`, onClick: () => apply(null) }),
        swatches,
        h('label', { class: 'de-color-custom' }, [custom, h('span', { text: t('customColor') })]),
    ]);
    const popover = new Popover(desk, { anchor, content });
}

export function tablePopover(desk, anchor) {
    const { editor, t } = desk;
    const max = { rows: 10, cols: 10 };
    const label = h('div', { class: 'de-grid-label', text: t('insertTable') });
    const header = checkbox(t('withHeaderRow'), true);
    const grid = h('div', { class: 'de-grid', style: { gridTemplateColumns: `repeat(${max.cols}, 1fr)` } });

    for (let r = 1; r <= max.rows; r++) {
        for (let c = 1; c <= max.cols; c++) {
            grid.append(h('button', {
                type: 'button',
                class: 'de-grid-cell',
                dataset: { r, c },
                'aria-label': `${r} × ${c}`,
                onMouseenter: () => {
                    label.textContent = `${r} × ${c}`;
                    grid.querySelectorAll('.de-grid-cell').forEach((cell) => cell.classList.toggle('is-on', +cell.dataset.r <= r && +cell.dataset.c <= c));
                },
                onClick: () => {
                    editor.chain().focus().insertTable({ rows: r, cols: c, withHeaderRow: header.input.checked }).run();
                    popover.close();
                },
            }));
        }
    }
    const popover = new Popover(desk, { anchor, content: h('div', { class: 'de-grid-panel' }, [label, grid, header]), focus: false });
}

let emojiCache = [];
const emojiList = () => emojiCache;

export async function emojiPopover(desk, anchor) {
    emojiCache = await import('../data/emojis.js').then((m) => m.emojis);
    const { editor, t } = desk;
    const search = h('input', { type: 'search', class: 'de-input', placeholder: t('searchEmoji'), autofocus: true });
    const grid = h('div', { class: 'de-emoji-grid' });
    const tabs = h('div', { class: 'de-emoji-tabs' });
    const groups = [...new Set(emojiList().map((e) => e.group).filter(Boolean))];
    let group = groups[0];

    const render = () => {
        const q = search.value.trim().toLowerCase();
        const items = emojiList().filter((e) => (q ? e.name.includes(q) || e.shortcodes.some((s) => s.includes(q)) || e.tags?.some((s) => s.includes(q)) : e.group === group)).slice(0, 400);
        grid.replaceChildren(...items.map((e) => h('button', {
            type: 'button',
            class: 'de-emoji',
            title: `:${e.shortcodes[0]}:`,
            text: e.emoji,
            onClick: () => {
                editor.chain().focus().insertContent(e.emoji).run();
                popover.close();
            },
        })));
        tabs.querySelectorAll('button').forEach((b) => b.classList.toggle('is-active', !q && b.dataset.group === group));
    };

    for (const name of groups) {
        const sample = emojiList().find((e) => e.group === name);
        tabs.append(h('button', { type: 'button', class: 'de-emoji-tab', title: name, text: sample.emoji, dataset: { group: name }, onClick: () => {
            group = name;
            search.value = '';
            render();
        } }));
    }
    search.addEventListener('input', render);
    render();
    const popover = new Popover(desk, { anchor, content: h('div', { class: 'de-emoji-panel' }, [search, tabs, grid]) });
}

export async function copyToClipboard(desk, text) {
    try {
        await navigator.clipboard.writeText(text);
        toast(desk, desk.t('copied'), 'success');
    } catch {
        toast(desk, 'Clipboard unavailable', 'error');
    }
}
