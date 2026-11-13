<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Models\DeskCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    use ResolvesArticles;

    public function index(Request $request): View
    {
        $this->authorizeAbility('manage-taxonomy');

        $all = DeskCategory::query()->withCount('articles')->orderBy('sort_order')->orderBy('name')->get();

        return view('desk-editor::articles.categories.index', [
            'categories' => $this->tree($all),
            'all' => $all,
            'swatches' => DeskCategory::swatches(),
            'uncategorised' => $this->model()::query()->whereNull('category_id')->count(),
            'editing' => $request->query('edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $category = new DeskCategory;
        $category->fill($this->validated($request, $category));
        $category->sort_order = (int) DeskCategory::query()->max('sort_order') + 1;
        $category->save();

        return $this->respond($request, $category, __('desk-editor::articles.categories.created'));
    }

    public function update(Request $request, DeskCategory $category): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $category->fill($this->validated($request, $category))->save();

        return $this->respond($request, $category, __('desk-editor::articles.categories.updated'));
    }

    public function destroy(Request $request, DeskCategory $category): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        DB::transaction(fn () => $category->deleteSafely());

        if ($request->wantsJson()) {
            return response()->json(['message' => __('desk-editor::articles.categories.deleted')]);
        }

        return redirect()->route('desk-editor.categories.index')->with('desk-status', __('desk-editor::articles.categories.deleted'));
    }

    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeAbility('manage-taxonomy');

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data): void {
            foreach (array_values($data['order']) as $position => $id) {
                DeskCategory::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => __('desk-editor::articles.categories.reordered')]);
        }

        return back()->with('desk-status', __('desk-editor::articles.categories.reordered'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, DeskCategory $category): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('desk_categories', 'name')->ignore($category->getKey())],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('desk_categories', 'slug')->ignore($category->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'parent_id' => ['nullable', 'integer', Rule::exists('desk_categories', 'id')],
        ]);

        $parent = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parent !== null && $category->exists && in_array($parent, [(int) $category->getKey(), ...$this->descendantIds($category)], true)) {
            throw ValidationException::withMessages(['parent_id' => __('desk-editor::articles.categories.invalid_parent')]);
        }

        $data['parent_id'] = $parent;
        $data['color'] = DeskCategory::sanitizeColor($data['color'] ?? null);
        $data['slug'] = ($data['slug'] ?? '') !== '' ? $data['slug'] : ($category->exists && $category->name === $data['name'] ? $category->slug : null);

        return $data;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(DeskCategory $category): array
    {
        $ids = [];
        $frontier = [(int) $category->getKey()];
        while ($frontier !== [] && count($ids) < 1000) {
            $children = DeskCategory::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $children = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$children];
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * Flatten into display order with depth.
     *
     * @param  Collection<int, DeskCategory>  $all
     * @return list<array{category: DeskCategory, depth: int}>
     */
    private function tree(Collection $all): array
    {
        $rows = [];
        $seen = [];
        $walk = function (?int $parent, int $depth) use (&$walk, &$rows, &$seen, $all): void {
            foreach ($all->filter(fn (DeskCategory $c) => $c->parent_id === $parent) as $category) {
                if (isset($seen[$category->id])) {
                    continue;
                }
                $seen[$category->id] = true;
                $rows[] = ['category' => $category, 'depth' => $depth];
                $walk((int) $category->id, $depth + 1);
            }
        };
        $walk(null, 0);

        foreach ($all as $category) {
            if (! isset($seen[$category->id])) {
                $rows[] = ['category' => $category, 'depth' => 0];
            }
        }

        return $rows;
    }

    private function respond(Request $request, DeskCategory $category, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'category' => $category->only(['id', 'name', 'slug', 'color', 'description', 'parent_id', 'sort_order'])]);
        }

        return redirect()->route('desk-editor.categories.index')->with('desk-status', $message);
    }
}
