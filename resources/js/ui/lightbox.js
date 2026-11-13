/**
 * Full-screen image viewer for reader content: keyboard, swipe, zoom, captions.
 */
let active = null;

export function openLightbox(images, startIndex = 0) {
    active?.close();
    let index = startIndex;
    let zoomed = false;
    let touchX = null;
    const previous = document.activeElement;

    const root = document.createElement('div');
    root.className = 'desk-editor-layer de-lightbox';
    root.dataset.theme = 'dark';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Image viewer');
    root.innerHTML = `
        <div class="de-lightbox-bar">
            <span class="de-lightbox-count" aria-live="polite"></span>
            <div class="de-lightbox-actions">
                <button type="button" data-action="zoom" aria-label="Zoom">⤢</button>
                <a data-action="open" target="_blank" rel="noopener" aria-label="Open original">↗</a>
                <button type="button" data-action="close" aria-label="Close">✕</button>
            </div>
        </div>
        <button type="button" class="de-lightbox-nav de-lightbox-prev" data-action="prev" aria-label="Previous image">‹</button>
        <figure class="de-lightbox-stage"><img alt=""><figcaption></figcaption></figure>
        <button type="button" class="de-lightbox-nav de-lightbox-next" data-action="next" aria-label="Next image">›</button>`;

    const img = root.querySelector('img');
    const caption = root.querySelector('figcaption');
    const count = root.querySelector('.de-lightbox-count');
    const open = root.querySelector('[data-action=open]');

    const show = () => {
        const item = images[index];
        zoomed = false;
        root.classList.remove('is-zoomed');
        img.classList.remove('is-in');
        img.onload = () => img.classList.add('is-in');
        img.src = item.src;
        img.alt = item.alt || '';
        caption.textContent = item.caption || item.alt || '';
        caption.hidden = !caption.textContent;
        count.textContent = images.length > 1 ? `${index + 1} / ${images.length}` : '';
        open.href = item.src;
        root.querySelectorAll('.de-lightbox-nav').forEach((b) => (b.hidden = images.length < 2));
    };
    const go = (dir) => {
        index = (index + dir + images.length) % images.length;
        show();
    };
    const close = () => {
        root.classList.remove('is-open');
        document.removeEventListener('keydown', onKey, true);
        document.documentElement.classList.remove('desk-editor-fullscreen-lock');
        setTimeout(() => root.remove(), 200);
        previous?.focus?.();
        active = null;
    };
    const onKey = (e) => {
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowRight') go(1);
        else if (e.key === 'ArrowLeft') go(-1);
        else return;
        e.preventDefault();
    };

    root.addEventListener('click', (e) => {
        const action = e.target.closest('[data-action]')?.dataset.action;
        if (action === 'close' || e.target === root || e.target.classList.contains('de-lightbox-stage')) close();
        else if (action === 'prev') go(-1);
        else if (action === 'next') go(1);
        else if (action === 'zoom' || e.target === img) {
            zoomed = !zoomed;
            root.classList.toggle('is-zoomed', zoomed);
        }
    });
    root.addEventListener('touchstart', (e) => (touchX = e.touches[0].clientX), { passive: true });
    root.addEventListener('touchend', (e) => {
        if (touchX === null) return;
        const dx = e.changedTouches[0].clientX - touchX;
        if (Math.abs(dx) > 50) go(dx < 0 ? 1 : -1);
        touchX = null;
    });

    document.addEventListener('keydown', onKey, true);
    document.documentElement.classList.add('desk-editor-fullscreen-lock');
    document.body.append(root);
    show();
    requestAnimationFrame(() => root.classList.add('is-open'));
    root.querySelector('[data-action=close]').focus();
    active = { close };
}

/** Make every image inside reader content open in the lightbox. */
export function bindLightbox(scope) {
    scope.querySelectorAll('.desk-content:not(.ProseMirror)').forEach((content) => {
        if (content.dataset.deskLightbox) return;
        content.dataset.deskLightbox = '1';
        content.addEventListener('click', (event) => {
            const img = event.target.closest('img');
            if (!img || img.closest('a') || img.classList.contains('de-bookmark-image') || img.classList.contains('de-bookmark-favicon')) return;
            const all = [...content.querySelectorAll('img')].filter((i) => !i.closest('a') && !i.classList.contains('de-bookmark-favicon') && !i.classList.contains('de-bookmark-image'));
            const items = all.map((i) => ({ src: i.currentSrc || i.src, alt: i.alt, caption: i.closest('figure')?.querySelector('figcaption')?.textContent }));
            openLightbox(items, Math.max(0, all.indexOf(img)));
        });
    });
}
