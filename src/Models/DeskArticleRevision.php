<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $desk_article_id
 * @property string $title
 * @property string|null $excerpt
 * @property string|null $body
 * @property string $status
 * @property int $word_count
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class DeskArticleRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'desk_article_revisions';

    protected $guarded = ['id'];

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
    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}
