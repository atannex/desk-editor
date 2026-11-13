<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Models\DeskTag;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    use ResolvesArticles;

    private const SORTS = ['name', 'articles_count', 'created_at'];

    public function index(Request $request): View
    {
        $this->authorizeAbility('manage-taxonomy');

        $term = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'articles_count';

        $tags = DeskTag::query()
            ->withCount('articles')
            ->when($term !== '', fn (Builder $q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%'))
            ->orderBy($sort, $sort === 'name' ? 'asc' : 'desc')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('desk-editor::articles.tags.index', [
            'tags' => $tags,
            'sort' => $sort,
            'total' => DeskTag::query()->count(),
            'unused' => DeskTag::query()->doesntHave('articles')->count(),
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $this->authorizeAbility('view');

        $term = trim((string) $request->query('q', ''));

        return response()->json(['data' => DeskTag::query()
            ->withCount('articles')
            ->when($term !== '', fn (Builder $q) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%'))
            ->orderByDesc('articles_count')
            ->orderBy('name')
            ->limit(20)
            ->pluck('name')]);
    }

    /**
     * Rename a tag. Renaming onto an existing tag's name merges the two.
     */
    public function update(Request $request, DeskTag $tag): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $name = trim((string) $data['name']);

        $existing = DeskTag::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->whereKeyNot($tag->getKey())->first();

        if ($existing !== null) {
            $existing->absorb([$tag->getKey()]);
            $message = __('desk-editor::articles.tags.merged', ['count' => 1, 'name' => $existing->name]);
        } else {
            $tag->name = $name;
            $tag->slug = '';
            $tag->save();
            $message = __('desk-editor::articles.tags.renamed', ['name' => $name]);
        }

        return $this->respond($request, $message);
    }

    /**
     * Merge several tags into one (an existing tag or a new name).
     */
    public function merge(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('desk_tags', 'id')],
            'target' => ['required', 'string', 'max:100'],
        ]);

        $name = trim((string) $data['target']);
        $target = DeskTag::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

        if ($target === null) {
            // Rename the first selected tag into the target and fold the rest into it.
            $target = DeskTag::query()->findOrFail((int) $data['ids'][0]);
            $target->name = $name;
            $target->slug = '';
            $target->save();
        }

        $merged = $target->absorb($data['ids']);

        return $this->respond($request, __('desk-editor::articles.tags.merged', ['count' => $merged, 'name' => $target->name]));
    }

    public function destroy(Request $request, DeskTag $tag): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $tag->articles()->detach();
        $tag->delete();

        return $this->respond($request, __('desk-editor::articles.tags.deleted'));
    }

    public function bulkDestroy(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']]);
        $tags = DeskTag::query()->whereKey($data['ids'])->get();
        foreach ($tags as $tag) {
            $tag->articles()->detach();
            $tag->delete();
        }

        return $this->respond($request, trans_choice('desk-editor::articles.tags.bulk_deleted', $tags->count(), ['count' => $tags->count()]));
    }

    private function respond(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('desk-status', $message);
    }
}
