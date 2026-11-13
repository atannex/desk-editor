import { Node } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

/**
 * Native <video> / <audio> blocks wrapped in a <figure> with an optional caption.
 */
function mediaNode(kind) {
    return Node.create({
        name: kind,
        group: 'block',
        atom: true,
        draggable: true,

        addAttributes() {
            return {
                src: { default: null, parseHTML: (el) => el.querySelector(kind)?.getAttribute('src') || el.getAttribute('src') },
                caption: { default: null, parseHTML: (el) => el.querySelector('figcaption')?.textContent || null },
                align: { default: 'center', parseHTML: (el) => el.getAttribute('data-align') || 'center' },
                poster: { default: null, parseHTML: (el) => el.querySelector('video')?.getAttribute('poster') || null },
            };
        },

        parseHTML() {
            return [
                { tag: `figure[data-type="${kind}"]` },
                { tag: `${kind}[src]`, getAttrs: (el) => ({ src: el.getAttribute('src'), poster: el.getAttribute('poster') }) },
            ];
        },

        renderHTML({ node }) {
            const media = [kind, {
                src: node.attrs.src,
                controls: 'true',
                preload: 'metadata',
                ...(kind === 'video' ? { playsinline: 'true', poster: node.attrs.poster } : {}),
            }];
            const figure = ['figure', { 'data-type': kind, 'data-align': node.attrs.align, class: `de-media de-media-${kind}` }, media];
            if (node.attrs.caption) figure.push(['figcaption', {}, node.attrs.caption]);

            return figure;
        },

        addNodeView() {
            return ({ node }) => {
                const dom = document.createElement('figure');
                dom.className = `de-media de-media-${kind}`;
                dom.dataset.type = kind;
                const media = document.createElement(kind);
                media.controls = true;
                media.preload = 'metadata';
                if (kind === 'video') media.playsInline = true;
                const caption = document.createElement('figcaption');
                dom.append(media, caption);

                const apply = (n) => {
                    if (media.getAttribute('src') !== n.attrs.src) media.src = n.attrs.src;
                    if (kind === 'video') {
                        if (n.attrs.poster) media.poster = n.attrs.poster;
                        else media.removeAttribute('poster');
                    }
                    dom.dataset.align = n.attrs.align || 'center';
                    dom.classList.toggle('is-uploading', String(n.attrs.src || '').startsWith('blob:'));
                    caption.textContent = n.attrs.caption || '';
                    caption.hidden = !n.attrs.caption;
                };
                apply(node);

                return {
                    dom,
                    update(updated) {
                        if (updated.type.name !== kind) return false;
                        apply(updated);
                        return true;
                    },
                    // Let the native controls receive clicks while editing.
                    stopEvent: (event) => event.target === media && !event.type.startsWith('drag'),
                    ignoreMutation: () => true,
                };
            };
        },

        addCommands() {
            const command = `set${kind[0].toUpperCase()}${kind.slice(1)}`;

            return {
                [command]: (attrs) => insertBlock({ type: this.name, attrs }),
            };
        },
    });
}

export const Video = mediaNode('video');
export const Audio = mediaNode('audio');
