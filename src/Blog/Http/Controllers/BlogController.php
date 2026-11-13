<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Http\Controllers;

use Atannex\DeskEditor\Blog\Models\DeskComment;
use Atannex\DeskEditor\Blog\Support\Blog;
use Atannex\DeskEditor\Blog\Support\RelatedArticles;
use Atannex\DeskEditor\Blog\Support\Seo;
use Atannex\DeskEditor\Blog\Support\ViewRecorder;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskCategory;
use Atannex\DeskEditor\Models\DeskTag;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function home(Request $request): View
    {
        $locale = Blog::locale($request);
        $category = $request->query('category');
        $page = max(1, (int) $request->query('page', 1));

        $featured = null;
        if ($page === 1 && ! $category) {
            $featured = Blog::articles($locale)->where('featured', true)->first() ?? Blog::articles($locale)->first();
        }

        $articles = Blog::articles($locale)
            ->when($featured, fn (Builder $q, DeskArticle $f) => $q->whereKeyNot($f->getKey()))
            ->when(is_string($category) && $category !== '', fn (Builder $q) => $q->whereHas('category', fn (Builder $c) => $c->where('slug', $category)))
            ->paginate(Blog::perPage())
            ->withQueryString();

        $seo = Seo::make(Blog::name(), Blog::description())
            ->canonical(route('desk-editor.blog.home', array_filter(Blog::params() + ['page' => $page > 1 ? $page : null])))
            ->website();
        if ($category) {
            $seo->noindex();
        }

        return view('desk-editor::blog.home', [
            'featured' => $featured,
            'articles' => $articles,
            'categories' => Blog::navCategories($locale),
            'activeCategory' => is_string($category) ? $category : null,
            'seo' => $seo,
        ]);
    }

    public function show(Request $request, string $slug, ViewRecorder $recorder, RelatedArticles $related): View
    {
        $article = $this->resolve($request, $slug);
        $article->loadMissing(['category', 'tags', 'author']);

        $translations = $article->translation_group
            ? Blog::model()::query()->published()->where('translation_group', $article->translation_group)->whereKeyNot($article->getKey())->orderBy('locale')->get()
            : new Collection;

        $siblings = Blog::articles($article->locale)->reorder()->setEagerLoads([]);
        $previous = (clone $siblings)
            ->where(fn (Builder $q) => $q->where('published_at', '<', $article->published_at)
                ->orWhere(fn (Builder $q) => $q->where('published_at', $article->published_at)->where('id', '<', $article->getKey())))
            ->orderByDesc('published_at')->orderByDesc('id')->first();
        $next = (clone $siblings)
            ->where(fn (Builder $q) => $q->where('published_at', '>', $article->published_at)
                ->orWhere(fn (Builder $q) => $q->where('published_at', $article->published_at)->where('id', '>', $article->getKey())))
            ->orderBy('published_at')->orderBy('id')->first();

        $recorder->record($article, $request);

        return view('desk-editor::blog.show', [
            'article' => $article,
            'outline' => array_values(array_filter($article->body?->outline() ?? [], fn (array $h) => $h['id'] && $h['level'] >= 2 && $h['level'] <= 3 && $h['text'] !== '')),
            'translations' => $translations,
            'previous' => $previous,
            'next' => $next,
            'related' => $related->for($article),
            'comments' => $this->comments($article),
            'commentsOpen' => $this->commentsOpen($article),
            'canComment' => $this->commentsOpen($article) && ($request->user() !== null || config('desk-editor.blog.comments.guests', true)),
            'commentToken' => Crypt::encryptString((string) now()->getTimestamp()),
            'seo' => Seo::article($article, $translations),
        ]);
    }

    public function category(Request $request, string $slug): View
    {
        $category = DeskCategory::query()->where('slug', $slug)->firstOrFail();
        $locale = Blog::locale($request);
        $articles = Blog::articles($locale)->where('category_id', $category->getKey())->paginate(Blog::perPage())->withQueryString();
        abort_if($articles->total() === 0 && ! Blog::articles()->where('category_id', $category->getKey())->exists(), 404);

        $seo = Seo::make($category->name, $category->description ?: __('desk-editor::blog.category_description', ['name' => $category->name]))
            ->breadcrumbs([[Blog::name(), route('desk-editor.blog.home')], [$category->name, route('desk-editor.blog.category', ['slug' => $category->slug])]]);

        return $this->listing($articles, $seo, [
            'eyebrow' => __('desk-editor::blog.category'),
            'heading' => $category->name,
            'intro' => $category->description,
            'color' => $category->color,
            'categories' => Blog::navCategories($locale),
            'activeCategory' => $category->slug,
        ]);
    }

    public function tag(Request $request, string $slug): View
    {
        $tag = DeskTag::query()->where('slug', $slug)->firstOrFail();
        $locale = Blog::locale($request);
        $articles = Blog::articles($locale)->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->getKey()))->paginate(Blog::perPage())->withQueryString();
        abort_if($articles->total() === 0 && ! Blog::articles()->whereHas('tags', fn (Builder $q) => $q->whereKey($tag->getKey()))->exists(), 404);

        $seo = Seo::make('#'.$tag->name, __('desk-editor::blog.tag_description', ['name' => $tag->name]))
            ->breadcrumbs([[Blog::name(), route('desk-editor.blog.home')], ['#'.$tag->name, route('desk-editor.blog.tag', ['slug' => $tag->slug])]]);

        return $this->listing($articles, $seo, [
            'eyebrow' => __('desk-editor::blog.tag'),
            'heading' => '#'.$tag->name,
        ]);
    }

    public function author(Request $request, string $id): View
    {
        $locale = Blog::locale($request);
        $base = Blog::articles($locale)->where('author_id', $id);
        $model = config('desk-editor.articles.authors.model') ?: config('auth.providers.users.model');
        if (is_string($model) && is_a($model, Model::class, true)) {
            $base->where('author_type', (new $model)->getMorphClass());
        }

        $articles = (clone $base)->paginate(Blog::perPage())->withQueryString();
        $first = $articles->first() ?? (clone $base)->first();
        abort_if(! $first instanceof DeskArticle, 404);

        $name = Blog::authorName($first) ?? __('desk-editor::blog.comments.anonymous');
        $seo = Seo::make($name, __('desk-editor::blog.author_description', ['name' => $name, 'blog' => Blog::name()]))
            ->canonical(Blog::authorUrl($first));
        $seo->schema(['@type' => 'ProfilePage', 'mainEntity' => ['@type' => 'Person', 'name' => $name]]);

        return $this->listing($articles, $seo, [
            'eyebrow' => __('desk-editor::blog.author'),
            'heading' => $name,
            'intro' => trans_choice('desk-editor::blog.stories', $articles->total(), ['count' => $articles->total()]),
            'avatar' => Blog::initials($name),
        ]);
    }

    public function search(Request $request): View
    {
        $term = Str::limit(trim((string) $request->query('q', '')), 100, '');
        $locale = Blog::locale($request);

        $articles = $term === ''
            ? new LengthAwarePaginator([], 0, Blog::perPage())
            : Blog::articles($locale)->search($term)->paginate(Blog::perPage())->withQueryString();

        $seo = Seo::make($term === '' ? __('desk-editor::blog.search') : __('desk-editor::blog.search_results_for', ['term' => $term]))->noindex();

        return $this->listing($articles, $seo, [
            'eyebrow' => __('desk-editor::blog.search'),
            'heading' => $term === '' ? __('desk-editor::blog.search_heading') : __('desk-editor::blog.search_results_for', ['term' => $term]),
            'intro' => $term === '' ? null : trans_choice('desk-editor::blog.results', $articles->total(), ['count' => $articles->total()]),
            'term' => $term,
            'isSearch' => true,
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, DeskArticle>  $articles
     * @param  array<string, mixed>  $data
     */
    private function listing(LengthAwarePaginator $articles, Seo $seo, array $data): View
    {
        return view('desk-editor::blog.listing', $data + [
            'articles' => $articles,
            'seo' => $seo,
            'term' => null,
            'intro' => null,
            'color' => null,
            'avatar' => null,
            'isSearch' => false,
            'categories' => null,
            'activeCategory' => null,
        ]);
    }

    private function resolve(Request $request, string $slug): DeskArticle
    {
        $candidates = Blog::model()::query()->published()->where('slug', $slug)->get();
        abort_if($candidates->isEmpty(), 404);

        $preferred = array_filter([$request->query('lang'), app()->getLocale(), Blog::defaultLocale()], 'is_string');
        foreach ($preferred as $locale) {
            $match = $candidates->firstWhere('locale', $locale);
            if ($match instanceof DeskArticle) {
                return $match;
            }
        }

        /** @var DeskArticle */
        return $candidates->first();
    }

    private function commentsOpen(DeskArticle $article): bool
    {
        return (bool) config('desk-editor.blog.comments.enabled', true) && $article->allow_comments;
    }

    /**
     * Approved comments as a tree: top-level roots with nested `replies` set.
     *
     * @return Collection<int, DeskComment>
     */
    private function comments(DeskArticle $article): Collection
    {
        if (! config('desk-editor.blog.comments.enabled', true)) {
            return new Collection;
        }

        $all = DeskComment::query()->where('article_id', $article->getKey())->approved()->with('user')->oldest('id')->get();
        $ids = array_flip($all->modelKeys());

        /** @var array<int, list<DeskComment>> $children */
        $children = [];
        foreach ($all as $comment) {
            // Replies whose parent is not visible are promoted to the top level.
            $key = $comment->parent_id && isset($ids[$comment->parent_id]) ? (int) $comment->parent_id : 0;
            $children[$key][] = $comment;
        }

        foreach ($all as $comment) {
            $comment->setRelation('replies', new Collection($children[$comment->id] ?? []));
        }

        $roots = new Collection($children[0] ?? []);

        return $roots;
    }
}
