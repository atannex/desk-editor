<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Prepares stored editor HTML for readers: removes editorial-only markup
 * (comment anchors, pending tracked-change suggestions) and lets the media
 * module enrich images/videos (srcset, posters, transcodes) when installed.
 */
class ContentRenderer
{
    public function render(string $html): string
    {
        if ($html === '' || ! preg_match('/data-comment|data-suggestion/', $html)) {
            return $this->enrich($html);
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="desk-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);

        // Pending insertions were never accepted: readers see the original text.
        foreach (iterator_to_array($xpath->query('//ins[@data-suggestion]') ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
        // Pending deletions still belong to the original text: unwrap them.
        foreach (iterator_to_array($xpath->query('//del[@data-suggestion] | //span[@data-comment]') ?: []) as $node) {
            $this->unwrap($node);
        }

        $root = $dom->getElementById('desk-root');
        $out = '';
        foreach ($root ? iterator_to_array($root->childNodes) : [] as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $this->enrich($out);
    }

    private function unwrap(\DOMNode $node): void
    {
        if (! $node instanceof DOMElement || ! $node->parentNode) {
            return;
        }
        while ($node->firstChild) {
            $node->parentNode->insertBefore($node->firstChild, $node);
        }
        $node->parentNode->removeChild($node);
    }

    private function enrich(string $html): string
    {
        $enricher = 'Atannex\\DeskEditor\\Media\\ContentEnricher';

        if ($html !== '' && class_exists($enricher)) {
            return (string) app($enricher)->enrich($html);
        }

        return $html;
    }
}
