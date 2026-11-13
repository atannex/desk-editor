<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Upgrades rendered content with what the media library knows:
 *
 *  - <img>: srcset + sizes from the responsive variants, and width/height
 *    (when missing) so the browser reserves space and avoids layout shift;
 *  - <video>: a poster when missing, and the MP4/H.264 transcode as source.
 *
 * Runs on already-sanitised HTML at render time; all media for a document is
 * loaded in a single query.
 */
class ContentEnricher
{
    public const SIZES = '(min-width: 768px) 768px, 100vw';

    private const ROOT_ID = 'desk-enricher-root';

    private ?bool $tableExists = null;

    public function enrich(string $html): string
    {
        if ($html === '' || ! preg_match('/<(img|video|source)\b/i', $html)) {
            return $html;
        }

        try {
            return $this->transform($html);
        } catch (Throwable $e) {
            report($e);

            return $html;
        }
    }

    private function transform(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="'.self::ROOT_ID.'">'.$html.'</div></body></html>',
                LIBXML_NONET | LIBXML_COMPACT,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementById(self::ROOT_ID);

        if (! $loaded || $root === null) {
            return $html;
        }

        /** @var list<DOMElement> $images */
        $images = iterator_to_array($root->getElementsByTagName('img'), false);
        /** @var list<DOMElement> $videos */
        $videos = iterator_to_array($root->getElementsByTagName('video'), false);

        $urls = [];

        foreach ($images as $img) {
            $urls[] = $img->getAttribute('src');
        }

        foreach ($videos as $video) {
            $urls[] = $video->getAttribute('src');

            foreach ($this->sources($video) as $source) {
                $urls[] = $source->getAttribute('src');
            }
        }

        $media = $this->lookup($urls);

        if ($media === []) {
            return $html;
        }

        $changed = false;

        foreach ($images as $img) {
            $changed = $this->enrichImage($img, $media) || $changed;
        }

        foreach ($videos as $video) {
            $changed = $this->enrichVideo($video, $media) || $changed;
        }

        return $changed ? $this->innerHtml($root) : $html;
    }

    /**
     * @param  array<string, DeskMedia>  $media
     */
    private function enrichImage(DOMElement $img, array $media): bool
    {
        $item = $this->match($img->getAttribute('src'), $media);

        if ($item === null || $item->kind !== 'image') {
            return false;
        }

        $changed = false;

        if (! $img->hasAttribute('srcset') && ($srcset = $item->variantsSrcset())) {
            $img->setAttribute('srcset', $srcset);
            $changed = true;

            if (! $img->hasAttribute('sizes')) {
                $img->setAttribute('sizes', self::SIZES);
            }
        }

        if ($item->width && $item->height && ! $img->hasAttribute('width') && ! $img->hasAttribute('height')) {
            $img->setAttribute('width', (string) $item->width);
            $img->setAttribute('height', (string) $item->height);
            $changed = true;
        }

        if (! $img->hasAttribute('alt') && filled($item->alt)) {
            $img->setAttribute('alt', (string) $item->alt);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @param  array<string, DeskMedia>  $media
     */
    private function enrichVideo(DOMElement $video, array $media): bool
    {
        $changed = false;
        $item = $this->match($video->getAttribute('src'), $media);

        if ($item === null) {
            foreach ($this->sources($video) as $source) {
                if (($item = $this->match($source->getAttribute('src'), $media)) !== null) {
                    if ($transcoded = $item->transcodedUrl()) {
                        $source->setAttribute('src', $transcoded);
                        $source->setAttribute('type', 'video/mp4');
                        $changed = true;
                    }

                    break;
                }
            }
        } elseif ($transcoded = $item->transcodedUrl()) {
            $video->setAttribute('src', $transcoded);
            $changed = true;
        }

        if ($item === null || $item->kind !== 'video') {
            return $changed;
        }

        if (! $video->hasAttribute('poster') && ($poster = $item->posterUrl())) {
            $video->setAttribute('poster', $poster);
            $changed = true;
        }

        if ($item->width && $item->height && ! $video->hasAttribute('width') && ! $video->hasAttribute('height')) {
            $video->setAttribute('width', (string) $item->width);
            $video->setAttribute('height', (string) $item->height);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @return list<DOMElement>
     */
    private function sources(DOMElement $video): array
    {
        $sources = [];

        foreach ($video->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'source') {
                $sources[] = $child;
            }
        }

        return $sources;
    }

    /**
     * Ready media keyed by URL and by URL path (so absolute and root-relative
     * references both match).
     *
     * @param  list<string>  $urls
     * @return array<string, DeskMedia>
     */
    private function lookup(array $urls): array
    {
        $urls = array_values(array_unique(array_filter(array_map('trim', $urls), fn (string $u) => $u !== '' && ! str_starts_with($u, 'data:'))));

        if ($urls === [] || ! ($this->tableExists ??= Schema::hasTable((new DeskMedia)->getTable()))) {
            return [];
        }

        $candidates = $urls;
        $paths = [];

        foreach ($urls as $url) {
            $path = $this->urlPath($url);

            if ($path !== null) {
                $paths[] = ltrim($path, '/');

                // Common public-disk layout: /storage/{path}.
                if (str_starts_with($path, '/storage/')) {
                    $paths[] = substr($path, 9);
                }
            }
        }

        /** @var Collection<int, DeskMedia> $items */
        $items = DeskMedia::query()
            ->where('status', DeskMedia::STATUS_READY)
            ->where(fn ($q) => $q->whereIn('url', $candidates)->orWhereIn('path', array_values(array_unique($paths))))
            ->limit(500)
            ->get();

        $map = [];

        foreach ($items as $item) {
            $map[$item->url] = $item;

            if (($path = $this->urlPath($item->url)) !== null) {
                $map['path:'.$path] ??= $item;
            }

            $map['disk:'.$item->path] ??= $item;
        }

        return $map;
    }

    /**
     * @param  array<string, DeskMedia>  $media
     */
    private function match(string $url, array $media): ?DeskMedia
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (isset($media[$url])) {
            return $media[$url];
        }

        $path = $this->urlPath($url);

        if ($path === null) {
            return null;
        }

        return $media['path:'.$path]
            ?? $media['disk:'.ltrim($path, '/')]
            ?? (str_starts_with($path, '/storage/') ? ($media['disk:'.substr($path, 9)] ?? null) : null);
    }

    private function urlPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rawurldecode($path) : null;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child);
        }

        return $html;
    }
}
