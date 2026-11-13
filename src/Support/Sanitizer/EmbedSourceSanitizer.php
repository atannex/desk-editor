<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support\Sanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Only allows <iframe src> pointing at an allow-listed video provider over HTTPS.
 */
final class EmbedSourceSanitizer implements AttributeSanitizerInterface
{
    /**
     * @param  list<string>  $hosts
     */
    public function __construct(private readonly array $hosts) {}

    public function getSupportedElements(): array
    {
        return ['iframe'];
    }

    public function getSupportedAttributes(): array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $parts = parse_url(trim($value));

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            return null;
        }

        return in_array(strtolower($parts['host'] ?? ''), $this->hosts, true) ? $value : null;
    }
}
