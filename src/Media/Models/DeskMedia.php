<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A file uploaded through the editor plus its generated derivatives
 * (responsive variants, video poster, MP4 transcode).
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $disk
 * @property string $path
 * @property string $url
 * @property string $name
 * @property string|null $mime
 * @property int $size
 * @property string $kind image|video|audio|file
 * @property int|null $width
 * @property int|null $height
 * @property float|null $duration
 * @property string|null $alt
 * @property string $status processing|ready|failed
 * @property array<string, mixed>|null $meta
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DeskMedia|null $parent
 * @property-read Collection<int, DeskMedia> $children
 */
class DeskMedia extends Model
{
    public const KINDS = ['image', 'video', 'audio', 'file'];

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'desk_media';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration' => 'float',
            'parent_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (DeskMedia $media): void {
            // Derivatives linked to this item (e.g. a custom video poster) go with it.
            $media->children()->get()->each(fn (DeskMedia $child) => $child->delete());

            // A deleted custom poster falls back to the generated one.
            if ($media->parent_id && ($parent = $media->parent) && (int) $parent->metaValue('poster_media_id') === $media->id) {
                $parent->setMeta([
                    'poster_media_id' => null,
                    'poster_url' => $parent->metaValue('generated_poster_url'),
                ])->save();
            }
        });

        static::deleted(fn (DeskMedia $media) => $media->deleteFiles());
    }

    /** @return BelongsTo<DeskMedia, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<DeskMedia, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return MorphTo<Model, $this> */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Limit a query to media the user may see: everything for media managers,
     * otherwise only their own uploads.
     *
     * @param  Builder<DeskMedia>  $query
     */
    public function scopeVisibleTo(Builder $query, ?Authenticatable $user): void
    {
        if ($user !== null && Gate::forUser($user)->allows('manage-media')) {
            return;
        }

        if ($user instanceof Model) {
            $query->where('owner_type', $user->getMorphClass())->where('owner_id', $user->getAuthIdentifier());

            return;
        }

        $query->whereRaw('1 = 0');
    }

    public function isOwnedBy(?Authenticatable $user): bool
    {
        return $user instanceof Model
            && $this->owner_type === $user->getMorphClass()
            && (string) $this->owner_id === (string) $user->getAuthIdentifier();
    }

    /**
     * Owners manage their own media; "manage-media" grants access to all of it.
     */
    public function isManageableBy(?Authenticatable $user): bool
    {
        return $user !== null && ($this->isOwnedBy($user) || Gate::forUser($user)->allows('manage-media'));
    }

    /**
     * Read one key from the meta column.
     */
    public function metaValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta ?? [], $key, $default);
    }

    /**
     * Merge values into the meta column (null removes a key).
     *
     * @param  array<string, mixed>  $values
     */
    public function setMeta(array $values): static
    {
        $meta = $this->meta ?? [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                unset($meta[$key]);
            } else {
                $meta[$key] = $value;
            }
        }

        $this->meta = $meta;

        return $this;
    }

    /**
     * Record a processing problem without losing earlier ones.
     */
    public function addError(string $message): static
    {
        $errors = (array) $this->metaValue('errors', []);
        $errors[] = mb_substr($message, 0, 500);

        return $this->setMeta(['errors' => array_values(array_slice($errors, -10))]);
    }

    /**
     * Generated responsive variants, smallest first.
     *
     * @return list<array{width: int, height: int|null, path: string, url: string}>
     */
    public function variants(): array
    {
        $variants = [];

        foreach ((array) $this->metaValue('variants', []) as $variant) {
            if (is_array($variant) && isset($variant['width'], $variant['path'], $variant['url'])) {
                $variants[] = [
                    'width' => (int) $variant['width'],
                    'height' => isset($variant['height']) ? (int) $variant['height'] : null,
                    'path' => (string) $variant['path'],
                    'url' => (string) $variant['url'],
                ];
            }
        }

        usort($variants, fn (array $a, array $b) => $a['width'] <=> $b['width']);

        return $variants;
    }

    /**
     * The srcset for responsive images (variants plus the original), or null.
     */
    public function variantsSrcset(): ?string
    {
        $variants = $this->variants();

        if ($variants === []) {
            return null;
        }

        $candidates = array_map(fn (array $v) => $v['url'].' '.$v['width'].'w', $variants);

        if ($this->width) {
            $candidates[] = $this->url.' '.$this->width.'w';
        }

        return implode(', ', $candidates);
    }

    public function thumbUrl(): ?string
    {
        return match ($this->kind) {
            'image' => $this->variants()[0]['url'] ?? $this->url,
            'video' => $this->posterUrl(),
            default => null,
        };
    }

    public function posterUrl(): ?string
    {
        $url = $this->metaValue('poster_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function transcodedUrl(): ?string
    {
        $url = $this->metaValue('transcoded_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * Every stored file belonging to this item (original + derivatives).
     *
     * @return list<string>
     */
    public function storedPaths(): array
    {
        $paths = [$this->path];

        foreach ($this->variants() as $variant) {
            $paths[] = $variant['path'];
        }

        foreach (['poster_path', 'transcoded_path'] as $key) {
            if (is_string($value = $this->metaValue($key)) && $value !== '') {
                $paths[] = $value;
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * Remove the original and every derivative from the disk.
     */
    public function deleteFiles(): void
    {
        try {
            $this->filesystem()->delete($this->storedPaths());
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function filesystem(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    /**
     * A string identifying this file inside stored content: the path without
     * its extension, which also matches variants, posters and transcodes.
     */
    public function referenceNeedle(): string
    {
        $dot = strrpos($this->path, '.');
        $slash = strrpos($this->path, '/');

        return $dot !== false && ($slash === false || $dot > $slash) ? substr($this->path, 0, $dot) : $this->path;
    }

    public static function humanSize(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $bytes : rtrim(rtrim(number_format($bytes, 1, '.', ''), '0'), '.')).' '.$units[$i];
    }

    public static function kindForMime(?string $mime): string
    {
        return match (true) {
            str_starts_with((string) $mime, 'image/') => 'image',
            str_starts_with((string) $mime, 'video/') => 'video',
            str_starts_with((string) $mime, 'audio/') => 'audio',
            default => 'file',
        };
    }

    /**
     * The JSON shape consumed by the editor's media picker and the admin page.
     *
     * @return array<string, mixed>
     */
    public function toEditorArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'name' => $this->name,
            'mime' => $this->mime,
            'kind' => $this->kind,
            'size' => (int) $this->size,
            'size_human' => static::humanSize((int) $this->size),
            'width' => $this->width,
            'height' => $this->height,
            'duration' => $this->duration !== null ? round($this->duration, 2) : null,
            'alt' => $this->alt,
            'status' => $this->status,
            'thumb_url' => $this->thumbUrl(),
            'srcset' => $this->variantsSrcset(),
            'poster_url' => $this->posterUrl(),
            'transcoded_url' => $this->transcodedUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
