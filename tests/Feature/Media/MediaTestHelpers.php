<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Tests\Feature\Media;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Atannex\DeskEditor\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

trait MediaTestHelpers
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function media(?User $owner = null, array $attributes = []): DeskMedia
    {
        $path = $attributes['path'] ?? 'desk-editor/2026/01/file-'.uniqid().'.png';
        Storage::disk('public')->put($path, 'x');

        $media = new DeskMedia;
        $media->forceFill(array_merge([
            'disk' => 'public',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'name' => 'photo.png',
            'mime' => 'image/png',
            'size' => 1024,
            'kind' => 'image',
            'width' => 1200,
            'height' => 800,
            'status' => DeskMedia::STATUS_READY,
        ], $attributes));

        if ($owner) {
            $media->owner()->associate($owner);
        }

        $media->save();

        return $media;
    }

    protected function article(string $body = '', ?string $cover = null): int
    {
        return (int) DB::table('desk_articles')->insertGetId([
            'title' => 'Hello world',
            'slug' => 'hello-'.uniqid(),
            'locale' => 'en',
            'body' => $body,
            'cover_image' => $cover,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
