import katex from 'katex';
import { toHtml } from 'hast-util-to-html';
import { DeskEditor, DEFAULTS } from './editor.js';
import { lowlight } from './lowlight.js';
import { bindLightbox } from './ui/lightbox.js';

const pageTheme = () => {
    const html = document.documentElement;
    return html.dataset.theme === 'dark' || html.classList.contains('dark') ? 'dark' : 'light';
};

/**
 * Renders stored Desk content for readers: collapsible sections, KaTeX
 * equations, footnotes, diagrams, charts, an image lightbox and highlighted
 * code. Used by <x-desk-editor::content> and any element with .desk-content.
 */
function renderContent(root = document) {
    const scope = root.querySelectorAll ? root : document;
    const readers = [...scope.querySelectorAll('.desk-content:not(.ProseMirror)')];
    if (scope.matches?.('.desk-content:not(.ProseMirror)')) readers.push(scope);

    // Collapsible sections become native <details> for readers.
    scope.querySelectorAll('.desk-content div[data-type="details"]').forEach((el) => {
        if (el.closest('.ProseMirror')) return;
        const summary = el.querySelector('summary, [data-type="detailsSummary"]');
        const content = el.querySelector('[data-type="detailsContent"]');
        const details = document.createElement('details');
        details.className = 'de-details';
        const label = document.createElement('summary');
        label.innerHTML = summary?.innerHTML || '';
        details.append(label);
        if (content) details.append(...content.childNodes);
        el.replaceWith(details);
    });

    scope.querySelectorAll('.desk-content [data-type="inline-math"], .desk-content [data-type="block-math"]').forEach((el) => {
        if (el.dataset.deskRendered || el.closest('.ProseMirror')) return;
        try {
            katex.render(el.getAttribute('data-latex') || el.textContent, el, { displayMode: el.dataset.type === 'block-math', throwOnError: false });
            el.dataset.deskRendered = '1';
        } catch {
            // leave the LaTeX source visible
        }
    });

    // Footnotes: number them, link both ways and list them at the end.
    readers.forEach((content, contentIndex) => {
        const notes = [...content.querySelectorAll('sup[data-type="footnote"]')];
        if (!notes.length || content.querySelector(':scope > .de-footnotes')) return;
        const list = document.createElement('ol');
        notes.forEach((sup, i) => {
            const n = i + 1;
            const id = `fn-${contentIndex}-${n}`;
            const text = sup.getAttribute('data-content') || '';
            sup.id = `${id}-ref`;
            sup.textContent = '';
            const link = document.createElement('a');
            link.href = `#${id}`;
            link.textContent = String(n);
            link.title = text;
            link.setAttribute('aria-describedby', `${id}-label`);
            sup.append(link);
            const li = document.createElement('li');
            li.id = id;
            li.textContent = `${text} `;
            const back = document.createElement('a');
            back.href = `#${id}-ref`;
            back.className = 'de-footnote-back';
            back.setAttribute('aria-label', `Back to reference ${n}`);
            back.textContent = '↩';
            li.append(back);
            list.append(li);
        });
        const section = document.createElement('section');
        section.className = 'de-footnotes';
        const heading = document.createElement('h2');
        heading.className = 'de-footnotes-title';
        heading.id = `fn-${contentIndex}-label`;
        heading.textContent = 'Notes';
        section.setAttribute('aria-labelledby', heading.id);
        section.append(heading, list);
        content.append(section);
        notes.forEach((sup) => sup.querySelector('a').setAttribute('aria-describedby', heading.id));
    });

    // Diagrams and charts load their libraries only when present.
    const diagrams = readers.flatMap((c) => [...c.querySelectorAll('div[data-type="diagram"]:not([data-desk-rendered])')]);
    const charts = readers.flatMap((c) => [...c.querySelectorAll('figure[data-type="chart"]:not([data-desk-rendered])')]);
    if (diagrams.length || charts.length) {
        import('./extensions/lazy-render.js').then(({ renderDiagram, renderChart }) => {
            diagrams.forEach((el) => {
                el.dataset.deskRendered = '1';
                const canvas = document.createElement('div');
                canvas.className = 'de-diagram-canvas';
                canvas.setAttribute('role', 'img');
                canvas.setAttribute('aria-label', 'Diagram');
                el.append(canvas);
                renderDiagram(el.getAttribute('data-code') || '', canvas, pageTheme());
            });
            charts.forEach((el) => {
                el.dataset.deskRendered = '1';
                let spec = null;
                try {
                    spec = JSON.parse(el.getAttribute('data-chart') || 'null');
                } catch {
                    return;
                }
                if (!spec) return;
                const frame = document.createElement('div');
                frame.className = 'de-chart-frame';
                const canvas = document.createElement('canvas');
                canvas.setAttribute('role', 'img');
                canvas.setAttribute('aria-label', spec.title || 'Chart');
                frame.append(canvas);
                el.prepend(frame);
                el.classList.add('is-rendered');
                renderChart(spec, canvas, pageTheme());
            });
        });
    }

    bindLightbox(scope);

    scope.querySelectorAll('.desk-content pre code').forEach((el) => {
        if (el.dataset.deskRendered || el.closest('.ProseMirror')) return;
        const language = [...el.classList].find((c) => c.startsWith('language-'))?.slice(9);
        try {
            const tree = language && lowlight.registered(language) ? lowlight.highlight(language, el.textContent) : lowlight.highlightAuto(el.textContent);
            el.innerHTML = toHtml(tree);
            el.classList.add('hljs');
            el.dataset.deskRendered = '1';
        } catch {
            // unknown language: keep plain text
        }
    });
}

function init(root = document) {
    const scope = root.querySelectorAll ? root : document;
    const editors = [];

    scope.querySelectorAll('[data-desk-editor]:not([data-desk-ready])').forEach((el) => {
        let config = {};
        try {
            config = JSON.parse(el.getAttribute('data-desk-editor') || '{}');
        } catch (error) {
            console.error('[desk-editor] Invalid configuration JSON', error);
        }
        editors.push(new DeskEditor(el, config));
    });

    if (scope.querySelector?.('[data-desk-content], .desk-content')) renderContent(scope);

    return editors;
}

function boot() {
    init();
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches?.('[data-desk-editor], [data-desk-content]') || node.querySelector?.('[data-desk-editor], [data-desk-content]')) {
                    init(node.parentElement || document);
                }
            }
            for (const node of mutation.removedNodes) {
                if (node.nodeType !== 1 || node.isConnected) continue;
                const roots = node.matches?.('[data-desk-ready]') ? [node] : [...(node.querySelectorAll?.('[data-desk-ready]') || [])];
                roots.forEach((el) => DeskEditor.get(el)?.destroy());
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
}

if (typeof window !== 'undefined') {
    window.DeskEditor = Object.assign(DeskEditor, { init, renderContent, defaults: DEFAULTS, version: '1.0.0' });
    if (window.DeskEditorAutoInit !== false) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
        else boot();
    }
}

export { DeskEditor, init, renderContent };
