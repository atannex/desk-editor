import { autoUpdate, computePosition, flip, offset, shift, size } from '@floating-ui/dom';
import { h } from './dom.js';

/**
 * A floating panel anchored to an element (or a virtual rect). Only one popover
 * per editor is open at a time; opening another closes the previous one.
 */
export class Popover {
    constructor(desk, { anchor, content, placement = 'bottom-start', className = '', onClose, focus = true }) {
        this.desk = desk;
        this.anchor = anchor;
        this.onClose = onClose;
        this.el = h('div', { class: `de-popover ${className}`.trim(), role: 'dialog' }, [content]);

        desk.closePopover();
        desk.activePopover = this;
        desk.layer.append(this.el);

        this.cleanup = autoUpdate(anchor, this.el, () => {
            computePosition(anchor, this.el, {
                placement,
                strategy: 'fixed',
                middleware: [
                    offset(6),
                    flip({ padding: 8 }),
                    shift({ padding: 8 }),
                    size({
                        padding: 8,
                        apply: ({ availableHeight }) => {
                            this.el.style.maxHeight = `${Math.max(160, availableHeight)}px`;
                        },
                    }),
                ],
            }).then(({ x, y }) => {
                Object.assign(this.el.style, { left: `${x}px`, top: `${y}px` });
            });
        });

        this.onDocumentDown = (event) => {
            if (this.el.contains(event.target)) return;
            if (anchor instanceof Element && anchor.contains(event.target)) return;
            this.close();
        };
        this.onKey = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.close(true);
            }
        };

        setTimeout(() => document.addEventListener('mousedown', this.onDocumentDown, true));
        this.el.addEventListener('keydown', this.onKey);

        requestAnimationFrame(() => {
            this.el.classList.add('is-open');
            if (focus) this.el.querySelector('[autofocus], input, textarea, select')?.focus();
        });
    }

    close(refocus = false) {
        if (this.closed) return;
        this.closed = true;
        this.cleanup?.();
        document.removeEventListener('mousedown', this.onDocumentDown, true);
        this.el.remove();
        if (this.desk.activePopover === this) this.desk.activePopover = null;
        if (this.anchor instanceof Element) this.anchor.setAttribute('aria-expanded', 'false');
        this.onClose?.();
        if (refocus) this.desk.editor?.commands.focus();
    }
}

/**
 * A vertical menu of actions — used by dropdown toolbar items.
 */
export function menu(items, onPick) {
    const list = h('div', { class: 'de-menu', role: 'menu' });

    for (const item of items) {
        if (item === '|') {
            list.append(h('div', { class: 'de-menu-sep', role: 'separator' }));
            continue;
        }
        if (item.heading) {
            list.append(h('div', { class: 'de-menu-heading', text: item.heading }));
            continue;
        }

        const btn = h('button', {
            type: 'button',
            role: 'menuitemradio',
            class: `de-menu-item ${item.active ? 'is-active' : ''}`.trim(),
            'aria-checked': item.active ? 'true' : 'false',
            disabled: item.disabled,
            onMousedown: (event) => event.preventDefault(),
            onClick: () => onPick(item),
        });

        if (item.icon) btn.append(h('span', { class: 'de-menu-icon', html: item.icon }));
        btn.append(h('span', { class: 'de-menu-label', style: item.style || null, text: item.label }));
        if (item.hint) btn.append(h('kbd', { class: 'de-menu-hint', text: item.hint }));
        list.append(btn);
    }

    list.addEventListener('keydown', (event) => {
        const buttons = [...list.querySelectorAll('.de-menu-item:not([disabled])')];
        const index = buttons.indexOf(document.activeElement);
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            buttons[(index + 1) % buttons.length]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            buttons[(index - 1 + buttons.length) % buttons.length]?.focus();
        }
    });

    return list;
}
