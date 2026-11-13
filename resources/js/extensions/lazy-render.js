// Shared lazy loaders and renderers for diagram (Mermaid) and chart (Chart.js) blocks.
let mermaidPromise = null;
let chartPromise = null;
let diagramCounter = 0;

export function loadMermaid() {
    mermaidPromise ||= import('mermaid').then((m) => m.default);
    return mermaidPromise;
}

export function loadChart() {
    chartPromise ||= import('chart.js/auto').then((m) => m.default);
    return chartPromise;
}

export async function renderDiagram(code, target, theme = 'light') {
    const mermaid = await loadMermaid();
    mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', theme: theme === 'dark' ? 'dark' : 'default', fontFamily: 'inherit' });
    try {
        const { svg } = await mermaid.render(`desk-diagram-${++diagramCounter}`, code || 'graph TD; A-->B');
        target.innerHTML = svg;
        target.classList.remove('has-error');
        return true;
    } catch (error) {
        target.textContent = String(error?.message || error).split('\n').slice(0, 3).join('\n');
        target.classList.add('has-error');
        return false;
    }
}

export const CHART_COLORS = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#ef4444', '#84cc16'];

export async function renderChart(spec, canvas, theme = 'light') {
    const Chart = await loadChart();
    const dark = theme === 'dark';
    const grid = dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
    const text = dark ? '#a1a1aa' : '#52525b';
    const type = spec.type === 'area' ? 'line' : spec.type || 'bar';
    const radial = ['pie', 'doughnut', 'polarArea'].includes(type);
    const datasets = (spec.datasets || []).map((ds, i) => {
        const color = ds.color || CHART_COLORS[i % CHART_COLORS.length];
        return {
            label: ds.label,
            data: (ds.values || []).map(Number),
            borderColor: radial ? (dark ? '#18181b' : '#fff') : color,
            backgroundColor: radial ? (spec.labels || []).map((_, j) => CHART_COLORS[j % CHART_COLORS.length]) : spec.type === 'area' ? `${color}33` : color,
            fill: spec.type === 'area',
            tension: 0.35,
            borderWidth: radial ? 2 : 2,
            borderRadius: type === 'bar' ? 6 : 0,
            pointRadius: type === 'line' ? 3 : 0,
        };
    });
    canvas._deskChart?.destroy();
    canvas._deskChart = new Chart(canvas, {
        type,
        data: { labels: spec.labels || [], datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 500 },
            plugins: {
                legend: { display: datasets.length > 1 || radial, labels: { color: text, usePointStyle: true } },
                title: { display: Boolean(spec.title), text: spec.title, color: dark ? '#fafafa' : '#18181b', font: { size: 15, weight: '600' } },
            },
            scales: radial ? {} : {
                x: { grid: { color: grid }, ticks: { color: text } },
                y: { grid: { color: grid }, ticks: { color: text }, beginAtZero: true },
            },
        },
    });
}
