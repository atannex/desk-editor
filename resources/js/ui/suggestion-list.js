import { computePosition, flip, offset, shift } from '@floating-ui/dom';
import { h } from './dom.js';

/**
 * Renderer used by every @tiptap/suggestion powered feature (slash commands,
 * mentions, emoji). Items may carry { group, icon, label, description, hint }.
 */
export function suggestionRenderer(desk, { emptyText, className = '', renderItem }) {
    return () => {
        let el;
        let items = [];
        let selected = 0;
        let command;
        let clientRect;

        const select = (index) => {
            const item = items[index];
            if (item) command(item);
        };

        const position = () => {
            const rect = clientRect?.();
            if (!rect || !el) return;
            const virtual = { getBoundingClientRect: () => rect };
            computePosition(virtual, el, {
                placement: 'bottom-start',
                strategy: 'fixed',
                middleware: [offset(6), flip({ padding: 8 }), shift({ padding: 8 })],
            }).then(({ x, y }) => Object.assign(el.style, { left: `${x}px`, top: `${y}px` }));
        };

        const render = () => {
            el.replaceChildren();
            if (!items.length) {
                el.append(h('div', { class: 'de-suggest-empty', text: emptyText }));
                return;
            }

            let group = null;
            items.forEach((item, index) => {
                if (item.group && item.group !== group) {
                    group = item.group;
                    el.append(h('div', { class: 'de-menu-heading', text: group }));
                }
                const button = h('button', {
                    type: 'button',
                    class: `de-suggest-item ${index === selected ? 'is-selected' : ''}`,
                    role: 'option',
                    'aria-selected': index === selected ? 'true' : 'false',
                    onMousedown: (event) => {
                        event.preventDefault();
                        select(index);
                    },
                    onMouseenter: () => {
                        selected = index;
                        el.querySelectorAll('.de-suggest-item').forEach((node, i) => node.classList.toggle('is-selected', i === index));
                    },
                });
                if (renderItem) {
                    renderItem(button, item);
                } else {
                    if (item.icon) button.append(h('span', { class: 'de-suggest-icon', html: item.icon }));
                    button.append(
                        h('span', { class: 'de-suggest-text' }, [
                            h('span', { class: 'de-suggest-label', text: item.label }),
                            item.description ? h('span', { class: 'de-suggest-desc', text: item.description }) : null,
                        ]),
                    );
                    if (item.hint) button.append(h('kbd', { class: 'de-menu-hint', text: item.hint }));
                }
                el.append(button);
            });
            el.querySelector('.is-selected')?.scrollIntoView({ block: 'nearest' });
        };

        return {
            onStart(props) {
                items = props.items;
                command = props.command;
                clientRect = props.clientRect;
                selected = 0;
                el = h('div', { class: `de-popover de-suggest is-open ${className}`.trim(), role: 'listbox' });
                desk.layer.append(el);
                render();
                position();
            },
            onUpdate(props) {
                if (!el) return;
                items = props.items;
                command = props.command;
                clientRect = props.clientRect;
                selected = 0;
                render();
                position();
            },
            onKeyDown({ event }) {
                if (!el || !items.length) return false;
                if (event.key === 'ArrowDown') {
                    selected = (selected + 1) % items.length;
                    render();
                    return true;
                }
                if (event.key === 'ArrowUp') {
                    selected = (selected - 1 + items.length) % items.length;
                    render();
                    return true;
                }
                if (event.key === 'Enter' || event.key === 'Tab') {
                    select(selected);
                    return true;
                }
                if (event.key === 'Escape') {
                    el?.remove();
                    el = null;
                    return true;
                }
                return false;
            },
            onExit() {
                el?.remove();
                el = null;
            },
        };
    };
}
