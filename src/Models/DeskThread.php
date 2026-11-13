<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An editorial comment thread anchored to a range of text in a document.
 *
 * @property int $id
 * @property string $document
 * @property string $anchor
 * @property string|null $quote
 * @property string|null $user_type
 * @property int|string|null $user_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 */
class DeskThread extends Model
{
    protected $table = 'desk_threads';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return HasMany<DeskThreadMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DeskThreadMessage::class, 'desk_thread_id')->oldest('id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function resolver(): MorphTo
    {
        return $this->morphTo();
    }
}
