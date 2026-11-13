<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Models;

use Atannex\DeskEditor\Blog\Enums\CommentStatus;
use Atannex\DeskEditor\Blog\Support\Blog;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A reader comment on a published article.
 *
 * @property int $id
 * @property int $article_id
 * @property int|null $parent_id
 * @property int $depth
 * @property string|null $user_type
 * @property int|string|null $user_id
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string $body
 * @property CommentStatus $status
 * @property bool $is_staff
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DeskArticle|null $article
 * @property-read DeskComment|null $parent
 * @property-read Model|null $user
 * @property-read Collection<int, DeskComment> $replies
 */
class DeskComment extends Model
{
    protected $table = 'desk_comments';

    protected $guarded = ['id'];

    protected $hidden = ['guest_email', 'ip_hash', 'user_agent'];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'is_staff' => 'boolean',
            'depth' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DeskArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Blog::model(), 'article_id')->withTrashed();
    }

    /**
     * @return BelongsTo<DeskComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<DeskComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $q) => $q
            ->where('body', 'like', $like)
            ->orWhere('guest_name', 'like', $like)
            ->orWhere('guest_email', 'like', $like));
    }

    public function authorName(): string
    {
        $user = $this->user_type ? $this->user : null;
        $label = (string) config('desk-editor.articles.authors.label', 'name');
        $name = $user instanceof Model ? (string) ($user->getAttribute($label) ?? '') : '';

        return trim($name ?: (string) $this->guest_name) ?: __('desk-editor::blog.comments.anonymous');
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/u', $this->authorName(), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $initials = mb_substr($words[0], 0, 1).(count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '');

        return Str::upper($initials);
    }

    /**
     * Stable avatar hue derived from the author name (no third-party avatars).
     */
    public function hue(): int
    {
        return (int) (crc32(Str::lower($this->authorName())) % 360);
    }

    /**
     * Escaped body with line breaks and auto-linked URLs (nofollow ugc).
     */
    public function renderedBody(): HtmlString
    {
        return new HtmlString(static::format($this->body));
    }

    public static function format(string $text): string
    {
        $escaped = e(trim($text));

        $linked = (string) preg_replace_callback(
            '~\bhttps?://[^\s<>"\']+~i',
            function (array $m): string {
                $url = $m[0];
                $trail = '';
                while ($url !== '' && in_array(substr($url, -1), ['.', ',', ')', '!', '?', ';', ':'], true)) {
                    $trail = substr($url, -1).$trail;
                    $url = substr($url, 0, -1);
                }

                return '<a href="'.$url.'" rel="nofollow ugc noopener" target="_blank">'.Str::limit($url, 60).'</a>'.$trail;
            },
            $escaped,
        );

        $paragraphs = preg_split('/(?:\r?\n){2,}/', $linked) ?: [$linked];

        return implode('', array_map(fn (string $p): string => '<p>'.nl2br(trim($p), false).'</p>', $paragraphs));
    }

    public static function linkCount(string $text): int
    {
        return (int) preg_match_all('~https?://|www\.~i', $text);
    }

    /**
     * Keep desk_articles.comments_count equal to the approved comment count.
     */
    public static function syncCount(int $articleId): void
    {
        $count = static::query()->where('article_id', $articleId)->approved()->count();

        DB::table('desk_articles')->where('id', $articleId)->update(['comments_count' => $count]);
    }
}
