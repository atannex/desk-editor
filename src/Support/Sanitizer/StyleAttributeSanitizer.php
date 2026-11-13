<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Keeps only the inline CSS declarations the editor itself writes
 * (colours, fonts, alignment, line height, image width) and rejects
 * anything that could load resources or break out of the content box.
 */
final class StyleAttributeSanitizer implements AttributeSanitizerInterface
{
    /** @var array<string, string> property => validation pattern */
    private const PROPERTIES = [
        'color' => self::COLOR,
        'background-color' => self::COLOR,
        'text-align' => '/^(left|right|center|justify|start|end)$/',
        'font-family' => '/^[\w\s,"\'-]{1,120}$/',
        'font-size' => '/^\d{1,3}(\.\d{1,2})?(px|em|rem|pt|%)$/',
        'line-height' => '/^\d{1,2}(\.\d{1,2})?(px|em|rem|%)?$/',
        'width' => '/^\d{1,4}(\.\d{1,2})?(px|%)$/',
        'min-width' => '/^\d{1,4}(\.\d{1,2})?(px|%)$/',
        'height' => '/^\d{2,4}px$/',
        'aspect-ratio' => '/^\d{1,4}(\.\d{1,4})?(\s*\/\s*\d{1,4}(\.\d{1,4})?)?$/',
        'vertical-align' => '/^(top|middle|bottom)$/',
    ];

    private const COLOR = '/^(#[0-9a-f]{3,8}|rgba?\(\s*[\d.\s,%\/]+\)|hsla?\(\s*[\d.\s,%\/deg]+\)|oklch\(\s*[\d.\s%\/]+\)|[a-z]{3,20}|inherit|transparent|currentcolor)$/i';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return self::clean($value);
    }

    public static function clean(string $value): ?string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);

        if (preg_match('/(url\s*\(|expression\s*\(|@import|javascript:|\\\\|\/\*|<|>)/i', $value)) {
            return null;
        }

        $kept = [];

        foreach (explode(';', $value) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $propertyValue] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $propertyValue = preg_replace('/\s*!important$/i', '', $propertyValue) ?? '';

            $pattern = self::PROPERTIES[$property] ?? null;

            if ($pattern !== null && $propertyValue !== '' && preg_match($pattern, $propertyValue)) {
                $kept[$property] = "{$property}: {$propertyValue}";
            }
        }

        return $kept === [] ? null : implode('; ', $kept).';';
    }
}
