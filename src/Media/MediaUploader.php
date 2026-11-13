<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Media\Processing\MediaProcessor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores an (already validated) upload under a server-generated name, records
 * it in desk_media and runs processing.
 */
class MediaUploader
{
    public function __construct(private readonly MediaProcessor $processor) {}

    /**
     * The upload's MIME as detected from its content (never the client's claim).
     */
    public static function detectMime(UploadedFile $file): string
    {
        return (string) ($file->getMimeType() ?: 'application/octet-stream');
    }

    /**
     * Store the file and return the editor payload.
     *
     * @param  string  $kind  The kind the editor asked for (image|video|audio|file).
     * @param  array{parent?: DeskMedia|null, purpose?: string|null}  $options
     * @return array{payload: array<string, mixed>, media: DeskMedia|null}
     */
    public function store(UploadedFile $file, string $kind, ?Authenticatable $user, array $options = []): array
    {
        $config = (array) config('desk-editor.uploads');
        $disk = (string) ($config['disk'] ?? 'public');
        $mime = static::detectMime($file);
        $directory = $this->directory((string) ($config['directory'] ?? 'desk-editor'), $kind, $user);

        $original = $this->cleanName($file->getClientOriginalName());
        $extension = strtolower((string) ($file->guessExtension() ?: 'bin'));
        $slug = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'file';
        $filename = Str::limit($slug, 60, '').'-'.Str::lower(Str::random(8)).'.'.$extension;

        $path = $file->storeAs($directory, $filename, ['disk' => $disk, 'visibility' => $config['visibility'] ?? 'public']);

        if ($path === false) {
            throw new RuntimeException('storage');
        }

        $payload = [
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
            'disk' => $disk,
            'name' => $original,
            'size' => $file->getSize(),
            'mime' => $mime,
            'kind' => $kind,
        ];

        if ($kind === 'image' && ($dimensions = @getimagesize((string) $file->getRealPath()))) {
            [$payload['width'], $payload['height']] = [$dimensions[0], $dimensions[1]];
        }

        if (! $this->recording()) {
            return ['payload' => $payload, 'media' => null];
        }

        $parent = $options['parent'] ?? null;
        $purpose = $options['purpose'] ?? null;

        $media = new DeskMedia;
        $media->forceFill([
            'parent_id' => $parent?->id,
            'disk' => $disk,
            'path' => $path,
            'url' => $payload['url'],
            'name' => Str::limit($original, 250, ''),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'kind' => DeskMedia::kindForMime($mime),
            'status' => DeskMedia::STATUS_READY,
            'meta' => $purpose ? ['purpose' => $purpose] : null,
        ]);

        if ($user instanceof Model) {
            $media->owner()->associate($user);
        }

        $media->save();
        $media = $this->processor->handleUpload($media, (string) $file->getRealPath());

        if ($parent && $purpose === 'poster' && $media->kind === 'image') {
            $parent->setMeta(['poster_url' => $media->url, 'poster_media_id' => $media->id])->save();
        }

        $payload = array_merge($payload, [
            'id' => $media->id,
            'status' => $media->status,
            // Processed dimensions are EXIF-oriented (images) or probed (video).
            'width' => $media->width ?? $payload['width'] ?? null,
            'height' => $media->height ?? $payload['height'] ?? null,
            'duration' => $media->duration,
            'thumb_url' => $media->thumbUrl(),
            'srcset' => $media->variantsSrcset(),
            'poster_url' => $media->posterUrl(),
            'transcoded_url' => $media->transcodedUrl(),
        ]);

        $alwaysPresent = ['thumb_url', 'srcset', 'poster_url'];

        return [
            'payload' => array_filter($payload, fn ($value, $key) => $value !== null || in_array($key, $alwaysPresent, true), ARRAY_FILTER_USE_BOTH),
            'media' => $media,
        ];
    }

    /**
     * Whether uploads are recorded (the desk_media table has been migrated).
     */
    public function recording(): bool
    {
        return Schema::hasTable((new DeskMedia)->getTable());
    }

    private function directory(string $pattern, string $kind, ?Authenticatable $user): string
    {
        $user = (string) ($user?->getAuthIdentifier() ?? 'guest');

        $directory = strtr($pattern, [
            '{Y}' => date('Y'),
            '{m}' => date('m'),
            '{d}' => date('d'),
            '{kind}' => $kind,
            '{user}' => preg_replace('/[^A-Za-z0-9_-]/', '', $user) ?: 'guest',
        ]);

        // Never let configuration or identifiers climb out of the disk root.
        $segments = array_filter(explode('/', str_replace('\\', '/', $directory)), fn (string $s) => $s !== '' && $s !== '.' && $s !== '..');

        return implode('/', $segments);
    }

    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        return trim($name) !== '' ? trim($name) : 'file';
    }
}
