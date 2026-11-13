import { openDialog } from './dialog.js';
import { debounce, formatBytes, h } from './dom.js';
import { icon } from './icons.js';
import { kindOf, pickFile, uploadFile } from '../upload.js';

/**
 * Browse previously uploaded media. Resolves with the selected items
 * (array, possibly empty) or null when dismissed.
 *
 * @param {{ kind?: string|null, multiple?: boolean }} options
 */
export function mediaLibrary(desk, { kind = null, multiple = false } = {}) {
    const { t, config } = desk;
    const endpoint = config.endpoints?.media;
    if (!endpoint) return Promise.resolve(null);

    let page = 1;
    let lastPage = 1;
    let filter = kind;
    let query = '';
    let loading = false;
    const selected = new Map();

    const grid = h('div', { class: 'de-library-grid', role: 'listbox', 'aria-multiselectable': multiple ? 'true' : 'false' });
    const status = h('div', { class: 'de-library-status' });
    const more = h('button', { type: 'button', class: 'de-button de-button-sm de-library-more', text: t('loadMore'), hidden: true, onClick: () => load(page + 1) });
    const search = h('input', { type: 'search', class: 'de-input', placeholder: t('searchMedia'), autofocus: true });
    const footerNote = h('span', { class: 'de-library-selected' });
    const tabs = h('div', { class: 'de-tabs' });

    const kinds = kind ? [kind] : [null, 'image', 'video', 'audio', 'file'];
    kinds.forEach((value) => tabs.append(h('button', {
        type: 'button',
        class: `de-tab ${value === filter ? 'is-active' : ''}`,
        text: value ? t(`kind_${value}`) : t('all'),
        onClick: (e) => {
            filter = value;
            tabs.querySelectorAll('.de-tab').forEach((b) => b.classList.toggle('is-active', b === e.currentTarget));
            load(1);
        },
    })));

    const card = (item) => {
        const thumb = item.kind === 'image'
            ? h('img', { src: item.thumb_url || item.url, alt: item.alt || '', loading: 'lazy' })
            : item.kind === 'video' && item.poster_url
                ? h('img', { src: item.poster_url, alt: '', loading: 'lazy' })
                : h('span', { class: 'de-library-icon', html: icon({ video: 'Video', audio: 'Music', file: 'FileText' }[item.kind] || 'File', 28) });
        const el = h('button', {
            type: 'button',
            class: 'de-library-item',
            role: 'option',
            title: item.name,
            'aria-selected': selected.has(item.id) ? 'true' : 'false',
            onClick: () => {
                if (!multiple) selected.clear();
                if (selected.has(item.id)) selected.delete(item.id);
                else selected.set(item.id, item);
                grid.querySelectorAll('.de-library-item').forEach((node) => node.setAttribute('aria-selected', selected.has(Number(node.dataset.id)) ? 'true' : 'false'));
                footerNote.textContent = selected.size ? t('selectedCount', { count: selected.size }) : '';
            },
            onDblclick: () => {
                selected.set(item.id, item);
                closeRef?.([...selected.values()]);
            },
        }, [
            h('span', { class: 'de-library-thumb' }, [thumb, item.kind === 'video' && item.duration ? h('span', { class: 'de-library-badge', text: formatDuration(item.duration) }) : null]),
            h('span', { class: 'de-library-name', text: item.name }),
            h('span', { class: 'de-library-meta', text: [item.width && item.height ? `${item.width}×${item.height}` : null, item.size_human || formatBytes(item.size)].filter(Boolean).join(' · ') }),
        ]);
        el.dataset.id = item.id;
        return el;
    };

    const load = async (next = 1) => {
        if (loading) return;
        loading = true;
        status.textContent = t('loading');
        status.hidden = false;
        if (next === 1) grid.replaceChildren(...Array.from({ length: 8 }, () => h('div', { class: 'de-library-skeleton' })));
        try {
            const url = new URL(endpoint, location.href);
            url.searchParams.set('page', next);
            if (filter) url.searchParams.set('kind', filter);
            if (query) url.searchParams.set('q', query);
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            const data = await response.json();
            if (next === 1) grid.replaceChildren();
            (data.data || []).forEach((item) => grid.append(card(item)));
            page = data.meta?.current_page || next;
            lastPage = data.meta?.last_page || 1;
            more.hidden = page >= lastPage;
            status.textContent = grid.childElementCount ? '' : t('libraryEmpty');
            status.hidden = Boolean(grid.childElementCount);
        } catch {
            status.textContent = t('libraryError');
        } finally {
            loading = false;
        }
    };

    search.addEventListener('input', debounce(() => {
        query = search.value.trim();
        load(1);
    }, 250));

    const uploadBtn = h('button', {
        type: 'button',
        class: 'de-button de-button-sm',
        html: `${icon('Upload', 14)}<span>${t('upload')}</span>`,
        onClick: async () => {
            const accept = filter ? ({ image: config.upload?.images, video: config.upload?.videos, audio: config.upload?.audio, file: config.upload?.files }[filter] || []) : [];
            const files = await pickFile(accept.join(','));
            for (const file of files) {
                status.hidden = false;
                try {
                    await uploadFile(desk, file, kindOf(file), (p) => (status.textContent = `${t('uploading')} ${file.name} · ${p}%`));
                } catch (error) {
                    status.textContent = t('uploadFailed', { reason: error.message });
                }
            }
            load(1);
        },
    });

    let closeRef = null;
    const result = openDialog(desk, {
        title: t('mediaLibrary'),
        wide: true,
        className: 'de-dialog-library',
        body: h('div', { class: 'de-stack' }, [
            h('div', { class: 'de-library-toolbar' }, [search, config.upload?.url ? uploadBtn : null]),
            kinds.length > 1 ? tabs : null,
            h('div', { class: 'de-library-scroll' }, [grid, status, more]),
        ]),
        actions: [
            { label: t('cancel'), onClick: (close) => close(null) },
            { label: multiple ? t('insertSelected') : t('insertButton'), primary: true, submit: true, onClick: (close) => close([...selected.values()]) },
        ],
    });
    // openDialog resolves on close; capture the close function for double-click selection.
    requestAnimationFrame(() => {
        const dialog = desk.layer.querySelector('.de-dialog-library');
        dialog?.querySelector('.de-dialog-footer')?.prepend(footerNote);
        closeRef = (value) => {
            const submit = dialog?.querySelector('.de-button-primary');
            selected.clear();
            value.forEach((v) => selected.set(v.id, v));
            submit?.click();
        };
    });
    load(1);

    return result;
}

function formatDuration(seconds) {
    const s = Math.round(seconds);
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** Insert library items into the editor according to their kind. */
export function insertMediaItems(desk, items) {
    const chain = desk.editor.chain().focus();
    for (const item of items) {
        if (item.kind === 'image') chain.insertContent({ type: 'image', attrs: { src: item.url, alt: item.alt || '' } });
        else if (item.kind === 'video') chain.insertContent({ type: 'video', attrs: { src: item.transcoded_url || item.url, poster: item.poster_url || null } });
        else if (item.kind === 'audio') chain.insertContent({ type: 'audio', attrs: { src: item.url, caption: item.name } });
        else chain.insertContent({ type: 'attachment', attrs: { href: item.url, name: item.name, size: item.size_human || formatBytes(item.size), mime: item.mime } });
    }
    chain.run();
}
