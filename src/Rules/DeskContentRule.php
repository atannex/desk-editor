<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Rules;

use Atannex\DeskEditor\Support\DeskContent;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates editor HTML by its visible content rather than its markup:
 *
 *     'body' => ['required', DeskContentRule::make()->minWords(20)->maxCharacters(10000)]
 */
class DeskContentRule implements ValidationRule
{
    private bool $required = false;

    private ?int $minWords = null;

    private ?int $maxWords = null;

    private ?int $minCharacters = null;

    private ?int $maxCharacters = null;

    private ?int $maxImages = null;

    public static function make(): self
    {
        return new self;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function minWords(int $words): self
    {
        $this->minWords = $words;

        return $this;
    }

    public function maxWords(int $words): self
    {
        $this->maxWords = $words;

        return $this;
    }

    public function minCharacters(int $characters): self
    {
        $this->minCharacters = $characters;

        return $this;
    }

    public function maxCharacters(int $characters): self
    {
        $this->maxCharacters = $characters;

        return $this;
    }

    public function maxImages(int $images): self
    {
        $this->maxImages = $images;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! $value instanceof DeskContent && $value !== null) {
            $fail('desk-editor::validation.invalid')->translate();

            return;
        }

        $content = $value instanceof DeskContent ? $value : DeskContent::make((string) $value);

        if ($content->isEmpty()) {
            if ($this->required) {
                $fail('desk-editor::validation.required')->translate();
            }

            return;
        }

        $words = $content->words();
        $characters = $content->characters();

        if ($this->minWords !== null && $words < $this->minWords) {
            $fail('desk-editor::validation.min_words')->translate(['min' => $this->minWords]);
        }
        if ($this->maxWords !== null && $words > $this->maxWords) {
            $fail('desk-editor::validation.max_words')->translate(['max' => $this->maxWords]);
        }
        if ($this->minCharacters !== null && $characters < $this->minCharacters) {
            $fail('desk-editor::validation.min_characters')->translate(['min' => $this->minCharacters]);
        }
        if ($this->maxCharacters !== null && $characters > $this->maxCharacters) {
            $fail('desk-editor::validation.max_characters')->translate(['max' => $this->maxCharacters]);
        }
        if ($this->maxImages !== null && count($content->images()) > $this->maxImages) {
            $fail('desk-editor::validation.max_images')->translate(['max' => $this->maxImages]);
        }
    }
}
