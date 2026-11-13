<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $color
 * @property int $sort_order
 * @property int|null $articles_count
 * @property-read DeskCategory|null $parent
 * @property-read Collection<int, DeskCategory> $children
 */
class DeskCategory extends Model
{
    protected $table = 'desk_categories';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (DeskCategory $category): void {
            $category->slug = static::uniqueSlug($category->slug ?: $category->name, $category->getKey());
        });
    }

    public static function uniqueSlug(string $value, mixed $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'category';
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public static function findOrCreateByName(?string $name): ?self
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return static::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
            ?? static::query()->create(['name' => $name]);
    }

    /**
     * @return BelongsTo<DeskCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<DeskCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<DeskArticle, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(DeskArticle::modelClass(), 'category_id');
    }

    /**
     * Safe CSS colour for inline styles.
     */
    public function cssColor(): string
    {
        return self::sanitizeColor($this->color) ?? '#a1a1aa';
    }

    public static function sanitizeColor(?string $color): ?string
    {
        $color = trim((string) $color);

        return preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color) ? strtolower($color) : null;
    }

    /**
     * Preset swatches offered by the colour picker.
     *
     * @return list<string>
     */
    public static function swatches(): array
    {
        return ['#6366f1', '#8b5cf6', '#ec4899', '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981', '#14b8a6', '#06b6d4', '#3b82f6', '#64748b'];
    }

    /**
     * Delete the category, leaving its articles uncategorised and lifting its children up.
     */
    public function deleteSafely(): void
    {
        $this->articles()->withoutGlobalScopes()->update(['category_id' => null]);
        static::query()->where('parent_id', $this->getKey())->update(['parent_id' => $this->parent_id]);
        $this->delete();
    }
}
