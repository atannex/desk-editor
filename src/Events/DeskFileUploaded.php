<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Events;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the editor stores an upload, e.g. to sync it to another
 * library or run extra processing. $media is the recorded desk_media row
 * (null when the table has not been migrated).
 */
class DeskFileUploaded
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $file  url, path, disk, name, size, mime, kind, width?, height?, id?, status?, thumb_url, srcset, poster_url
     */
    public function __construct(
        public readonly array $file,
        public readonly ?Authenticatable $user = null,
        public readonly ?DeskMedia $media = null,
    ) {}
}
