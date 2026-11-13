<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Http\Controllers;

use Atannex\DeskEditor\Media\MediaReferences;
use Atannex\DeskEditor\Media\Models\DeskMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * JSON media library used by the editor's media picker and the admin page.
 */
class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureEnabled();

        $validated = $request->validate([
            'kind' => ['nullable', 'string', 'in:'.implode(',', DeskMedia::KINDS)],
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? config('desk-editor.media.per_page', 40));
        $page = static::query($request, $validated['kind'] ?? null, $validated['q'] ?? null)
            ->paginate(max(1, min(100, $perPage)));

        return response()->json([
            'data' => collect($page->items())->map(fn (DeskMedia $media) => $media->toEditorArray())->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    public function show(Request $request, MediaReferences $references, int $media): JsonResponse
    {
        $item = $this->find($request, $media);

        return response()->json([...$item->toEditorArray(), 'usage' => $references->usage($item)]);
    }

    public function update(Request $request, int $media): JsonResponse
    {
        $item = $this->find($request, $media);

        $validated = $request->validate([
            'alt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'name' => ['sometimes', 'required', 'string', 'max:250'],
        ]);

        if (array_key_exists('alt', $validated)) {
            $item->alt = filled($validated['alt']) ? trim((string) $validated['alt']) : null;
        }

        if (array_key_exists('name', $validated)) {
            $item->name = trim(str_replace(['/', '\\'], '-', (string) $validated['name']));
        }

        $item->save();

        return response()->json($item->toEditorArray());
    }

    public function destroy(Request $request, int $media): Response
    {
        $this->find($request, $media)->delete();

        return response()->noContent();
    }

    /**
     * Library query shared with the admin page: visible to the user, filtered
     * by kind and search, newest first. Derivative children are hidden.
     *
     * @return Builder<DeskMedia>
     */
    public static function query(Request $request, ?string $kind = null, ?string $search = null): Builder
    {
        $query = DeskMedia::query()
            ->visibleTo($request->user())
            ->whereNull('parent_id')
            ->latest('id');

        if ($kind !== null && in_array($kind, DeskMedia::KINDS, true)) {
            $query->where('kind', $kind);
        }

        if (($search = trim((string) $search)) !== '') {
            $like = '%'.addcslashes(mb_substr($search, 0, 200), '!%_').'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("alt LIKE ? ESCAPE '!'", [$like]);
            });
        }

        return $query;
    }

    private function find(Request $request, int $id): DeskMedia
    {
        $this->ensureEnabled();

        $media = DeskMedia::query()->find($id);
        abort_if($media === null, 404);

        abort_unless($media->isManageableBy($request->user()), 403);

        return $media;
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('desk-editor.media.library', true), 404);
    }
}
