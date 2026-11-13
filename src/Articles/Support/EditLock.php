<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Support;

use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;

/**
 * Soft edit lock kept alive by the editor's heartbeat.
 *
 * The lock is advisory: it powers the "Ada is editing" banner and the
 * "Take over" action, while optimistic concurrency (the loaded updated_at)
 * protects against lost updates.
 */
class EditLock
{
    public function __construct(private readonly Repository $cache) {}

    public function seconds(): int
    {
        return max(15, (int) config('desk-editor.articles.lock_seconds', 90));
    }

    /**
     * @return array{user_id: string, name: string, at: int, since: int}|null
     */
    public function holder(DeskArticle $article): ?array
    {
        $lock = $this->cache->get($this->key($article));

        if (! is_array($lock) || ! isset($lock['user_id'], $lock['at']) || (int) $lock['at'] < now()->getTimestamp() - $this->seconds()) {
            return null;
        }

        return [
            'user_id' => (string) $lock['user_id'],
            'name' => (string) ($lock['name'] ?? ''),
            'at' => (int) $lock['at'],
            'since' => (int) ($lock['since'] ?? $lock['at']),
        ];
    }

    /**
     * Holder when it is someone other than $user.
     *
     * @return array{user_id: string, name: string, at: int, since: int}|null
     */
    public function heldByOther(DeskArticle $article, ?Authenticatable $user): ?array
    {
        $holder = $this->holder($article);

        return $holder !== null && ($user === null || $holder['user_id'] !== (string) $user->getAuthIdentifier()) ? $holder : null;
    }

    /**
     * Acquire or refresh the lock. Fails (returns false) when someone else holds it, unless forced.
     */
    public function acquire(DeskArticle $article, Authenticatable $user, bool $force = false): bool
    {
        $holder = $this->holder($article);
        $mine = $holder !== null && $holder['user_id'] === (string) $user->getAuthIdentifier();

        if ($holder !== null && ! $mine && ! $force) {
            return false;
        }

        $now = now()->getTimestamp();
        $this->cache->put($this->key($article), [
            'user_id' => (string) $user->getAuthIdentifier(),
            'name' => self::nameOf($user),
            'at' => $now,
            'since' => $mine ? $holder['since'] : $now,
        ], $this->seconds());

        return true;
    }

    public function release(DeskArticle $article, Authenticatable $user): void
    {
        $holder = $this->holder($article);
        if ($holder !== null && $holder['user_id'] === (string) $user->getAuthIdentifier()) {
            $this->cache->forget($this->key($article));
        }
    }

    /**
     * Shape returned to the browser.
     *
     * @param  array{user_id: string, name: string, at: int, since: int}|null  $holder
     * @return array{user_id: string, name: string, since: string, since_human: string}|null
     */
    public static function present(?array $holder): ?array
    {
        if ($holder === null) {
            return null;
        }

        $since = Carbon::createFromTimestamp($holder['since']);

        return [
            'user_id' => $holder['user_id'],
            'name' => $holder['name'],
            'since' => $since->toIso8601String(),
            'since_human' => $since->diffForHumans(),
        ];
    }

    public static function nameOf(Authenticatable $user): string
    {
        $label = (string) config('desk-editor.articles.authors.label', 'name');
        $value = data_get($user, $label) ?? data_get($user, 'name') ?? data_get($user, 'email');

        return is_scalar($value) && (string) $value !== '' ? (string) $value : __('desk-editor::articles.someone');
    }

    private function key(DeskArticle $article): string
    {
        return 'desk-editor:article-lock:'.$article->getKey();
    }
}
