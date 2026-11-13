<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $desk_thread_id
 * @property string $body
 * @property string|null $user_type
 * @property int|string|null $user_id
 * @property Carbon|null $created_at
 */
class DeskThreadMessage extends Model
{
    protected $table = 'desk_thread_messages';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<DeskThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(DeskThread::class, 'desk_thread_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo();
    }
}
