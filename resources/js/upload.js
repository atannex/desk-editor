import { formatBytes } from './ui/dom.js';
import { toast } from './ui/dialog.js';

/**
 * Uploads a file to the Laravel endpoint with progress reporting.
 * Resolves with the JSON payload: { url, name, size, mime, width, height }.
 */
export function uploadFile(desk, file, kind = 'file', onProgress = () => {}) {
    const { upload } = desk.config;

    return new Promise((resolve, reject) => {
        if (!upload?.url) return reject(new Error('Uploads are not configured.'));

        const isMedia = kind === 'video' || kind === 'audio';
        const maxBytes = ((isMedia ? upload.maxMediaSize : upload.maxSize) || 0) * 1024;
        if (maxBytes && file.size > maxBytes) return reject(new Error(desk.t('fileTooLarge', { size: formatBytes(maxBytes) })));

        const accept = { image: upload.images, video: upload.videos, audio: upload.audio }[kind] || [...(upload.files || []), ...(upload.images || [])];
        if (accept?.length && !accept.some((type) => matchesMime(file, type))) {
            return reject(new Error(desk.t('fileTypeNotAllowed')));
        }

        const body = new FormData();
        body.append('file', file);
        body.append('kind', kind);
        if (upload.context) body.append('context', upload.context);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', upload.url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const token = desk.config.csrf || document.querySelector('meta[name="csrf-token"]')?.content;
        if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token);
        for (const [name, value] of Object.entries(upload.headers || {})) xhr.setRequestHeader(name, value);

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100));
        });
        xhr.addEventListener('load', () => {
            let data = null;
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                // fall through
            }
            if (xhr.status >= 200 && xhr.status < 300 && data?.url) return resolve(data);
            const reason = data?.errors?.file?.[0] || data?.message || `HTTP ${xhr.status}`;
            reject(new Error(reason));
        });
        xhr.addEventListener('error', () => reject(new Error('Network error')));
        xhr.send(body);
    });
}

function matchesMime(file, type) {
    if (type.startsWith('.')) return file.name.toLowerCase().endsWith(type.toLowerCase());
    if (type.endsWith('/*')) return file.type.startsWith(type.slice(0, -1));

    return file.type === type;
}

export function kindOf(file) {
    if (file.type.startsWith('image/')) return 'image';
    if (file.type.startsWith('video/')) return 'video';
    if (file.type.startsWith('audio/')) return 'audio';

    return 'file';
}

/**
 * Inserts files into the document. Images, video and audio appear instantly
 * (from a local blob URL) and are swapped for the uploaded URL on completion.
 */
export async function insertFiles(desk, files, pos = null, extraAttrs = {}) {
    const { editor } = desk;

    for (const file of files) {
        const kind = kindOf(file);
        const progress = (p) => desk.setStatus(`${desk.t('uploading')} ${file.name} · ${p}%`);

        if (kind !== 'file') {
            const blobUrl = URL.createObjectURL(file);
            const attrs = { src: blobUrl, ...(kind === 'image' ? { alt: file.name.replace(/\.[^.]+$/, '') } : {}), ...extraAttrs };
            const chain = editor.chain().focus();
            if (pos !== null) chain.insertContentAt(pos, { type: kind, attrs });
            else chain.insertContent({ type: kind, attrs });
            chain.run();

            desk.setBusy(true);
            try {
                const data = await uploadFile(desk, file, kind, progress);
                swapSource(editor, kind, blobUrl, data.url);
                desk.emit('upload', { kind, file: data });
            } catch (error) {
                removeBySource(editor, kind, blobUrl);
                toast(desk, desk.t('uploadFailed', { reason: error.message }), 'error');
            } finally {
                desk.setBusy(false);
                desk.setStatus('');
                setTimeout(() => URL.revokeObjectURL(blobUrl), 5000);
            }
            continue;
        }

        desk.setBusy(true);
        try {
            const data = await uploadFile(desk, file, 'file', progress);
            const attrs = { href: data.url, name: data.name || file.name, size: formatBytes(data.size ?? file.size), mime: data.mime || file.type };
            if (pos !== null) editor.chain().focus().insertContentAt(pos, { type: 'attachment', attrs }).run();
            else editor.chain().focus().setAttachment(attrs).run();
            desk.emit('upload', { kind, file: data });
        } catch (error) {
            toast(desk, desk.t('uploadFailed', { reason: error.message }), 'error');
        } finally {
            desk.setBusy(false);
            desk.setStatus('');
        }
    }
}

function findBySource(editor, type, src) {
    let found = null;
    editor.state.doc.descendants((node, pos) => {
        if (found !== null) return false;
        if (node.type.name === type && node.attrs.src === src) found = pos;
    });

    return found;
}

function swapSource(editor, type, blobUrl, url) {
    const pos = findBySource(editor, type, blobUrl);
    if (pos === null) return;
    editor.view.dispatch(editor.state.tr.setNodeAttribute(pos, 'src', url).setMeta('addToHistory', false));
}

function removeBySource(editor, type, blobUrl) {
    const pos = findBySource(editor, type, blobUrl);
    if (pos === null) return;
    const node = editor.state.doc.nodeAt(pos);
    editor.view.dispatch(editor.state.tr.delete(pos, pos + node.nodeSize));
}

export function pickFile(accept, multiple = true) {
    return new Promise((resolve) => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = accept || '';
        input.multiple = multiple;
        input.addEventListener('change', () => resolve([...input.files]));
        input.click();
    });
}
