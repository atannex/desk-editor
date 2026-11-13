<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $articles_count
 */
class DeskTag extends Model
{
    protected $table = 'desk_tags';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (DeskTag $tag): void {
            $tag->slug = static::uniqueSlug($tag->slug ?: $tag->name, $tag->getKey());
        });
    }

    public static function uniqueSlug(string $value, mixed $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'tag';
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Resolve tag names to ids, creating missing tags (case-insensitive).
     *
     * @param  iterable<string>  $names
     * @return list<int>
     */
    public static function idsFor(iterable $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tag = static::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                ?? static::query()->create(['name' => $name]);
            $ids[] = (int) $tag->getKey();
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return BelongsToMany<DeskArticle, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(DeskArticle::modelClass(), 'desk_article_tag', 'desk_tag_id', 'desk_article_id');
    }

    /**
     * Move every article from the given tags onto this one and delete them.
     *
     * @param  iterable<int|string>  $ids
     */
    public function absorb(iterable $ids): int
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === (int) $this->getKey())->values();
        if ($ids->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($ids): void {
            $articleIds = DB::table('desk_article_tag')->whereIn('desk_tag_id', $ids)->distinct()->pluck('desk_article_id');
            $existing = DB::table('desk_article_tag')->where('desk_tag_id', $this->getKey())->pluck('desk_article_id')->all();

            $rows = $articleIds->reject(fn ($id) => in_array($id, $existing))->map(fn ($id) => ['desk_article_id' => $id, 'desk_tag_id' => $this->getKey()])->values()->all();
            if ($rows !== []) {
                DB::table('desk_article_tag')->insert($rows);
            }

            DB::table('desk_article_tag')->whereIn('desk_tag_id', $ids)->delete();
            static::query()->whereKey($ids)->delete();
        });

        return $ids->count();
    }
}
