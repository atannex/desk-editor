<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Support;

use Atannex\DeskEditor\Articles\Policies\DeskArticlePolicy;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Primary navigation of the Desk admin shell. Links to optional modules
 * (media, comments, analytics, blog) only appear when their routes exist.
 */
class Navigation
{
    public function __construct(private readonly DeskArticlePolicy $policy) {}

    /**
     * @return list<array{key: string, label: string, url: string, active: bool, icon: string, badge: int|null, external: bool}>
     */
    public function items(): array
    {
        $items = [];

        $add = function (string $key, string $route, string $pattern, string $icon, ?int $badge = null, bool $external = false, array $parameters = []) use (&$items): void {
            if (! Route::has($route)) {
                return;
            }
            $items[] = [
                'key' => $key,
                'label' => __('desk-editor::articles.nav.'.$key),
                'url' => route($route, $parameters),
                'active' => ! $external && request()->routeIs($pattern),
                'icon' => $icon,
                'badge' => $badge,
                'external' => $external,
            ];
        };

        $add('articles', 'desk-editor.articles.index', 'desk-editor.articles.*', 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7ZM14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8');

        if ($this->policy->allows('manage-taxonomy')) {
            $add('categories', 'desk-editor.categories.index', 'desk-editor.categories.*', 'M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z');
            $add('tags', 'desk-editor.tags.index', 'desk-editor.tags.*', 'M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42zM7.5 7.5h.01');
        }

        $add('media', 'desk-editor.media.manage', 'desk-editor.media.*', 'M21 15V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10M3 15l5-5 4 4M16 19h6M19 16v6M9 8h.01');

        if ($this->policy->allows('moderate-comments')) {
            $add('comments', 'desk-editor.comments.index', 'desk-editor.comments.*', 'M7.9 20A9 9 0 1 0 4 16.1L2 22Z', $this->pendingComments());
        }

        $add('analytics', 'desk-editor.analytics', 'desk-editor.analytics*', 'M3 3v16a2 2 0 0 0 2 2h16M7 16l4-8 4 4 4-6');

        return $items;
    }

    /**
     * @return array{label: string, url: string}|null
     */
    public function blog(): ?array
    {
        if (! Route::has('desk-editor.blog.home')) {
            return null;
        }

        return ['label' => __('desk-editor::articles.nav.blog'), 'url' => route('desk-editor.blog.home')];
    }

    public function pendingComments(): ?int
    {
        try {
            if (! Schema::hasTable('desk_comments')) {
                return null;
            }

            $query = Schema::getConnection()->table('desk_comments');

            if (Schema::hasColumn('desk_comments', 'status')) {
                $query->where('status', 'pending');
            } elseif (Schema::hasColumn('desk_comments', 'approved_at')) {
                $query->whereNull('approved_at');
            } else {
                return null;
            }

            if (Schema::hasColumn('desk_comments', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return $query->count() ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
