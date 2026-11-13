<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Blog\Models;

use Atannex\DeskEditor\Blog\Support\Blog;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Daily view counters for an article.
 *
 * @property int $id
 * @property int $article_id
 * @property Carbon $date
 * @property int $views
 * @property int $visitors
 * @property-read DeskArticle|null $article
 */
class DeskArticleView extends Model
{
    protected $table = 'desk_article_views';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'views' => 'integer',
            'visitors' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DeskArticle, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Blog::model(), 'article_id');
    }
}
