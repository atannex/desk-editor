<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Events;

use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a reviewer sends an article back to its writer with a note.
 */
class ArticleChangesRequested
{
    use Dispatchable;

    public function __construct(
        public readonly DeskArticle $article,
        public readonly ?Authenticatable $user = null,
        public readonly ?string $note = null,
    ) {}
}
