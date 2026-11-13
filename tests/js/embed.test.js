import { describe, expect, it } from 'vitest';
import { resolveEmbed } from '../../resources/js/extensions/embed.js';
import { formatBytes, safeUrl } from '../../resources/js/ui/dom.js';

describe('embeds', () => {
    it.each([
        ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
        ['https://youtu.be/dQw4w9WgXcQ', 'youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
        ['https://youtube.com/shorts/dQw4w9WgXcQ', 'youtube', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
        ['https://vimeo.com/76979871', 'vimeo', 'https://player.vimeo.com/video/76979871'],
        ['https://www.loom.com/share/abc123def', 'loom', 'https://www.loom.com/embed/abc123def'],
        ['https://open.spotify.com/album/1DFixLWuPkv3KT3TnV35m3', 'spotify', 'https://open.spotify.com/embed/album/1DFixLWuPkv3KT3TnV35m3'],
        ['https://x.com/jack/status/20', 'twitter', 'https://platform.twitter.com/embed/Tweet.html?id=20&dnt=true'],
        ['https://www.instagram.com/p/C1abcDEF/', 'instagram', 'https://www.instagram.com/p/C1abcDEF/embed'],
        ['https://codepen.io/team/pen/xyz', 'codepen', 'https://codepen.io/team/embed/xyz?default-tab=result'],
    ])('resolves %s', (url, provider, src) => {
        expect(resolveEmbed(url)).toMatchObject({ provider, src });
    });

    it('gives audio players a fixed height', () => {
        expect(resolveEmbed('https://open.spotify.com/track/abc').height).toBe(152);
    });

    it('rejects unknown hosts', () => {
        expect(resolveEmbed('https://evil.example/watch?v=dQw4w9WgXcQ&youtube.com')).toBeNull();
        expect(resolveEmbed('')).toBeNull();
    });
});

describe('url helpers', () => {
    it('blocks script URLs and normalises bare domains', () => {
        expect(safeUrl('javascript:alert(1)')).toBe('');
        expect(safeUrl(' JaVa\nScRiPt:alert(1)')).toBe('');
        expect(safeUrl('data:text/html,<b>')).toBe('');
        expect(safeUrl('example.com/page')).toBe('https://example.com/page');
        expect(safeUrl('/relative')).toBe('/relative');
        expect(safeUrl('mailto:a@b.co')).toBe('mailto:a@b.co');
    });

    it('formats byte sizes', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(1536)).toBe('1.5 KB');
        expect(formatBytes(5 * 1048576)).toBe('5.0 MB');
    });
});
