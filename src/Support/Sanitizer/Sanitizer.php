<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server-side allow-list sanitiser tuned to the markup Desk Editor produces.
 * Anything the editor cannot create (scripts, event handlers, foreign iframes,
 * arbitrary classes/styles) is removed.
 */
final class Sanitizer
{
    private ?HtmlSanitizer $sanitizer = null;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(private readonly array $options = []) {}

    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $clean = $this->sanitizer()->sanitize($html);

        // Embeds whose source failed validation are removed entirely.
        $clean = preg_replace('#<iframe(?![^>]*\ssrc=)[^>]*>\s*</iframe>#i', '', $clean) ?? $clean;
        $clean = preg_replace('#<div data-embed="[a-z]+" class="de-embed">\s*</div>#i', '', $clean) ?? $clean;

        return trim($clean);
    }

    private function sanitizer(): HtmlSanitizer
    {
        return $this->sanitizer ??= new HtmlSanitizer($this->config());
    }

    private function config(): HtmlSanitizerConfig
    {
        $o = $this->options;
        $global = ['class', 'style', 'dir', 'id', 'data-type', 'data-indent', 'data-toc-id', 'data-clear'];

        $elements = [
            'p' => ['data-clear'],
            'br' => [],
            'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
            'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
            'sub' => [], 'sup' => ['data-content'], 'code' => [], 'kbd' => [],
            'ins' => ['data-suggestion', 'data-author'], 'del' => ['data-suggestion', 'data-author'],
            'mark' => ['data-color'],
            'span' => ['data-latex', 'data-id', 'data-label', 'data-name', 'data-comment'],
            'a' => ['href', 'target', 'rel', 'title', 'download', 'data-attachment', 'data-name', 'data-size', 'data-mime', 'data-variant'],
            'section' => [],
            'blockquote' => [],
            'pre' => [],
            'hr' => [],
            'ul' => [],
            'ol' => ['start', 'type', 'data-start'],
            'li' => ['data-checked'],
            'label' => [],
            'table' => [],
            'colgroup' => [],
            'col' => ['span'],
            'thead' => [], 'tbody' => [], 'tfoot' => [],
            'tr' => [],
            'th' => ['colspan', 'rowspan', 'colwidth', 'data-colwidth', 'scope'],
            'td' => ['colspan', 'rowspan', 'colwidth', 'data-colwidth'],
            'figure' => ['data-align', 'data-columns', 'data-chart'],
            'video' => ['src', 'controls', 'preload', 'playsinline', 'poster', 'width', 'height'],
            'audio' => ['src', 'controls', 'preload'],
            'figcaption' => [],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
            'div' => ['data-callout', 'data-embed', 'data-latex', 'data-layout', 'data-columns', 'data-align', 'data-code', 'data-ratio', 'data-height'],
            'iframe' => ['src', 'frameborder', 'allowfullscreen', 'allow', 'loading', 'referrerpolicy', 'title'],
            'details' => ['open'],
            'summary' => [],
        ];

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes($o['allowed_link_schemes'] ?? ['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks((bool) ($o['allow_relative_links'] ?? true))
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias((bool) ($o['allow_relative_media'] ?? true))
            ->forceHttpsUrls((bool) ($o['force_https_media'] ?? false))
            ->withMaxInputLength((int) ($o['max_input_length'] ?? 2_000_000))
            ->withAttributeSanitizer(new StyleAttributeSanitizer)
            ->withAttributeSanitizer(new ClassAttributeSanitizer)
            ->withAttributeSanitizer(new DataAttributeSanitizer)
            ->withAttributeSanitizer(new EmbedSourceSanitizer(array_map('strtolower', $o['embed_hosts'] ?? [])))
            ->forceAttribute('iframe', 'sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups')
            ->dropElement('script')
            ->dropElement('style');

        foreach ($elements as $element => $attributes) {
            $config = $config->allowElement($element, array_values(array_unique([...$global, ...$attributes])));
        }

        return $config;
    }
}
