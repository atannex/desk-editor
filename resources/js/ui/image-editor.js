import { openDialog, toast } from './dialog.js';
import { h } from './dom.js';
import { icon } from './icons.js';
import { uploadFile } from '../upload.js';

const RATIOS = [['free', null], ['1:1', 1], ['4:3', 4 / 3], ['3:2', 3 / 2], ['16:9', 16 / 9], ['3:4', 3 / 4], ['9:16', 9 / 16]];
const PRESETS = {
    none: { brightness: 100, contrast: 100, saturate: 100, grayscale: 0, sepia: 0 },
    vivid: { brightness: 104, contrast: 112, saturate: 135, grayscale: 0, sepia: 0 },
    warm: { brightness: 103, contrast: 104, saturate: 110, grayscale: 0, sepia: 22 },
    mono: { brightness: 102, contrast: 115, saturate: 100, grayscale: 100, sepia: 0 },
    fade: { brightness: 108, contrast: 86, saturate: 80, grayscale: 0, sepia: 6 },
};

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('The image could not be loaded for editing.'));
        img.src = src;
    });
}

/**
 * Crop / rotate / flip / adjust an image, then upload the result and swap it in.
 * @param {object} desk
 * @param {number} pos position of the image node
 */
export async function editImage(desk, pos) {
    const { editor, t } = desk;
    const node = editor.state.doc.nodeAt(pos);
    if (!node || node.type.name !== 'image') return;

    let source;
    try {
        source = await loadImage(node.attrs.src);
    } catch (error) {
        return toast(desk, error.message, 'error');
    }

    const state = { rotate: 0, flipH: false, flipV: false, ratio: null, crop: { x: 0, y: 0, w: 1, h: 1 }, ...structuredClone(PRESETS.none) };
    const stage = h('div', { class: 'de-crop-stage' });
    const canvas = h('canvas', { class: 'de-crop-canvas' });
    const box = h('div', { class: 'de-crop-box' }, ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map((dir) => h('span', { class: `de-crop-handle de-crop-${dir}`, dataset: { dir } })));
    const info = h('span', { class: 'de-crop-info' });
    stage.append(canvas, box);

    const rotatedSize = () => (state.rotate % 180 === 0 ? [source.naturalWidth, source.naturalHeight] : [source.naturalHeight, source.naturalWidth]);
    const filter = () => `brightness(${state.brightness}%) contrast(${state.contrast}%) saturate(${state.saturate}%) grayscale(${state.grayscale}%) sepia(${state.sepia}%)`;

    const paint = (target, sw, sh) => {
        const ctx = target.getContext('2d');
        ctx.save();
        ctx.clearRect(0, 0, target.width, target.height);
        ctx.filter = filter();
        ctx.translate(target.width / 2, target.height / 2);
        ctx.rotate((state.rotate * Math.PI) / 180);
        ctx.scale(state.flipH ? -1 : 1, state.flipV ? -1 : 1);
        const [dw, dh] = state.rotate % 180 === 0 ? [sw, sh] : [sh, sw];
        ctx.drawImage(source, -dw / 2, -dh / 2, dw, dh);
        ctx.restore();
    };

    const draw = () => {
        const [rw, rh] = rotatedSize();
        const maxW = Math.min(stage.clientWidth || 640, 820);
        const maxH = Math.min(420, Math.max(220, window.innerHeight * 0.42));
        const scale = Math.min(maxW / rw, maxH / rh, 1);
        canvas.width = Math.round(rw * scale);
        canvas.height = Math.round(rh * scale);
        paint(canvas, canvas.width, canvas.height);
        placeBox();
    };

    const placeBox = () => {
        const { x, y, w, h: hh } = state.crop;
        Object.assign(box.style, {
            left: `${canvas.offsetLeft + x * canvas.width}px`,
            top: `${canvas.offsetTop + y * canvas.height}px`,
            width: `${w * canvas.width}px`,
            height: `${hh * canvas.height}px`,
        });
        const [rw, rh] = rotatedSize();
        info.textContent = `${Math.round(w * rw)} × ${Math.round(hh * rh)} px`;
    };

    const applyRatio = () => {
        if (!state.ratio) return;
        const [rw, rh] = rotatedSize();
        const target = state.ratio * (rh / rw); // ratio in normalized units
        let { w, h: hh } = state.crop;
        if (w / hh > target) w = hh * target;
        else hh = w / target;
        if (w > 1) { w = 1; hh = w / target; }
        if (hh > 1) { hh = 1; w = hh * target; }
        state.crop = { x: (1 - w) / 2, y: (1 - hh) / 2, w, h: hh };
    };

    // Drag to move / resize the crop box.
    box.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        const dir = event.target.dataset.dir || 'move';
        const start = { x: event.clientX, y: event.clientY, crop: { ...state.crop } };
        const [rw, rh] = rotatedSize();
        const target = state.ratio ? state.ratio * (rh / rw) : null;
        box.setPointerCapture(event.pointerId);
        const move = (e) => {
            const dx = (e.clientX - start.x) / canvas.width;
            const dy = (e.clientY - start.y) / canvas.height;
            let { x, y, w, h: hh } = start.crop;
            if (dir === 'move') {
                x = Math.min(Math.max(0, x + dx), 1 - w);
                y = Math.min(Math.max(0, y + dy), 1 - hh);
            } else {
                if (dir.includes('e')) w = Math.min(1 - x, Math.max(0.05, w + dx));
                if (dir.includes('s')) hh = Math.min(1 - y, Math.max(0.05, hh + dy));
                if (dir.includes('w')) { const nx = Math.min(x + w - 0.05, Math.max(0, x + dx)); w += x - nx; x = nx; }
                if (dir.includes('n')) { const ny = Math.min(y + hh - 0.05, Math.max(0, y + dy)); hh += y - ny; y = ny; }
                if (target) {
                    if (dir === 'n' || dir === 's') w = Math.min(1 - x, hh * target);
                    else hh = Math.min(1 - y, w / target);
                }
            }
            state.crop = { x, y, w, h: hh };
            placeBox();
        };
        const up = () => {
            box.removeEventListener('pointermove', move);
            box.removeEventListener('pointerup', up);
        };
        box.addEventListener('pointermove', move);
        box.addEventListener('pointerup', up);
    });

    const toolButton = (iconName, label, fn) => h('button', { type: 'button', class: 'de-button de-button-sm', title: label, 'aria-label': label, html: icon(iconName, 16), onClick: () => { fn(); draw(); } });
    const ratios = h('div', { class: 'de-tabs de-crop-ratios' }, RATIOS.map(([label, value]) => h('button', {
        type: 'button',
        class: `de-tab ${value === null ? 'is-active' : ''}`,
        text: label === 'free' ? t('free') : label,
        onClick: (e) => {
            state.ratio = value;
            ratios.querySelectorAll('.de-tab').forEach((b) => b.classList.toggle('is-active', b === e.currentTarget));
            if (!value) state.crop = { x: 0, y: 0, w: 1, h: 1 };
            applyRatio();
            placeBox();
        },
    })));

    const slider = (key, label, min, max) => {
        const input = h('input', { type: 'range', min, max, value: state[key], class: 'de-range' });
        const out = h('output', { text: `${state[key]}` });
        input.addEventListener('input', () => {
            state[key] = Number(input.value);
            out.textContent = input.value;
            draw();
        });
        sliders.set(key, { input, out });
        return h('label', { class: 'de-slider' }, [h('span', { text: label }), input, out]);
    };
    const sliders = new Map();
    const presets = h('div', { class: 'de-crop-presets' }, Object.keys(PRESETS).map((name) => h('button', {
        type: 'button',
        class: 'de-preset',
        text: t(`preset_${name}`),
        onClick: () => {
            Object.assign(state, PRESETS[name]);
            sliders.forEach(({ input, out }, key) => { input.value = state[key]; out.textContent = state[key]; });
            draw();
        },
    })));

    const body = h('div', { class: 'de-image-editor' }, [
        h('div', { class: 'de-crop-toolbar' }, [
            ratios,
            h('div', { class: 'de-crop-tools' }, [
                toolButton('RotateCcw', t('rotateLeft'), () => { state.rotate = (state.rotate + 270) % 360; state.crop = { x: 0, y: 0, w: 1, h: 1 }; applyRatio(); }),
                toolButton('RotateCw', t('rotateRight'), () => { state.rotate = (state.rotate + 90) % 360; state.crop = { x: 0, y: 0, w: 1, h: 1 }; applyRatio(); }),
                toolButton('FlipHorizontal2', t('flipHorizontal'), () => { state.flipH = !state.flipH; }),
                toolButton('FlipVertical2', t('flipVertical'), () => { state.flipV = !state.flipV; }),
            ]),
        ]),
        stage,
        h('div', { class: 'de-crop-footer' }, [info]),
        presets,
        h('div', { class: 'de-sliders' }, [
            slider('brightness', t('brightness'), 50, 150),
            slider('contrast', t('contrast'), 50, 150),
            slider('saturate', t('saturation'), 0, 200),
        ]),
    ]);

    requestAnimationFrame(draw);
    window.addEventListener('resize', draw);

    const result = await openDialog(desk, {
        title: t('editImage'),
        wide: true,
        className: 'de-dialog-image-editor',
        body,
        actions: [
            { label: t('cancel'), onClick: (close) => close(null) },
            { label: t('apply'), primary: true, submit: true, onClick: (close) => close(true) },
        ],
    });
    window.removeEventListener('resize', draw);
    if (!result) return editor.commands.focus();

    // Render the crop at full resolution.
    const [rw, rh] = rotatedSize();
    const full = document.createElement('canvas');
    full.width = rw;
    full.height = rh;
    paint(full, source.naturalWidth, source.naturalHeight);
    const out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(state.crop.w * rw));
    out.height = Math.max(1, Math.round(state.crop.h * rh));
    out.getContext('2d').drawImage(full, Math.round(state.crop.x * rw), Math.round(state.crop.y * rh), out.width, out.height, 0, 0, out.width, out.height);

    let blob;
    try {
        blob = await new Promise((resolve, reject) => out.toBlob((b) => (b ? resolve(b) : reject(new Error('export'))), 'image/webp', 0.9));
    } catch {
        return toast(desk, t('imageTainted'), 'error');
    }

    const name = `${(node.attrs.alt || 'image').replace(/[^\w-]+/g, '-').slice(0, 40) || 'image'}-edited.webp`;
    const file = new File([blob], name, { type: 'image/webp' });
    const preview = URL.createObjectURL(blob);
    editor.view.dispatch(editor.state.tr.setNodeAttribute(pos, 'src', preview));
    desk.setBusy(true);
    try {
        const data = await uploadFile(desk, file, 'image', (p) => desk.setStatus(`${t('uploading')} ${p}%`));
        const current = editor.state.doc.nodeAt(pos);
        if (current?.type.name === 'image') editor.view.dispatch(editor.state.tr.setNodeAttribute(pos, 'src', data.url));
        desk.emit('upload', { kind: 'image', file: data });
    } catch (error) {
        editor.view.dispatch(editor.state.tr.setNodeAttribute(pos, 'src', node.attrs.src));
        toast(desk, t('uploadFailed', { reason: error.message }), 'error');
    } finally {
        desk.setBusy(false);
        desk.setStatus('');
        setTimeout(() => URL.revokeObjectURL(preview), 4000);
    }
}
