import { Node, mergeAttributes } from '@tiptap/core';
import { insertBlock } from './insert-block.js';

const providers = [
    {
        name: 'youtube',
        match: /(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([\w-]{11})/i,
        embed: (m) => `https://www.youtube-nocookie.com/embed/${m[1]}`,
    },
    { name: 'vimeo', match: /vimeo\.com\/(?:video\/)?(\d+)/i, embed: (m) => `https://player.vimeo.com/video/${m[1]}` },
    { name: 'loom', match: /loom\.com\/(?:share|embed)\/([\da-f]+)/i, embed: (m) => `https://www.loom.com/embed/${m[1]}` },
    { name: 'dailymotion', match: /(?:dailymotion\.com\/(?:video|embed\/video)\/|dai\.ly\/)([\da-z]+)/i, embed: (m) => `https://www.dailymotion.com/embed/video/${m[1]}` },
    {
        name: 'spotify',
        match: /open\.spotify\.com\/(?:embed\/)?(track|album|playlist|episode|show|artist)\/([\w]+)/i,
        embed: (m) => `https://open.spotify.com/embed/${m[1]}/${m[2]}`,
        height: (m) => (['track', 'episode'].includes(m[1]) ? 152 : 352),
    },
    {
        name: 'soundcloud',
        match: /(https?:\/\/soundcloud\.com\/[\w-]+\/[\w-]+)/i,
        embed: (m) => `https://w.soundcloud.com/player/?url=${encodeURIComponent(m[1])}&color=%236366f1&visual=true`,
        height: () => 300,
    },
    { name: 'codepen', match: /codepen\.io\/([\w-]+)\/(?:pen|embed)\/(\w+)/i, embed: (m) => `https://codepen.io/${m[1]}/embed/${m[2]}?default-tab=result`, height: () => 420 },
    { name: 'codesandbox', match: /codesandbox\.io\/(?:s|embed|p\/sandbox)\/([\w-]+)/i, embed: (m) => `https://codesandbox.io/embed/${m[1]}`, height: () => 500 },
    {
        name: 'figma',
        match: /(https:\/\/(?:www\.)?figma\.com\/(?:file|design|proto|board)\/[\w-]+[^\s]*)/i,
        embed: (m) => `https://www.figma.com/embed?embed_host=desk&url=${encodeURIComponent(m[1])}`,
        ratio: 1.6,
    },
    {
        name: 'maps',
        match: /(?:google\.[a-z.]+\/maps\/embed\?pb=([^\s"&]+)|maps\.google\.[a-z.]+\/maps\?q=([^\s"&]+)|google\.[a-z.]+\/maps\/(?:place|search)\/([^/\s?]+))/i,
        embed: (m) => (m[1] ? `https://www.google.com/maps/embed?pb=${m[1]}` : `https://maps.google.com/maps?q=${encodeURIComponent(decodeURIComponent((m[2] || m[3]).replace(/\+/g, ' ')))}&output=embed`),
        ratio: 1.6,
    },
    {
        name: 'twitter',
        match: /(?:twitter|x)\.com\/\w+\/status(?:es)?\/(\d+)/i,
        embed: (m) => `https://platform.twitter.com/embed/Tweet.html?id=${m[1]}&dnt=true`,
        height: () => 560,
    },
    { name: 'instagram', match: /instagram\.com\/(?:p|reel|tv)\/([\w-]+)/i, embed: (m) => `https://www.instagram.com/p/${m[1]}/embed`, height: () => 640 },
    { name: 'tiktok', match: /tiktok\.com\/(?:@[\w.-]+\/video|embed\/v2)\/(\d+)/i, embed: (m) => `https://www.tiktok.com/embed/v2/${m[1]}`, height: () => 740 },
];

export function resolveEmbed(url) {
    for (const provider of providers) {
        const match = String(url || '').match(provider.match);
        if (match) {
            return {
                provider: provider.name,
                src: provider.embed(match),
                height: provider.height ? provider.height(match) : null,
                ratio: provider.ratio || null,
            };
        }
    }

    return null;
}

export const Embed = Node.create({
    name: 'embed',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            src: { default: null },
            provider: { default: null },
            height: { default: null },
            ratio: { default: null },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'div[data-embed]',
                getAttrs: (el) => {
                    const resolved = resolveEmbed(el.querySelector('iframe')?.getAttribute('src'));
                    if (!resolved) return false;
                    const fixed = parseInt(el.style.height, 10);
                    return { ...resolved, height: Number.isFinite(fixed) ? fixed : resolved.height };
                },
            },
            {
                tag: 'iframe[src]',
                getAttrs: (el) => resolveEmbed(el.getAttribute('src')) || false,
            },
        ];
    },

    renderHTML({ node }) {
        const { height, ratio } = node.attrs;
        const style = height ? `height: ${parseInt(height, 10)}px` : ratio ? `aspect-ratio: ${Number(ratio)}` : null;

        return [
            'div',
            { 'data-embed': node.attrs.provider, class: `de-embed${height ? ' de-embed-fixed' : ''}`, ...(style ? { style } : {}) },
            [
                'iframe',
                mergeAttributes({
                    src: node.attrs.src,
                    frameborder: '0',
                    loading: 'lazy',
                    allowfullscreen: 'true',
                    allow: 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen',
                    referrerpolicy: 'strict-origin-when-cross-origin',
                }),
            ],
        ];
    },

    addCommands() {
        return {
            setEmbed: (url) => (props) => {
                const resolved = resolveEmbed(url);
                if (!resolved) return false;

                return insertBlock({ type: this.name, attrs: resolved })(props);
            },
        };
    },
});
