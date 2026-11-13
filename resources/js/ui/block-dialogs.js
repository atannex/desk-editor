import { checkbox, field, openDialog, toast } from './dialog.js';
import { debounce, h, safeUrl } from './dom.js';
import { icon } from './icons.js';
import { Popover } from './popover.js';
import { BUTTON_VARIANTS } from '../extensions/button.js';
import { DEFAULT_CHART } from '../extensions/chart.js';
import { CHART_COLORS, renderChart, renderDiagram } from '../extensions/lazy-render.js';
import { mediaLibrary } from './media-library.js';
import { insertFiles, pickFile, uploadFile } from '../upload.js';

const DIAGRAM_TEMPLATES = {
    flowchart: 'graph TD\n  A[Idea] --> B{Worth it?}\n  B -->|Yes| C[Build]\n  B -->|No| D[Park it]\n  C --> E[Ship]',
    sequence: 'sequenceDiagram\n  participant U as User\n  participant A as App\n  participant S as Server\n  U->>A: Click save\n  A->>S: PUT /articles/1\n  S-->>A: 200 OK\n  A-->>U: Saved ✓',
    gantt: 'gantt\n  title Launch plan\n  dateFormat YYYY-MM-DD\n  section Build\n  Design :a1, 2026-01-05, 10d\n  Develop :after a1, 20d\n  section Launch\n  Beta :2026-02-10, 7d\n  Release :milestone, 2026-02-20, 0d',
    mindmap: 'mindmap\n  root((Desk))\n    Writing\n      Blocks\n      Shortcuts\n    Media\n      Images\n      Video\n    Team\n      Comments\n      Suggestions',
    pie: 'pie title Traffic sources\n  "Search" : 46\n  "Social" : 24\n  "Direct" : 18\n  "Referral" : 12',
    er: 'erDiagram\n  AUTHOR ||--o{ ARTICLE : writes\n  ARTICLE }o--o{ TAG : "tagged with"\n  ARTICLE ||--o{ COMMENT : has',
};

/**
 * Dialogs for the richer blocks (buttons, footnotes, diagrams, charts, link
 * previews, galleries). Returned as an object bound to one editor instance.
 */
export function blockDialogs(desk) {
    const t = desk.t;
    const ed = () => desk.editor;

    async function button({ node = null, pos = null } = {}) {
        const attrs = node?.attrs || { href: '', label: '', variant: 'primary', align: 'center', newTab: false };
        const label = h('input', { type: 'text', class: 'de-input', value: attrs.label, placeholder: t('buttonLabelPlaceholder'), autofocus: true });
        const href = h('input', { type: 'text', class: 'de-input', value: attrs.href === '#' ? '' : attrs.href, placeholder: 'https://' });
        const newTab = checkbox(t('openInNewTab'), attrs.newTab);
        let variant = attrs.variant;
        let align = attrs.align;
        const preview = h('div', { class: 'de-cta-wrap de-cta-preview' });
        const draw = () => {
            preview.dataset.align = align;
            preview.replaceChildren(h('span', { class: 'de-cta', dataset: { variant }, text: label.value || t('buttonLabelPlaceholder') }));
        };
        const choice = (values, current, onPick, labelFn) => {
            const group = h('div', { class: 'de-tabs' });
            values.forEach((value) => group.append(h('button', {
                type: 'button',
                class: `de-tab ${value === current ? 'is-active' : ''}`,
                text: labelFn(value),
                onClick: (e) => {
                    onPick(value);
                    group.querySelectorAll('.de-tab').forEach((b) => b.classList.toggle('is-active', b === e.currentTarget));
                    draw();
                },
            })));
            return group;
        };
        label.addEventListener('input', draw);
        draw();

        const result = await openDialog(desk, {
            title: t('button'),
            body: h('div', { class: 'de-stack' }, [
                preview,
                field(t('buttonLabel'), label),
                field(t('linkUrl'), href),
                h('div', { class: 'de-field' }, [h('span', { class: 'de-field-label', text: t('style') }), choice(BUTTON_VARIANTS, variant, (v) => (variant = v), (v) => t(`variant_${v}`))]),
                h('div', { class: 'de-field' }, [h('span', { class: 'de-field-label', text: t('align') }), choice(['left', 'center', 'right'], align, (v) => (align = v), (v) => t(`align_${v}`))]),
                newTab,
            ]),
            actions: [
                { label: t('cancel'), onClick: (close) => close(null) },
                { label: node ? t('save') : t('insertButton'), primary: true, submit: true, onClick: (close) => close(true) },
            ],
        });
        if (!result) return ed().commands.focus();
        const next = { label: label.value.trim() || t('buttonLabelPlaceholder'), href: safeUrl(href.value) || '#', variant, align, newTab: newTab.input.checked };
        if (pos !== null) ed().chain().focus().setNodeSelection(pos).updateAttributes('ctaButton', next).run();
        else ed().chain().focus().setCtaButton(next).run();
    }

    function footnote({ node = null, pos = null, anchor = null } = {}) {
        const input = h('textarea', { class: 'de-input', rows: 3, placeholder: t('footnotePlaceholder') });
        input.value = node?.attrs.content || '';
        const save = () => {
            const content = input.value.trim();
            if (pos !== null) {
                if (!content) ed().chain().focus().deleteRange({ from: pos, to: pos + 1 }).run();
                else ed().view.dispatch(ed().state.tr.setNodeAttribute(pos, 'content', content));
            } else if (content) {
                ed().chain().focus().setFootnote(content).run();
            }
            popover.close();
            ed().commands.focus();
        };
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                save();
            }
        });
        const target = anchor || caretAnchor();
        const popover = new Popover(desk, {
            anchor: target,
            placement: 'bottom-start',
            content: h('div', { class: 'de-footnote-panel' }, [
                h('div', { class: 'de-field-label', text: t('footnote') }),
                input,
                h('div', { class: 'de-thread-actions' }, [
                    pos !== null ? h('button', { type: 'button', class: 'de-button de-button-sm de-button-danger', text: t('remove'), onClick: () => { input.value = ''; save(); } }) : null,
                    h('button', { type: 'button', class: 'de-button de-button-sm de-button-primary', text: pos !== null ? t('save') : t('insertButton'), onClick: save }),
                ]),
            ]),
        });
    }

    function caretAnchor() {
        const { from } = ed().state.selection;
        const coords = ed().view.coordsAtPos(from);
        return { getBoundingClientRect: () => new DOMRect(coords.left, coords.top, 1, coords.bottom - coords.top) };
    }

    async function diagram({ node = null, pos = null } = {}) {
        const code = h('textarea', { class: 'de-input de-mono de-diagram-code', rows: 14, spellcheck: 'false', autofocus: true });
        code.value = node?.attrs.code || DIAGRAM_TEMPLATES.flowchart;
        const preview = h('div', { class: 'de-diagram-canvas de-diagram-preview', text: '…' });
        const draw = debounce(() => renderDiagram(code.value, preview, desk.resolvedTheme), 250);
        code.addEventListener('input', draw);
        const templates = h('div', { class: 'de-tabs de-tabs-wrap' }, Object.keys(DIAGRAM_TEMPLATES).map((name) => h('button', {
            type: 'button',
            class: 'de-tab',
            text: t(`diagram_${name}`),
            onClick: () => {
                code.value = DIAGRAM_TEMPLATES[name];
                draw();
            },
        })));
        draw();

        const result = await openDialog(desk, {
            title: t('diagram'),
            wide: true,
            className: 'de-dialog-split',
            body: h('div', { class: 'de-stack' }, [
                templates,
                h('div', { class: 'de-split' }, [field(t('diagramSource'), code, t('diagramHint')), h('div', { class: 'de-field' }, [h('span', { class: 'de-field-label', text: t('latexPreview') }), preview])]),
            ]),
            actions: [
                { label: t('cancel'), onClick: (close) => close(null) },
                { label: node ? t('save') : t('insertButton'), primary: true, submit: true, onClick: (close) => close(code.value) },
            ],
        });
        if (result === null) return ed().commands.focus();
        if (pos !== null) ed().view.dispatch(ed().state.tr.setNodeAttribute(pos, 'code', result));
        else ed().chain().focus().setDiagram(result).run();
    }

    async function chart({ node = null, pos = null } = {}) {
        const spec = structuredClone(node?.attrs.spec || DEFAULT_CHART);
        const canvas = h('canvas');
        const frame = h('div', { class: 'de-chart-frame' }, [canvas]);
        const title = h('input', { type: 'text', class: 'de-input', value: spec.title || '' });
        const table = h('table', { class: 'de-grid-editor' });
        const draw = debounce(() => renderChart(spec, canvas, desk.resolvedTheme), 120);
        title.addEventListener('input', () => {
            spec.title = title.value;
            draw();
        });

        const renderTable = () => {
            const cell = (value, onInput, cls = '') => {
                const input = h('input', { type: 'text', class: `de-grid-input ${cls}`, value: value ?? '' });
                input.addEventListener('input', () => {
                    onInput(input.value);
                    draw();
                });
                return h('td', {}, [input]);
            };
            const head = h('tr', {}, [
                h('th', {}, ''),
                ...spec.datasets.map((ds, i) => h('th', {}, [
                    h('div', { class: 'de-grid-series' }, [
                        h('input', { type: 'color', class: 'de-color-input', value: ds.color || CHART_COLORS[i % CHART_COLORS.length], onInput: (e) => { ds.color = e.target.value; draw(); } }),
                        h('input', { type: 'text', class: 'de-grid-input de-grid-head', value: ds.label || '', onInput: (e) => { ds.label = e.target.value; draw(); } }),
                        h('button', { type: 'button', class: 'de-grid-remove', title: t('remove'), text: '×', onClick: () => { spec.datasets.splice(i, 1); renderTable(); draw(); } }),
                    ]),
                ])),
                h('th', {}, [h('button', { type: 'button', class: 'de-button de-button-sm', text: `+ ${t('series')}`, onClick: () => {
                    spec.datasets.push({ label: `${t('series')} ${spec.datasets.length + 1}`, values: spec.labels.map(() => 0) });
                    renderTable();
                    draw();
                } })]),
            ]);
            const rows = spec.labels.map((label, r) => h('tr', {}, [
                cell(label, (v) => (spec.labels[r] = v), 'de-grid-label'),
                ...spec.datasets.map((ds) => cell(ds.values[r], (v) => (ds.values[r] = Number(v) || 0))),
                h('td', {}, [h('button', { type: 'button', class: 'de-grid-remove', title: t('remove'), text: '×', onClick: () => {
                    spec.labels.splice(r, 1);
                    spec.datasets.forEach((ds) => ds.values.splice(r, 1));
                    renderTable();
                    draw();
                } })]),
            ]));
            table.replaceChildren(h('thead', {}, [head]), h('tbody', {}, rows));
        };
        renderTable();

        const types = h('div', { class: 'de-tabs de-tabs-wrap' }, ['bar', 'line', 'area', 'pie', 'doughnut', 'radar', 'polarArea'].map((type) => h('button', {
            type: 'button',
            class: `de-tab ${spec.type === type ? 'is-active' : ''}`,
            text: t(`chart_${type}`),
            onClick: (e) => {
                spec.type = type;
                types.querySelectorAll('.de-tab').forEach((b) => b.classList.toggle('is-active', b === e.currentTarget));
                draw();
            },
        })));
        requestAnimationFrame(draw);

        const result = await openDialog(desk, {
            title: t('chart'),
            wide: true,
            className: 'de-dialog-split',
            body: h('div', { class: 'de-stack' }, [
                types,
                field(t('chartTitle'), title),
                frame,
                h('div', { class: 'de-grid-scroll' }, [table]),
                h('button', { type: 'button', class: 'de-button de-button-sm', text: `+ ${t('row')}`, onClick: () => {
                    spec.labels.push(`${t('row')} ${spec.labels.length + 1}`);
                    spec.datasets.forEach((ds) => ds.values.push(0));
                    renderTable();
                    draw();
                } }),
            ]),
            actions: [
                { label: t('cancel'), onClick: (close) => close(null) },
                { label: node ? t('save') : t('insertButton'), primary: true, submit: true, onClick: (close) => close(true) },
            ],
        });
        canvas._deskChart?.destroy();
        if (!result) return ed().commands.focus();
        if (pos !== null) ed().view.dispatch(ed().state.tr.setNodeAttribute(pos, 'spec', spec));
        else ed().chain().focus().setChart(spec).run();
    }

    async function bookmark() {
        const endpoint = desk.config.endpoints?.unfurl;
        const url = h('input', { type: 'url', class: 'de-input', placeholder: 'https://', autofocus: true });
        const preview = h('div', { class: 'de-bookmark-preview' });
        const error = h('p', { class: 'de-field-error', hidden: true });
        let data = null;
        const fetchPreview = debounce(async () => {
            data = null;
            error.hidden = true;
            const value = safeUrl(url.value);
            if (!/^https?:\/\//.test(value)) return preview.replaceChildren();
            preview.replaceChildren(h('div', { class: 'de-library-skeleton de-bookmark-skeleton' }));
            if (!endpoint) {
                data = { url: value, title: value, site: new URL(value).hostname };
            } else {
                try {
                    const response = await fetch(`${endpoint}?url=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const json = await response.json();
                    if (!response.ok) throw new Error(json.message || t('previewUnavailable'));
                    data = json.data;
                } catch (e) {
                    data = { url: value, title: value, site: new URL(value).hostname };
                    error.textContent = e.message;
                    error.hidden = false;
                }
            }
            const card = h('div', { class: 'de-bookmark' }, [
                h('span', { class: 'de-bookmark-body' }, [
                    h('span', { class: 'de-bookmark-title', text: data.title }),
                    data.description ? h('span', { class: 'de-bookmark-desc', text: data.description }) : null,
                    h('span', { class: 'de-bookmark-meta' }, [data.favicon ? h('img', { class: 'de-bookmark-favicon', src: data.favicon, alt: '' }) : null, h('span', { class: 'de-bookmark-site', text: data.site || '' })]),
                ]),
                data.image ? h('img', { class: 'de-bookmark-image', src: data.image, alt: '' }) : null,
            ]);
            preview.replaceChildren(card);
        }, 400);
        url.addEventListener('input', fetchPreview);

        const result = await openDialog(desk, {
            title: t('bookmark'),
            body: h('div', { class: 'de-stack' }, [field(t('linkUrl'), url, t('bookmarkHint')), error, preview]),
            actions: [
                { label: t('cancel'), onClick: (close) => close(null) },
                { label: t('insertButton'), primary: true, submit: true, onClick: (close) => (data ? close(data) : null) },
            ],
        });
        if (result) ed().chain().focus().setBookmark(result).run();
        else ed().commands.focus();
    }

    async function chooseImages() {
        const fromLibrary = Boolean(desk.config.endpoints?.media);
        if (fromLibrary) {
            const items = await mediaLibrary(desk, { kind: 'image', multiple: true });
            return (items || []).map((item) => ({ src: item.url, alt: item.alt || '', caption: '' }));
        }
        const files = await pickFile((desk.config.upload?.images || ['image/*']).join(','));
        const images = [];
        for (const file of files) {
            try {
                const data = await uploadFile(desk, file, 'image', (p) => desk.setStatus(`${t('uploading')} ${file.name} · ${p}%`));
                images.push({ src: data.url, alt: file.name.replace(/\.[^.]+$/, ''), caption: '' });
            } catch (error) {
                toast(desk, t('uploadFailed', { reason: error.message }), 'error');
            }
        }
        desk.setStatus('');
        return images;
    }

    async function gallery() {
        const images = await chooseImages();
        if (images.length) ed().chain().focus().setGallery({ images, columns: Math.min(3, Math.max(2, images.length)) }).run();
    }

    async function addGalleryImages(done) {
        const images = await chooseImages();
        if (images.length) done(images);
    }

    async function galleryCaption(image, done) {
        const alt = h('input', { type: 'text', class: 'de-input', value: image.alt || '', autofocus: true });
        const caption = h('input', { type: 'text', class: 'de-input', value: image.caption || '' });
        const result = await openDialog(desk, {
            title: t('caption'),
            body: h('div', { class: 'de-stack' }, [field(t('caption'), caption), field(t('altText'), alt, t('altTextHint'))]),
            actions: [
                { label: t('cancel'), onClick: (close) => close(null) },
                { label: t('save'), primary: true, submit: true, onClick: (close) => close(true) },
            ],
        });
        if (result) done({ alt: alt.value, caption: caption.value });
    }

    async function library() {
        const { insertMediaItems } = await import('./media-library.js');
        const items = await mediaLibrary(desk, { multiple: true });
        if (items?.length) insertMediaItems(desk, items);
        else ed().commands.focus();
    }

    async function uploadAny() {
        const files = await pickFile('');
        if (files.length) insertFiles(desk, files);
    }

    return { button, footnote, diagram, chart, bookmark, gallery, addGalleryImages, galleryCaption, library, uploadAny, caretAnchor, icon };
}
