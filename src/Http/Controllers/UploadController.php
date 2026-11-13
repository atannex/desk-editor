<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Atannex\DeskEditor\Events\DeskFileUploaded;
use Atannex\DeskEditor\Media\MediaUploader;
use Atannex\DeskEditor\Media\Models\DeskMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Receives editor uploads. MIME types are checked against the file's real
 * content; storage, recording and processing live in MediaUploader.
 */
class UploadController extends Controller
{
    public function __invoke(Request $request, MediaUploader $uploader): JsonResponse
    {
        $config = (array) config('desk-editor.uploads');
        $kind = in_array($request->input('kind'), ['image', 'video', 'audio', 'file'], true) ? (string) $request->input('kind') : 'file';

        $mimes = match ($kind) {
            'image' => (array) ($config['images'] ?? []),
            'video' => (array) ($config['videos'] ?? []),
            'audio' => (array) ($config['audio'] ?? []),
            default => array_merge((array) ($config['files'] ?? []), (array) ($config['images'] ?? [])),
        };
        $maxSize = (int) (($kind === 'video' || $kind === 'audio') ? ($config['max_media_size'] ?? $config['max_size'] ?? 10240) : ($config['max_size'] ?? 10240));

        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxSize, 'mimetypes:'.implode(',', $mimes)],
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'in:poster'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $purpose = $request->filled('purpose') ? (string) $request->input('purpose') : null;
        $parent = $this->parent($request, $purpose, $file, $uploader);

        try {
            ['payload' => $payload, 'media' => $media] = $uploader->store($file, $kind, $request->user(), [
                'parent' => $parent,
                'purpose' => $purpose,
            ]);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['message' => __('desk-editor::editor.uploadFailed', ['reason' => 'storage'])], 500);
        }

        event(new DeskFileUploaded($payload, $request->user(), $media));

        return response()->json($payload, 201);
    }

    /**
     * Resolve and authorise the media item an upload is attached to.
     */
    private function parent(Request $request, ?string $purpose, UploadedFile $file, MediaUploader $uploader): ?DeskMedia
    {
        if (! $request->filled('parent_id')) {
            if ($purpose !== null) {
                throw ValidationException::withMessages(['parent_id' => __('validation.required', ['attribute' => 'parent id'])]);
            }

            return null;
        }

        $parent = $uploader->recording() ? DeskMedia::query()->find((int) $request->input('parent_id')) : null;

        if ($parent === null) {
            throw ValidationException::withMessages(['parent_id' => __('validation.exists', ['attribute' => 'parent id'])]);
        }

        abort_unless($parent->isManageableBy($request->user()), 403);

        if ($purpose === 'poster' && (! in_array($parent->kind, ['video', 'audio'], true) || DeskMedia::kindForMime(MediaUploader::detectMime($file)) !== 'image')) {
            throw ValidationException::withMessages(['purpose' => __('desk-editor::media.poster_invalid')]);
        }

        return $parent;
    }
}
