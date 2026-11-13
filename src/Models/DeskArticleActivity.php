<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Timeline entry for an article (created, submitted, approved, published, …).
 *
 * @property int $id
 * @property int $desk_article_id
 * @property string $type
 * @property string|null $description
 * @property array<string, mixed>|null $properties
 * @property string|null $causer_type
 * @property int|string|null $causer_id
 * @property Carbon|null $created_at
 * @property-read Model|null $causer
 */
class DeskArticleActivity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'desk_article_activities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /**
     * @return BelongsTo<DeskArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(DeskArticle::class, 'desk_article_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        $key = 'desk-editor::articles.activity.'.$this->type;
        $label = __($key);

        return $label === $key ? ucfirst(str_replace('_', ' ', $this->type)) : (string) $label;
    }

    /**
     * Tailwind classes for the timeline dot.
     */
    public function tone(): string
    {
        return match ($this->type) {
            'published', 'approved' => 'bg-emerald-500 text-white',
            'submitted' => 'bg-amber-500 text-white',
            'changes_requested' => 'bg-rose-500 text-white',
            'scheduled' => 'bg-sky-500 text-white',
            'archived', 'unpublished' => 'bg-zinc-500 text-white',
            'translated' => 'bg-violet-500 text-white',
            'revision_restored', 'restored' => 'bg-indigo-500 text-white',
            default => 'bg-zinc-200 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-200',
        };
    }

    /**
     * SVG path for the timeline icon.
     */
    public function icon(): string
    {
        return match ($this->type) {
            'created' => 'M12 5v14M5 12h14',
            'published', 'approved' => 'M20 6 9 17l-5-5',
            'submitted' => 'M22 2 11 13M22 2l-7 20-4-9-9-4Z',
            'changes_requested' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
            'scheduled' => 'M12 6v6l4 2M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z',
            'archived' => 'M21 8v13H3V8M1 3h22v5H1zM10 12h4',
            'unpublished' => 'M9.9 4.2A10.7 10.7 0 0 1 12 4c4.5 0 8.4 2.8 10 7a11 11 0 0 1-1.6 2.8M6.6 6.6A11 11 0 0 0 2 11c1.6 4.2 5.5 7 10 7a10.7 10.7 0 0 0 5.4-1.4M2 2l20 20',
            'translated' => 'm5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6',
            'revision_restored', 'restored' => 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8M3 3v5h5',
            default => 'M12 20h9M16.4 3.6a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z',
        };
    }
}
