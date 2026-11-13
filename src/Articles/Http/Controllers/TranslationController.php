<?php

declare(strict_types=1);

namespace Atannex\DeskEditor\Articles\Http\Controllers;

use Atannex\DeskEditor\Articles\Http\Concerns\ResolvesArticles;
use Atannex\DeskEditor\Enums\ArticleStatus;
use Atannex\DeskEditor\Models\DeskArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * "Add translation": copies an article into another locale as a linked draft.
 */
class TranslationController extends Controller
{
    use ResolvesArticles;

    public function store(Request $request, DeskArticle|string $article): RedirectResponse
    {
        $source = $this->find($article);
        $this->authorizeAbility('create');
        $this->authorizeAbility('view', $source);

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys($this->locales()))],
        ]);
        $locale = (string) $data['locale'];

        $group = $source->ensureTranslationGroup();

        $existing = $this->model()::withTrashed()->where('translation_group', $group)->where('locale', $locale)->first();
        if ($existing !== null) {
            return redirect()->route('desk-editor.articles.edit', $existing->trashed() ? $source : $existing)
                ->withErrors(['locale' => __('desk-editor::articles.translation_exists')]);
        }

        $copy = $source->replicate(['published_at', 'word_count', 'reading_time', 'views_count', 'comments_count']);
        $copy->locale = $locale;
        $copy->translation_group = $group;
        $copy->status = ArticleStatus::Draft;
        $copy->featured = false;
        $user = $request->user();
        if ($user instanceof Model) {
            $copy->author()->associate($user);
            $copy->editor()->associate($user);
        }
        $copy->revisionNote = __('desk-editor::articles.activity.translation_of', ['locale' => $source->locale]);
        $copy->save();
        $copy->tags()->sync($source->tags->modelKeys());

        $label = $this->locales()[$locale] ?? $locale;
        $copy->logActivity('translated', __('desk-editor::articles.activity.translation_of', ['locale' => $this->locales()[$source->locale] ?? $source->locale]), ['source' => $source->getKey(), 'locale' => $locale]);
        $source->logActivity('translated', __('desk-editor::articles.activity.translation_added', ['locale' => $label]), ['translation' => $copy->getKey(), 'locale' => $locale]);

        return redirect()->route('desk-editor.articles.edit', $copy)->with('desk-status', __('desk-editor::articles.translation_created', ['locale' => $label]));
    }
}
