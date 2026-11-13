<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A user's server-side working copy of an editor (autosave).
 *
 * @property int $id
 * @property string $key
 * @property string $content
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $updated_at
 */
class DeskDraft extends Model
{
    protected $table = 'desk_drafts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
