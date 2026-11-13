<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;

/**
 * Sanitised editor content with reader-friendly helpers.
 *
 * {{ $post->body }} renders it, $post->body->excerpt(160) summarises it,
 * $post->body->outline() builds a table of contents.
 */
final class DeskContent implements Htmlable, JsonSerializable, Stringable
{
    private ?string $text = null;

    public function __construct(private readonly string $html) {}

    public static function make(?string $html): self
    {
        return new self((string) $html);
    }

    public function html(): string
    {
        return $this->html;
    }

    public function toHtml(): string
    {
        return $this->html;
    }

    public function text(): string
    {
        if ($this->text !== null) {
            return $this->text;
        }

        $html = preg_replace('/<(br|\/p|\/h[1-6]|\/li|\/blockquote|\/pre|\/tr|\/figcaption|\/div)\b[^>]*>/i', "$0\n", $this->html) ?? $this->html;
        $html = preg_replace('/<span[^>]*data-latex="([^"]*)"[^>]*><\/span>/i', ' $1 ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\s*\n\s*/", "\n", $text) ?? $text;

        return $this->text = trim($text);
    }

    public function isEmpty(): bool
    {
        return $this->text() === '' && ! preg_match('/<(img|iframe|hr|table)\b|data-(latex|attachment)/i', $this->html);
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function words(): int
    {
        $text = $this->text();

        return $text === '' ? 0 : count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    public function characters(): int
    {
        return mb_strlen(str_replace("\n", '', $this->text()));
    }

    public function readingTime(int $wordsPerMinute = 230): int
    {
        return max(1, (int) ceil($this->words() / max(1, $wordsPerMinute)));
    }

    public function excerpt(int $limit = 160, string $end = '…'): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $this->text()) ?? '', $limit, $end);
    }

    /**
     * Headings in document order: [['level' => 2, 'text' => '…', 'id' => '…'], …]
     *
     * @return list<array{level: int, text: string, id: string|null}>
     */
    public function outline(): array
    {
        preg_match_all('/<h([1-6])([^>]*)>(.*?)<\/h\1>/is', $this->html, $matches, PREG_SET_ORDER);

        return array_map(function (array $m): array {
            preg_match('/\sid="([^"]+)"/', $m[2], $id);

            return [
                'level' => (int) $m[1],
                'text' => trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'id' => $id[1] ?? null,
            ];
        }, $matches);
    }

    /**
     * Every image source referenced by the content.
     *
     * @return list<string>
     */
    public function images(): array
    {
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $this->html, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Mentioned ids, e.g. to notify users: [['id' => '12', 'label' => 'Ada']]
     *
     * @return list<array{id: string, label: string|null}>
     */
    public function mentions(): array
    {
        preg_match_all('/<span[^>]*data-type="mention"[^>]*>/i', $this->html, $tags);

        $mentions = [];
        foreach ($tags[0] as $tag) {
            if (preg_match('/data-id="([^"]+)"/', $tag, $id)) {
                preg_match('/data-label="([^"]+)"/', $tag, $label);
                $mentions[$id[1]] = ['id' => $id[1], 'label' => isset($label[1]) ? html_entity_decode($label[1]) : null];
            }
        }

        return array_values($mentions);
    }

    public function jsonSerialize(): string
    {
        return $this->html;
    }

    public function __toString(): string
    {
        return $this->html;
    }
}
