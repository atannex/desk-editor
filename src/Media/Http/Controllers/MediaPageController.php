<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Media\Http\Controllers;

use Atannex\DeskEditor\Media\Models\DeskMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The admin media library page (/desk/media).
 */
class MediaPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless((bool) config('desk-editor.media.library', true), 404);

        $kind = in_array($request->query('kind'), DeskMedia::KINDS, true) ? (string) $request->query('kind') : null;
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 200);

        $media = MediaController::query($request, $kind, $search)
            ->paginate(max(1, min(100, (int) config('desk-editor.media.per_page', 40))))
            ->withQueryString();

        $counts = MediaController::query($request, null, $search)
            ->reorder()
            ->selectRaw('kind, count(*) as aggregate')
            ->groupBy('kind')
            ->pluck('aggregate', 'kind')
            ->map(fn ($count) => (int) $count)
            ->all();

        $totals = DeskMedia::query()->visibleTo($request->user())->whereNull('parent_id')
            ->selectRaw('count(*) as items, coalesce(sum(size), 0) as bytes')
            ->first();

        return view('desk-editor::media.index', [
            'media' => $media,
            'kind' => $kind,
            'search' => $search,
            'counts' => $counts,
            'total' => array_sum($counts),
            'storage' => DeskMedia::humanSize((int) ($totals?->getAttribute('bytes') ?? 0)),
            'items' => collect($media->items())->mapWithKeys(fn (DeskMedia $item) => [$item->id => $item->toEditorArray()])->all(),
        ]);
    }
}
