import { h } from './dom.js';
import { icon } from './icons.js';

/**
 * Accessible modal dialog rendered inside the editor's themed layer.
 * Resolves a Promise with the value passed to close(), or null when dismissed.
 */
export function openDialog(desk, { title, body, actions = [], wide = false, className = '' }) {
    return new Promise((resolve) => {
        const previous = document.activeElement;
        let done = false;

        const close = (value = null) => {
            if (done) return;
            done = true;
            backdrop.classList.remove('is-open');
            setTimeout(() => backdrop.remove(), 160);
            document.removeEventListener('keydown', onKey, true);
            previous?.focus?.();
            resolve(value);
        };

        const footer = h('div', { class: 'de-dialog-footer' });
        for (const action of actions) {
            footer.append(
                h('button', {
                    type: action.submit ? 'submit' : 'button',
                    class: `de-button ${action.primary ? 'de-button-primary' : ''} ${action.danger ? 'de-button-danger' : ''}`.trim(),
                    text: action.label,
                    onClick: action.submit ? null : () => action.onClick?.(close),
                }),
            );
        }

        const form = h('form', {
            class: `de-dialog ${wide ? 'de-dialog-wide' : ''} ${className}`.trim(),
            role: 'dialog',
            'aria-modal': 'true',
            'aria-label': title,
            onSubmit: (event) => {
                event.preventDefault();
                const submit = actions.find((a) => a.submit);
                submit?.onClick?.(close);
            },
        }, [
            h('div', { class: 'de-dialog-header' }, [
                h('h2', { class: 'de-dialog-title', text: title }),
                h('button', {
                    type: 'button',
                    class: 'de-btn de-dialog-close',
                    'aria-label': desk.t('close'),
                    html: icon('X'),
                    onClick: () => close(null),
                }),
            ]),
            h('div', { class: 'de-dialog-body' }, [body]),
            actions.length ? footer : null,
        ]);

        const backdrop = h('div', {
            class: 'de-dialog-backdrop',
            onMousedown: (event) => {
                if (event.target === backdrop) close(null);
            },
        }, [form]);

        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                close(null);
            }
            if (event.key === 'Tab') {
                const focusable = [...form.querySelectorAll('button, input, textarea, select, [tabindex]:not([tabindex="-1"])')]
                    .filter((el) => !el.disabled && el.offsetParent !== null);
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };

        desk.closePopover();
        document.addEventListener('keydown', onKey, true);
        desk.layer.append(backdrop);
        requestAnimationFrame(() => {
            backdrop.classList.add('is-open');
            (form.querySelector('[autofocus]') || form.querySelector('input, textarea, select'))?.focus();
        });
    });
}

export function field(label, control, hint) {
    return h('label', { class: 'de-field' }, [
        h('span', { class: 'de-field-label', text: label }),
        control,
        hint ? h('span', { class: 'de-field-hint', text: hint }) : null,
    ]);
}

export function checkbox(label, checked = false) {
    const input = h('input', { type: 'checkbox', checked });
    const wrap = h('label', { class: 'de-check' }, [input, h('span', { text: label })]);
    wrap.input = input;

    return wrap;
}

export function toast(desk, message, type = 'info') {
    const el = h('div', { class: `de-toast de-toast-${type}`, role: 'status', text: message });
    desk.layer.append(el);
    requestAnimationFrame(() => el.classList.add('is-open'));
    setTimeout(() => {
        el.classList.remove('is-open');
        setTimeout(() => el.remove(), 200);
    }, 2800);
}
