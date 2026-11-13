import { Node } from '@tiptap/core';
import { renderChart } from './lazy-render.js';
import { insertBlock } from './insert-block.js';

export const DEFAULT_CHART = {
    type: 'bar',
    title: 'Quarterly revenue',
    labels: ['Q1', 'Q2', 'Q3', 'Q4'],
    datasets: [
        { label: '2025', values: [12, 19, 15, 24] },
        { label: '2026', values: [16, 23, 21, 31] },
    ],
};

function parseSpec(value) {
    try {
        const spec = JSON.parse(value);
        return spec && typeof spec === 'object' ? spec : DEFAULT_CHART;
    } catch {
        return DEFAULT_CHART;
    }
}

/**
 * Chart block. Stores its data as JSON and also renders an accessible data
 * table, so the content stays meaningful without JavaScript.
 */
export const ChartBlock = Node.create({
    name: 'chart',
    group: 'block',
    atom: true,
    draggable: true,

    addOptions() {
        return { onEdit: null, theme: () => 'light' };
    },

    addAttributes() {
        return {
            spec: {
                default: DEFAULT_CHART,
                parseHTML: (el) => parseSpec(el.getAttribute('data-chart')),
                renderHTML: (attrs) => ({ 'data-chart': JSON.stringify(attrs.spec) }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-type="chart"]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        const spec = node.attrs.spec;
        const head = ['tr', {}, ['th', {}, ''], ...(spec.datasets || []).map((ds) => ['th', {}, String(ds.label ?? '')])];
        const rows = (spec.labels || []).map((label, i) => ['tr', {}, ['th', {}, String(label)], ...(spec.datasets || []).map((ds) => ['td', {}, String(ds.values?.[i] ?? '')])]);

        return ['figure', { ...HTMLAttributes, 'data-type': 'chart', class: 'de-chart' },
            ['table', { class: 'de-chart-data' }, ['thead', {}, head], ['tbody', {}, ...rows]],
            ...(spec.title ? [['figcaption', {}, spec.title]] : []),
        ];
    },

    addNodeView() {
        return ({ node, getPos }) => {
            let current = node;
            const dom = document.createElement('figure');
            dom.className = 'de-chart';
            dom.dataset.type = 'chart';
            const frame = document.createElement('div');
            frame.className = 'de-chart-frame';
            const canvas = document.createElement('canvas');
            frame.append(canvas);
            dom.append(frame);
            dom.addEventListener('dblclick', () => this.options.onEdit?.(current, getPos()));
            const draw = () => renderChart(current.attrs.spec, canvas, this.options.theme());
            draw();

            return {
                dom,
                update(updated) {
                    if (updated.type.name !== 'chart') return false;
                    current = updated;
                    draw();
                    return true;
                },
                ignoreMutation: () => true,
                selectNode: () => dom.classList.add('ProseMirror-selectednode'),
                deselectNode: () => dom.classList.remove('ProseMirror-selectednode'),
                destroy: () => canvas._deskChart?.destroy(),
            };
        };
    },

    addCommands() {
        return {
            setChart: (spec) => insertBlock({ type: this.name, attrs: { spec: spec || DEFAULT_CHART } }),
        };
    },
});
