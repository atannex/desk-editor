<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Support;

use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Support\Str;

/**
 * Collects the SEO metadata a blog page prints in <head>: title, description,
 * canonical, Open Graph / Twitter cards, hreflang alternates and JSON-LD.
 */
class Seo
{
    public string $title;

    public string $description;

    public ?string $canonical = null;

    public ?string $image = null;

    public ?string $imageAlt = null;

    public string $type = 'website';

    public ?string $robots = null;

    public ?string $locale = null;

    /** @var array<string, string> property => content (article:* etc.) */
    public array $properties = [];

    /** @var array<string, string> hreflang => url */
    public array $alternates = [];

    /** @var list<array<string, mixed>> */
    public array $schemas = [];

    public function __construct(?string $title = null, ?string $description = null)
    {
        $this->title = $title ?? Blog::name();
        $this->description = Str::limit(trim(preg_replace('/\s+/', ' ', (string) ($description ?? Blog::description())) ?? ''), 300, '…');
        $this->canonical = url()->current();
    }

    public static function make(?string $title = null, ?string $description = null): self
    {
        return new self($title, $description);
    }

    public function documentTitle(): string
    {
        $name = Blog::name();

        return $this->title === $name ? $name : $this->title.' · '.$name;
    }

    public function canonical(?string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function noindex(): self
    {
        $this->robots = 'noindex, follow';

        return $this;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function schema(array $schema): self
    {
        $this->schemas[] = ['@context' => 'https://schema.org'] + $schema;

        return $this;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs  [name, url]
     */
    public function breadcrumbs(array $crumbs): self
    {
        return $this->schema([
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $c, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c[0],
                'item' => $c[1],
            ], $crumbs, array_keys($crumbs)),
        ]);
    }

    public function website(): self
    {
        return $this->schema([
            '@type' => 'WebSite',
            'name' => Blog::name(),
            'description' => Blog::description(),
            'url' => route('desk-editor.blog.home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('desk-editor.blog.search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    /**
     * @param  iterable<DeskArticle>  $translations
     */
    public static function article(DeskArticle $article, iterable $translations = []): self
    {
        $seo = new self($article->meta_title ?: $article->title, $article->meta_description ?: ($article->excerpt ?: $article->subtitle));
        $url = Blog::url($article);
        $seo->canonical = $article->canonical_url ?: $url;
        $seo->type = 'article';
        $seo->image = Blog::absolute($article->cover_image);
        $seo->imageAlt = $article->cover_alt ?: $article->title;
        $seo->locale = $article->locale;

        $published = $article->published_at?->toAtomString();
        $modified = ($article->updated_at ?? $article->published_at)?->toAtomString();
        $seo->properties = array_filter([
            'article:published_time' => $published,
            'article:modified_time' => $modified,
            'article:section' => $article->category?->name,
            'article:author' => Blog::authorName($article),
        ]);
        foreach ($article->tags as $tag) {
            $seo->properties['article:tag:'.$tag->id] = $tag->name;
        }

        $alternates = [$article->locale => $url];
        foreach ($translations as $translation) {
            $alternates[$translation->locale] = Blog::url($translation);
        }
        if (count($alternates) > 1) {
            $seo->alternates = $alternates;
            $default = $alternates[Blog::defaultLocale()] ?? null;
            if ($default) {
                $seo->alternates['x-default'] = $default;
            }
        }

        $author = Blog::authorName($article);
        $seo->schema(array_filter([
            '@type' => 'BlogPosting',
            'headline' => Str::limit($article->title, 110, ''),
            'description' => $seo->description ?: null,
            'image' => $seo->image ? [$seo->image] : null,
            'datePublished' => $published,
            'dateModified' => $modified,
            'inLanguage' => $article->locale,
            'wordCount' => $article->word_count ?: null,
            'articleSection' => $article->category?->name,
            'keywords' => $article->tags->isNotEmpty() ? $article->tags->pluck('name')->implode(', ') : null,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'url' => $url,
            'author' => $author ? array_filter(['@type' => 'Person', 'name' => $author, 'url' => Blog::authorUrl($article)]) : null,
            'publisher' => ['@type' => 'Organization', 'name' => Blog::name(), 'url' => route('desk-editor.blog.home')],
            'commentCount' => $article->comments_count ?: null,
        ], fn ($v) => $v !== null));

        $crumbs = [[Blog::name(), route('desk-editor.blog.home')]];
        if ($article->category) {
            $crumbs[] = [$article->category->name, route('desk-editor.blog.category', Blog::params(['slug' => $article->category->slug]))];
        }
        $crumbs[] = [$article->title, $url];

        return $seo->breadcrumbs($crumbs);
    }

    public function jsonLd(): string
    {
        $json = json_encode(count($this->schemas) === 1 ? $this->schemas[0] : $this->schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

        return $json === false ? '{}' : $json;
    }
}
