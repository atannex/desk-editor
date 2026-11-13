<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Validates the editor's data-* attributes against their expected shapes.
 */
final class DataAttributeSanitizer implements AttributeSanitizerInterface
{
    /** @var array<string, string> */
    public const PATTERNS = [
        'data-type' => '/^(taskList|taskItem|image|video|audio|columns|column|gallery|button|page-break|diagram|chart|bookmark|footnote|details|detailsSummary|detailsContent|inline-math|block-math|mention|emoji)$/',
        'data-checked' => '/^(true|false)$/',
        'data-callout' => '/^(info|tip|success|warning|danger|note)$/',
        'data-align' => '/^(left|center|right|full)$/',
        'data-indent' => '/^[0-8]$/',
        'data-embed' => '/^(youtube|vimeo|loom|dailymotion|spotify|soundcloud|codepen|codesandbox|figma|maps|twitter|instagram|tiktok)$/',
        'data-layout' => '/^(1-1|2-1|1-2|1-1-1|1-2-1|1-1-1-1)$/',
        'data-columns' => '/^[1-6]$/',
        'data-variant' => '/^(primary|secondary|outline|ghost)$/',
        'data-clear' => '/^(true)$/',
        'data-code' => '/^.{0,20000}$/s',
        'data-chart' => '/^\{.{0,20000}\}$/s',
        'data-content' => '/^.{0,2000}$/s',
        'data-suggestion' => '/^[\w-]{1,64}$/',
        'data-author' => '/^[^<>]{0,120}$/',
        'data-comment' => '/^[\w-]{1,64}$/',
        'data-ratio' => '/^\d{1,3}(\.\d{1,3})?$/',
        'data-height' => '/^\d{2,4}$/',
        'data-attachment' => '/^[A-Za-z0-9]{0,5}$/',
        'data-name' => '/^[^<>]{1,255}$/',
        'data-size' => '/^[\d.,]+\s?[KMGT]?B$/',
        'data-mime' => '/^[\w.+-]+\/[\w.+-]+$/',
        'data-latex' => '/^[^<>]{0,5000}$/s',
        'data-id' => '/^[\w.:@-]{1,128}$/',
        'data-label' => '/^[^<>]{1,255}$/',
        'data-color' => '/^#[0-9a-fA-F]{3,8}$/',
        'data-toc-id' => '/^[\w-]{1,64}$/',
        'data-start' => '/^\d{1,6}$/',
        'data-colwidth' => '/^\d{1,4}(,\d{1,4})*$/',
    ];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return array_keys(self::PATTERNS);
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $pattern = self::PATTERNS[$attribute] ?? null;

        return $pattern !== null && preg_match($pattern, $value) ? $value : null;
    }
}
