<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Support;

use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Shared helpers for the public blog: configuration, locale resolution,
 * the published-article query and URL builders.
 */
class Blog
{
    /**
     * @return class-string<DeskArticle>
     */
    public static function model(): string
    {
        $model = config('desk-editor.articles.model', DeskArticle::class);

        return is_string($model) && is_a($model, DeskArticle::class, true) ? $model : DeskArticle::class;
    }

    /**
     * Published articles, newest first, with the relations every listing needs.
     *
     * @return Builder<DeskArticle>
     */
    public static function articles(?string $locale = null): Builder
    {
        $model = static::model();

        return $model::query()
            ->published()
            ->when($locale, fn (Builder $q, string $l) => $q->where('locale', $l))
            ->with(['category', 'tags', 'author'])
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * Categories with at least one published article (for navigation).
     *
     * @return Collection<int, DeskCategory>
     */
    public static function navCategories(?string $locale = null): Collection
    {
        $key = 'desk-blog.nav.'.$locale;
        $cached = request()->attributes->get($key);
        if ($cached instanceof Collection) {
            return $cached;
        }

        $categories = DeskCategory::query()
            ->whereHas('articles', static::publishedConstraint($locale))
            ->withCount(['articles as published_count' => static::publishedConstraint($locale)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        request()->attributes->set($key, $categories);

        return $categories;
    }

    /**
     * Relation constraint limiting related articles to published ones.
     *
     * @return Closure(Builder<DeskArticle>): void
     */
    public static function publishedConstraint(?string $locale = null): Closure
    {
        return function (Builder $query) use ($locale): void {
            /** @var Builder<DeskArticle> $query */
            $query->published()->when($locale, fn (Builder $q, string $l) => $q->where('locale', $l));
        };
    }

    public static function name(): string
    {
        return (string) config('desk-editor.blog.name', config('app.name', 'Blog'));
    }

    public static function description(): string
    {
        return (string) config('desk-editor.blog.description', '');
    }

    public static function perPage(): int
    {
        return max(1, (int) config('desk-editor.blog.per_page', 12));
    }

    /**
     * CSS colour for the accent (any valid CSS colour), defaulting to indigo.
     */
    public static function accent(): ?string
    {
        $accent = config('desk-editor.blog.accent');

        return is_string($accent) && preg_match('/^[#a-zA-Z0-9(),.%\s-]{1,60}$/', $accent) ? $accent : null;
    }

    /**
     * @return array<string, string> locale => label
     */
    public static function locales(): array
    {
        $locales = (array) config('desk-editor.articles.locales', []);
        $out = [];
        foreach ($locales as $key => $label) {
            $code = is_int($key) ? (string) $label : (string) $key;
            $out[$code] = is_int($key) ? Str::upper($code) : (string) $label;
        }

        return $out ?: [static::defaultLocale() => Str::upper(static::defaultLocale())];
    }

    public static function defaultLocale(): string
    {
        return (string) config('desk-editor.articles.default_locale', config('app.locale', 'en'));
    }

    public static function multilingual(): bool
    {
        return count(static::locales()) > 1;
    }

    /**
     * The locale listings are filtered by: `?lang=` when valid, otherwise the
     * app locale (or default locale) on multilingual sites; null = no filter.
     */
    public static function locale(Request $request): ?string
    {
        $lang = $request->query('lang');
        if (is_string($lang) && array_key_exists($lang, static::locales())) {
            return $lang;
        }

        if (! static::multilingual()) {
            return null;
        }

        $app = app()->getLocale();

        return array_key_exists($app, static::locales()) ? $app : static::defaultLocale();
    }

    public static function localeLabel(string $locale): string
    {
        return static::locales()[$locale] ?? Str::upper($locale);
    }

    /**
     * Route parameters that keep the active `?lang=` across links.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function params(array $params = [], ?string $locale = null): array
    {
        $locale ??= request()->query('lang');
        if (is_string($locale) && static::multilingual() && $locale !== static::defaultLocale() && array_key_exists($locale, static::locales())) {
            $params['lang'] = $locale;
        }

        return $params;
    }

    public static function url(DeskArticle $article): string
    {
        return route('desk-editor.blog.show', static::params(['slug' => $article->slug], $article->locale));
    }

    public static function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://', '//']) ? $url : url($url);
    }

    public static function authorName(DeskArticle $article): ?string
    {
        $author = $article->author_type ? $article->author : null;
        if (! $author instanceof Model) {
            return null;
        }

        $label = (string) config('desk-editor.articles.authors.label', 'name');
        $name = trim((string) ($author->getAttribute($label) ?? ''));

        return $name !== '' ? $name : null;
    }

    public static function authorUrl(DeskArticle $article): ?string
    {
        $name = static::authorName($article);
        if ($name === null || $article->author_id === null) {
            return null;
        }

        return route('desk-editor.blog.author', static::params(['id' => $article->author_id, 'name' => Str::slug($name) ?: null]));
    }

    /**
     * A category colour safe to print inside a style attribute (hex or named colour).
     */
    public static function color(?string $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$/', $color) === 1 ? $color : null;
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];

        return Str::upper(mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr((string) end($words), 0, 1) : ''));
    }

    /**
     * Wrap occurrences of the search terms in <mark> (input is escaped first).
     */
    public static function highlight(?string $text, ?string $term): string
    {
        $escaped = e((string) $text);
        $words = array_filter(preg_split('/\s+/u', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) ?: [], fn (string $w) => mb_strlen($w) > 1);
        if ($words === []) {
            return $escaped;
        }

        $pattern = '/('.implode('|', array_map(fn (string $w) => preg_quote(e($w), '/'), $words)).')/iu';

        return (string) preg_replace($pattern, '<mark class="rounded-sm bg-amber-200/70 px-0.5 text-inherit dark:bg-amber-400/30">$1</mark>', $escaped);
    }
}
