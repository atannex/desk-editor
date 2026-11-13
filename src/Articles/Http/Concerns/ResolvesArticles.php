<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Concerns;

use Atannex\DeskEditor\Articles\Policies\DeskArticlePolicy;
use Atannex\DeskEditor\Models\DeskArticle;

trait ResolvesArticles
{
    /**
     * @return class-string<DeskArticle>
     */
    protected function model(): string
    {
        return DeskArticle::modelClass();
    }

    protected function find(DeskArticle|string|int $article, bool $withTrashed = false): DeskArticle
    {
        if ($article instanceof DeskArticle) {
            return $article;
        }

        $query = $this->model()::query();

        return ($withTrashed ? $query->withTrashed() : $query)->findOrFail($article);
    }

    protected function policy(): DeskArticlePolicy
    {
        return app(DeskArticlePolicy::class);
    }

    protected function authorizeAbility(string $ability, ?DeskArticle $article = null): void
    {
        $this->policy()->authorize($ability, $article);
    }

    /**
     * Locales configured for articles: code => label.
     *
     * @return array<string, string>
     */
    protected function locales(): array
    {
        $locales = config('desk-editor.articles.locales', []);
        $default = (string) config('desk-editor.articles.default_locale', config('app.locale', 'en'));
        $locales = is_array($locales) && $locales !== [] ? $locales : [$default => strtoupper($default)];

        $out = [];
        foreach ($locales as $code => $label) {
            $code = is_int($code) ? (string) $label : (string) $code;
            $out[$code] = (string) $label;
        }

        return $out;
    }
}
