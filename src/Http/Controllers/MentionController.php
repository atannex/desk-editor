<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Atannex\DeskEditor\Contracts\MentionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MentionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $resolver = config('desk-editor.mentions.resolver');
        abort_unless(is_string($resolver) && is_subclass_of($resolver, MentionResolver::class), 404);

        $query = mb_substr(trim((string) $request->query('q', '')), 0, 64);

        /** @var MentionResolver $instance */
        $instance = app($resolver);
        $items = collect($instance->search($query, $request))
            ->take(8)
            ->map(fn (array $item) => [
                'id' => (string) $item['id'],
                'label' => (string) $item['label'],
                'description' => $item['description'] ?? null,
                'avatar' => $item['avatar'] ?? null,
            ])
            ->values();

        return response()->json(['data' => $items]);
    }
}
