<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Articles\Support\ArticleWorkflow;
use Atannex\DeskEditor\Articles\Support\Authors;
use Atannex\DeskEditor\Articles\Support\EditLock;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Atannex\DeskEditor\Models\DeskArticleActivity;
use Atannex\DeskEditor\Models\DeskArticleRevision;
use Atannex\DeskEditor\Models\DeskCategory;
use Atannex\DeskEditor\Models\DeskTag;
use Atannex\DeskEditor\Rules\DeskContentRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ArticleController extends Controller
{
    use ResolvesArticles;

    private const SORTS = ['updated_at', 'created_at', 'published_at', 'title', 'word_count'];

    public function __construct(
        private readonly ArticleWorkflow $workflow,
        private readonly Authors $authors,
        private readonly EditLock $locks,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeAbility('view');

        $model = $this->model();
        $status = $request->query('status');
        $status = is_string($status) && (ArticleStatus::tryFrom($status) || $status === 'trashed') ? $status : null;
        $sort = in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : 'updated_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $term = trim((string) $request->query('q', ''));

        $query = $model::query()
            ->when($request->query('category'), fn (Builder $q, $c) => $q->whereHas('category', fn (Builder $cq) => $cq->where('name', $c)->orWhere('slug', $c)))
            ->when($request->boolean('uncategorised'), fn (Builder $q) => $q->whereNull('category_id'))
            ->when($request->boolean('featured'), fn (Builder $q) => $q->where('featured', true))
            ->when($request->query('author'), fn (Builder $q, $author) => $q->authoredBy((string) $author, $this->authorMorph()))
            ->when($request->query('locale'), fn (Builder $q, $locale) => $q->where('locale', $locale));

        $this->applySearch($query, $term);

        $counts = (clone $query)->toBase()->reorder()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $trashed = (clone $query)->onlyTrashed()->count();

        $articles = $query
            ->when($status === 'trashed', fn (Builder $q) => $q->onlyTrashed())
            ->when($status && $status !== 'trashed', fn (Builder $q) => $q->status((string) $status))
            ->with(['category', 'tags', 'author'])
            ->when($term !== '' && ! $request->has('sort'), fn (Builder $q) => $q->orderByRelevance($term))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate((int) config('desk-editor.articles.per_page', 15))
            ->withQueryString();

        $locales = $this->locales();

        return view('desk-editor::articles.index', [
            'articles' => $articles,
            'counts' => $counts,
            'total' => $counts->sum(),
            'trashed' => $trashed,
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
            'categories' => DeskCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
            'authors' => $this->articleAuthors(),
            'locales' => $locales,
            'multilingual' => count($locales) > 1 || $model::query()->distinct()->count('locale') > 1,
            'stats' => [
                'published' => (int) ($counts[ArticleStatus::Published->value] ?? 0),
                'review' => (int) ($counts[ArticleStatus::Review->value] ?? 0),
                'words' => (int) $model::query()->sum('word_count'),
                'scheduled' => $model::query()->status(ArticleStatus::Scheduled)->where('published_at', '>', now())->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeAbility('create');

        $locale = (string) $request->query('locale', '');
        $article = new ($this->model())([
            'status' => ArticleStatus::Draft,
            'allow_comments' => true,
            'locale' => array_key_exists($locale, $this->locales()) ? $locale : (string) config('desk-editor.articles.default_locale', config('app.locale', 'en')),
        ]);

        return view('desk-editor::articles.form', $this->formData($article, $request));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeAbility('create');

        $article = new ($this->model());
        [$data, $tags, $author] = $this->validated($request, $article);
        $article->fill($data);
        $this->stampUser($article, $request, true, $author);
        $article->revisionNote = $article->revisionNote ?: __('desk-editor::articles.created');
        $note = $request->input('revision_note');
        $article->save();
        $article->syncTagNames($tags);
        $this->workflow->recorded($article, null, true, is_string($note) && $note !== '' ? $note : null);

        return $this->respond($request, $article, __('desk-editor::articles.saved'));
    }

    public function edit(Request $request, DeskArticle|string $article): View
    {
        $article = $this->find($article);
        $this->authorizeAbility('view', $article);

        $user = $request->user();
        if ($user !== null && $this->policy()->allows('update', $article)) {
            $this->locks->acquire($article, $user);
        }

        return view('desk-editor::articles.form', $this->formData($article, $request));
    }

    public function update(Request $request, DeskArticle|string $article): RedirectResponse|JsonResponse
    {
        $article = $this->find($article);
        $this->authorizeAbility('update', $article);

        if ($conflict = $this->conflict($request, $article)) {
            return $conflict;
        }

        $from = $article->status;
        [$data, $tags, $author] = $this->validated($request, $article);
        $note = $article->revisionNote;
        $article->fill($data);
        $this->stampUser($article, $request, false, $author);
        $article->save();
        $article->syncTagNames($tags);
        $this->workflow->recorded($article, $from, false, $note);

        if ($user = $request->user()) {
            $this->locks->acquire($article, $user);
        }

        $message = match (true) {
            $from !== ArticleStatus::Review && $article->status === ArticleStatus::Review => __('desk-editor::articles.submitted'),
            $from !== ArticleStatus::Published && $article->status === ArticleStatus::Published => __('desk-editor::articles.published'),
            $from !== ArticleStatus::Scheduled && $article->status === ArticleStatus::Scheduled => __('desk-editor::articles.scheduled'),
            default => __('desk-editor::articles.saved'),
        };

        return $this->respond($request, $article, $message);
    }

    public function preview(DeskArticle|string $article): View
    {
        $article = $this->find($article);
        $this->authorizeAbility('view', $article);

        return view('desk-editor::articles.preview', ['article' => $article]);
    }

    public function duplicate(DeskArticle|string $article): RedirectResponse
    {
        $original = $this->find($article);
        $this->authorizeAbility('create');

        $copy = $original->replicate(['slug', 'published_at', 'word_count', 'reading_time', 'translation_group', 'views_count', 'comments_count']);
        $copy->title = __('desk-editor::articles.copy_of', ['title' => $original->title]);
        $copy->status = ArticleStatus::Draft;
        $copy->featured = false;
        $user = auth()->user();
        if ($user instanceof Model) {
            $copy->editor()->associate($user);
        }
        $copy->save();
        $copy->tags()->sync($original->tags->modelKeys());
        $copy->logActivity('created', __('desk-editor::articles.activity.duplicated_from', ['title' => $original->title]), ['source' => $original->getKey()]);

        return redirect()->route('desk-editor.articles.edit', $copy)->with('desk-status', __('desk-editor::articles.duplicated'));
    }

    public function restoreRevision(DeskArticle|string $article, int $revision): RedirectResponse
    {
        $article = $this->find($article);
        $this->authorizeAbility('update', $article);

        /** @var DeskArticleRevision $model */
        $model = $article->revisions()->findOrFail($revision);
        $article->restoreRevision($model);
        $article->logActivity('revision_restored', $model->note, ['revision' => $model->getKey(), 'date' => $model->created_at?->toIso8601String()]);

        return redirect()->route('desk-editor.articles.edit', $article)->with('desk-status', __('desk-editor::articles.revision_restored'));
    }

    public function destroy(DeskArticle|string $article): RedirectResponse
    {
        $article = $this->find($article);
        $this->authorizeAbility('delete', $article);

        $article->logActivity('trashed');
        $article->delete();

        return redirect()->route('desk-editor.articles.index')->with('desk-status', __('desk-editor::articles.trashed'));
    }

    public function restore(DeskArticle|string $article): RedirectResponse
    {
        $article = $this->find($article, true);
        $this->authorizeAbility('delete', $article);

        $article->restore();
        $article->logActivity('restored');

        return redirect()->route('desk-editor.articles.edit', $article)->with('desk-status', __('desk-editor::articles.restored'));
    }

    public function forceDelete(DeskArticle|string $article): RedirectResponse
    {
        $article = $this->find($article, true);
        $this->authorizeAbility('delete', $article);

        $article->forceDelete();

        return redirect()->route('desk-editor.articles.index', ['status' => 'trashed'])->with('desk-status', __('desk-editor::articles.deleted'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'draft', 'archive', 'feature', 'unfeature', 'trash', 'restore', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $action = (string) $data['action'];
        $ability = match ($action) {
            'publish', 'archive' => 'publish',
            'trash', 'restore', 'delete' => 'delete',
            default => 'update',
        };
        $this->authorizeAbility($ability);

        $articles = $this->model()::withTrashed()->whereKey($data['ids'])->get()
            ->filter(fn (DeskArticle $article) => $this->policy()->allows($ability, $article));

        foreach ($articles as $article) {
            match ($action) {
                'publish' => $this->workflow->moveTo($article, ArticleStatus::Published),
                'draft' => $this->workflow->moveTo($article, ArticleStatus::Draft),
                'archive' => $this->workflow->moveTo($article, ArticleStatus::Archived),
                'feature' => $article->fill(['featured' => true])->save(),
                'unfeature' => $article->fill(['featured' => false])->save(),
                'trash' => $article->delete(),
                'restore' => $article->restore() && $article->logActivity('restored'),
                'delete' => $article->forceDelete(),
                default => null,
            };
        }

        return back()->with('desk-status', trans_choice('desk-editor::articles.bulk_done', $articles->count(), ['count' => $articles->count()]));
    }

    /**
     * Shared data for the create/edit form.
     *
     * @return array<string, mixed>
     */
    private function formData(DeskArticle $article, Request $request): array
    {
        $policy = $this->policy();
        $exists = $article->exists;
        $user = $request->user();

        $translations = $exists && $article->translation_group
            ? $article->translations()->withTrashed()->orderBy('locale')->get(['id', 'title', 'locale', 'status', 'deleted_at', 'translation_group'])
            : collect();

        /** @var Collection<int, DeskArticleActivity> $activities */
        $activities = $exists ? $article->activities()->with('causer')->limit(40)->get() : collect();
        $changesRequested = $article->status === ArticleStatus::Draft ? $activities->first(fn (DeskArticleActivity $a) => in_array($a->type, ['changes_requested', 'submitted', 'approved', 'published'], true)) : null;

        $author = $exists ? $article->author : ($user instanceof Model ? $user : null);

        return [
            'article' => $article,
            'categories' => $this->categoryOptions(),
            'revisions' => $exists ? $article->revisions()->with('author')->limit(30)->get() : collect(),
            'activities' => $activities,
            'changesRequested' => $changesRequested?->type === 'changes_requested' ? $changesRequested : null,
            'translations' => $translations,
            'locales' => $this->locales(),
            'lock' => $exists ? EditLock::present($this->locks->heldByOther($article, $user)) : null,
            'lockSeconds' => $this->locks->seconds(),
            'author' => $author instanceof Model ? $this->authors->present($author) : null,
            'authorOptions' => $policy->canAssignAuthor($article) ? $this->authors->search(null, 50) : [],
            'tagSuggestions' => DeskTag::query()->withCount('articles')->orderByDesc('articles_count')->orderBy('name')->limit(500)->pluck('name')->all(),
            'can' => [
                ...$policy->map($exists ? $article : null),
                'save' => $exists ? $policy->allows('update', $article) : $policy->allows('create'),
                'assign_author' => $policy->canAssignAuthor($exists ? $article : null),
            ],
            'needsReview' => $policy->needsReview($exists ? $article : null),
            'allowedStatuses' => $policy->allowedStatuses($exists ? $article : null),
        ];
    }

    /**
     * @return list<array{name: string, color: string|null, depth: int}>
     */
    private function categoryOptions(): array
    {
        $all = DeskCategory::query()->orderBy('sort_order')->orderBy('name')->get();
        $options = [];

        $walk = function (?int $parent, int $depth) use (&$walk, &$options, $all): void {
            foreach ($all->where('parent_id', $parent) as $category) {
                $options[] = ['name' => $category->name, 'color' => DeskCategory::sanitizeColor($category->color), 'depth' => $depth];
                if ($depth < 4) {
                    $walk((int) $category->getKey(), $depth + 1);
                }
            }
        };
        $walk(null, 0);

        // Orphans whose parent no longer resolves.
        $listed = array_column($options, 'name');
        foreach ($all as $category) {
            if (! in_array($category->name, $listed, true)) {
                $options[] = ['name' => $category->name, 'color' => DeskCategory::sanitizeColor($category->color), 'depth' => 0];
            }
        }

        $names = array_map('mb_strtolower', array_column($options, 'name'));
        foreach ((array) config('desk-editor.articles.categories', []) as $preset) {
            if (is_string($preset) && ! in_array(mb_strtolower($preset), $names, true)) {
                $options[] = ['name' => $preset, 'color' => null, 'depth' => 0];
            }
        }

        return $options;
    }

    /**
     * Authors that have written at least one article (for the index filter).
     *
     * @return list<array{id: string, name: string, initials: string}>
     */
    private function articleAuthors(): array
    {
        $model = $this->authors->model();
        if ($model === null) {
            return [];
        }

        $ids = $this->model()::withTrashed()->where('author_type', $this->authorMorph())->whereNotNull('author_id')->distinct()->limit(200)->pluck('author_id');
        if ($ids->isEmpty()) {
            return [];
        }

        return $model::query()->whereKey($ids->all())->get()
            ->map(fn (Model $user) => $this->authors->present($user))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function authorMorph(): ?string
    {
        $model = $this->authors->model();

        return $model === null ? null : (new $model)->getMorphClass();
    }

    /**
     * @param  Builder<DeskArticle>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $model = $this->model();
        if (config('desk-editor.articles.scout') && class_exists('Laravel\\Scout\\Builder') && method_exists($model, 'search')
            && in_array('Laravel\\Scout\\Searchable', class_uses_recursive($model), true)) {
            try {
                $builder = call_user_func([$model, 'search'], $term);
                $keys = method_exists($builder, 'keys') ? $builder->keys() : null;
                $ids = $keys instanceof Collection ? array_slice($keys->values()->all(), 0, 1000) : [];
                $query->whereKey($ids);
                if ($ids !== []) {
                    // Preserve Scout's ranking.
                    $query->orderByRaw('case '.implode(' ', array_map(fn (int $i) => 'when id = ? then '.$i, array_keys($ids))).' else '.count($ids).' end', $ids);
                }

                return;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $query->search($term);
    }

    /**
     * Optimistic concurrency: refuse to overwrite changes saved since the form was loaded.
     */
    private function conflict(Request $request, DeskArticle $article): RedirectResponse|JsonResponse|null
    {
        $loaded = $request->input('loaded_at');
        if (! is_scalar($loaded) || (string) $loaded === '' || $request->boolean('overwrite') || $article->updated_at === null) {
            return null;
        }

        if ((string) $article->updated_at->getTimestamp() === (string) $loaded) {
            return null;
        }

        $editor = $article->editor instanceof Model ? $this->authors->nameOf($article->editor) : '';
        $message = $editor !== ''
            ? __('desk-editor::articles.conflict.by', ['name' => $editor, 'time' => $article->updated_at->diffForHumans()])
            : __('desk-editor::articles.conflict.message', ['time' => $article->updated_at->diffForHumans()]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'conflict' => true,
                'editor' => $editor ?: null,
                'updated_at' => $article->updated_at->toIso8601String(),
                'version' => (string) $article->updated_at->getTimestamp(),
                'reload_url' => route('desk-editor.articles.edit', $article),
            ], 409);
        }

        return back()->withInput()->withErrors(['conflict' => $message])->with('desk-conflict', [
            'editor' => $editor ?: null,
            'version' => (string) $article->updated_at->getTimestamp(),
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>, 2: Model|null}
     */
    private function validated(Request $request, DeskArticle $article): array
    {
        $policy = $this->policy();
        $context = $article->exists ? $article : null;

        // Buttons express intent; the server is the source of truth for what it means.
        $intent = $request->input('intent');
        if ($intent === 'submit') {
            $request->merge(['status' => ArticleStatus::Review->value]);
        } elseif ($intent === 'publish') {
            if ($policy->needsReview($context)) {
                $request->merge(['status' => ArticleStatus::Review->value]);
            } elseif ($request->input('status') !== ArticleStatus::Scheduled->value) {
                $request->merge(['status' => ArticleStatus::Published->value]);
            }
        } elseif ($intent === 'draft') {
            $request->merge(['status' => ArticleStatus::Draft->value]);
        }

        if (! $request->filled('status')) {
            $request->merge(['status' => $article->status->value ?? ArticleStatus::Draft->value]);
        }

        $table = $article->getTable();
        $locales = $this->locales();
        $locale = (string) $request->input('locale', $article->locale ?: config('desk-editor.articles.default_locale', config('app.locale', 'en')));
        $allowed = array_map(fn (ArticleStatus $s) => $s->value, $policy->allowedStatuses($context));

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', Rule::unique($table, 'slug')->where('locale', $locale)->ignore($article->getKey())],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'body' => ['nullable', 'string', DeskContentRule::make()],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ArticleStatus::class), Rule::in($allowed)],
            'published_at' => ['nullable', 'date', Rule::requiredIf($request->input('status') === ArticleStatus::Scheduled->value)],
            'featured' => ['sometimes', 'boolean'],
            'allow_comments' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable', 'string', 'max:2000'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'revision_note' => ['nullable', 'string', 'max:255'],
            'locale' => ['sometimes', 'string', Rule::in(array_unique([...array_keys($locales), (string) $article->locale]))],
            'author_id' => ['nullable'],
        ], [
            'status.in' => __('desk-editor::articles.status_forbidden'),
        ]);

        if ($article->exists && $article->translation_group && isset($data['locale']) && $data['locale'] !== $article->locale
            && $article->translations()->where('locale', $data['locale'])->exists()) {
            throw ValidationException::withMessages(['locale' => __('desk-editor::articles.translation_exists')]);
        }

        $author = null;
        if (array_key_exists('author_id', $data) && $data['author_id'] !== null && $data['author_id'] !== '' && $policy->canAssignAuthor($context)) {
            $author = $this->authors->find($data['author_id']);
            if ($author === null) {
                throw ValidationException::withMessages(['author_id' => __('desk-editor::articles.author_missing')]);
            }
        }
        unset($data['author_id']);

        $article->revisionNote = $data['revision_note'] ?? null;
        unset($data['revision_note']);

        $tags = collect(explode(',', (string) ($data['tags'] ?? '')))
            ->map(fn ($tag) => trim($tag))->filter()->unique(fn ($tag) => mb_strtolower($tag))->take(20)->values()->all();
        unset($data['tags']);

        $data['category_id'] = DeskCategory::findOrCreateByName($data['category'] ?? null)?->getKey();
        unset($data['category']);
        $data['featured'] = $request->boolean('featured');
        $data['allow_comments'] = $request->boolean('allow_comments');
        $data['published_at'] = ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : ($data['status'] === ArticleStatus::Published->value ? $article->published_at : null);

        return [$data, $tags, $author];
    }

    private function respond(Request $request, DeskArticle $article, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'article' => [
                    'id' => $article->getKey(),
                    'slug' => $article->slug,
                    'status' => $article->status->value,
                    'status_label' => $article->status->label(),
                    'word_count' => $article->word_count,
                    'updated_at' => $article->updated_at?->toIso8601String(),
                    'version' => (string) $article->updated_at?->getTimestamp(),
                    'edit_url' => route('desk-editor.articles.edit', $article),
                    'update_url' => route('desk-editor.articles.update', $article),
                ],
            ]);
        }

        return redirect()->route('desk-editor.articles.edit', $article)->with('desk-status', $message);
    }

    private function stampUser(DeskArticle $article, Request $request, bool $creating = false, ?Model $author = null): void
    {
        if ($author !== null) {
            $article->author()->associate($author);
        }

        $user = $request->user();
        if (! $user instanceof Model) {
            return;
        }

        if ($creating && $author === null) {
            $article->author()->associate($user);
        }
        $article->editor()->associate($user);
    }
}
