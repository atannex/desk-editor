import { TextSelection } from '@tiptap/pm/state';
import { h, iconButton } from './dom.js';
import { icon } from './icons.js';
import { toast } from './dialog.js';
import { commentAnchors } from '../extensions/comments.js';

const relative = (iso) => {
    if (!iso) return '';
    const seconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    if (seconds < 60) return rtf.format(-seconds, 'second');
    if (seconds < 3600) return rtf.format(-Math.round(seconds / 60), 'minute');
    if (seconds < 86400) return rtf.format(-Math.round(seconds / 3600), 'hour');
    return rtf.format(-Math.round(seconds / 86400), 'day');
};

const initials = (name) => String(name || '?').split(/\s+/).map((p) => p[0]).join('').slice(0, 2).toUpperCase();

/**
 * Side panel listing editorial comment threads for the editor's document.
 */
export class CommentsPanel {
    constructor(desk) {
        this.desk = desk;
        this.threads = [];
        this.filter = 'open';
        this.el = h('aside', { class: 'de-comments', hidden: true, 'aria-label': desk.t('comments') });
        this.list = h('div', { class: 'de-comments-list' });
        this.composer = null;
        this.build();

        desk.editor.view.dom.addEventListener('click', (event) => {
            const anchor = event.target.closest?.('[data-comment]');
            if (anchor) {
                this.open();
                this.focusThread(anchor.dataset.comment);
            }
        });
    }

    get endpoint() {
        return this.desk.config.endpoints?.threads;
    }

    build() {
        const { t } = this.desk;
        const tabs = h('div', { class: 'de-tabs de-tabs-full' }, ['open', 'resolved'].map((value) => h('button', {
            type: 'button',
            class: `de-tab ${value === this.filter ? 'is-active' : ''}`,
            dataset: { filter: value },
            text: t(value === 'open' ? 'openThreads' : 'resolvedThreads'),
            onClick: () => {
                this.filter = value;
                tabs.querySelectorAll('.de-tab').forEach((b) => b.classList.toggle('is-active', b.dataset.filter === value));
                this.render();
            },
        })));
        this.el.append(
            h('div', { class: 'de-comments-head' }, [
                h('span', { class: 'de-toc-title', text: t('comments') }),
                iconButton('X', t('close'), () => this.desk.toggleComments(false)),
            ]),
            tabs,
            this.list,
        );
    }

    open() {
        if (this.el.hidden) this.desk.toggleComments(true);
    }

    async request(method, url, body) {
        const token = this.desk.config.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(token ? { 'X-CSRF-TOKEN': token } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (!response.ok && response.status !== 204) throw new Error((await response.json().catch(() => ({}))).message || `HTTP ${response.status}`);
        return response.status === 204 ? null : response.json();
    }

    async load() {
        if (!this.endpoint) return;
        const url = new URL(this.endpoint, location.href);
        url.searchParams.set('document', this.desk.config.documentKey);
        try {
            const data = await this.request('GET', url);
            this.threads = data.data || [];
            this.render();
            this.desk.emit('comments', { count: this.openCount() });
        } catch (error) {
            this.list.replaceChildren(h('p', { class: 'de-toc-empty', text: error.message }));
        }
    }

    openCount() {
        return this.threads.filter((thread) => !thread.resolved).length;
    }

    /** Start a new thread for the current selection. */
    startThread() {
        const { editor, t } = this.desk;
        const { from, to, empty } = editor.state.selection;
        if (empty) return toast(this.desk, t('selectTextToComment'));
        const quote = editor.state.doc.textBetween(from, to, ' ');
        this.open();
        this.filter = 'open';
        this.render();

        const anchor = `c-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
        const input = h('textarea', { class: 'de-input de-comment-input', rows: 3, placeholder: t('writeComment'), autofocus: true });
        const card = h('div', { class: 'de-thread is-new' }, [
            h('blockquote', { class: 'de-thread-quote', text: quote }),
            input,
            h('div', { class: 'de-thread-actions' }, [
                h('button', { type: 'button', class: 'de-button de-button-sm', text: t('cancel'), onClick: () => card.remove() }),
                h('button', {
                    type: 'button',
                    class: 'de-button de-button-sm de-button-primary',
                    text: t('comment'),
                    onClick: async () => {
                        if (!input.value.trim()) return input.focus();
                        try {
                            const data = await this.request('POST', this.endpoint, { document: this.desk.config.documentKey, anchor, quote, body: input.value });
                            editor.chain().setTextSelection({ from, to }).setComment(anchor).run();
                            this.threads.unshift(data.data);
                            card.remove();
                            this.render();
                            this.focusThread(anchor);
                            this.desk.emit('comments', { count: this.openCount() });
                        } catch (error) {
                            toast(this.desk, error.message, 'error');
                        }
                    },
                }),
            ]),
        ]);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) card.querySelector('.de-button-primary').click();
        });
        this.list.prepend(card);
        requestAnimationFrame(() => input.focus());
    }

    render() {
        const { t } = this.desk;
        const anchors = commentAnchors(this.desk.editor.state.doc);
        const threads = this.threads.filter((thread) => (this.filter === 'open' ? !thread.resolved : thread.resolved));
        const newCard = this.list.querySelector('.de-thread.is-new');
        this.list.replaceChildren(...(newCard ? [newCard] : []));

        if (!threads.length) {
            this.list.append(h('div', { class: 'de-comments-empty' }, [
                h('span', { html: icon('MessageSquare', 28) }),
                h('p', { text: this.filter === 'open' ? t('noOpenThreads') : t('noResolvedThreads') }),
            ]));
            return;
        }

        // Order by position in the document, orphaned threads last.
        threads.sort((a, b) => (anchors.get(a.anchor) ?? Infinity) - (anchors.get(b.anchor) ?? Infinity));

        for (const thread of threads) {
            const orphan = !anchors.has(thread.anchor);
            const reply = h('textarea', { class: 'de-input de-comment-input', rows: 1, placeholder: t('reply') });
            const card = h('article', { class: `de-thread ${orphan ? 'is-orphan' : ''}`, dataset: { anchor: thread.anchor }, tabindex: '0' }, [
                thread.quote ? h('blockquote', { class: 'de-thread-quote', text: thread.quote, title: orphan ? t('anchorRemoved') : null }) : null,
                ...thread.messages.map((message) => h('div', { class: 'de-message' }, [
                    h('span', { class: 'de-avatar', text: initials(message.author) }),
                    h('div', { class: 'de-message-body' }, [
                        h('div', { class: 'de-message-head' }, [
                            h('strong', { text: message.author }),
                            h('time', { datetime: message.created_at, title: message.created_at, text: relative(message.created_at) }),
                            message.mine ? h('button', {
                                type: 'button',
                                class: 'de-message-delete',
                                title: t('remove'),
                                html: icon('Trash2', 13),
                                onClick: async () => {
                                    await this.request('DELETE', `${this.endpoint.replace(/\/threads$/, '')}/thread-messages/${message.id}`);
                                    this.load();
                                },
                            }) : null,
                        ]),
                        h('p', { text: message.body }),
                    ]),
                ])),
                thread.resolved
                    ? h('p', { class: 'de-thread-resolved', html: `${icon('CircleCheck', 14)} ${t('resolvedBy', { name: thread.resolved_by || '' })}` })
                    : reply,
                h('div', { class: 'de-thread-actions' }, [
                    thread.resolved
                        ? h('button', { type: 'button', class: 'de-button de-button-sm', text: t('reopen'), onClick: () => this.resolve(thread, false) })
                        : h('button', { type: 'button', class: 'de-button de-button-sm', html: `${icon('Check', 14)}<span>${t('resolve')}</span>`, onClick: () => this.resolve(thread, true) }),
                    thread.can_delete ? h('button', { type: 'button', class: 'de-button de-button-sm de-button-danger', text: t('deleteThread'), onClick: () => this.remove(thread) }) : null,
                ]),
            ]);
            reply.addEventListener('focus', () => (reply.rows = 3));
            reply.addEventListener('keydown', async (e) => {
                if (e.key !== 'Enter' || e.shiftKey || !reply.value.trim()) return;
                e.preventDefault();
                const data = await this.request('POST', `${this.endpoint}/${thread.id}/messages`, { body: reply.value });
                Object.assign(thread, data.data);
                this.render();
                this.focusThread(thread.anchor);
            });
            card.addEventListener('click', (e) => {
                if (e.target.closest('button, textarea')) return;
                this.select(thread.anchor);
            });
            this.list.append(card);
        }
    }

    async resolve(thread, resolved) {
        const data = await this.request('PATCH', `${this.endpoint}/${thread.id}`, { resolved });
        Object.assign(thread, data.data);
        this.render();
        this.desk.emit('comments', { count: this.openCount() });
    }

    async remove(thread) {
        await this.request('DELETE', `${this.endpoint}/${thread.id}`);
        this.desk.editor.commands.unsetCommentById(thread.anchor);
        this.threads = this.threads.filter((t) => t !== thread);
        this.render();
        this.desk.emit('comments', { count: this.openCount() });
    }

    /** Select the commented text in the editor. */
    select(anchor) {
        const { editor } = this.desk;
        let range = null;
        editor.state.doc.descendants((node, pos) => {
            if (node.marks.some((m) => m.type.name === 'comment' && m.attrs.id === anchor)) {
                range = range ? { from: range.from, to: pos + node.nodeSize } : { from: pos, to: pos + node.nodeSize };
            }
        });
        if (!range) return;
        editor.view.dispatch(editor.state.tr.setSelection(TextSelection.create(editor.state.doc, range.from, range.to)).scrollIntoView());
        editor.view.focus();
        this.highlight(anchor);
    }

    focusThread(anchor) {
        const card = this.list.querySelector(`[data-anchor="${CSS.escape(anchor)}"]`);
        if (!card) {
            const thread = this.threads.find((t) => t.anchor === anchor);
            if (thread && thread.resolved !== (this.filter === 'resolved')) {
                this.filter = thread.resolved ? 'resolved' : 'open';
                this.el.querySelectorAll('.de-tab[data-filter]').forEach((b) => b.classList.toggle('is-active', b.dataset.filter === this.filter));
                this.render();
                return this.focusThread(anchor);
            }
            return;
        }
        this.list.querySelectorAll('.de-thread').forEach((c) => c.classList.toggle('is-active', c === card));
        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        this.highlight(anchor);
    }

    highlight(anchor) {
        this.desk.editor.view.dom.querySelectorAll('[data-comment]').forEach((el) => el.classList.toggle('is-active', el.dataset.comment === anchor));
    }
}
