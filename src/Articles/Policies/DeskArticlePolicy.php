<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Policies;

use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Single source of truth for article-manager permissions.
 *
 * Every ability maps to a "desk-articles.{ability}" gate. The package defines
 * permissive defaults (any signed-in user) only for gates the host app has not
 * defined itself, so restricting access is a matter of defining the gate:
 *
 *     Gate::define('desk-articles.publish', fn ($user) => $user->isEditor());
 *
 * The gate receives the article (when there is one) as its second argument.
 */
class DeskArticlePolicy
{
    public const PREFIX = 'desk-articles.';

    public const DEFAULT_ABILITIES = ['view', 'create', 'update', 'publish', 'review', 'delete', 'manage-taxonomy', 'moderate-comments'];

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        $configured = config('desk-editor.articles.abilities', self::DEFAULT_ABILITIES);

        return array_values(array_unique(array_map('strval', is_array($configured) ? $configured : self::DEFAULT_ABILITIES)));
    }

    public static function gate(string $ability): string
    {
        return self::PREFIX.$ability;
    }

    /**
     * Define the permissive default gates the host app has not defined.
     */
    public function defineDefaultGates(): void
    {
        foreach (array_unique([...$this->abilities(), ...self::DEFAULT_ABILITIES]) as $ability) {
            if (! Gate::has(self::gate($ability))) {
                Gate::define(self::gate($ability), fn ($user = null, mixed $article = null): bool => $user !== null);
            }
        }
    }

    public function allows(string $ability, ?DeskArticle $article = null, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();
        if ($user === null) {
            return false;
        }

        $gate = Gate::forUser($user);
        $name = self::gate($ability);

        if (! $gate->has($name)) {
            return true;
        }

        return $gate->allows($name, $article ? [$article] : []);
    }

    public function denies(string $ability, ?DeskArticle $article = null, ?Authenticatable $user = null): bool
    {
        return ! $this->allows($ability, $article, $user);
    }

    public function authorize(string $ability, ?DeskArticle $article = null, ?Authenticatable $user = null): void
    {
        if ($this->denies($ability, $article, $user)) {
            abort(403, __('desk-editor::articles.forbidden'));
        }
    }

    /**
     * Whether the user must go through review instead of publishing directly.
     */
    public function needsReview(?DeskArticle $article = null, ?Authenticatable $user = null): bool
    {
        if ($this->denies('publish', $article, $user)) {
            return true;
        }

        return (bool) config('desk-editor.articles.require_review', false) && $this->denies('review', $article, $user);
    }

    /**
     * Statuses the user may save the article with.
     *
     * @return list<ArticleStatus>
     */
    public function allowedStatuses(?DeskArticle $article = null, ?Authenticatable $user = null): array
    {
        if (! $this->needsReview($article, $user)) {
            return ArticleStatus::cases();
        }

        $allowed = [ArticleStatus::Draft, ArticleStatus::Review];

        // Keeping the current status is not a transition (e.g. fixing a typo in a live article).
        if ($article?->exists && ! in_array($article->status, $allowed, true)) {
            $allowed[] = $article->status;
        }

        return $allowed;
    }

    public function canAssignAuthor(?DeskArticle $article = null, ?Authenticatable $user = null): bool
    {
        return $this->allows('review', $article, $user) || $this->allows('publish', $article, $user);
    }

    /**
     * Map of ability => bool for the current user (handy in views).
     *
     * @return array<string, bool>
     */
    public function map(?DeskArticle $article = null, ?Authenticatable $user = null): array
    {
        $map = [];
        foreach ($this->abilities() as $ability) {
            $map[$ability] = $this->allows($ability, $article, $user);
        }

        return $map;
    }
}
