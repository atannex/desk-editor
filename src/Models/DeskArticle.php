<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Atannex\DeskEditor\Casts\AsDeskContent;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Support\DeskContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $subtitle
 * @property string|null $excerpt
 * @property DeskContent|null $body
 * @property string|null $cover_image
 * @property string|null $cover_alt
 * @property ArticleStatus $status
 * @property Carbon|null $published_at
 * @property bool $featured
 * @property bool $allow_comments
 * @property string $locale
 * @property string|null $translation_group
 * @property int|null $category_id
 * @property int $views_count
 * @property int $comments_count
 * @property-read DeskCategory|null $category
 * @property-read Collection<int, DeskTag> $tags
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property int $word_count
 * @property int $reading_time
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $author_type
 * @property int|string|null $author_id
 * @property string|null $editor_type
 * @property int|string|null $editor_id
 * @property-read Model|null $author
 * @property-read Model|null $editor
 * @property-read Collection<int, DeskArticleActivity> $activities
 * @property-read Collection<int, DeskArticleRevision> $revisions
 */
class DeskArticle extends Model
{
    use SoftDeletes;

    protected $table = 'desk_articles';

    protected $guarded = ['id', 'word_count', 'reading_time'];

    /** Set to false to skip the revision snapshot on the next save. */
    public bool $recordRevision = true;

    public ?string $revisionNote = null;

    protected function casts(): array
    {
        return [
            'body' => AsDeskContent::class,
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
            'featured' => 'boolean',
            'allow_comments' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DeskArticle $article): void {
            $article->title = trim($article->title) ?: __('desk-editor::articles.untitled');
            $article->locale = $article->locale ?: (string) config('desk-editor.articles.default_locale', config('app.locale', 'en'));
            $article->slug = static::uniqueSlug($article->slug ?: $article->title, $article->getKey(), $article->locale);

            $body = $article->body ?? DeskContent::make('');
            $article->word_count = $body->words();
            $article->reading_time = $body->words() ? $body->readingTime() : 0;

            if (! $article->excerpt && $body->isNotEmpty()) {
                $article->excerpt = $body->excerpt(220);
            }

            if ($article->status === ArticleStatus::Published && ! $article->published_at) {
                $article->published_at = now();
            }

            if ($article->status === ArticleStatus::Scheduled && $article->published_at?->isPast()) {
                $article->status = ArticleStatus::Published;
            }
        });

        static::saved(function (DeskArticle $article): void {
            if ($article->recordRevision && ($article->wasRecentlyCreated || $article->wasChanged(['title', 'body', 'excerpt', 'status']))) {
                $article->snapshot($article->revisionNote);
            }
            $article->recordRevision = true;
            $article->revisionNote = null;
        });
    }

    /**
     * The configured article model class.
     *
     * @return class-string<DeskArticle>
     */
    public static function modelClass(): string
    {
        $model = config('desk-editor.articles.model', self::class);

        return is_string($model) && is_a($model, self::class, true) ? $model : self::class;
    }

    public static function uniqueSlug(string $value, mixed $ignoreId = null, ?string $locale = null): string
    {
        $base = Str::slug($value) ?: 'article';
        $base = Str::limit($base, 180, '');
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->when($locale, fn (Builder $q) => $q->where('locale', $locale))->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @return HasMany<DeskArticleRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DeskArticleRevision::class, 'desk_article_id')->latest('id');
    }

    /**
     * @return BelongsTo<DeskCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DeskCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<DeskTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DeskTag::class, 'desk_article_tag', 'desk_article_id', 'desk_tag_id')->orderBy('name');
    }

    /**
     * @return HasMany<DeskArticleActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DeskArticleActivity::class, 'desk_article_id')->latest('id');
    }

    /**
     * Articles sharing this article's translation group (excluding itself).
     *
     * @return HasMany<static, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(static::class, 'translation_group', 'translation_group')->whereKeyNot($this->getKey());
    }

    /**
     * Replace the article's tags from names, creating missing tags.
     *
     * @param  iterable<string>  $names
     */
    public function syncTagNames(iterable $names): void
    {
        $this->tags()->sync(DeskTag::idsFor($names));
        $this->unsetRelation('tags');
    }

    /**
     * @return list<string>
     */
    public function tagNames(): array
    {
        return $this->tags->pluck('name')->values()->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function logActivity(string $type, ?string $description = null, array $properties = []): DeskArticleActivity
    {
        $user = auth()->user();

        /** @var DeskArticleActivity $activity */
        $activity = $this->activities()->create([
            'type' => $type,
            'description' => $description,
            'properties' => $properties ?: null,
            'causer_type' => $user instanceof Model ? $user->getMorphClass() : null,
            'causer_id' => $user instanceof Model ? $user->getKey() : null,
        ]);

        return $activity;
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function editor(): MorphTo
    {
        return $this->morphTo();
    }

    public function snapshot(?string $note = null): DeskArticleRevision
    {
        $user = auth()->user();

        /** @var DeskArticleRevision $revision */
        $revision = $this->revisions()->create([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body?->html(),
            'status' => $this->status->value,
            'word_count' => $this->word_count,
            'note' => $note,
            'author_type' => $user instanceof Model ? $user->getMorphClass() : null,
            'author_id' => $user instanceof Model ? $user->getKey() : null,
        ]);

        $keep = (int) config('desk-editor.articles.revisions.keep', 50);
        $stale = $this->revisions()->skip($keep)->take(PHP_INT_MAX)->pluck('id');
        if ($stale->isNotEmpty()) {
            DeskArticleRevision::query()->whereIn('id', $stale)->delete();
        }

        return $revision;
    }

    public function restoreRevision(DeskArticleRevision $revision): void
    {
        $this->revisionNote = __('desk-editor::articles.restored_from', ['date' => $revision->created_at?->locale(app()->getLocale())->isoFormat('LLL')]);
        $this->fill([
            'title' => $revision->title,
            'excerpt' => $revision->excerpt,
            'body' => DeskContent::make($revision->body),
        ])->save();
    }

    public function isLive(): bool
    {
        return $this->status === ArticleStatus::Published && $this->published_at !== null && $this->published_at->isPast();
    }

    public function publicUrl(): ?string
    {
        $pattern = config('desk-editor.articles.public_url');

        if (is_string($pattern) && $pattern !== '') {
            return str_replace('{slug}', $this->slug, $pattern);
        }

        return Route::has('desk-editor.blog.show') ? route('desk-editor.blog.show', ['slug' => $this->slug]) : null;
    }

    /**
     * Give the article a translation group (shared by all its translations).
     */
    public function ensureTranslationGroup(): string
    {
        if (! $this->translation_group) {
            // Written directly so updated_at (used for conflict detection) is left untouched.
            $group = (string) Str::uuid();
            static::withTrashed()->whereKey($this->getKey())->toBase()->update(['translation_group' => $group]);
            $this->translation_group = $group;
            $this->syncOriginalAttribute('translation_group');
        }

        return $this->translation_group;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAuthoredBy(Builder $query, Model|string|int|null $author, ?string $type = null): void
    {
        if ($author instanceof Model) {
            $query->where('author_type', $author->getMorphClass())->where('author_id', $author->getKey());

            return;
        }

        if ($author === null || $author === '') {
            return;
        }

        $query->where('author_id', $author)->when($type, fn (Builder $q) => $q->where('author_type', $type));
    }

    /**
     * Order by search relevance: exact title, title prefix, title contains, then the rest.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOrderByRelevance(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($term));
        $query->orderByRaw(
            'case when lower(title) = ? then 0 when lower(title) like ? then 1 when lower(title) like ? then 2 when lower(excerpt) like ? then 3 else 4 end',
            [mb_strtolower($term), $escaped.'%', '%'.$escaped.'%', '%'.$escaped.'%'],
        );
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereIn('status', [ArticleStatus::Published->value, ArticleStatus::Scheduled->value])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now()));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeStatus(Builder $query, ArticleStatus|string $status): void
    {
        $query->where('status', $status instanceof ArticleStatus ? $status->value : $status);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $q) => $q
            ->where('title', 'like', $like)
            ->orWhere('excerpt', 'like', $like)
            ->orWhere('slug', 'like', $like)
            ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
            ->orWhereHas('tags', fn (Builder $t) => $t->where('name', 'like', $like)));
    }
}
