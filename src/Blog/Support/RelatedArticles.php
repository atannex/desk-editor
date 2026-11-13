<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Support;

use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds articles related to a given one: shared tags first (ranked by overlap),
 * then the same category, then the most recent posts in the same locale.
 */
class RelatedArticles
{
    /**
     * @return Collection<int, DeskArticle>
     */
    public function for(DeskArticle $article, int $limit = 3): Collection
    {
        $exclude = [$article->getKey()];
        $results = new Collection;
        $tagIds = $article->tags->modelKeys();

        if ($tagIds !== []) {
            $ranked = Blog::articles($article->locale)
                ->whereKeyNot($exclude)
                ->whereHas('tags', fn (Builder $q) => $q->whereIn('desk_tags.id', $tagIds))
                ->withCount(['tags as shared_tags' => fn (Builder $q) => $q->whereIn('desk_tags.id', $tagIds)])
                ->reorder()
                ->orderByDesc('shared_tags')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();
            $results = $results->merge($ranked);
            $exclude = array_merge($exclude, $ranked->modelKeys());
        }

        if ($results->count() < $limit && $article->category_id) {
            $same = Blog::articles($article->locale)
                ->whereKeyNot($exclude)
                ->where('category_id', $article->category_id)
                ->limit($limit - $results->count())
                ->get();
            $results = $results->merge($same);
            $exclude = array_merge($exclude, $same->modelKeys());
        }

        if ($results->count() < $limit) {
            $results = $results->merge(Blog::articles($article->locale)->whereKeyNot($exclude)->limit($limit - $results->count())->get());
        }

        return $results->values();
    }
}
