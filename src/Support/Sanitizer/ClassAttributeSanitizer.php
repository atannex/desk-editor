<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Restricts class names to the editor's own vocabulary so stored content
 * cannot borrow arbitrary styling from the host application.
 */
final class ClassAttributeSanitizer implements AttributeSanitizerInterface
{
    private const PATTERN = '/^(de-[a-z0-9-]+|language-[a-z0-9+#-]+|hljs(-[a-z_-]+)?|tableWrapper|katex[a-z-]*)$/';

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['class'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $classes = array_filter(
            preg_split('/\s+/', trim($value)) ?: [],
            fn (string $class): bool => (bool) preg_match(self::PATTERN, $class),
        );

        return $classes === [] ? null : implode(' ', array_unique($classes));
    }
}
