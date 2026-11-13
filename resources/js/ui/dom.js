import { icon } from './icons.js';

/**
 * Tiny hyperscript helper: h('button', { class: 'x', onClick: fn }, [children]).
 */
export function h(tag, props = {}, children = []) {
    const el = document.createElement(tag);

    for (const [key, value] of Object.entries(props || {})) {
        if (value === undefined || value === null || value === false) continue;

        if (key === 'class') el.className = value;
        else if (key === 'html') el.innerHTML = value;
        else if (key === 'text') el.textContent = value;
        else if (key === 'style' && typeof value === 'object') {
            // setProperty also supports CSS custom properties (e.g. "--swatch").
            for (const [prop, v] of Object.entries(value)) {
                if (v === null || v === undefined) continue;
                if (prop.startsWith('--')) el.style.setProperty(prop, v);
                else el.style[prop] = v;
            }
        }
        else if (key === 'dataset') Object.assign(el.dataset, value);
        else if (key.startsWith('on') && typeof value === 'function') el.addEventListener(key.slice(2).toLowerCase(), value);
        else if (value === true) el.setAttribute(key, '');
        else el.setAttribute(key, value);
    }

    for (const child of [].concat(children)) {
        if (child === null || child === undefined || child === false) continue;
        el.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }

    return el;
}

export function iconButton(name, label, onClick, extra = {}) {
    return h('button', {
        type: 'button',
        class: `de-btn ${extra.class || ''}`.trim(),
        title: extra.shortcut ? `${label} (${extra.shortcut})` : label,
        'aria-label': label,
        html: icon(name) + (extra.caret ? icon('ChevronDown', 12) : ''),
        onMousedown: (event) => event.preventDefault(),
        onClick,
        ...(extra.attrs || {}),
    });
}

export const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

export function shortcut(keys) {
    if (!keys) return '';

    return keys
        .replace(/Mod/g, isMac ? '⌘' : 'Ctrl')
        .replace(/Shift/g, isMac ? '⇧' : 'Shift')
        .replace(/Alt/g, isMac ? '⌥' : 'Alt')
        .replace(/-/g, isMac ? '' : '+');
}

export function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i++;
    }

    return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

export function download(filename, content, type) {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const a = h('a', { href: url, download: filename });
    document.body.append(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function debounce(fn, wait) {
    let timer;

    const wrapped = (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
    wrapped.cancel = () => clearTimeout(timer);

    return wrapped;
}

export function safeUrl(url) {
    const value = String(url || '').trim();
    if (!value) return '';
    if (/^(javascript|vbscript|data):/i.test(value.replace(/\s/g, ''))) return '';
    if (/^(https?:|mailto:|tel:|\/|#|\.)/i.test(value)) return value;

    return /^[\w.-]+\.[a-z]{2,}/i.test(value) ? `https://${value}` : value;
}
