<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Atannex\DeskEditor\Models\DeskDraft;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Per-user server autosave so a working copy follows the writer across devices.
 */
class DraftController extends Controller
{
    public function show(Request $request, string $key): JsonResponse
    {
        $draft = $this->query($request, $key)->first();

        return response()->json(['draft' => $draft ? [
            'content' => $draft->content,
            'meta' => $draft->meta,
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ] : null]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'content' => ['present', 'nullable', 'string', 'max:'.(int) config('desk-editor.sanitizer.max_input_length', 2_000_000)],
            'meta' => ['nullable', 'array'],
        ]);

        /** @var Model $user */
        $user = $request->user();
        $draft = DeskDraft::query()->updateOrCreate(
            ['key' => $key, 'user_type' => $user->getMorphClass(), 'user_id' => $user->getKey()],
            ['content' => (string) ($data['content'] ?? ''), 'meta' => $data['meta'] ?? null],
        );

        return response()->json(['saved_at' => $draft->updated_at?->toIso8601String()]);
    }

    public function destroy(Request $request, string $key): Response
    {
        $this->query($request, $key)->delete();

        return response()->noContent();
    }

    /**
     * @return Builder<DeskDraft>
     */
    private function query(Request $request, string $key): Builder
    {
        /** @var Model $user */
        $user = $request->user();

        return DeskDraft::query()
            ->where('key', $key)
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey());
    }
}
